<?php
/**
 * Server render — photo gallery block.
 *
 * Photos attached to the post being viewed: an artist's press shots, a release's
 * artwork and studio photos, a video's stills. The block started life as the
 * artist gallery and kept its name (a saved template references it); what changed
 * in 0.15.0 is the question it asks — attachments of *this* post, whatever it is
 * (ADR 0024).
 *
 * @package Wavira\Theme
 * @since   0.9.0
 *
 * @var array    $attributes Block attributes.
 * @var WP_Block $block      Block instance (supplies the loop context).
 */

defined( 'ABSPATH' ) || exit;

$wavira_post_id = isset( $attributes['artistId'] ) ? absint( $attributes['artistId'] ) : 0;
$wavira_current = isset( $block->context['postId'] ) ? absint( $block->context['postId'] ) : (int) get_the_ID();

if ( ! isset( $block->context['postId'] ) && ! is_singular() ) {
	$wavira_current = 0;
}

if ( $wavira_post_id < 1 ) {
	$wavira_post_id = $wavira_current;
}

$wavira_markup = '';

if ( $wavira_post_id > 0 && 'wavira_artist' === get_post_type( $wavira_post_id ) ) {
	// An artist has a payload from the plugin (counts, captions, ordering), so the
	// artist gallery keeps using it.
	$wavira_markup = wavira_get_artist_gallery_only(
		$wavira_post_id,
		array(
			'limit'   => isset( $attributes['limit'] ) ? absint( $attributes['limit'] ) : 12,
			'columns' => isset( $attributes['columns'] ) ? absint( $attributes['columns'] ) : 3,
		)
	);
} elseif ( $wavira_post_id > 0 ) {
	$wavira_markup = wavira_get_post_gallery(
		$wavira_post_id,
		array(
			'limit'   => isset( $attributes['limit'] ) ? absint( $attributes['limit'] ) : 12,
			'columns' => isset( $attributes['columns'] ) ? absint( $attributes['columns'] ) : 3,
		)
	);
}

if ( '' === $wavira_markup ) {
	wavira_block_placeholder( __( 'No photos yet. Upload them from this post’s media library.', 'wavira' ) );

	return;
}

echo $wavira_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- images are built by wp_get_attachment_image(), captions escaped in inc/artists.php.
