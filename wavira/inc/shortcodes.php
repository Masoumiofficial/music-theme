<?php
/**
 * Server-rendered content shortcodes used by the block templates.
 *
 * Block templates are HTML, so anything that must read music data is exposed as
 * a shortcode and rendered by PHP. Both shortcodes here go through the public
 * function API of Wavira Core and degrade to an empty string when the plugin is
 * inactive — a template never fatals because a music feature is missing.
 *
 * The attribute value `current` resolves to the queried object, which is what a
 * template means when it says "this album" or "this genre".
 *
 * The dynamic blocks (wavira/blocks/) render through the same markup helpers, so
 * classic content and block content cannot drift apart.
 *
 * @package Wavira\Theme
 * @since   0.6.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wavira_shortcode_post_id' ) ) {
	/**
	 * Resolve a shortcode ID attribute.
	 *
	 * @param mixed $value Attribute value: a number or the word `current`.
	 * @return int Post ID, 0 when it cannot be resolved.
	 */
	function wavira_shortcode_post_id( $value ) {
		if ( is_string( $value ) && 'current' === strtolower( trim( $value ) ) ) {
			return (int) get_queried_object_id();
		}

		return absint( $value );
	}
}

if ( ! function_exists( 'wavira_shortcode_term_slug' ) ) {
	/**
	 * Resolve a shortcode slug attribute.
	 *
	 * @param mixed $value Attribute value: a slug or the word `current`.
	 * @return string Sanitised slug, empty string when it cannot be resolved.
	 */
	function wavira_shortcode_term_slug( $value ) {
		if ( is_string( $value ) && 'current' === strtolower( trim( $value ) ) ) {
			$term = get_queried_object();

			return $term instanceof WP_Term ? (string) $term->slug : '';
		}

		return sanitize_title( (string) $value );
	}
}

/**
 * Print the ordered tracklist of an album.
 *
 * Works without JavaScript, which is the point: the player's queue panel needs
 * the engine, but an album page must still list its tracks and link to them
 * (progressive enhancement, PERFORMANCE-AUDIT P2).
 *
 * @param array<string, mixed>|string $atts Shortcode attributes (`id`, `duration`, `subtitle`).
 * @return string Markup, empty string when there is nothing to show.
 */
function wavira_tracklist_shortcode( $atts = array() ) {
	$atts     = shortcode_atts(
		array(
			'id'       => 'current',
			'duration' => '1',
			'subtitle' => '1',
		),
		$atts,
		'wavira_tracklist'
	);
	$album_id = wavira_shortcode_post_id( $atts['id'] );

	if ( $album_id < 1 ) {
		return '';
	}

	return wavira_get_tracklist(
		$album_id,
		array(
			'show_duration' => (bool) filter_var( $atts['duration'], FILTER_VALIDATE_BOOLEAN ),
			'show_subtitle' => (bool) filter_var( $atts['subtitle'], FILTER_VALIDATE_BOOLEAN ),
		)
	);
}
add_shortcode( 'wavira_tracklist', 'wavira_tracklist_shortcode' );

/**
 * Print the video of a video post: a hosted file, an oEmbed, or nothing.
 *
 * @param array<string, mixed>|string $atts Shortcode attributes (`id`).
 * @return string Markup, empty string when the post has no video.
 */
function wavira_video_shortcode( $atts = array() ) {
	$atts    = shortcode_atts(
		array(
			'id' => 'current',
		),
		$atts,
		'wavira_video'
	);
	$post_id = wavira_shortcode_post_id( $atts['id'] );

	if ( $post_id < 1 ) {
		return '';
	}

	return wavira_get_video( $post_id );
}
add_shortcode( 'wavira_video', 'wavira_video_shortcode' );
