<?php
/**
 * Server render — related music block.
 *
 * The cards that answer “what do I play next?” on a single page. The plugin
 * scores them (shared genre, the same artist, the same album — ADR 0009); this
 * file decides *which* post they belong to: the one chosen in a Query Loop
 * first, then the post being viewed. On an archive, a search or a 404 there is
 * no “this” to relate anything to, so it prints the editor hint instead of
 * inventing a source.
 *
 * The heading is an attribute rather than a block in the template, unlike the
 * gallery block (ADR 0024): a related list is legitimately empty — one track in
 * one genre has nothing beside it — and a heading the visitor can read with
 * nothing under it is a defect. A template therefore passes its own Persian
 * heading here, and an empty result takes the heading with it.
 *
 * @package Wavira\Theme
 * @since   0.15.0
 *
 * @var array    $attributes Block attributes.
 * @var WP_Block $block      Block instance (supplies the loop context).
 */

defined( 'ABSPATH' ) || exit;

$wavira_post_id = isset( $block->context['postId'] ) ? absint( $block->context['postId'] ) : (int) get_the_ID();

if ( ! isset( $block->context['postId'] ) && ! is_singular() ) {
	$wavira_post_id = 0;
}

$wavira_markup = '';

if ( $wavira_post_id > 0 && function_exists( 'wavira_get_related' ) ) {
	$wavira_markup = wavira_get_related(
		array(
			'post_id' => $wavira_post_id,
			'limit'   => isset( $attributes['limit'] ) ? absint( $attributes['limit'] ) : 6,
			'title'   => isset( $attributes['title'] ) ? (string) $attributes['title'] : '',
		)
	);
}

if ( '' === $wavira_markup ) {
	wavira_block_placeholder( _x( 'Nothing published here yet.', 'message shown when a query returns no posts', 'wavira' ) );

	return;
}

echo $wavira_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped field by field inside wavira_get_related() and wavira_get_card().
