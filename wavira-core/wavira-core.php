<?php
/**
 * Plugin Name:       Wavira Core
 * Plugin URI:        https://etehadwp.com/
 * Description:       Music content engine for Wavira: artists, albums, tracks, videos, genres, playback data and REST API. Theme-independent — your music survives any theme switch.
 * Version:           0.8.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Etehad WP (اتحاد وردپرس)
 * Author URI:        https://etehadwp.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wavira-core
 * Domain Path:       /languages
 *
 * @package Wavira\Core
 */

defined( 'ABSPATH' ) || exit;

define( 'WAVIRA_CORE_VERSION', '0.8.0' );
define( 'WAVIRA_CORE_FILE', __FILE__ );
define( 'WAVIRA_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'WAVIRA_CORE_URI', plugin_dir_url( __FILE__ ) );
define( 'WAVIRA_CORE_MIN_PHP', '7.4' );
define( 'WAVIRA_CORE_MIN_WP', '6.6' );

/*
 * Hard guard before loading any class file: the plugin's sources use PHP 7.4
 * syntax (typed properties), so on an older host we must not require them —
 * that would be a parse error, not an exception. Bail out with a notice instead
 * and keep the site working (front end untouched, admins informed).
 */
if ( version_compare( PHP_VERSION, WAVIRA_CORE_MIN_PHP, '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: required PHP version, 2: current PHP version. */
						__( 'Wavira Core requires PHP %1$s or newer. This server runs PHP %2$s, so music features are disabled.', 'wavira-core' ),
						WAVIRA_CORE_MIN_PHP,
						PHP_VERSION
					)
				)
			);
		}
	);

	return;
}

require_once WAVIRA_CORE_DIR . 'src/Support/Autoloader.php';
require_once WAVIRA_CORE_DIR . 'src/Support/Requirements.php';

\Wavira\Core\Support\Autoloader::register();

/*
 * Public function API: the only surface a theme may call. Loaded before the boot
 * sequence so a template that renders early still finds the functions.
 */
require_once WAVIRA_CORE_DIR . 'public-api.php';

/**
 * Boot the plugin on `plugins_loaded`, after the requirements gate.
 *
 * @return void
 */
function wavira_core_bootstrap() {
	\Wavira\Core\Plugin::instance()->boot();
}
add_action( 'plugins_loaded', 'wavira_core_bootstrap', 5 );

/**
 * Requirements notice for administrators (never a fatal error on the front end).
 *
 * @return void
 */
function wavira_core_requirements_notice() {
	$requirements = new \Wavira\Core\Support\Requirements();

	if ( ! current_user_can( 'activate_plugins' ) || $requirements->is_satisfied() ) {
		return;
	}

	echo '<div class="notice notice-error"><p>';
	echo esc_html(
		sprintf(
			/* translators: 1: required PHP version, 2: required WordPress version. */
			__( 'Wavira Core needs PHP %1$s+ and WordPress %2$s+ to run. Music features are disabled until the server meets these versions.', 'wavira-core' ),
			WAVIRA_CORE_MIN_PHP,
			WAVIRA_CORE_MIN_WP
		)
	);
	echo '</p></div>';
}
add_action( 'admin_notices', 'wavira_core_requirements_notice' );

/**
 * Activation: requirements gate. No demo content, no tables, no writes here —
 * data structures are created by the content layer on `init` when needed.
 *
 * @return void
 */
function wavira_core_activate() {
	$requirements = new \Wavira\Core\Support\Requirements();

	if ( ! $requirements->is_satisfied() ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );

		wp_die(
			esc_html__( 'Wavira Core was deactivated: your server does not meet the minimum PHP or WordPress version.', 'wavira-core' ),
			esc_html__( 'Plugin activation failed', 'wavira-core' ),
			array( 'back_link' => true )
		);
	}
}
register_activation_hook( __FILE__, 'wavira_core_activate' );

/**
 * Deactivation: keep data, clear only transient caches.
 *
 * @return void
 */
function wavira_core_deactivate() {
	// Derived data only: dropping the generation orphans every versioned cache
	// key, while content and settings are never touched on deactivation.
	delete_option( 'wavira_cache_version' );

	/**
	 * Fires after Wavira Core was deactivated.
	 *
	 * @since 0.4.0
	 */
	do_action( 'wavira_core_deactivated' );
}
register_deactivation_hook( __FILE__, 'wavira_core_deactivate' );
