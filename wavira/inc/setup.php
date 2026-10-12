<?php
/**
 * Theme supports, menus, image sizes and text domain.
 *
 * @package Wavira\Theme
 * @since   0.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register theme features after the theme is loaded.
 *
 * @return void
 */
function wavira_setup() {
	load_theme_textdomain( 'wavira', WAVIRA_THEME_DIR . 'languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/css/editor.css' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 40,
			'width'       => 150,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	// Music artwork sizes: small card, standard card, large cover and full-width backdrop.
	add_image_size( 'wavira-cover-xs', 150, 150, true );
	add_image_size( 'wavira-cover-sm', 300, 300, true );
	add_image_size( 'wavira-cover', 640, 640, true );
	add_image_size( 'wavira-cover-lg', 1200, 1200, true );
	add_image_size( 'wavira-hero', 1920, 1080, true );

	register_nav_menus(
		array(
			'primary' => __( 'Primary navigation', 'wavira' ),
			'footer'  => __( 'Footer navigation', 'wavira' ),
			'legal'   => __( 'Legal links (license, privacy)', 'wavira' ),
		)
	);
}
add_action( 'after_setup_theme', 'wavira_setup' );

/**
 * Register the pattern category the theme's patterns live in.
 *
 * @return void
 */
function wavira_register_pattern_category() {
	if ( ! function_exists( 'register_block_pattern_category' ) ) {
		return;
	}

	register_block_pattern_category(
		'wavira-music',
		array( 'label' => __( 'Wavira music', 'wavira' ) )
	);
}
add_action( 'init', 'wavira_register_pattern_category' );

/**
 * Set the content width used by embeds and oEmbeds.
 *
 * @return void
 */
function wavira_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'wavira_content_width', 720 );
}
add_action( 'after_setup_theme', 'wavira_content_width', 0 );
