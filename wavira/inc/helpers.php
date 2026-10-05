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
 * @return bool
 */
function wavira_has_core() {
	return class_exists( '\Wavira\Core\Plugin' );
}

/**
 * Read a theme setting managed by Wavira Core.
 *
 * Falls back to the stored option array so the theme still renders a usable
 * site (with empty settings) when the plugin is deactivated. This is the
 * "theme switch safety" contract in reverse: the theme never stores music data.
 *
 * @param string $key     Setting key inside the settings array.
 * @param mixed  $fallback Value returned when the key is missing.
 * @return mixed
 */
function wavira_get_setting( $key, $fallback = null ) {
	$settings = get_option( 'wavira_settings', array() );

	if ( ! is_array( $settings ) || ! array_key_exists( $key, $settings ) ) {
		return $fallback;
	}

	return $settings[ $key ];
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
