<?php
/**
 * Server render — artist profile block.
 *
 * Resolution rules, in order: an artist chosen in the sidebar, the post the block
 * sits in (a Query Loop supplies it through `usesContext`), or the artist being
 * viewed on a singular template. Nothing is invented on an archive, a search or a
 * 404 — the block prints the editor hint there instead of a random artist.
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
	$wavira_markup = wavira_get_artist(
		$wavira_artist_id,
		array(
			'sections'      => isset( $attributes['sections'] ) ? (array) $attributes['sections'] : array( 'albums', 'tracks', 'videos' ),
			'limit'         => isset( $attributes['limit'] ) ? absint( $attributes['limit'] ) : 6,
			'gallery_limit' => isset( $attributes['galleryLimit'] ) ? absint( $attributes['galleryLimit'] ) : 8,
			'columns'       => isset( $attributes['columns'] ) ? absint( $attributes['columns'] ) : 3,
			'show_quote'    => isset( $attributes['showQuote'] ) && (bool) $attributes['showQuote'],
			'show_bio'      => isset( $attributes['showBio'] ) && (bool) $attributes['showBio'],
			'show_socials'  => isset( $attributes['showSocials'] ) && (bool) $attributes['showSocials'],
			'show_counts'   => isset( $attributes['showCounts'] ) && (bool) $attributes['showCounts'],
			'show_works'    => isset( $attributes['showWorks'] ) && (bool) $attributes['showWorks'],
			'show_gallery'  => isset( $attributes['showGallery'] ) && (bool) $attributes['showGallery'],
		)
	);
}

if ( '' === $wavira_markup ) {
	wavira_block_placeholder( __( 'Choose an artist for this block, or open it on an artist page.', 'wavira' ) );

	return;
}

echo $wavira_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped field by field in inc/markup.php and inc/artists.php; the biography is passed through wp_kses_post().
