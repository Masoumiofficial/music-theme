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

$wavira_source = isset( $attributes['source'] ) ? (string) $attributes['source'] : 'album';
$wavira_ids    = array();

if ( 'latest' === $wavira_source ) {
	$wavira_limit = isset( $attributes['limit'] ) ? absint( $attributes['limit'] ) : 6;
	$wavira_limit = max( 1, min( 12, $wavira_limit > 0 ? $wavira_limit : 6 ) );

	$wavira_query = new WP_Query(
		array(
			'post_type'      => 'wavira_track',
			'post_status'    => 'publish',
			'posts_per_page' => $wavira_limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);

	$wavira_ids = $wavira_query->posts;
}

$wavira_tracklist_args = array(
	'show_duration' => ! isset( $attributes['showDuration'] ) || (bool) $attributes['showDuration'],
	'show_subtitle' => ! isset( $attributes['showSubtitle'] ) || (bool) $attributes['showSubtitle'],
	'show_download' => ! isset( $attributes['showDownload'] ) || (bool) $attributes['showDownload'],
	'show_play'     => ! isset( $attributes['showPlay'] ) || (bool) $attributes['showPlay'],
);

// An empty ID list is meaningful only in `latest` mode: it means the query
// returned no tracks, not "fall back to this album's hand-curated order".
if ( 'latest' === $wavira_source ) {
	$wavira_tracklist_args['ids'] = $wavira_ids;
}

$wavira_markup = wavira_get_tracklist( $wavira_album_id, $wavira_tracklist_args );

if ( '' === $wavira_markup ) {
	// «Nothing here yet» is a different sentence for a section of the home page
	// than for an album that has not been filled in.
	wavira_block_placeholder(
		'latest' === $wavira_source
			? __( 'No published tracks yet.', 'wavira' )
			: __( 'No published tracks in this album yet.', 'wavira' )
	);

	return;
}

echo $wavira_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped field by field inside wavira_get_tracklist().
