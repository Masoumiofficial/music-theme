<?php
/**
 * Plugin bootstrap and service container.
 *
 * Responsibilities are intentionally limited at this phase (0.2.0): boot the
 * plugin, wire i18n and assets, and expose seams for the modules that arrive in
 * later phases (Content, Player, Rest, Settings, …).
 *
 * Deliberately NOT a "god class": modules register their own hooks in their own
 * `boot()` methods. This class only knows the list of modules.
 *
 * @package Wavira\Core
 */

namespace Wavira\Core;

use Wavira\Core\Support\Requirements;

defined( 'ABSPATH' ) || exit;

/**
 * Class Plugin
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Whether boot() already ran.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Retrieve the single plugin instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor — use instance().
	 */
	private function __construct() {}

	/**
	 * Whether the plugin finished booting.
	 *
	 * @return bool
	 */
	public function is_booted(): bool {
		return $this->booted;
	}

	/**
	 * Boot the plugin.
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$requirements = new Requirements();

		if ( ! $requirements->is_satisfied() ) {
			// Front end stays silent; admins already get a notice from the bootstrap file.
			return;
		}

		$this->load_textdomain();

		/**
		 * Fires when Wavira Core is ready to register its modules.
		 *
		 * Modules (content types, player, REST, settings, …) hook here instead of
		 * calling init() directly, which keeps boot order explicit and testable.
		 *
		 * @since 0.2.0
		 * @param Plugin $plugin The plugin instance.
		 */
		do_action( 'wavira_core_booted', $this );

		$this->booted = true;
	}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	private function load_textdomain(): void {
		load_plugin_textdomain( 'wavira-core', false, dirname( plugin_basename( WAVIRA_CORE_FILE ) ) . '/languages' );
	}
}
