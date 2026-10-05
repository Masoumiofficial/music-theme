<?php
/**
 * Related music: the recommendation seam of the product.
 *
 * Deliberately transparent and explainable — no machine learning, no tracking:
 * related items are scored from facts the site owner entered (shared genres,
 * same artist, same album, shared featured artists) and completed with recent
 * published items when the catalogue is small. Results are cached and every
 * query is bounded (ADR 0009, CODING-STANDARD F1).
 *
 * @package Wavira\Core\Related
 */

namespace Wavira\Core\Related;

use WP_Post;
use WP_Query;
use Wavira\Core\Content\MetaSchema;
use Wavira\Core\Content\MetaValues;
use Wavira\Core\Content\PostTypes;
use Wavira\Core\Content\Taxonomies;
use Wavira\Core\Settings\Settings;
use Wavira\Core\Support\Cache;

defined( 'ABSPATH' ) || exit;

/**
 * Class RelatedService
 */
final class RelatedService {

	/**
	 * Hard upper bound for a related list.
	 *
	 * @var int
	 */
	public const MAX_ITEMS = 50;

	/**
	 * How many candidates one pool query may fetch per requested item.
	 *
	 * @var int
	 */
	private const POOL_FACTOR = 6;

	/**
	 * TTL for a cached related list, in seconds.
	 *
	 * @var int
	 */
	private const TTL = 3600;

	/**
	 * Whether this post type can have related items.
	 *
	 * @param string $post_type Post type name.
	 * @return bool
	 */
	public static function supports( string $post_type ): bool {
		return in_array(
			$post_type,
			array( PostTypes::TRACK, PostTypes::ALBUM, PostTypes::ARTIST, PostTypes::VIDEO ),
			true
		);
	}

	/**
	 * Related post IDs for one item, best match first.
	 *
	 * @param int $post_id Source post ID.
	 * @param int $limit   Maximum number of items (0 = site setting).
	 * @return int[]
	 */
	public static function ids( int $post_id, int $limit = 0 ): array {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || ! self::supports( (string) $post->post_type ) ) {
			return array();
		}

		$limit = self::limit( $limit );
		$key   = 'related:' . $post_id . ':' . $limit;
		$hit   = Cache::get( $key );

		if ( $hit['found'] && is_array( $hit['value'] ) ) {
			return array_map( 'absint', $hit['value'] );
		}

		$term_ids = self::genre_term_ids( $post_id );
		$scored   = self::score( $post, $term_ids, $limit );
		$ids      = self::fill( $post, $scored, $limit );

		/**
		 * Filters the related post IDs of an item.
		 *
		 * Return an empty array to suppress the related block entirely.
		 *
		 * @since 0.4.0
		 * @param int[]  $ids     Related post IDs, best match first.
		 * @param int    $post_id Source post ID.
		 * @param int    $limit   Requested maximum.
		 */
		$ids = array_values(
			array_unique(
				array_filter(
					array_map( 'absint', (array) apply_filters( 'wavira_related_ids', $ids, $post_id, $limit ) )
				)
			)
		);

		Cache::set( $key, $ids, self::TTL );

		return $ids;
	}

	/**
	 * Related posts of one item, ready for templates.
	 *
	 * @param int $post_id Source post ID.
	 * @param int $limit   Maximum number of items (0 = site setting).
	 * @return WP_Post[]
	 */
	public static function posts( int $post_id, int $limit = 0 ): array {
		$ids  = self::ids( $post_id, $limit );
		$post = get_post( $post_id );

		if ( empty( $ids ) || ! $post instanceof WP_Post ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'           => (string) $post->post_type,
				'post_status'         => 'publish',
				'post__in'            => $ids,
				'orderby'             => 'post__in',
				'posts_per_page'      => count( $ids ),
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			)
		);

		return array_values(
			array_filter(
				$posts,
				static function ( $item ): bool {
					return $item instanceof WP_Post;
				}
			)
		);
	}

	/**
	 * Resolve and clamp the requested item count.
	 *
	 * @param int $limit Requested limit (0 = site setting).
	 * @return int
	 */
	public static function limit( int $limit = 0 ): int {
		if ( $limit < 1 ) {
			$limit = (int) Settings::get( 'related_limit', 8 );
		}

		return (int) min( self::MAX_ITEMS, max( 1, $limit ) );
	}

	/**
	 * Genre term IDs of a post.
	 *
	 * @param int $post_id Post ID.
	 * @return int[]
	 */
	private static function genre_term_ids( int $post_id ): array {
		if ( ! taxonomy_exists( Taxonomies::GENRE ) ) {
			return array();
		}

		$terms = wp_get_post_terms( $post_id, Taxonomies::GENRE, array( 'fields' => 'ids' ) );

		return is_wp_error( $terms ) ? array() : array_map( 'absint', $terms );
	}

	/**
	 * Score a candidate pool for a source post.
	 *
	 * @param WP_Post $post     Source post.
	 * @param int[]   $term_ids Genre term IDs of the source post.
	 * @param int     $limit    Requested number of items.
	 * @return int[] Candidate IDs, best match first.
	 */
	private static function score( WP_Post $post, array $term_ids, int $limit ): array {
		$cap        = (int) min( 50, max( 12, $limit * self::POOL_FACTOR ) );
		$candidates = array();

		if ( ! empty( $term_ids ) ) {
			$candidates = self::query_ids(
				array(
					'post_type'      => (string) $post->post_type,
					'post__not_in'   => array( (int) $post->ID ),
					'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- bounded genre pool.
						array(
							'taxonomy'         => Taxonomies::GENRE,
							'field'            => 'term_id',
							'terms'            => $term_ids,
							'include_children' => false,
						),
					),
					'posts_per_page' => $cap,
					'orderby'        => 'date',
					'order'          => 'DESC',
				)
			);
		}

		$scores = array();

		foreach ( $candidates as $candidate_id ) {
			$score = self::score_one( $post, $candidate_id, $term_ids );

			if ( $score > 0 ) {
				$scores[ $candidate_id ] = $score;
			}
		}

		arsort( $scores );

		return array_map( 'absint', array_keys( $scores ) );
	}

	/**
	 * Score one candidate against the source post.
	 *
	 * Weighting is intentionally simple and documented so site owners can reason
	 * about why an item is related: shared genre +3 each, same artist +2, same
	 * album +1, each shared featured artist +1.
	 *
	 * @param WP_Post $post         Source post.
	 * @param int     $candidate_id Candidate post ID.
	 * @param int[]   $term_ids     Genre term IDs of the source post.
	 * @return int
	 */
	private static function score_one( WP_Post $post, int $candidate_id, array $term_ids ): int {
		$score = 0;

		if ( ! empty( $term_ids ) ) {
			$candidate_terms = self::genre_term_ids( $candidate_id );
			$score          += 3 * count( array_intersect( $term_ids, $candidate_terms ) );
		}

		$post_id  = (int) $post->ID;
		$artist   = MetaValues::int( $post_id, MetaSchema::ARTIST );
		$album    = MetaValues::int( $post_id, MetaSchema::ALBUM );
		$features = MetaValues::featured_artists( $post_id );

		if ( $artist > 0 && MetaValues::int( $candidate_id, MetaSchema::ARTIST ) === $artist ) {
			$score += 2;
		}

		if ( $album > 0 && MetaValues::int( $candidate_id, MetaSchema::ALBUM ) === $album ) {
			++$score;
		}

		if ( ! empty( $features ) ) {
			$score += count( array_intersect( $features, MetaValues::featured_artists( $candidate_id ) ) );
		}

		/**
		 * Filters the relation score of one candidate.
		 *
		 * @since 0.4.0
		 * @param int     $score        Computed score.
		 * @param int     $candidate_id Candidate post ID.
		 * @param WP_Post $post         Source post.
		 */
		return (int) apply_filters( 'wavira_related_score', $score, $candidate_id, $post );
	}

	/**
	 * Complete a scored list with recent published items.
	 *
	 * @param WP_Post $post   Source post.
	 * @param int[]   $scored Already scored IDs.
	 * @param int     $limit  Requested number of items.
	 * @return int[]
	 */
	private static function fill( WP_Post $post, array $scored, int $limit ): array {
		$ids = array_slice( $scored, 0, $limit );

		if ( count( $ids ) >= $limit ) {
			return $ids;
		}

		$exclude = array_merge( array( (int) $post->ID ), $ids );
		$recent  = self::query_ids(
			array(
				'post_type'      => (string) $post->post_type,
				'post__not_in'   => $exclude,
				'posts_per_page' => (int) ( $limit - count( $ids ) ),
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		return array_values( array_unique( array_merge( $ids, $recent ) ) );
	}

	/**
	 * Bounded ID query used for candidate pools.
	 *
	 * Always returns IDs, never loads post objects, and never omits a page bound.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return int[]
	 */
	private static function query_ids( array $args ): array {
		$args = array_merge(
			array(
				'post_status'         => 'publish',
				'fields'              => 'ids',
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
				'posts_per_page'      => 12,
			),
			$args
		);

		// Never allow an unbounded pool, whatever a caller passes.
		$args['posts_per_page'] = (int) min( self::MAX_ITEMS, max( 1, (int) $args['posts_per_page'] ) );

		$query = new WP_Query( $args );

		return array_map( 'absint', $query->posts );
	}
}
