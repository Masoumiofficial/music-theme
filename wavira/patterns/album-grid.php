<?php
/**
 * Title: Album grid
 * Slug: wavira/album-grid
 * Categories: wavira-music
 * Description: The latest albums in a responsive grid, with pagination.
 * Keywords: albums, grid, releases
 *
 * @package Wavira\Theme
 * @since   0.6.0
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"className":"wavira-section","layout":{"type":"default"}} -->
<div class="wp-block-group wavira-section">
<!-- wp:heading {"level":2} -->
<h2><?php echo esc_html_x( 'Latest albums', 'heading of the album grid pattern', 'wavira' ); ?></h2>
<!-- /wp:heading -->
<!-- wp:query {"queryId":2,"query":{"perPage":6,"pages":0,"offset":0,"postType":"wavira_album","order":"desc","orderBy":"date","inherit":false},"layout":{"type":"grid","columnCount":3}} -->
<div class="wp-block-query">
<!-- wp:post-template {"layout":{"type":"grid","columnCount":3}} -->
<!-- wp:post-featured-image {"aspectRatio":"1"} /-->
<!-- wp:post-title {"level":3,"isLink":true} /-->
<!-- wp:post-terms {"term":"wavira_genre","separator":"","prefix":""} /-->
<!-- /wp:post-template -->
<!-- wp:query-no-results -->
<!-- wp:paragraph -->
<p><?php echo esc_html_x( 'Nothing published here yet.', 'message shown when a query returns no posts', 'wavira' ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:query-no-results -->
<!-- wp:query-pagination {"layout":{"type":"flex","justifyContent":"space-between"}} -->
<!-- wp:query-pagination-previous /-->
<!-- wp:query-pagination-numbers /-->
<!-- wp:query-pagination-next /-->
<!-- /wp:query-pagination -->
</div>
<!-- /wp:query -->
</div>
<!-- /wp:group -->
