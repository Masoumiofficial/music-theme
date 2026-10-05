<?php
/**
 * Music structured data (schema.org JSON-LD), built from the product's own model.
 *
 * Why the product ships this at all: every general-purpose SEO plugin covers
 * `Article`, breadcrumbs and sitemaps, and none of them knows what a
 * `wavira_album` is. The relationship between an artist, an album, a track's
 * duration and a release date is data this product already stores, so the graph
 * is assembled from the same source the templates read — never from a second
 * copy (ARCHITECTURE §1, ADR 0016).
 *
 * Rules the graph follows:
 * - only published music posts produce nodes; posts and pages are the SEO
 *   plugin's territory and are deliberately left alone;
 * - every value is read through `MetaValues`/`Cover`, so escaped-in storage or a
 *   changed meta key cannot produce invalid JSON-LD;
 * - the album tracklist is bounded (`ALBUM_TRACK_LIMIT`), because a search
 *   engine needs a representative list, not a payload larger than the page;
 * - nodes are plain arrays: the theme decides how to print them, tests assert
 *   them without a browser, and a headless consumer can reuse them (ADR 0016).
 *
 * @package Wavira\Core\Seo
 */

namespace Wavira\Core\Seo;

use Wavira\Core\Content\Cover;
use Wavira\Core\Content\Credit;
use Wavira\Core\Content\MetaSchema;
use Wavira\Core\Content\MetaValues;
use Wavira\Core\Content\PostTypes;
use Wavira\Core\Content\Taxonomies;

defined( 'ABSPATH' ) || exit;

/**
 * Class StructuredData
 */
final class StructuredData {

	/**
	 * Track nodes included in one album graph.
	 *
	 * A schema consumer reads this as "what is on the album"; the full list stays
	 * in the tracklist data and in the page itself.
	 */
	public const ALBUM_TRACK_LIMIT = 50;

	/**
	 * Genre terms included in one node.
	 */
	public const GENRE_LIMIT = 6;

	/**
	 * The JSON-LD graph for one post.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int, array<string, mixed>> Nodes, empty when the post is not a
	 *                                          published music entity.
	 */
	public static function graph( int $post_id ): array {
		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status ) {
			return array();
		}

		switch ( $post->post_type ) {
			case PostTypes::ARTIST:
				return array( self::artist( $post ) );
			case PostTypes::ALBUM:
				return array( self::album( $post ) );
			case PostTypes::TRACK:
				return array( self::track( $post ) );
			case PostTypes::VIDEO:
				return array( self::video( $post ) );
			default:
				// Posts and pages: the site's SEO plugin owns that surface.
				return array();
		}
	}

	/**
	 * Whether a post type produces a music graph.
	 *
	 * @param string $post_type Post type slug.
	 * @return bool
	 */
	public static function supports( string $post_type ): bool {
		return in_array(
			$post_type,
			array( PostTypes::ARTIST, PostTypes::ALBUM, PostTypes::TRACK, PostTypes::VIDEO ),
			true
		);
	}

	/**
	 * Artist node.
	 *
	 * @param \WP_Post $post Artist post.
	 * @return array<string, mixed>
	 */
	private static function artist( \WP_Post $post ): array {
		$node = array(
			'@type' => 'MusicGroup',
			'@id'   => self::node_id( $post, 'musicgroup' ),
			'name'  => get_the_title( $post ),
			'url'   => (string) get_permalink( $post ),
		);

		$node = self::with_common( $node, $post );
		$node = self::with_same_as( $node, $post->ID );

		return $node;
	}

	/**
	 * Album node, with its tracklist.
	 *
	 * @param \WP_Post $post Album post.
	 * @return array<string, mixed>
	 */
	private static function album( \WP_Post $post ): array {
		$node = array(
			'@type'         => 'MusicAlbum',
			'@id'           => self::node_id( $post, 'musicalbum' ),
			'name'          => get_the_title( $post ),
			'url'           => (string) get_permalink( $post ),
			'datePublished' => self::date( $post ),
		);

		$node = self::with_common( $node, $post );
		$node = self::with_artists( $node, $post->ID );

		$tracks = self::tracklist( $post->ID );

		if ( array() !== $tracks ) {
			$node['numTracks'] = count( $tracks );
			$node['track']     = array_slice( $tracks, 0, self::ALBUM_TRACK_LIMIT );
		}

		return $node;
	}

	/**
	 * Track node.
	 *
	 * @param \WP_Post $post Track post.
	 * @return array<string, mixed>
	 */
	private static function track( \WP_Post $post ): array {
		$node = array(
			'@type'         => 'MusicRecording',
			'@id'           => self::node_id( $post, 'musicrecording' ),
			'name'          => get_the_title( $post ),
			'url'           => (string) get_permalink( $post ),
			'datePublished' => self::date( $post ),
		);

		$node     = self::with_common( $node, $post );
		$node     = self::with_artists( $node, $post->ID );
		$duration = MetaValues::int( $post->ID, MetaSchema::DURATION );

		if ( $duration > 0 ) {
			$node['duration'] = self::duration_iso8601( $duration );
		}

		$isrc = MetaValues::text( $post->ID, MetaSchema::ISRC );

		if ( '' !== $isrc ) {
			$node['isrcCode'] = $isrc;
		}

		$album = MetaValues::int( $post->ID, MetaSchema::ALBUM );

		if ( $album > 0 ) {
			$album_post = get_post( $album );

			if ( $album_post instanceof \WP_Post && 'publish' === $album_post->post_status ) {
				$node['inAlbum'] = array(
					'@type' => 'MusicAlbum',
					'@id'   => self::node_id( $album_post, 'musicalbum' ),
					'name'  => get_the_title( $album_post ),
					'url'   => (string) get_permalink( $album_post ),
				);
			}
		}

		return $node;
	}

	/**
	 * Music video node.
	 *
	 * @param \WP_Post $post Video post.
	 * @return array<string, mixed>
	 */
	private static function video( \WP_Post $post ): array {
		$node = array(
			'@type'      => 'MusicVideoObject',
			'@id'        => self::node_id( $post, 'musicvideoobject' ),
			'name'       => get_the_title( $post ),
			'url'        => (string) get_permalink( $post ),
			'uploadDate' => self::date( $post ),
		);

		$node = self::with_common( $node, $post );
		$node = self::with_artists( $node, $post->ID );

		$image = self::image_url( $post );

		if ( '' !== $image ) {
			$node['thumbnailUrl'] = $image;
		}

		$hosted = '';

		foreach ( array( MetaSchema::VIDEO_1080, MetaSchema::VIDEO_720, MetaSchema::VIDEO_480 ) as $key ) {
			$hosted = MetaValues::url( $post->ID, $key );

			if ( '' !== $hosted ) {
				break;
			}
		}

		if ( '' !== $hosted ) {
			$node['contentUrl'] = $hosted;

			return $node;
		}

		$external = MetaValues::url( $post->ID, MetaSchema::VIDEO_URL );
		$embed    = self::embed_url( $external );

		if ( '' !== $embed ) {
			$node['embedUrl'] = $embed;
		} elseif ( '' !== $external ) {
			$node['contentUrl'] = $external;
		}

		return $node;
	}

	/**
	 * Fields every music node shares: description, image, genre.
	 *
	 * @param array<string, mixed> $node Node so far.
	 * @param \WP_Post             $post Post.
	 * @return array<string, mixed>
	 */
	private static function with_common( array $node, \WP_Post $post ): array {
		$description = self::description( $post );

		if ( '' !== $description ) {
			$node['description'] = $description;
		}

		$image = self::image_url( $post );

		if ( '' !== $image ) {
			$node['image'] = $image;
		}

		$genres = self::genres( $post->ID );

		if ( array() !== $genres ) {
			$node['genre'] = $genres;
		}

		return $node;
	}

	/**
	 * Attach the credited artists as inline `MusicGroup` nodes.
	 *
	 * Inline rather than `@id` references on purpose: a track page usually has no
	 * artist node of its own, and a reference to an absent node tells a consumer
	 * nothing (ADR 0016).
	 *
	 * @param array<string, mixed> $node    Node so far.
	 * @param int                  $post_id Post ID.
	 * @return array<string, mixed>
	 */
	private static function with_artists( array $node, int $post_id ): array {
		$artists = array();

		foreach ( Credit::ids( $post_id ) as $id ) {
			$artist = get_post( $id );

			if ( ! $artist instanceof \WP_Post ) {
				continue;
			}

			$artists[] = array(
				'@type' => 'MusicGroup',
				'@id'   => self::node_id( $artist, 'musicgroup' ),
				'name'  => get_the_title( $artist ),
				'url'   => (string) get_permalink( $artist ),
			);
		}

		if ( array() === $artists ) {
			return $node;
		}

		$node['byArtist'] = 1 === count( $artists ) ? $artists[0] : $artists;

		return $node;
	}

	/**
	 * Social and website profiles of an artist, as `sameAs`.
	 *
	 * @param array<string, mixed> $node      Node so far.
	 * @param int                  $artist_id Artist ID.
	 * @return array<string, mixed>
	 */
	private static function with_same_as( array $node, int $artist_id ): array {
		$urls = array();

		foreach ( MetaValues::socials( $artist_id ) as $social ) {
			if ( isset( $social['url'] ) && '' !== (string) $social['url'] ) {
				$urls[] = (string) $social['url'];
			}
		}

		$website = MetaValues::url( $artist_id, MetaSchema::WEBSITE );

		if ( '' !== $website ) {
			$urls[] = $website;
		}

		if ( array() !== $urls ) {
			$node['sameAs'] = array_values( array_unique( $urls ) );
		}

		return $node;
	}

	/**
	 * Published tracks of an album, as minimal `MusicRecording` nodes.
	 *
	 * @param int $album_id Album ID.
	 * @return array<int, array<string, mixed>>
	 */
	private static function tracklist( int $album_id ): array {
		$nodes = array();

		foreach ( MetaValues::tracklist( $album_id ) as $track_id ) {
			$track = get_post( $track_id );

			if ( ! $track instanceof \WP_Post || PostTypes::TRACK !== $track->post_type || 'publish' !== $track->post_status ) {
				continue;
			}

			$node = array(
				'@type' => 'MusicRecording',
				'@id'   => self::node_id( $track, 'musicrecording' ),
				'name'  => get_the_title( $track ),
				'url'   => (string) get_permalink( $track ),
			);

			$duration = MetaValues::int( $track->ID, MetaSchema::DURATION );

			if ( $duration > 0 ) {
				$node['duration'] = self::duration_iso8601( $duration );
			}

			$nodes[] = $node;
		}

		return $nodes;
	}

	/**
	 * Genre names of a post, most specific first.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int, string>
	 */
	private static function genres( int $post_id ): array {
		if ( ! taxonomy_exists( Taxonomies::GENRE ) ) {
			return array();
		}

		$terms = wp_get_post_terms(
			$post_id,
			Taxonomies::GENRE,
			array(
				'fields' => 'names',
				'number' => self::GENRE_LIMIT,
			)
		);

		if ( ! is_array( $terms ) ) {
			return array();
		}

		return array_values( array_map( 'strval', $terms ) );
	}

	/**
	 * Description: the excerpt, else a trimmed, markup-free body.
	 *
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	private static function description( \WP_Post $post ): string {
		$excerpt = trim( wp_strip_all_tags( (string) $post->post_excerpt, true ) );

		if ( '' !== $excerpt ) {
			return $excerpt;
		}

		return trim( wp_trim_words( wp_strip_all_tags( (string) $post->post_content, true ), 30, '…' ) );
	}

	/**
	 * Image URL of a post: cover or featured image, in the large size.
	 *
	 * @param \WP_Post $post Post.
	 * @return string Empty string when the post has no image.
	 */
	private static function image_url( \WP_Post $post ): string {
		$payload = Cover::payload( $post->ID );

		if ( isset( $payload['id'] ) && (int) $payload['id'] > 0 ) {
			$url = (string) wp_get_attachment_image_url( (int) $payload['id'], 'wavira-cover-lg' );

			if ( '' !== $url ) {
				return $url;
			}
		}

		return isset( $payload['url'] ) ? (string) $payload['url'] : '';
	}

	/**
	 * Publication date, ISO 8601.
	 *
	 * The release date is an editorial fact (`wavira_release_date`); the post
	 * date is only the fallback, because a re-published old album must not look
	 * new to a search engine.
	 *
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	private static function date( \WP_Post $post ): string {
		$release = MetaValues::text( $post->ID, MetaSchema::RELEASE_DATE );

		// The field is a date (`sanitize => date`, i.e. `Y-m-d`); anything else is
		// stored text and must not be guessed into a timestamp.
		if ( 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $release ) ) {
			$stamp = strtotime( $release . ' 00:00:00 UTC' );

			if ( false !== $stamp ) {
				return (string) gmdate( 'c', $stamp );
			}
		}

		return (string) get_the_date( 'c', $post );
	}

	/**
	 * Seconds as an ISO 8601 duration (`PT3M42S`).
	 *
	 * @param int $seconds Duration in seconds.
	 * @return string
	 */
	public static function duration_iso8601( int $seconds ): string {
		$seconds = max( 0, $seconds );
		$hours   = (int) floor( $seconds / 3600 );
		$minutes = (int) floor( ( $seconds % 3600 ) / 60 );
		$rest    = $seconds % 60;
		$iso     = 'PT';

		if ( $hours > 0 ) {
			$iso .= $hours . 'H';
		}

		if ( $minutes > 0 ) {
			$iso .= $minutes . 'M';
		}

		if ( $rest > 0 || 'PT' === $iso ) {
			$iso .= $rest . 'S';
		}

		return $iso;
	}

	/**
	 * A provider watch URL as an embeddable URL, when the provider is known.
	 *
	 * Only the two providers the product documents are mapped (YouTube, Aparat);
	 * anything else keeps its own URL, and the theme's oEmbed path handles the
	 * actual playback (ADR 0016 §3).
	 *
	 * @param string $url Provider URL.
	 * @return string Embed URL, empty string when the provider is unknown.
	 */
	public static function embed_url( string $url ): string {
		if ( '' === $url ) {
			return '';
		}

		$host = (string) wp_parse_url( $url, PHP_URL_HOST );
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$host = strtolower( preg_replace( '/^www\./', '', $host ) );

		if ( 'youtu.be' === $host ) {
			$id = trim( $path, '/' );

			return '' !== $id ? 'https://www.youtube.com/embed/' . rawurlencode( $id ) : '';
		}

		if ( 'youtube.com' === $host || 'm.youtube.com' === $host || 'music.youtube.com' === $host ) {
			$id = '';

			if ( '/watch' === $path ) {
				$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
				parse_str( $query, $args );
				$id = isset( $args['v'] ) ? (string) $args['v'] : '';
			} elseif ( 0 === strpos( $path, '/embed/' ) || 0 === strpos( $path, '/shorts/' ) ) {
				$id = substr( $path, strrpos( $path, '/' ) + 1 );
			}

			return '' !== $id ? 'https://www.youtube.com/embed/' . rawurlencode( $id ) : '';
		}

		if ( 'aparat.com' === $host ) {
			$hash = trim( $path, '/' );

			if ( 0 === strpos( $hash, 'v/' ) ) {
				$hash = substr( $hash, 2 );
			}

			return '' !== $hash ? 'https://www.aparat.com/video/video/embed/videohash/' . rawurlencode( $hash ) . '/vt/frame' : '';
		}

		return '';
	}

	/**
	 * Fragment identifier of a node, derived from its permalink.
	 *
	 * @param \WP_Post $post Post.
	 * @param string   $kind Node kind.
	 * @return string
	 */
	private static function node_id( \WP_Post $post, string $kind ): string {
		return (string) get_permalink( $post ) . '#' . $kind;
	}
}
