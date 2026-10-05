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
 * These are interim surfaces: phase 0.7.0 replaces them inside the block editor
 * with dynamic blocks. They stay supported for classic content either way.
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
 * @param array<string, mixed>|string $atts Shortcode attributes (`id`).
 * @return string Markup, empty string when there is nothing to show.
 */
function wavira_tracklist_shortcode( $atts = array() ) {
	$atts     = shortcode_atts(
		array(
			'id' => 'current',
		),
		$atts,
		'wavira_tracklist'
	);
	$album_id = wavira_shortcode_post_id( $atts['id'] );

	if ( $album_id < 1 || ! function_exists( 'wavira_core_album_tracklist' ) ) {
		return '';
	}

	$rows = wavira_core_album_tracklist( $album_id );

	if ( array() === $rows ) {
		return '';
	}

	$html = '<ol class="wavira-tracklist">';

	foreach ( $rows as $index => $row ) {
		$html .= '<li class="wavira-tracklist__item">';
		$html .= '<span class="wavira-tracklist__index" aria-hidden="true">' . esc_html( (string) ( $index + 1 ) ) . '</span>';
		$html .= '<span class="wavira-tracklist__title"><a href="' . esc_url( (string) $row['permalink'] ) . '">' . esc_html( (string) $row['title'] ) . '</a></span>';

		if ( '' !== (string) $row['duration_label'] ) {
			$html .= '<span class="wavira-tracklist__duration">' . esc_html( (string) $row['duration_label'] ) . '</span>';
		}

		$html .= '</li>';
	}

	return $html . '</ol>';
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

	if ( $post_id < 1 || ! function_exists( 'wavira_core_video_source' ) ) {
		return '';
	}

	$source = wavira_core_video_source( $post_id );
	$url    = isset( $source['url'] ) ? (string) $source['url'] : '';

	if ( '' === $url ) {
		return '';
	}

	$poster = isset( $source['poster'] ) ? (string) $source['poster'] : '';

	if ( 'file' === (string) $source['kind'] ) {
		return sprintf(
			'<div class="wavira-video-frame"><video controls preload="metadata" playsinline%s src="%s"></video></div>',
			'' !== $poster ? ' poster="' . esc_url( $poster ) . '"' : '',
			esc_url( $url )
		);
	}

	$embed = function_exists( 'wp_oembed_get' ) ? wp_oembed_get( $url ) : false;

	if ( is_string( $embed ) && '' !== $embed ) {
		return '<div class="wavira-video-frame">' . $embed . '</div>';
	}

	// A provider that cannot be embedded still deserves a usable link.
	return sprintf(
		'<p class="wavira-video-link"><a href="%s">%s</a></p>',
		esc_url( $url ),
		esc_html__( 'Watch the video', 'wavira' )
	);
}
add_shortcode( 'wavira_video', 'wavira_video_shortcode' );
