<?php
/**
 * Search across the music catalogue.
 *
 * The service is the single search entry point for the REST API, the templates
 * and future integrations. It only ever queries published music content, always
 * bounds `posts_per_page`, and caches its result set behind the versioned cache
 * helper so a busy site does not repeat the same search on every request
 * (ADR 0009).
 *
 * @package Wavira\Core\Search
 */

namespace Wavira\Core\Search;

use WP_Post;
use WP_Query;
use Wavira\Core\Content\PostTypes;
use Wavira\Core\Content\QueryFilters;
use Wavira\Core\Settings\Settings;
use Wavira\Core\Support\Cache;

defined( 'ABSPATH' ) || exit;

/**
 * Class SearchService
 */
final class SearchService {

	/**
	 * Hard upper bound for one page of results.
	 *
	 * @var int
	 */
	public const MAX_PER_PAGE = 50;

	/**
	 * Longest accepted search term, in characters.
	 *
	 * @var int
	 */
	public const MAX_TERM_LENGTH = 100;

	/**
	 * TTL for a cached result set, in seconds.
	 *
	 * @var int
	 */
	private const TTL = 300;

	/**
	 * Search the catalogue.
	 *
	 * @param array<string, mixed> $args Query arguments: `term`, `type`, `page`,
	 *                                   `per_page`, `genre`, `artist`, `orderby`, `order`.
	 * @return array{items: WP_Post[], total: int, pages: int}
	 */
	public static function search( array $args = array() ): array {
		$args = self::normalize( $args );

		if ( 'rand' === $args['orderby'] ) {
			return self::run( $args );
		}

		$cached = Cache::remember(
			'search:' . md5( (string) wp_json_encode( $args ) ),
			static function () use ( $args ): array {
				return self::run( $args );
			},
			self::TTL
		);

		return is_array( $cached ) ? $cached : self::run( $args );
	}

	/**
	 * Lightweight grouped suggestions for a term.
	 *
	 * @param string $term  Search term.
	 * @param int    $limit Results per entity (1–12).
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public static function suggest( string $term, int $limit = 6 ): array {
		$term  = self::clean_term( $term );
		$limit = (int) min( 12, max( 1, $limit ) );
		$out   = array();

		if ( '' === $term ) {
			return $out;
		}

		foreach ( array( PostTypes::TRACK, PostTypes::ALBUM, PostTypes::ARTIST ) as $type ) {
			$result = self::search(
				array(
					'term'     => $term,
					'type'     => $type,
					'per_page' => $limit,
					'orderby'  => 'relevance',
				)
			);

			$out[ self::group_key( $type ) ] = array_map( array( self::class, 'summarize' ), $result['items'] );
		}

		return $out;
	}

	/**
	 * Group key of a suggestion list: the public slug when one exists.
	 *
	 * @param string $post_type Post type name.
	 * @return string
	 */
	private static function group_key( string $post_type ): string {
		$slug = PostTypes::slug_for( $post_type );

		return '' !== $slug ? $slug : $post_type;
	}

	/**
	 * Minimal public summary of one item (used by suggestions and mixed lists).
	 *
	 * @param WP_Post $post Post object.
	 * @return array<string, mixed>
	 */
	public static function summarize( WP_Post $post ): array {
		return array(
			'id'        => (int) $post->ID,
			'type'      => $post->post_type,
			'title'     => get_the_title( $post ),
			'url'       => (string) get_permalink( $post ),
			'thumbnail' => (string) get_the_post_thumbnail_url( $post, 'thumbnail' ),
		);
	}

	/**
	 * Post types this service searches.
	 *
	 * @return string[]
	 */
	public static function searchable_types(): array {
		/**
		 * Filters the post types included in music search.
		 *
		 * @since 0.4.0
		 * @param string[] $types Post type names.
		 */
		return array_values( array_filter( (array) apply_filters( 'wavira_searchable_types', PostTypes::all() ), 'is_string' ) );
	}

	/**
	 * Resolve a requested type into a list of post types.
	 *
	 * Accepts a post type name (`wavira_track`), a public slug (`tracks`), an
	 * empty string or `any` for everything searchable.
	 *
	 * @param string $type Requested type.
	 * @return string[]
	 */
	public static function types_for( string $type ): array {
		$type = sanitize_key( $type );

		if ( '' === $type || 'any' === $type ) {
			return self::searchable_types();
		}

		foreach ( PostTypes::all() as $post_type ) {
			if ( $post_type === $type || PostTypes::slug_for( $post_type ) === $type ) {
				return array( $post_type );
			}
		}

		return self::searchable_types();
	}

	/**
	 * Normalise incoming arguments.
	 *
	 * @param array<string, mixed> $args Raw arguments.
	 * @return array<string, mixed>
	 */
	private static function normalize( array $args ): array {
		$args = wp_parse_args(
			$args,
			array(
				'term'     => '',
				'type'     => '',
				'page'     => 1,
				'per_page' => 0,
				'genre'    => '',
				'artist'   => 0,
				'orderby'  => 'relevance',
				'order'    => 'desc',
			)
		);

		$orderby = sanitize_key( (string) $args['orderby'] );

		if ( ! in_array( $orderby, array( 'relevance', 'date', 'title', 'modified', 'rand' ), true ) ) {
			$orderby = 'relevance';
		}

		return array(
			'term'     => self::clean_term( (string) $args['term'] ),
			'type'     => sanitize_key( (string) $args['type'] ),
			'page'     => max( 1, absint( $args['page'] ) ),
			'per_page' => QueryFilters::clamp_per_page( $args['per_page'], (int) Settings::get( 'tracks_per_page', 20 ), self::MAX_PER_PAGE ),
			'genre'    => sanitize_title( (string) $args['genre'] ),
			'artist'   => absint( $args['artist'] ),
			'orderby'  => $orderby,
			'order'    => 'asc' === strtolower( (string) $args['order'] ) ? 'asc' : 'desc',
		);
	}

	/**
	 * Trim and bound a search term.
	 *
	 * @param string $term Raw term.
	 * @return string
	 */
	private static function clean_term( string $term ): string {
		$term = trim( sanitize_text_field( $term ) );

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $term, 0, self::MAX_TERM_LENGTH );
		}

		return substr( $term, 0, self::MAX_TERM_LENGTH );
	}

	/**
	 * Execute one search.
	 *
	 * @param array<string, mixed> $args Normalised arguments.
	 * @return array{items: WP_Post[], total: int, pages: int}
	 */
	private static function run( array $args ): array {
		$query = new WP_Query( self::query_args( $args ) );
		$items = array();

		foreach ( $query->posts as $post ) {
			if ( $post instanceof WP_Post ) {
				$items[] = $post;
			}
		}

		return array(
			'items' => $items,
			'total' => (int) $query->found_posts,
			'pages' => (int) $query->max_num_pages,
		);
	}

	/**
	 * Build the WP_Query arguments.
	 *
	 * @param array<string, mixed> $args Normalised arguments.
	 * @return array<string, mixed>
	 */
	private static function query_args( array $args ): array {
		$query = array_merge(
			array(
				'post_type'           => self::types_for( (string) $args['type'] ),
				'post_status'         => 'publish',
				'posts_per_page'      => (int) $args['per_page'],
				'paged'               => (int) $args['page'],
				'ignore_sticky_posts' => true,
				's'                   => (string) $args['term'],
				'order'               => 'asc' === $args['order'] ? 'ASC' : 'DESC',
			),
			QueryFilters::args(
				array(
					'genre'  => $args['genre'],
					'artist' => $args['artist'],
				),
				''
			)
		);

		$query['orderby'] = self::orderby( (string) $args['orderby'], (string) $args['term'] );

		return $query;
	}

	/**
	 * Map the requested order to a WP_Query value.
	 *
	 * `relevance` without a term has nothing to rank against, so it falls back to
	 * newest first.
	 *
	 * @param string $orderby Requested order.
	 * @param string $term    Search term.
	 * @return string
	 */
	private static function orderby( string $orderby, string $term ): string {
		if ( 'rand' === $orderby ) {
			return 'rand';
		}

		if ( 'relevance' === $orderby ) {
			return '' === $term ? 'date' : 'relevance';
		}

		return $orderby;
	}
}
