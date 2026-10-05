<?php
/**
 * Server render — artist photo gallery block.
 *
 * Same resolution rules as the profile block: chosen artist, loop context, or the
 * artist being viewed.
 *
 * @package Wavira\Theme
 * @since   0.9.0
 *
 * @var array    $attributes Block attributes.
 * @var WP_Block $block      Block instance (supplies the loop context).
 */

defined( 'ABSPATH' ) || exit;

$wavira_artist_id = isset( $attributes['artistId'] ) ? absint( $attributes['artistId'] ) : 0;
$wavira_current   = isset( $block->context['postId'] ) ? absint( $block->context['postId'] ) : (int) get_the_ID();

if ( ! isset( $block->context['postId'] ) && ! is_singular() ) {
	$wavira_current = 0;
}

if ( $wavira_artist_id < 1 && $wavira_current > 0 && 'wavira_artist' === get_post_type( $wavira_current ) ) {
	$wavira_artist_id = $wavira_current;
}

$wavira_markup = '';

if ( $wavira_artist_id > 0 ) {
	$wavira_markup = wavira_get_artist_gallery_only(
		$wavira_artist_id,
		array(
			'limit'   => isset( $attributes['limit'] ) ? absint( $attributes['limit'] ) : 12,
			'columns' => isset( $attributes['columns'] ) ? absint( $attributes['columns'] ) : 3,
		)
	);
}

if ( '' === $wavira_markup ) {
	wavira_block_placeholder( __( 'This artist has no photos yet. Upload them from the artist’s media library.', 'wavira' ) );

	return;
}

echo $wavira_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- images are built by wp_get_attachment_image(), captions escaped in inc/artists.php.
