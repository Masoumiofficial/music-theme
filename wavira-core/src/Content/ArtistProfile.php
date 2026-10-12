<?php
/**
 * Everything an artist profile page needs, in one payload.
 *
 * A Persian music site's artist page is not a blog post with a photo: it is the
 * artist's biography, their social channels, their counts, and their works
 * grouped by type (albums, singles, videos) plus an image gallery. Building that
 * from four separate theme queries would put the music model in the theme, which
 * is exactly what ARCHITECTURE §1 forbids, so the aggregation lives here and the
 * theme only renders.
 *
 * The payload is data, never markup: attachment IDs for the theme to build
 * `<img>` tags from (so core emits `srcset`/`sizes`/`width`/`height`), plain
 * strings for text, and term payloads from `Terms`.
 *
 * @package Wavira\Core\Content
 * @since   0.9.0
 */

namespace Wavira\Core\Content;

use WP_Post;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Class ArtistProfile
 */
final class ArtistProfile {

	/**
	 * Hard ceiling for one section, whatever a caller asks for.
	 *
	 * An artist page must stay a page: an unbounded "all works" grid is the
	 * unbounded query CODING-STANDARD W3 forbids, so the section is capped and
	 * links to the full archive instead.
	 *
	 * @var int
	 */
	public const MAX_ITEMS = 24;

	/**
	 * Sections the profile can contain, in display order.
	 *
	 * @var string[]
	 */
	public const SECTIONS = array( 'albums', 'tracks', 'videos' );

	/**
	 * Build the profile payload of one artist.
	 *
	 * @param int                  $artist_id Artist post ID.
	 * @param array<string, mixed> $args {
	 *     Optional. Payload arguments.
	 *
	 *     @type int      $limit         Items per section (default 6, max 24).
	 *     @type int      $gallery_limit Gallery images (default 8, max 24).
	 *     @type string[] $sections      Sections to build, any of SECTIONS.
	 * }
	 * @return array<string, mixed> Empty array when the ID is not a published artist.
	 */
	public static function for_artist( int $artist_id, array $args = array() ): array {
		$artist = get_post( $artist_id );

		if ( ! $artist instanceof WP_Post
			|| PostTypes::ARTIST !== $artist->post_type
			|| 'publish' !== $artist->post_status
		) {
			return array();
		}

		$args = wp_parse_args(
			$args,
			array(
				'limit'         => 6,
				'gallery_limit' => 8,
				'sections'      => self::SECTIONS,
			)
		);

		$limit    = self::clamp( (int) $args['limit'] );
		$sections = array_values( array_intersect( (array) $args['sections'], self::SECTIONS ) );

		$payload = array(
			'id'      => $artist->ID,
			'slug'    => $artist->post_name,
			'name'    => get_the_title( $artist ),
			'link'    => (string) get_permalink( $artist ),
			'context' => self::quote( $artist ),
		);

		$payload['biography'] = self::biography( $artist );
		$payload['avatar']    = self::avatar( $artist );
		$payload['socials']   = self::socials( $artist->ID );

		$payload['sections'] = array();

		foreach ( $sections as $section ) {
			$payload['sections'][ $section ] = self::section( $artist->ID, $section, $limit );
		}

		$gallery = self::gallery( $artist->ID, self::clamp( (int) $args['gallery_limit'] ) );

		$payload['gallery'] = $gallery;
		$payload['counts']  = self::counts( $artist->ID, count( $gallery ) );

		/**
		 * Filters a completed artist profile payload.
		 *
		 * @param array<string, mixed> $payload Profile payload.
		 * @param int                  $artist_id Artist post ID.
		 */
		return (array) apply_filters( 'wavira_core_artist_profile', $payload, $artist->ID );
	}

	/**
	 * Artist biography: the post content, or the excerpt as a short form.
	 *
	 * Rendered through `the_content` exactly like a post body, so a biography may
	 * contain paragraphs, lists and blocks; a caller that prints it owns the
	 * standard post-content escaping (core's own `the_content` path).
	 *
	 * @param WP_Post $artist Artist post.
	 * @return string HTML, empty when the artist has neither content nor excerpt.
	 */
	private static function biography( WP_Post $artist ): string {
		if ( '' !== trim( (string) $artist->post_content ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter; a biography is post content and renders through the same pipeline.
			return (string) apply_filters( 'the_content', $artist->post_content );
		}

		if ( '' !== trim( (string) $artist->post_excerpt ) ) {
			return '<p>' . esc_html( $artist->post_excerpt ) . '</p>';
		}

		return '';
	}

	/**
	 * Short quote used in cards and the jingle of the profile header.
	 *
	 * @param WP_Post $artist Artist post.
	 * @return string
	 */
	private static function quote( WP_Post $artist ): string {
		$source = '' !== trim( (string) $artist->post_excerpt ) ? $artist->post_excerpt : wp_strip_all_tags( (string) $artist->post_content, true );

		return wp_trim_words( $source, 24, '…' );
	}

	/**
	 * Artist avatar.
	 *
	 * The featured image is the portrait; the dedicated artist image fields are
	 * the escape hatch, and the cover field is the last resort — the same
	 * precedence the REST payload uses, so the card and the profile agree.
	 *
	 * @param WP_Post $artist Artist post.
	 * @return array<string, mixed> `id`, `url`, `alt`; empty when the artist has no image.
	 */
	private static function avatar( WP_Post $artist ): array {
		$attachment_id = Cover::id( $artist->ID );

		if ( ! $attachment_id ) {
			$attachment_id = MetaValues::int( $artist->ID, MetaSchema::ARTIST_IMAGE );
		}

		if ( ! $attachment_id ) {
			$attachment_id = MetaValues::int( $artist->ID, MetaSchema::ARTIST_COVER );
		}

		if ( ! $attachment_id ) {
			return array();
		}

		return array(
			'id'  => $attachment_id,
			'url' => Cover::url( $attachment_id ),
			'alt' => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
		);
	}

	/**
	 * Social channels of an artist, in a stable order, with translated labels.
	 *
	 * The label is translated here (not in the theme) because the network list is
	 * part of the data model: a site that removes a platform removes it once.
	 *
	 * @param int $artist_id Artist post ID.
	 * @return array<int, array<string, string>> Rows of `network`, `label`, `url`.
	 */
	private static function socials( int $artist_id ): array {
		$labels = array(
			'instagram' => __( 'Instagram', 'wavira-core' ),
			'telegram'  => __( 'Telegram', 'wavira-core' ),
			'youtube'   => __( 'YouTube', 'wavira-core' ),
			'aparat'    => __( 'Aparat', 'wavira-core' ),
			'facebook'  => __( 'Facebook', 'wavira-core' ),
			'x'         => __( 'X (Twitter)', 'wavira-core' ),
		);

		$socials = array();

		foreach ( MetaValues::socials( $artist_id ) as $network => $data ) {
			$url = isset( $data['url'] ) ? (string) $data['url'] : '';

			if ( '' === $url ) {
				continue;
			}

			$socials[] = array(
				'network' => (string) $network,
				'label'   => isset( $labels[ $network ] ) ? (string) $labels[ $network ] : ucfirst( (string) $network ),
				'url'     => $url,
			);
		}

		return $socials;
	}

	/**
	 * One works section of the profile.
	 *
	 * @param int    $artist_id  Artist post ID.
	 * @param string $section    Section key (album slug suffix: albums, tracks, videos).
	 * @param int    $limit      Items to return.
	 * @return array<string, mixed> `label`, `post_type`, `count`, `more`, `items`.
	 */
	private static function section( int $artist_id, string $section, int $limit ): array {
		$types = array(
			'albums' => PostTypes::ALBUM,
			'tracks' => PostTypes::TRACK,
			'videos' => PostTypes::VIDEO,
		);

		$post_type = $types[ $section ];
		$posts     = self::works( $artist_id, $post_type, $limit );
		$items     = array();

		foreach ( $posts as $post ) {
			$items[] = self::card( $post );
		}

		return array(
			'label'     => self::section_label( $post_type ),
			'post_type' => $post_type,
			'count'     => self::count( $artist_id, $post_type ),
			'more'      => self::archive_link( $post_type ),
			'items'     => $items,
		);
	}

	/**
	 * Translated section label for a post type.
	 *
	 * @param string $post_type Post type name.
	 * @return string
	 */
	private static function section_label( string $post_type ): string {
		switch ( $post_type ) {
			case PostTypes::ALBUM:
				return __( 'Albums', 'wavira-core' );

			case PostTypes::TRACK:
				return __( 'Singles', 'wavira-core' );

			case PostTypes::VIDEO:
				return __( 'Music videos', 'wavira-core' );

			default:
				return __( 'Works', 'wavira-core' );
		}
	}

	/**
	 * Archive URL of a music post type, read from the post type object.
	 *
	 * @param string $post_type Post type name.
	 * @return string Empty when the post type has no archive.
	 */
	private static function archive_link( string $post_type ): string {
		$url = get_post_type_archive_link( $post_type );

		return is_string( $url ) ? $url : '';
	}

	/**
	 * Published works of one type credited to an artist.
	 *
	 * @param int    $artist_id Artist post ID.
	 * @param string $post_type Post type name.
	 * @param int    $limit     Maximum items.
	 * @return WP_Post[]
	 */
	private static function works( int $artist_id, string $post_type, int $limit ): array {
		$query = new WP_Query(
			array(
				'post_type'           => $post_type,
				'post_status'         => 'publish',
				'posts_per_page'      => $limit,
				'orderby'             => 'date',
				'order'               => 'DESC',
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'meta_query'          => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- indexed relation key, limited by $limit.
					array(
						'key'     => MetaSchema::ARTIST,
						'value'   => $artist_id,
						'compare' => '=',
						'type'    => 'NUMERIC',
					),
				),
			)
		);

		return $query->posts;
	}

	/**
	 * How many published items of one type credit this artist.
	 *
	 * @param int    $artist_id Artist post ID.
	 * @param string $post_type Post type name.
	 * @return int
	 */
	private static function count( int $artist_id, string $post_type ): int {
		$query = new WP_Query(
			array(
				'post_type'           => $post_type,
				'post_status'         => 'publish',
				'posts_per_page'      => 1,
				'fields'              => 'ids',
				'ignore_sticky_posts' => true,
				'meta_query'          => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- count of an indexed relation key.
					array(
						'key'     => MetaSchema::ARTIST,
						'value'   => $artist_id,
						'compare' => '=',
						'type'    => 'NUMERIC',
					),
				),
			)
		);

		return (int) $query->found_posts;
	}

	/**
	 * Counts for every section plus the gallery.
	 *
	 * @param int $artist_id   Artist post ID.
	 * @param int $gallery_now Images already loaded for the gallery section (a
	 *                         full count would be a second query for a number
	 *                         nobody displays as a taxonomy count).
	 * @return array<string, int>
	 */
	private static function counts( int $artist_id, int $gallery_now ): array {
		return array(
			'albums'  => self::count( $artist_id, PostTypes::ALBUM ),
			'tracks'  => self::count( $artist_id, PostTypes::TRACK ),
			'videos'  => self::count( $artist_id, PostTypes::VIDEO ),
			'gallery' => $gallery_now,
		);
	}

	/**
	 * Image gallery of an artist: images attached to the artist post.
	 *
	 * Attachments are the WordPress-native gallery (upload from the artist screen
	 * and the file is attached to it), so no second gallery meta is invented and
	 * the media library stays the single place images live.
	 *
	 * @param int $artist_id Artist post ID.
	 * @param int $limit     Maximum images.
	 * @return array<int, array<string, mixed>> Rows of `id`, `url`, `alt`, `caption`, `width`, `height`.
	 */
	private static function gallery( int $artist_id, int $limit ): array {
		$query = new WP_Query(
			array(
				'post_type'           => 'attachment',
				'post_status'         => 'inherit',
				'post_parent'         => $artist_id,
				'post_mime_type'      => 'image',
				'posts_per_page'      => $limit,
				'orderby'             => 'menu_order date',
				'order'               => 'ASC',
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			)
		);

		$images = array();

		foreach ( $query->posts as $attachment ) {
			$data = array(
				'id'      => (int) $attachment->ID,
				'url'     => Cover::url( (int) $attachment->ID ),
				'alt'     => (string) get_post_meta( (int) $attachment->ID, '_wp_attachment_image_alt', true ),
				'caption' => (string) $attachment->post_excerpt,
				'width'   => 0,
				'height'  => 0,
			);

			$metadata = wp_get_attachment_metadata( (int) $attachment->ID );

			if ( is_array( $metadata ) && isset( $metadata['width'], $metadata['height'] ) ) {
				$data['width']  = (int) $metadata['width'];
				$data['height'] = (int) $metadata['height'];
			}

			$images[] = $data;
		}

		return $images;
	}

	/**
	 * One card payload for a work.
	 *
	 * @param WP_Post $post Work post.
	 * @return array<string, mixed> `id`, `title`, `subtitle`, `permalink`, `date`,
	 *                              `date_label`, `cover`, `genres`, `type`.
	 */
	private static function card( WP_Post $post ): array {
		$attachment_id = Cover::id( $post->ID );

		return array(
			'id'         => $post->ID,
			'type'       => $post->post_type,
			'title'      => get_the_title( $post ),
			'subtitle'   => MetaValues::text( $post->ID, MetaSchema::SUBTITLE ),
			'permalink'  => (string) get_permalink( $post ),
			'date'       => (int) get_post_time( 'U', true, $post ),
			'date_label' => Dates::label_for_post( $post ),
			'cover'      => $attachment_id ? array(
				'id'  => $attachment_id,
				'url' => Cover::url( $attachment_id ),
			) : array(),
			'genres'     => Terms::genres( $post->ID ),
		);
	}

	/**
	 * Keep a requested item count inside the product's ceiling.
	 *
	 * @param int $limit Requested limit.
	 * @return int
	 */
	private static function clamp( int $limit ): int {
		return (int) max( 1, min( self::MAX_ITEMS, $limit ) );
	}
}
