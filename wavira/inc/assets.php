<?php
/**
 * Front-end asset registration and conditional enqueueing.
 *
 * Rules (docs/PERFORMANCE-AUDIT.md §3, docs/CODING-STANDARD.md):
 * - no front-end jQuery dependency;
 * - assets are only enqueued when the built file exists, so an un-built
 *   checkout never produces a 404;
 * - the player bundle is loaded only where a player is actually rendered
 *   (wired in phase 0.5.0 / 0.6.0).
 *
 * @package Wavira\Theme
 * @since   0.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Return the built stylesheet URI, or an empty string when not built yet.
 *
 * @return string
 */
function wavira_theme_stylesheet_uri() {
	$relative = 'assets/dist/theme.css';

	return file_exists( WAVIRA_THEME_DIR . $relative ) ? WAVIRA_THEME_URI . $relative : '';
}

/**
 * Version string for cache busting, falling back to the theme version.
 *
 * @param string $relative Path relative to the theme root.
 * @return string
 */
function wavira_asset_version( $relative ) {
	$path = WAVIRA_THEME_DIR . ltrim( $relative, '/' );

	return file_exists( $path ) ? (string) filemtime( $path ) : WAVIRA_THEME_VERSION;
}

/**
 * Enqueue front-end styles and scripts.
 *
 * @return void
 */
function wavira_enqueue_assets() {
	$stylesheet = wavira_theme_stylesheet_uri();

	if ( '' !== $stylesheet ) {
		wp_enqueue_style(
			'wavira-theme',
			$stylesheet,
			array(),
			wavira_asset_version( 'assets/dist/theme.css' )
		);
	}

	$script = 'assets/dist/theme.js';

	if ( file_exists( WAVIRA_THEME_DIR . $script ) ) {
		wp_enqueue_script(
			'wavira-theme',
			WAVIRA_THEME_URI . $script,
			array(),
			wavira_asset_version( $script ),
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);
	}
}
add_action( 'wp_enqueue_scripts', 'wavira_enqueue_assets' );
