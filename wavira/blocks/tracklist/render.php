<?php
/**
 * Server render — album tracklist block.
 *
 * The block resolves *which* album, then hands over to the shared markup helper:
 * a block, a shortcode and a template must never grow their own copy of the
 * tracklist (REBUILD-PLAN 0.7.0).
 *
 * @package Wavira\Theme
 * @since   0.7.0
 *
 * @var array    $attributes Block attributes.
 * @var WP_Block $block      Block instance (supplies the query-loop context).
 */

defined( 'ABSPATH' ) || exit;

$wavira_album_id = isset( $attributes['albumId'] ) ? absint( $attributes['albumId'] ) : 0;

if ( $wavira_album_id < 1 && isset( $block->context['postId'] ) ) {
	$wavira_album_id = absint( $block->context['postId'] );
}

if ( $wavira_album_id < 1 ) {
	$wavira_album_id = (int) get_the_ID();
}

$wavira_markup = wavira_get_tracklist(
	$wavira_album_id,
	array(
		'show_duration' => ! isset( $attributes['showDuration'] ) || (bool) $attributes['showDuration'],
		'show_subtitle' => ! isset( $attributes['showSubtitle'] ) || (bool) $attributes['showSubtitle'],
		'show_download' => ! isset( $attributes['showDownload'] ) || (bool) $attributes['showDownload'],
	)
);

if ( '' === $wavira_markup ) {
	wavira_block_placeholder( __( 'No published tracks in this album yet.', 'wavira' ) );

	return;
}

echo $wavira_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped field by field inside wavira_get_tracklist().
