<?php
/**
 * Theme hook adjustments (small, documented, reversible).
 *
 * @package Wavira\Theme
 * @since   0.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Add contextual body classes without UA sniffing (see PERFORMANCE-AUDIT P9).
 *
 * @param array $classes Existing classes.
 * @return array
 */
function wavira_body_classes( $classes ) {
	if ( ! is_singular() ) {
		$classes[] = 'wavira-archive-view';
	}

	if ( is_rtl() ) {
		$classes[] = 'wavira-rtl';
	}

	if ( wavira_has_core() ) {
		$classes[] = 'wavira-has-core';
	}

	return $classes;
}
add_filter( 'body_class', 'wavira_body_classes' );

/**
 * Remove the core emoji detection script (dead weight on every request;
 * emoji render natively everywhere this product supports).
 *
 * @return void
 */
function wavira_disable_emoji_assets() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
}
add_action( 'init', 'wavira_disable_emoji_assets' );

/**
 * Keep excerpts short and predictable.
 *
 * @param int $length Current length.
 * @return int
 */
function wavira_excerpt_length( $length ) {
	unset( $length );

	return 28;
}
add_filter( 'excerpt_length', 'wavira_excerpt_length', 20 );
