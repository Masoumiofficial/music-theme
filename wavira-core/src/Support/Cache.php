<?php
/**
 * Thin caching helper for computed results.
 *
 * Respects persistent object caches when present, falls back to transients, and
 * supports versioned invalidation so a service can flush everything it owns
 * without scanning keys (ADR 0009 — performance as a merge gate).
 *
 * @package Wavira\Core\Support
 */

namespace Wavira\Core\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Class Cache
 */
final class Cache {

	/**
	 * Cache group used for all Wavira entries.
	 *
	 * @var string
	 */
	public const GROUP = 'wavira';

	/**
	 * Option that stores the current cache generation.
	 *
	 * @var string
	 */
	private const VERSION_OPTION = 'wavira_cache_version';

	/**
	 * Get a value from the object cache.
	 *
	 * @param string $key Cache key.
	 * @return array{found: bool, value: mixed}
	 */
	public static function get( string $key ): array {
		$found = false;
		$value = wp_cache_get( self::versioned_key( $key ), self::GROUP, false, $found );

		return array(
			'found' => (bool) $found,
			'value' => $value,
		);
	}

	/**
	 * Store a value in the object cache.
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value to store.
	 * @param int    $ttl   Time to live in seconds.
	 * @return void
	 */
	public static function set( string $key, $value, int $ttl = 300 ): void {
		wp_cache_set( self::versioned_key( $key ), $value, self::GROUP, $ttl );
	}

	/**
	 * Return a cached value, computing and storing it on a miss.
	 *
	 * @param string   $key      Cache key.
	 * @param callable $callback Produces the value on a miss.
	 * @param int      $ttl      Time to live in seconds.
	 * @return mixed
	 */
	public static function remember( string $key, callable $callback, int $ttl = 300 ) {
		$cached = self::get( $key );

		if ( $cached['found'] ) {
			return $cached['value'];
		}

		$value = $callback();
		self::set( $key, $value, $ttl );

		return $value;
	}

	/**
	 * Invalidate every cached value (all groups) by bumping the generation.
	 *
	 * Called on content saves and settings updates.
	 *
	 * @return void
	 */
	public static function flush(): void {
		$generation = (int) get_option( self::VERSION_OPTION, 1 );

		update_option( self::VERSION_OPTION, $generation + 1, false );
	}

	/**
	 * Current cache generation.
	 *
	 * @return int
	 */
	public static function generation(): int {
		return max( 1, (int) get_option( self::VERSION_OPTION, 1 ) );
	}

	/**
	 * Prefix a key with the current generation.
	 *
	 * @param string $key Base cache key.
	 * @return string
	 */
	private static function versioned_key( string $key ): string {
		return 'g' . self::generation() . ':' . $key;
	}
}
