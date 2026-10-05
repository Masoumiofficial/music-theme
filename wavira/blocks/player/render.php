<?php
/**
 * Server render — music player block.
 *
 * `wavira_player_mount()` prints the mount point (and the no-JavaScript
 * fallback); the engine takes it over in the browser. Nothing is printed when
 * the core plugin is inactive, so a theme switch never leaves a broken page.
 *
 * Resolution rules (they mirror what a template author means):
 *
 * - a query-loop context or a singular view supplies "this post";
 * - an album/artist view mounts that post, a track view mounts that track;
 * - a genre archive mounts the queried term's slug, because the queue for a
 *   genre is addressed by slug (Queue::ids());
 * - nothing is invented on an archive, a search or a 404.
 *
 * @package Wavira\Theme
 * @since   0.7.0
 *
 * @var array    $attributes Block attributes.
 * @var WP_Block $block      Block instance (supplies the query-loop context).
 */

defined( 'ABSPATH' ) || exit;

$wavira_context = isset( $attributes['context'] ) ? (string) $attributes['context'] : 'tracks';
$wavira_source  = isset( $attributes['sourceId'] ) ? absint( $attributes['sourceId'] ) : 0;
$wavira_track   = isset( $attributes['trackId'] ) ? absint( $attributes['trackId'] ) : 0;
$wavira_slug    = isset( $attributes['slug'] ) ? (string) $attributes['slug'] : '';

$wavira_current = isset( $block->context['postId'] ) ? absint( $block->context['postId'] ) : (int) get_the_ID();

// A loop supplies an explicit post; a singular view supplies the queried one.
// Everywhere else (archive, search, 404) there is no "this post" to mount.
if ( ! isset( $block->context['postId'] ) && ! is_singular() ) {
	$wavira_current = 0;
}

if ( $wavira_source < 1 && $wavira_current > 0 && 'wavira_track' !== get_post_type( $wavira_current ) ) {
	$wavira_source = $wavira_current;
}

if ( $wavira_track < 1 && $wavira_current > 0 && 'wavira_track' === get_post_type( $wavira_current ) ) {
	$wavira_track = $wavira_current;
}

if ( '' === $wavira_slug && 'genre' === $wavira_context ) {
	$wavira_term = get_queried_object();

	if ( $wavira_term instanceof WP_Term ) {
		$wavira_slug = (string) $wavira_term->slug;
	}
}

wavira_player_mount(
	array(
		'context'  => $wavira_context,
		'id'       => $wavira_source,
		'slug'     => $wavira_slug,
		'track'    => $wavira_track,
		'limit'    => isset( $attributes['limit'] ) ? absint( $attributes['limit'] ) : 0,
		'sticky'   => isset( $attributes['sticky'] ) && (bool) $attributes['sticky'],
		'autoplay' => isset( $attributes['autoplay'] ) && (bool) $attributes['autoplay'],
		'fallback' => true,
	)
);
