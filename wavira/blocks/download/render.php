<?php
/**
 * Server render — download button.
 *
 * Prints a link to `wavira/v1/download/{id}` for whatever file the post has: a
 * track's audio, an album's master file, a hosted video, a cover or a gallery
 * image (ADR 0023). Nothing is printed when there is no file, because a download
 * link that 404s is worse than no link — the one rule the endpoint and the button
 * share.
 *
 * Three shapes, because the sections differ: a `button` for a hero, a `link` for
 * a tracklist row, and a `list` for an album or an artist page, where every
 * quality is offered and the visitor picks.
 *
 * @package Wavira\Theme
 * @since   0.15.0
 *
 * @var array    $attributes Block attributes.
 * @var WP_Block $block      Block instance (supplies the loop context).
 */

defined( 'ABSPATH' ) || exit;

$wavira_post_id = isset( $attributes['postId'] ) ? absint( $attributes['postId'] ) : 0;

if ( $wavira_post_id < 1 && isset( $block->context['postId'] ) ) {
	$wavira_post_id = absint( $block->context['postId'] );
}

if ( $wavira_post_id < 1 ) {
	$wavira_post_id = (int) get_the_ID();
}

$wavira_variant = isset( $attributes['variant'] ) ? (string) $attributes['variant'] : 'button';
$wavira_quality = isset( $attributes['quality'] ) ? absint( $attributes['quality'] ) : 0;
$wavira_label   = isset( $attributes['label'] ) ? trim( (string) $attributes['label'] ) : '';
$wavira_sizes   = ! empty( $attributes['showSize'] );

$wavira_markup = wavira_get_download( $wavira_post_id, $wavira_variant, $wavira_quality, $wavira_label, $wavira_sizes );

if ( '' === $wavira_markup ) {
	// A site with downloads turned off, or a post with no file yet: the editor
	// shows why, the front end shows nothing.
	if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( function_exists( 'wp_is_block_editor' ) && wp_is_block_editor() ) ) {
		wavira_block_placeholder( __( 'Nothing to download yet: add an audio file to this track, an album file, a hosted video, or a cover image.', 'wavira' ) );
	}

	return;
}

echo $wavira_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- URLs escaped in inc/downloads.php, labels escaped there too.
