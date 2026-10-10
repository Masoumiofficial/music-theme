<?php
/**
 * Plugin bootstrap and module registry.
 *
 * Deliberately not a "god class": it knows the list of modules and asks each one
 * to register itself; all behaviour lives in the modules (ADR 0002, ARCHITECTURE §4).
 *
 * @package Wavira\Core
 */

namespace Wavira\Core;

use Wavira\Core\Admin\Cli;
use Wavira\Core\Admin\DemoPage;
use Wavira\Core\Content\ContentModule;
use Wavira\Core\Contracts\Registrable;
use Wavira\Core\Player\Assets;
use Wavira\Core\Rest\ContentRoutes;
use Wavira\Core\Settings\Settings;
use Wavira\Core\Support\CacheInvalidator;
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
	 * Registered modules.
	 *
	 * @var Registrable[]
	 */
	private array $modules = array();

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

		// And again once `init` has started. WordPress 6.7 warns about a
		// translation load that happens before `init` and skips it, which leaves
		// the plugin's strings English inside a Persian page; the second call
		// costs nothing when the first one worked.
		add_action( 'init', array( $this, 'load_textdomain' ), 0 );

		foreach ( $this->default_modules() as $module ) {
			$this->register( $module );
		}

		/**
		 * Fires when Wavira Core is ready to accept additional modules.
		 *
		 * A module added here must implement Registrable; if the plugin has
		 * already booted, it is registered immediately.
		 *
		 * @since 0.2.0
		 * @param Plugin $plugin The plugin instance.
		 */
		do_action( 'wavira_core_booted', $this );

		foreach ( $this->modules as $module ) {
			$module->register();
		}

		$this->booted = true;
	}

	/**
	 * Add a module to the registry.
	 *
	 * @param Registrable $module Module to register.
	 * @return void
	 */
	public function register( Registrable $module ): void {
		$this->modules[] = $module;
	}

	/**
	 * Registered modules (for tooling and diagnostics).
	 *
	 * @return Registrable[]
	 */
	public function modules(): array {
		return $this->modules;
	}

	/**
	 * Modules shipped with the plugin, in boot order.
	 *
	 * @return Registrable[]
	 */
	private function default_modules(): array {
		return array(
			new ContentModule(),
			new Settings(),
			new ContentRoutes(),
			new Assets(),
			new CacheInvalidator(),
			new DemoPage(),
			new Cli(),
		);
	}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'wavira-core', false, dirname( plugin_basename( WAVIRA_CORE_FILE ) ) . '/languages' );

		// `load_plugin_textdomain()` gives up quietly: it loads the plugin's own
		// `languages/<locale>.mo` only when WordPress lets it, and a load it
		// considers too early leaves the domain empty. The result is invisible in
		// an otherwise Persian site — only the plugin's strings stay English, so
		// an artist page read “Albums (۲)” and every check passed (0.15.0's
		// render). Loading the file by hand makes the domain load whatever the
		// request's timing is, and keeps it loaded when the catalogue ships inside
		// the plugin rather than in `wp-content/languages`.
		$locale = determine_locale();
		$file   = WAVIRA_CORE_DIR . 'languages/' . $locale . '.mo';

		if ( ! is_textdomain_loaded( 'wavira-core' ) && file_exists( $file ) ) {
			load_textdomain( 'wavira-core', $file, $locale );
		}
	}
}
