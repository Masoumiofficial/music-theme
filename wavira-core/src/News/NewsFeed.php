<?php
/**
 * The site's news feed: WordPress posts as music-media news items.
 *
 * Persian music sites publish news (release announcements, concerts, interviews)
 * as ordinary posts, and the theme needs one predictable shape to render them
 * everywhere: the blog index, a category archive, a "music news" section on the
 * home page, and a `[wavira_news]` shortcode in an article.
 *
 * This service turns a post query into lean items. It deliberately does not
 * invent a "news" post type: posts, categories and the editor's own workflow are
 * what a site owner already knows, and a second content type would split the
 * archive, the feed and the sitemap in two (docs/DECISIONS.md 0.9.0).
 *
 * @package Wavira\Core\News
 * @since   0.9.0
 */

namespace Wavira\Core\News;

use Wavira\Core\Content\Cover;
use WP_Post;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Class NewsFeed
 */
final class NewsFeed {

	/**
	 * Hard ceiling for one feed request.
	 *
	 * @var int
	 */
	public const MAX_ITEMS = 24;

	/**
	 * Default items per request.
	 *
	 * @var int
	 */
	public const DEFAULT_ITEMS = 6;

	/**
	 * Read the newest posts as news items.
	 *
	 * @param array<string, mixed> $args {
	 *     Optional. Query arguments.
	 *
	 *     @type int    $limit      Items to return (default 6, max 24).
	 *     @type string $category   Category slug to restrict the feed to.
	 *     @type int    $offset     Items to skip (for a second, hand-built feed).
	 *     @type int[]  $exclude    Post IDs to leave out.
	 *     @type string $post_type  Post type to read (default `post`).
	 * }
	 * @return array<int, array<string, mixed>> Rows of `id`, `title`, `excerpt`,
	 *                                          `permalink`, `date`, `date_label`,
	 *                                          `thumbnail`, `categories`, `author`.
	 */
	public static function items( array $args = array() ): array {
		$args = wp_parse_args(
			$args,
			array(
				'limit'     => self::DEFAULT_ITEMS,
				'category'  => '',
				'offset'    => 0,
				'exclude'   => array(),
				'post_type' => 'post',
			)
		);

		$post_type = self::post_type( (string) $args['post_type'] );
		$limit     = (int) max( 1, min( self::MAX_ITEMS, (int) $args['limit'] ) );
		$exclude   = array_values( array_filter( array_map( 'absint', (array) $args['exclude'] ) ) );

		$query_args = array(
			'post_type'           => $post_type,
			'post_status'         => 'publish',
			'posts_per_page'      => $limit,
			'offset'              => (int) max( 0, (int) $args['offset'] ),
			'orderby'             => 'date',
			'order'               => 'DESC',
			'ignore_sticky_posts' => false,
			'no_found_rows'       => true,
		);

		if ( $exclude ) {
			$query_args['post__not_in'] = $exclude; // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.post__not_in -- a hand-built feed excludes a handful of editor-picked IDs, never a growing list.
		}

		$category = sanitize_title( (string) $args['category'] );

		if ( '' !== $category && 'post' === $post_type ) {
			$query_args['category_name'] = $category;
		}

		$query = new WP_Query( $query_args );
		$items = array();

		foreach ( $query->posts as $post ) {
			if ( $post instanceof WP_Post ) {
				$items[] = self::item( $post );
			}
		}

		/**
		 * Filters the news items before they are returned.
		 *
		 * @param array<int, array<string, mixed>> $items News items.
		 * @param array<string, mixed>             $args  The original arguments.
		 */
		return (array) apply_filters( 'wavira_core_news_items', $items, $args );
	}

	/**
	 * One item payload.
	 *
	 * @param WP_Post $post Post.
	 * @return array<string, mixed>
	 */
	private static function item( WP_Post $post ): array {
		$thumbnail_id = Cover::id( $post->ID );
		$author_id    = (int) $post->post_author;

		return array(
			'id'         => $post->ID,
			'title'      => get_the_title( $post ),
			'excerpt'    => self::excerpt( $post ),
			'permalink'  => (string) get_permalink( $post ),
			'date'       => (int) get_post_time( 'U', true, $post ),
			'date_label' => (string) get_the_date( '', $post ),
			'thumbnail'  => $thumbnail_id ? array(
				'id'  => $thumbnail_id,
				'url' => Cover::url( $thumbnail_id ),
			) : array(),
			'categories' => self::categories( $post->ID ),
			'author'     => array(
				'id'   => $author_id,
				'name' => (string) get_the_author_meta( 'display_name', $author_id ),
			),
		);
	}

	/**
	 * Excerpt, falling back to a trimmed body for posts that have none.
	 *
	 * @param WP_Post $post Post.
	 * @return string Plain text, no markup.
	 */
	private static function excerpt( WP_Post $post ): string {
		if ( '' !== trim( (string) $post->post_excerpt ) ) {
			return wp_strip_all_tags( $post->post_excerpt, true );
		}

		return wp_trim_words( wp_strip_all_tags( (string) $post->post_content, true ), 28, '…' );
	}

	/**
	 * Category payloads of a post, for the card's kicker line.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int, array<string, string>> Rows of `name`, `link`.
	 */
	private static function categories( int $post_id ): array {
		$terms = get_the_category( $post_id );

		if ( ! is_array( $terms ) ) {
			return array();
		}

		$rows = array();

		foreach ( $terms as $term ) {
			$link = get_category_link( $term );

			$rows[] = array(
				'name' => (string) $term->name,
				'link' => is_string( $link ) ? $link : '',
			);
		}

		return $rows;
	}

	/**
	 * Resolve the requested post type to a public, queryable one.
	 *
	 * Falls back to `post` rather than querying something private: a caller may
	 * pass any string, and a feed must never be able to surface drafts of a
	 * private type.
	 *
	 * @param string $post_type Requested post type.
	 * @return string
	 */
	private static function post_type( string $post_type ): string {
		$post_type = sanitize_key( $post_type );

		if ( '' === $post_type ) {
			return 'post';
		}

		$object = get_post_type_object( $post_type );

		if ( ! $object instanceof \WP_Post_Type || ! $object->public || ! $object->publicly_queryable ) {
			return 'post';
		}

		return $post_type;
	}
}
