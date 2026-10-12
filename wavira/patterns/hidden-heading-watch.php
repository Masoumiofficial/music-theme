<?php
/**
 * Title: Heading — watch
 * Slug: wavira/hidden-heading-watch
 * Categories: hidden
 * Inserter: no
 *
 * @package Wavira\Theme
 * @since   0.7.0
 */

defined( 'ABSPATH' ) || exit;

$wavira_video_id = (int) get_the_ID();
$wavira_source   = function_exists( 'wavira_core_video_source' ) ? wavira_core_video_source( $wavira_video_id ) : array();

// The block below prints nothing when there is no file and no embed — a demo
// video has neither, because the demo fabricates no media (ADR 0010) — and a
// heading over nothing is an empty box on the page.
if ( '' === (string) ( $wavira_source['url'] ?? '' ) ) {
	return;
}
?>

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading"><?php echo esc_html_x( 'Watch', 'section heading above a music video', 'wavira' ); ?></h2>
<!-- /wp:heading -->
