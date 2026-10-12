<?php
/**
 * Runtime requirements gate (PHP + WordPress versions).
 *
 * @package Wavira\Core\Support
 */

namespace Wavira\Core\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Class Requirements
 */
final class Requirements {

	/**
	 * Minimum PHP version.
	 *
	 * @var string
	 */
	private string $min_php;

	/**
	 * Minimum WordPress version.
	 *
	 * @var string
	 */
	private string $min_wp;

	/**
	 * Constructor.
	 *
	 * @param string $min_php Minimum PHP version.
	 * @param string $min_wp  Minimum WordPress version.
	 */
	public function __construct( string $min_php = '', string $min_wp = '' ) {
		$this->min_php = '' !== $min_php ? $min_php : ( defined( 'WAVIRA_CORE_MIN_PHP' ) ? WAVIRA_CORE_MIN_PHP : '7.4' );
		$this->min_wp  = '' !== $min_wp ? $min_wp : ( defined( 'WAVIRA_CORE_MIN_WP' ) ? WAVIRA_CORE_MIN_WP : '6.6' );
	}

	/**
	 * Whether PHP is new enough.
	 *
	 * @return bool
	 */
	public function php_ok(): bool {
		return version_compare( PHP_VERSION, $this->min_php, '>=' );
	}

	/**
	 * Whether WordPress is new enough.
	 *
	 * @return bool
	 */
	public function wp_ok(): bool {
		global $wp_version;

		return ! isset( $wp_version ) || version_compare( (string) $wp_version, $this->min_wp, '>=' );
	}

	/**
	 * Whether all requirements are satisfied.
	 *
	 * @return bool
	 */
	public function is_satisfied(): bool {
		return $this->php_ok() && $this->wp_ok();
	}

	/**
	 * Human-readable failure reason, or an empty string when satisfied.
	 *
	 * @return string
	 */
	public function failure_reason(): string {
		if ( ! $this->php_ok() ) {
			return sprintf( 'PHP %s+ required (running %s).', $this->min_php, PHP_VERSION );
		}

		if ( ! $this->wp_ok() ) {
			global $wp_version;

			return sprintf( 'WordPress %s+ required (running %s).', $this->min_wp, (string) $wp_version );
		}

		return '';
	}
}
