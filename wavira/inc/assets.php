<?php
/**
 * Front-end asset registration and conditional enqueueing.
 *
 * Rules (docs/PERFORMANCE-AUDIT.md §3, ADR 0006, ADR 0009):
 * - no front-end jQuery dependency and no polyfills;
 * - assets are only enqueued when the built file exists, so an un-built checkout
 *   never produces a 404;
 * - the player bundle belongs to Wavira Core and loads only where a player is
 *   rendered (`wavira_core_enqueue_player()`);
 * - the colour mode is applied by a tiny inline script before the first paint, so
 *   a stored dark preference never flashes a light page.
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
 * Everything the theme script needs from PHP.
 *
 * The colour-mode strings live here so the toggle speaks the site's language
 * without a second translation source, and so the script has English fallbacks
 * when PHP printed nothing (tests, static previews).
 *
 * @return array<string, mixed>
 */
function wavira_theme_settings() {
	$settings = array(
		'storageKey' => 'wavira.theme',
		'strings'    => array(
			'label' => __( 'Colour theme', 'wavira' ),
			'light' => __( 'Light', 'wavira' ),
			'dark'  => __( 'Dark', 'wavira' ),
			'auto'  => __( 'Auto', 'wavira' ),
		),
	);

	/**
	 * Filters the theme script settings.
	 *
	 * @since 0.6.0
	 * @param array<string, mixed> $settings Theme settings.
	 */
	return (array) apply_filters( 'wavira_theme_settings', $settings );
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

		wp_add_inline_script(
			'wavira-theme',
			'window.waviraThemeSettings = ' . wp_json_encode( wavira_theme_settings() ) . ';',
			'before'
		);
	}
}
add_action( 'wp_enqueue_scripts', 'wavira_enqueue_assets' );

/**
 * Apply the stored colour mode before the first paint.
 *
 * This must stay tiny and dependency-free: it runs in <head>, before the
 * stylesheet, so `auto` follows the system and a stored `dark` never flashes a
 * light page. Everything else (toggle, cycling, persistence) is theme.js.
 *
 * @return void
 */
function wavira_print_colour_mode() {
	$key = 'wavira.theme';

	$script = '(function(){try{var m=window.localStorage.getItem(' . wp_json_encode( $key ) . ');'
		. 'if(m!=="light"&&m!=="dark"&&m!=="auto"){m="auto";}'
		. 'document.documentElement.setAttribute("data-theme",m);}catch(e){'
		. 'document.documentElement.setAttribute("data-theme","auto");}})();';

	wp_print_inline_script_tag( $script );
}
add_action( 'wp_head', 'wavira_print_colour_mode', 1 );
