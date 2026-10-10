<?php
/**
 * Minimal PSR-4 autoloader for the Wavira\Core namespace.
 *
 * Deliberately dependency-free: no Composer requirement at runtime, so the
 * plugin can be installed by uploading a ZIP. Composer remains supported for
 * development tooling (see composer.json).
 *
 * @package Wavira\Core\Support
 */

namespace Wavira\Core\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Class Autoloader
 */
final class Autoloader {

	/**
	 * Namespace prefix handled by this autoloader.
	 *
	 * @var string
	 */
	private const PREFIX = 'Wavira\\Core\\';

	/**
	 * Directory that maps to the namespace root.
	 *
	 * @var string
	 */
	private static string $root = '';

	/**
	 * Register the autoloader.
	 *
	 * @param string $root Absolute path to the src directory.
	 * @return void
	 */
	public static function register( string $root = '' ): void {
		self::$root = '' !== $root ? trailingslashit( $root ) : trailingslashit( __DIR__ . '/..' );

		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Map a class name to a file and load it.
	 *
	 * @param string $class_name Fully qualified class name.
	 * @return void
	 */
	public static function autoload( string $class_name ): void {
		if ( 0 !== strpos( $class_name, self::PREFIX ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( self::PREFIX ) );
		$relative = str_replace( '\\', '/', $relative );
		$path     = self::$root . $relative . '.php';

		// Only ever load files that stay inside the plugin's src directory.
		$real = realpath( $path );
		$base = realpath( self::$root );

		if ( false === $real || false === $base || 0 !== strpos( $real, $base ) ) {
			return;
		}

		require_once $real;
	}
}
