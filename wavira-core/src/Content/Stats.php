<?php
/**
 * Engagement metrics (views, plays, likes, admin offsets) and Top Artists rankings.
 *
 * Recorded user interactions (`wavira_views`, `wavira_likes`) are stored
 * separately from administrator manual adjustments (`wavira_views_offset`,
 * `wavira_likes_offset`). Public totals are the sum of both, clamped at zero,
 * so an editor can set an initial baseline or correction without overwriting
 * real listener activity.
 *
 * @package Wavira\Core\Content
 */

namespace Wavira\Core\Content;

use WP_Post;
use Wavira\Core\Support\Cache;

defined( 'ABSPATH' ) || exit;

/**
 * Class Stats
 */
final class Stats {

	/**
	 * Transient TTL (seconds) for deduplicating repeated view/play hits.
	 *
	 * @var int
	 */
	public const VIEW_WINDOW = 600;

	/**
	 * Public view/play total (`recorded + offset`).
	 *
	 * @param int $post_id Post ID.
	 * @return int
	 */
	public static function views( int $post_id ): int {
		if ( $post_id < 1 ) {
			return 0;
		}

		$recorded = MetaValues::int( $post_id, MetaSchema::VIEWS );
		$offset   = MetaValues::int( $post_id, MetaSchema::VIEWS_OFFSET );

		return max( 0, $recorded + $offset );
	}

	/**
	 * Public like total (`recorded + offset`).
	 *
	 * @param int $post_id Post ID.
	 * @return int
	 */
	public static function likes( int $post_id ): int {
		if ( $post_id < 1 ) {
			return 0;
		}

		$recorded = MetaValues::int( $post_id, MetaSchema::LIKES );
		$offset   = MetaValues::int( $post_id, MetaSchema::LIKES_OFFSET );

		return max( 0, $recorded + $offset );
	}

	/**
	 * Complete breakdown of a post's engagement counters for admin and REST.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $token   Optional visitor token.
	 * @return array<string, mixed>
	 */
	public static function breakdown( int $post_id, string $token = '' ): array {
		$recorded_views = $post_id > 0 ? MetaValues::int( $post_id, MetaSchema::VIEWS ) : 0;
		$views_offset   = $post_id > 0 ? MetaValues::int( $post_id, MetaSchema::VIEWS_OFFSET ) : 0;
		$recorded_likes = $post_id > 0 ? MetaValues::int( $post_id, MetaSchema::LIKES ) : 0;
		$likes_offset   = $post_id > 0 ? MetaValues::int( $post_id, MetaSchema::LIKES_OFFSET ) : 0;
		$downloads      = $post_id > 0 ? MetaValues::int( $post_id, MetaSchema::DOWNLOAD_COUNT ) : 0;

		return array(
			'id'             => $post_id,
			'recorded_views' => $recorded_views,
			'views_offset'   => $views_offset,
			'views'          => max( 0, $recorded_views + $views_offset ),
			'views_label'    => Dates::digits( number_format_i18n( max( 0, $recorded_views + $views_offset ) ) ),
			'recorded_likes' => $recorded_likes,
			'likes_offset'   => $likes_offset,
			'likes'          => max( 0, $recorded_likes + $likes_offset ),
			'likes_label'    => Dates::digits( number_format_i18n( max( 0, $recorded_likes + $likes_offset ) ) ),
			'downloads'      => $downloads,
			'liked'          => self::has_liked( $post_id, $token ),
		);
	}

	/**
	 * Record a view or play start on a published music post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $token   Visitor fingerprint or `'force'` to bypass the window.
	 * @return int Updated public view count.
	 */
	public static function record_view( int $post_id, string $token = '' ): int {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || ! PostTypes::is_music_type( $post->post_type ) ) {
			return 0;
		}

		if ( 'force' !== $token ) {
			$actor = self::actor_hash( $post_id, $token );
			$guard = 'wavira_vw_' . $actor;

			if ( false !== get_transient( $guard ) ) {
				return self::views( $post_id );
			}

			set_transient( $guard, 1, self::VIEW_WINDOW );
		}

		$recorded = MetaValues::int( $post_id, MetaSchema::VIEWS ) + 1;
		update_post_meta( $post_id, MetaSchema::VIEWS, $recorded );

		foreach ( self::period_keys( 'views' ) as $period_key ) {
			$current = max( 0, (int) get_post_meta( $post_id, $period_key, true ) );
			update_post_meta( $post_id, $period_key, $current + 1 );
		}

		Cache::flush();

		return self::views( $post_id );
	}

	/**
	 * Whether the current user or visitor token has liked a post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $token   Optional guest token.
	 * @return bool
	 */
	public static function has_liked( int $post_id, string $token = '' ): bool {
		if ( $post_id < 1 ) {
			return false;
		}

		$user_id = get_current_user_id();

		if ( $user_id > 0 ) {
			$liked = get_user_meta( $user_id, '_wavira_liked_posts', true );
			$liked = is_array( $liked ) ? array_map( 'absint', $liked ) : array();

			return in_array( $post_id, $liked, true );
		}

		$actor = self::actor_hash( $post_id, $token );

		return false !== get_transient( 'wavira_lk_' . $actor );
	}

	/**
	 * Toggle a like on a published music post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $token   Optional guest token.
	 * @return array<string, mixed> `liked` (bool), `likes` (int), `likes_label` (string).
	 */
	public static function toggle_like( int $post_id, string $token = '' ): array {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || ! PostTypes::is_music_type( $post->post_type ) ) {
			return array(
				'liked'       => false,
				'likes'       => 0,
				'likes_label' => Dates::digits( '0' ),
			);
		}

		$user_id  = get_current_user_id();
		$recorded = MetaValues::int( $post_id, MetaSchema::LIKES );
		$now_like = false;

		if ( $user_id > 0 ) {
			$liked = get_user_meta( $user_id, '_wavira_liked_posts', true );
			$liked = is_array( $liked ) ? array_values( array_unique( array_map( 'absint', $liked ) ) ) : array();

			if ( in_array( $post_id, $liked, true ) ) {
				$liked    = array_values( array_diff( $liked, array( $post_id ) ) );
				$recorded = max( 0, $recorded - 1 );
				$now_like = false;
			} else {
				$liked[] = $post_id;
				++$recorded;
				$now_like = true;
			}

			update_user_meta( $user_id, '_wavira_liked_posts', $liked );
		} else {
			$actor = self::actor_hash( $post_id, $token );
			$guard = 'wavira_lk_' . $actor;

			if ( false !== get_transient( $guard ) ) {
				delete_transient( $guard );
				$recorded = max( 0, $recorded - 1 );
				$now_like = false;
			} else {
				set_transient( $guard, 1, 30 * DAY_IN_SECONDS );
				++$recorded;
				$now_like = true;
			}
		}

		update_post_meta( $post_id, MetaSchema::LIKES, $recorded );

		$delta = $now_like ? 1 : -1;

		foreach ( self::period_keys( 'likes' ) as $period_key ) {
			$current = max( 0, (int) get_post_meta( $post_id, $period_key, true ) );
			update_post_meta( $post_id, $period_key, max( 0, $current + $delta ) );
		}

		Cache::flush();

		$total = self::likes( $post_id );

		return array(
			'liked'       => $now_like,
			'likes'       => $total,
			'likes_label' => Dates::digits( number_format_i18n( $total ) ),
		);
	}

	/**
	 * Ranked Top Artists for a time period (`week`, `month`, `year`, `all`).
	 *
	 * Aggregates works credited to each artist (`wavira_artist` and
	 * `wavira_featured_artists`, deduplicated per work) without double-counting.
	 *
	 * @param string $period `week`, `month`, `year`, or `all`.
	 * @param int    $limit  Maximum number of artists to return.
	 * @return array<int, array<string, mixed>>
	 */
	public static function top_artists( string $period = 'all', int $limit = 8 ): array {
		$period = in_array( $period, array( 'week', 'month', 'year', 'all' ), true ) ? $period : 'all';
		$limit  = max( 1, min( 24, $limit ) );
		$key    = 'top_artists:' . $period . ':' . $limit . ':' . Dates::style();
		$cached = Cache::get( $key );

		if ( ! empty( $cached['found'] ) && is_array( $cached['value'] ) ) {
			return $cached['value'];
		}

		$artist_ids = get_posts(
			array(
				'post_type'              => PostTypes::ARTIST,
				'post_status'            => 'publish',
				'posts_per_page'         => 100,
				'fields'                 => 'ids',
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);

		if ( array() === $artist_ids ) {
			return array();
		}

		$buckets = array();

		foreach ( $artist_ids as $aid ) {
			$aid             = (int) $aid;
			$buckets[ $aid ] = array(
				'views'       => self::period_metric( $aid, 'views', $period ),
				'likes'       => self::period_metric( $aid, 'likes', $period ),
				'downloads'   => 0,
				'works_count' => 0,
			);
		}

		$works = get_posts(
			array(
				'post_type'              => array( PostTypes::TRACK, PostTypes::ALBUM, PostTypes::VIDEO ),
				'post_status'            => 'publish',
				'posts_per_page'         => 300,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);

		foreach ( $works as $work_id ) {
			$work_id  = (int) $work_id;
			$credited = array();
			$primary  = MetaValues::int( $work_id, MetaSchema::ARTIST );

			if ( $primary > 0 ) {
				$credited[] = $primary;
			}

			foreach ( MetaValues::ids( $work_id, MetaSchema::FEATURED_ARTISTS ) as $feat_id ) {
				if ( $feat_id > 0 ) {
					$credited[] = (int) $feat_id;
				}
			}

			$credited = array_values( array_unique( $credited ) );

			if ( array() === $credited ) {
				continue;
			}

			$w_views = self::period_metric( $work_id, 'views', $period );
			$w_likes = self::period_metric( $work_id, 'likes', $period );
			$w_dl    = MetaValues::int( $work_id, MetaSchema::DOWNLOAD_COUNT );

			foreach ( $credited as $aid ) {
				if ( ! isset( $buckets[ $aid ] ) ) {
					continue;
				}

				$buckets[ $aid ]['views']     += $w_views;
				$buckets[ $aid ]['likes']     += $w_likes;
				$buckets[ $aid ]['downloads'] += $w_dl;
				++$buckets[ $aid ]['works_count'];
			}
		}

		$rows = array();

		foreach ( $buckets as $aid => $metrics ) {
			$score  = ( $metrics['views'] * 1 ) + ( $metrics['likes'] * 3 ) + ( $metrics['downloads'] * 2 ) + ( $metrics['works_count'] * 5 );
			$rows[] = array(
				'id'          => $aid,
				'name'        => (string) get_the_title( $aid ),
				'permalink'   => (string) get_permalink( $aid ),
				'verified'    => MetaValues::bool( $aid, MetaSchema::VERIFIED ),
				'cover'       => Cover::payload( $aid ),
				'views'       => $metrics['views'],
				'views_label' => Dates::digits( number_format_i18n( $metrics['views'] ) ),
				'likes'       => $metrics['likes'],
				'likes_label' => Dates::digits( number_format_i18n( $metrics['likes'] ) ),
				'downloads'   => $metrics['downloads'],
				'works_count' => $metrics['works_count'],
				'works_label' => Dates::digits( number_format_i18n( $metrics['works_count'] ) ),
				'score'       => $score,
			);
		}

		usort(
			$rows,
			static function ( array $a, array $b ): int {
				if ( $b['score'] !== $a['score'] ) {
					return $b['score'] <=> $a['score'];
				}

				return $b['id'] <=> $a['id'];
			}
		);

		$rows = array_slice( $rows, 0, $limit );

		foreach ( $rows as $idx => &$row ) {
			$row['rank']       = $idx + 1;
			$row['rank_label'] = Dates::digits( (string) ( $idx + 1 ) );
		}
		unset( $row );

		Cache::set( $key, $rows, 300 );

		return $rows;
	}

	/**
	 * Read period-specific views or likes for a post, falling back to total when
	 * a period bucket has no recorded events yet (e.g. freshly seeded demo data).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $metric  `views` or `likes`.
	 * @param string $period  `week`, `month`, `year`, or `all`.
	 * @return int
	 */
	private static function period_metric( int $post_id, string $metric, string $period ): int {
		$total = 'likes' === $metric ? self::likes( $post_id ) : self::views( $post_id );

		if ( 'all' === $period ) {
			return $total;
		}

		$keys = self::period_keys( $metric );

		if ( isset( $keys[ $period ] ) ) {
			$bucket = (int) get_post_meta( $post_id, $keys[ $period ], true );

			if ( $bucket > 0 ) {
				return $bucket;
			}
		}

		return $total;
	}

	/**
	 * Meta keys for the current week, month and year buckets.
	 *
	 * @param string $metric `views` or `likes`.
	 * @return array<string, string>
	 */
	private static function period_keys( string $metric ): array {
		return array(
			'week'  => '_wavira_' . $metric . '_week_' . gmdate( 'oW' ),
			'month' => '_wavira_' . $metric . '_month_' . gmdate( 'Ym' ),
			'year'  => '_wavira_' . $metric . '_year_' . gmdate( 'Y' ),
		);
	}

	/**
	 * Build a privacy-safe hash for rate-limiting views and guest likes.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $token   Optional client token.
	 * @return string
	 */
	private static function actor_hash( int $post_id, string $token = '' ): string {
		$user_id = get_current_user_id();

		if ( $user_id > 0 ) {
			return substr( md5( $post_id . ':u:' . $user_id ), 0, 16 );
		}

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';

		return substr( md5( $post_id . ':g:' . $ip . ':' . $token ), 0, 16 );
	}
}
