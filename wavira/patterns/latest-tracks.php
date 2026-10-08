<?php
/**
 * Title: Catalogue player with latest tracks
 * Slug: wavira/latest-tracks
 * Categories: wavira-music
 * Description: One player for the catalogue followed by the newest tracks.
 * Keywords: tracks, player, catalogue, queue
 *
 * @package Wavira\Theme
 * @since   0.6.0
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"className":"wavira-section","layout":{"type":"default"}} -->
<div class="wp-block-group wavira-section">
<!-- wp:heading {"level":2} -->
<h2><?php echo esc_html_x( 'Listen now', 'heading of the catalogue player pattern', 'wavira' ); ?></h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p><?php echo esc_html_x( 'Play straight through the catalogue — shuffle, repeat and the queue are in the player.', 'description in the catalogue player pattern', 'wavira' ); ?></p>
<!-- /wp:paragraph -->
<!-- wp:wavira/player {"context": "tracks", "limit": 20, "sticky": false} /-->
<!-- wp:query {"queryId":3,"query":{"perPage":6,"pages":0,"offset":0,"postType":"wavira_track","order":"desc","orderBy":"date","inherit":false},"layout":{"type":"grid","columnCount":3}} -->
<div class="wp-block-query">
<!-- wp:post-template {"layout":{"type":"grid","columnCount":3}} -->
<!-- wp:post-featured-image {"aspectRatio":"1"} /-->
<!-- wp:post-title {"level":3,"isLink":true} /-->
<!-- /wp:post-template -->
</div>
<!-- /wp:query -->
</div>
<!-- /wp:group -->
