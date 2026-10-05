<?php
/**
 * Queue building for the player engine.
 *
 * A queue is a bounded, ordered list of playable tracks in a context: an album
 * plays its tracklist order, an artist or genre plays newest-first, "tracks"
 * plays the catalogue and "related" plays the scored related items. Every
 * context is limited — the player is a player, not an export tool — and every
 * entry is the same playback payload a single track returns.
 *
 * @package Wavira\Core\Player
 */

namespace Wavira\Core\Player;

use Wavira\Core\Content\MetaValues;
use Wavira\Core\Content\PostTypes;
use Wavira\Core\Content\OrderArgs;
use Wavira\Core\Content\QueryFilters;
use Wavira\Core\Related\RelatedService;
use Wavira\Core\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class Queue
 */
final class Queue {

	/**
	 * Hard ceiling for one queue, independent of any setting or request.
	 *
	 * @var int
	 */
	public const MAX_ITEMS = 100;

	/**
	 * Contexts the queue can be built from.
	 *
	 * @var string[]
	 */
	public const CONTEXTS = array( 'album', 'artist', 'genre', 'tracks', 'related' );

	/**
	 * Playable items of a context.
	 *
	 * Entries without any audio source are dropped: a queue that contains
	 * unplayable tracks is worse than a shorter queue. The payload filter has
	 * already run, so a site that adds its own sources is never filtered out.
	 *
	 * @param string               $context Context name (see CONTEXTS).
	 * @param array<string, mixed> $args    Context arguments (`id`, `slug`, `limit`, `orderby`, `order`).
	 * @return array<int, array<string, mixed>> Playback payloads, in play order.
	 */
	public static function items( string $context, array $args = array() ): array {
		$items = array();

		foreach ( self::ids( $context, $args ) as $post_id ) {
			$payload = Payload::for_track( (int) $post_id );

			if ( array() !== $payload && ! empty( $payload['sources'] ) ) {
				$items[] = $payload;
			}
		}

		/**
		 * Filters the items of a player queue.
		 *
		 * @since 0.5.0
		 * @param array<int, array<string, mixed>> $items   Playback payloads.
		 * @param string                           $context Queue context.
		 * @param array<string, mixed>             $args    Context arguments.
		 */
		return (array) apply_filters( 'wavira_player_queue_items', $items, $context, $args );
	}

	/**
	 * Post IDs of a context, in play order.
	 *
	 * @param string               $context Context name (see CONTEXTS).
	 * @param array<string, mixed> $args    Context arguments.
	 * @return int[]
	 */
	public static function ids( string $context, array $args = array() ): array {
		$context = strtolower( $context );
		$limit   = self::clamp_limit( isset( $args['limit'] ) ? $args['limit'] : 0 );

		switch ( $context ) {
			case 'album':
				return self::album_ids( isset( $args['id'] ) ? absint( $args['id'] ) : 0, $limit );

			case 'artist':
				return self::query_ids(
					array( 'artist' => isset( $args['id'] ) ? absint( $args['id'] ) : 0 ),
					$limit,
					$args
				);

			case 'genre':
				return self::query_ids(
					array( 'genre' => isset( $args['slug'] ) ? (string) $args['slug'] : '' ),
					$limit,
					$args
				);

			case 'related':
				return self::related_ids( isset( $args['id'] ) ? absint( $args['id'] ) : 0, $limit );

			case 'tracks':
				return self::query_ids( array(), $limit, $args );

			default:
				return array();
		}
	}

	/**
	 * Clamp a requested queue length.
	 *
	 * @param mixed $requested Requested length (0 = site default).
	 * @return int
	 */
	public static function clamp_limit( $requested ): int {
		$fallback = (int) Settings::get( 'tracks_per_page' );

		return QueryFilters::clamp_per_page( $requested, $fallback, self::MAX_ITEMS );
	}

	/**
	 * Ordered tracks of an album.
	 *
	 * The album's own tracklist is authoritative (ADR 0012); it is queried by ID
	 * so drafts and trashed tracks disappear without reordering the rest.
	 *
	 * @param int $album_id Album post ID.
	 * @param int $limit    Maximum number of items.
	 * @return int[]
	 */
	private static function album_ids( int $album_id, int $limit ): array {
		if ( $album_id < 1 || PostTypes::ALBUM !== get_post_type( $album_id ) ) {
			return array();
		}

		$track_ids = array_slice( MetaValues::tracklist( $album_id ), 0, $limit );

		if ( empty( $track_ids ) ) {
			return array();
		}

		return self::query_ids( array(), $limit, array(), array( 'post__in' => $track_ids ) );
	}

	/**
	 * Related tracks, scored by the related service.
	 *
	 * @param int $post_id Source track ID.
	 * @param int $limit   Maximum number of items.
	 * @return int[]
	 */
	private static function related_ids( int $post_id, int $limit ): array {
		if ( $post_id < 1 || PostTypes::TRACK !== get_post_type( $post_id ) ) {
			return array();
		}

		return array_slice( RelatedService::ids( $post_id, $limit ), 0, $limit );
	}

	/**
	 * Run a bounded track query and return its IDs.
	 *
	 * @param array<string, mixed> $filters Relation filters for QueryFilters.
	 * @param int                  $limit   Maximum number of items.
	 * @param array<string, mixed> $args    Raw context arguments (`orderby`, `order`).
	 * @param array<string, mixed> $extra   Extra query arguments (e.g. `post__in`).
	 * @return int[]
	 */
	private static function query_ids( array $filters, int $limit, array $args, array $extra = array() ): array {
		$orderby = isset( $args['orderby'] ) ? (string) $args['orderby'] : 'date';
		$order   = isset( $args['order'] ) ? (string) $args['order'] : 'desc';

		if ( isset( $extra['post__in'] ) && empty( $extra['post__in'] ) ) {
			return array();
		}

		$query_args = array_merge(
			array(
				'post_type'           => PostTypes::TRACK,
				'post_status'         => 'publish',
				'posts_per_page'      => $limit,
				'fields'              => 'ids',
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
			),
			OrderArgs::get( $orderby, $order ),
			QueryFilters::args( $filters, PostTypes::TRACK ),
			$extra
		);

		$ids = get_posts( $query_args );

		return array_map( 'absint', is_array( $ids ) ? $ids : array() );
	}
}
