<?php
/**
 * Small, dependency-free template helpers.
 *
 * The theme never owns music data or settings: settings live in Wavira Core.
 * These helpers therefore degrade gracefully when the plugin is missing.
 *
 * @package Wavira\Theme
 * @since   0.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the Wavira Core plugin is active and providing music features.
 *
 * Asks the plugin's own signal instead of a class name: the theme is not
 * supposed to know anything about the plugin's internals (ARCHITECTURE §2,
 * enforced by `tools/check-boundaries.mjs`).
 *
 * @return bool
 */
function wavira_has_core() {
	return function_exists( 'wavira_core_is_active' ) && wavira_core_is_active();
}

/**
 * Read a theme setting managed by Wavira Core.
 *
 * Delegates to the plugin's public function so the theme sees exactly the value
 * the plugin acts on: schema defaults, sanitized stored value and the
 * `wavira_setting` filter. Without the plugin the fallback is returned, so the
 * theme still renders a usable site (theme-switch safety, ADR 0002).
 *
 * @param string $key      Setting key inside the settings array.
 * @param mixed  $fallback Value returned when the plugin is unavailable.
 * @return mixed
 */
function wavira_get_setting( $key, $fallback = null ) {
	if ( ! function_exists( 'wavira_core_get_setting' ) ) {
		return $fallback;
	}

	return wavira_core_get_setting( $key, $fallback );
}

/**
 * Render an inline SVG icon from the theme icon directory, escaping nothing but
 * trusting only files that ship with the theme.
 *
 * Icons are files in assets/icons/{name}.svg — never user input. The name is
 * sanitised to a strict slug before the file is read.
 *
 * @param string $name Icon slug (e.g. "play").
 * @return string SVG markup, or an empty string when the icon does not exist.
 */
function wavira_icon( $name ) {
	$slug = sanitize_key( $name );
	$path = WAVIRA_THEME_DIR . 'assets/icons/' . $slug . '.svg';

	if ( '' === $slug || ! file_exists( $path ) ) {
		return '';
	}

	$svg = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local, theme-owned file.

	return (string) $svg;
}

/**
 * Related items for a music post, or an empty array when the core plugin is off.
 *
 * Presentation never stores music data (ARCHITECTURE §1): with the plugin
 * inactive the theme renders nothing instead of guessing.
 *
 * @param int $post_id Source post ID.
 * @param int $limit   Maximum number of items (0 = site setting).
 * @return WP_Post[]
 */
function wavira_related_posts( int $post_id, int $limit = 0 ): array {
	if ( ! function_exists( 'wavira_core_related_posts' ) ) {
		return array();
	}

	return (array) wavira_core_related_posts( $post_id, $limit );
}
