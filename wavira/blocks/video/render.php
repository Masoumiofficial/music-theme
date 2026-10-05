<?php
/**
 * Server render — music video block.
 *
 * @package Wavira\Theme
 * @since   0.7.0
 *
 * @var array    $attributes Block attributes.
 * @var WP_Block $block      Block instance (supplies the query-loop context).
 */

defined( 'ABSPATH' ) || exit;

$wavira_post_id = isset( $attributes['videoId'] ) ? absint( $attributes['videoId'] ) : 0;

if ( $wavira_post_id < 1 && isset( $block->context['postId'] ) ) {
	$wavira_post_id = absint( $block->context['postId'] );
}

if ( $wavira_post_id < 1 ) {
	$wavira_post_id = (int) get_the_ID();
}

$wavira_markup = wavira_get_video( $wavira_post_id );

if ( '' === $wavira_markup ) {
	wavira_block_placeholder( __( 'This post has no video source yet.', 'wavira' ) );

	return;
}

echo $wavira_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- file URLs escaped above; oEmbed output is filtered by core.
