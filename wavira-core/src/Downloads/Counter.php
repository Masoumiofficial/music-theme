<?php
/**
 * Download counters.
 *
 * Counters are metrics, not content: they are written by the plugin only — never
 * through the editor screen and never through the REST API — and they are bumped
 * with one prepared UPDATE so concurrent downloads cannot lose increments
 * (ADR 0013). Reading them is a plain meta read and can never be stale for long
 * because nothing caches them.
 *
 * Honest scope (SECURITY-AUDIT §3): the counter records downloads that go through
 * the Wavira endpoint. A visitor who saves a public audio file directly from the
 * web server is not counted. No counter can be trusted as an access log.
 *
 * @package Wavira\Core\Downloads
 */

namespace Wavira\Core\Downloads;

use Wavira\Core\Content\MetaSchema;
use Wavira\Core\Content\PostTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Class Counter
 */
final class Counter {

	/**
	 * Bump the counters of one track and return the new total.
	 *
	 * @param int $post_id Track post ID.
	 * @param int $quality Audio quality in kbps (0 = unknown / total only).
	 * @param int $by      Amount to add (always >= 1 in practice).
	 * @return int Total downloads after the increment.
	 */
	public static function increment( int $post_id, int $quality = 0, int $by = 1 ): int {
		if ( $by < 1 || ! self::supports( $post_id ) ) {
			return self::total( $post_id );
		}

		self::bump( $post_id, MetaSchema::DOWNLOAD_COUNT, $by );

		$quality_key = self::key_for( $quality );

		if ( '' !== $quality_key ) {
			self::bump( $post_id, $quality_key, $by );
		}

		/**
		 * Fires after a download was counted.
		 *
		 * @since 0.4.0
		 * @param int $post_id Track post ID.
		 * @param int $quality Audio quality in kbps, 0 when unspecified.
		 * @param int $by      Counted amount.
		 */
		do_action( 'wavira_download_counted', $post_id, $quality, $by );

		return self::total( $post_id );
	}

	/**
	 * Total downloads of a track.
	 *
	 * @param int $post_id Track post ID.
	 * @return int
	 */
	public static function total( int $post_id ): int {
		return self::count( $post_id, MetaSchema::DOWNLOAD_COUNT );
	}

	/**
	 * Downloads of a track for one quality.
	 *
	 * @param int $post_id Track post ID.
	 * @param int $quality Audio quality in kbps.
	 * @return int
	 */
	public static function for_quality( int $post_id, int $quality ): int {
		$key = self::key_for( $quality );

		return '' === $key ? 0 : self::count( $post_id, $key );
	}

	/**
	 * Complete counter snapshot of a track.
	 *
	 * @param int $post_id Track post ID.
	 * @return array<string, mixed>
	 */
	public static function summary( int $post_id ): array {
		$by_quality = array();

		foreach ( array_keys( self::quality_keys() ) as $quality ) {
			$by_quality[ (int) $quality ] = self::for_quality( $post_id, (int) $quality );
		}

		return array(
			'total'      => self::total( $post_id ),
			'by_quality' => $by_quality,
		);
	}

	/**
	 * Reset the counters of a track.
	 *
	 * Deliberately explicit: nothing in the product calls this on its own, so a
	 * site owner can never lose download history through a stray action.
	 *
	 * @param int $post_id Track post ID.
	 * @return void
	 */
	public static function reset( int $post_id ): void {
		foreach ( array_merge( array( MetaSchema::DOWNLOAD_COUNT ), array_values( self::quality_keys() ) ) as $key ) {
			delete_post_meta( $post_id, $key );
		}
	}

	/**
	 * Whether this post can carry download counters.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function supports( int $post_id ): bool {
		return PostTypes::TRACK === get_post_type( $post_id );
	}

	/**
	 * Meta key that stores the count for one quality.
	 *
	 * Unknown qualities return an empty string: a future FLAC download counts
	 * towards the total and can add its own key through the filter.
	 *
	 * @param int $quality Audio quality in kbps (0 = total only).
	 * @return string
	 */
	private static function key_for( int $quality ): string {
		$keys = self::quality_keys();

		if ( 0 === $quality ) {
			return '';
		}

		return isset( $keys[ $quality ] ) ? (string) $keys[ $quality ] : '';
	}

	/**
	 * Supported quality => meta key map.
	 *
	 * @return array<int, string>
	 */
	private static function quality_keys(): array {
		/**
		 * Filters the meta key used per download quality.
		 *
		 * @since 0.4.0
		 * @param array<int, string> $keys Quality in kbps mapped to a meta key.
		 */
		return (array) apply_filters(
			'wavira_download_counter_keys',
			array(
				128 => MetaSchema::DOWNLOAD_COUNT_128,
				320 => MetaSchema::DOWNLOAD_COUNT_320,
			)
		);
	}

	/**
	 * Read one counter key.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @return int
	 */
	private static function count( int $post_id, string $key ): int {
		return max( 0, (int) get_post_meta( $post_id, $key, true ) );
	}

	/**
	 * Add to one counter with a single prepared UPDATE.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param int    $by      Amount to add.
	 * @return void
	 */
	private static function bump( int $post_id, string $key, int $by ): void {
		global $wpdb;

		$updated = (int) $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- atomic counter bump; a read-then-write through the meta API would lose increments under concurrency.
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_value = CAST( meta_value AS UNSIGNED ) + %d WHERE post_id = %d AND meta_key = %s",
				$by,
				$post_id,
				$key
			)
		);

		if ( 0 === $updated ) {
			// First download of this track: create the row with a zero value and
			// run the same single UPDATE. Creating it with `$by` and then updating
			// again counted the first download twice (caught by the integration
			// suite), and `add_post_meta(..., true )` keeps two concurrent first
			// downloads from creating two rows — each request still adds its own
			// increment through the UPDATE.
			add_post_meta( $post_id, $key, 0, true );

			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- see above.
				$wpdb->prepare(
					"UPDATE {$wpdb->postmeta} SET meta_value = CAST( meta_value AS UNSIGNED ) + %d WHERE post_id = %d AND meta_key = %s",
					$by,
					$post_id,
					$key
				)
			);
		}

		wp_cache_delete( $post_id, 'post_meta' );
	}
}
