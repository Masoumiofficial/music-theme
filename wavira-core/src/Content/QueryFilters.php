<?php
/**
 * Shared query filters for music collections.
 *
 * One place translates the public filters (`genre`, `artist`, `album`,
 * `featured`) into `WP_Query` arguments, so the REST controllers, the search
 * service and future template helpers cannot drift apart (ADR 0003).
 *
 * Every filter here is bounded and indexed: taxonomy slugs, relation post IDs
 * and a boolean flag. No unbounded queries are built anywhere in the product
 * (CODING-STANDARD F1).
 *
 * @package Wavira\Core\Content
 */

namespace Wavira\Core\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Class QueryFilters
 */
final class QueryFilters {

	/**
	 * Build query arguments from a normalised filter set.
	 *
	 * @param array<string, mixed> $filters   Filter values (`genre`, `artist`, `album`, `featured`).
	 * @param string               $post_type Post type the query targets.
	 * @return array<string, mixed>
	 */
	public static function args( array $filters, string $post_type ): array {
		$args = array();

		$genre = isset( $filters['genre'] ) ? (string) $filters['genre'] : '';
		$genre = self::genre( $genre );

		if ( ! empty( $genre ) ) {
			$args['tax_query'] = $genre; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- bounded, indexed taxonomy filter.
		}

		$meta = self::relations( $filters, $post_type );

		if ( ! empty( $meta ) ) {
			$args['meta_query'] = $meta; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- bounded relation filters on indexed meta keys.
		}

		return $args;
	}

	/**
	 * Taxonomy query for a genre slug.
	 *
	 * @param string $slug Genre slug.
	 * @return array<int, array<string, mixed>>
	 */
	public static function genre( string $slug ): array {
		if ( '' === $slug || ! taxonomy_exists( Taxonomies::GENRE ) ) {
			return array();
		}

		return array(
			array(
				'taxonomy' => Taxonomies::GENRE,
				'field'    => 'slug',
				'terms'    => $slug,
			),
		);
	}

	/**
	 * Meta query for relation filters.
	 *
	 * @param array<string, mixed> $filters   Filter values.
	 * @param string               $post_type Post type the query targets.
	 * @return array<int, array<string, mixed>>
	 */
	public static function relations( array $filters, string $post_type ): array {
		$meta = array();

		$artist = isset( $filters['artist'] ) ? absint( $filters['artist'] ) : 0;

		if ( $artist > 0 ) {
			$meta[] = array(
				'key'   => MetaSchema::ARTIST,
				'value' => $artist,
			);
		}

		$album = isset( $filters['album'] ) ? absint( $filters['album'] ) : 0;

		if ( $album > 0 && in_array( $post_type, array( PostTypes::TRACK, PostTypes::VIDEO ), true ) ) {
			$meta[] = array(
				'key'   => MetaSchema::ALBUM,
				'value' => $album,
			);
		}

		if ( ! empty( $filters['featured'] ) ) {
			$meta[] = array(
				'key'   => MetaSchema::FEATURED,
				'value' => '1',
			);
		}

		return $meta;
	}

	/**
	 * Clamp a requested page size.
	 *
	 * @param mixed $requested Requested size.
	 * @param int   $fallback  Used when nothing sensible was requested.
	 * @param int   $maximum   Hard upper bound.
	 * @return int
	 */
	public static function clamp_per_page( $requested, int $fallback, int $maximum ): int {
		$requested = absint( $requested );

		if ( $requested < 1 ) {
			$requested = $fallback;
		}

		return (int) min( max( 1, $maximum ), max( 1, $requested ) );
	}
}
