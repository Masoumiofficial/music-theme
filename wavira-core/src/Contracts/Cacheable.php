<?php
/**
 * Contract for services that cache computed results.
 *
 * Implementations must be safe with and without a persistent object cache
 * (Redis/Memcached) and must respect page caching: nothing here may depend on
 * a logged-in user's request state.
 *
 * @package Wavira\Core\Contracts
 */

namespace Wavira\Core\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Interface Cacheable
 */
interface Cacheable {

	/**
	 * Build a cache key for the given arguments.
	 *
	 * @param array<mixed> $args Arguments that affect the result.
	 * @return string
	 */
	public function cache_key( array $args ): string;

	/**
	 * Invalidate all cached results for this service.
	 *
	 * @return void
	 */
	public function flush(): void;
}
