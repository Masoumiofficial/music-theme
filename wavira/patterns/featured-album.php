<?php
/**
 * Title: Featured album with player
 * Slug: wavira/featured-album
 * Categories: wavira-music
 * Description: A cover-led album hero with the player directly underneath.
 * Keywords: album, cover, player, hero
 *
 * @package Wavira\Theme
 * @since   0.6.0
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"className":"wavira-section","layout":{"type":"constrained"}} -->
<div class="wp-block-group wavira-section">
<!-- wp:group {"className":"wavira-hero","layout":{"type":"default"}} -->
<div class="wp-block-group wavira-hero">
<!-- wp:post-featured-image {"className":"wavira-hero__media","aspectRatio":"1"} /-->
<!-- wp:group {"className":"wavira-hero__body","layout":{"type":"default"}} -->
<div class="wp-block-group wavira-hero__body">
<!-- wp:post-title {"level":2,"isLink":true} /-->
<!-- wp:group {"className":"wavira-hero__meta","layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group wavira-hero__meta">
<!-- wp:post-terms {"term":"wavira_genre","separator":"","prefix":""} /-->
<!-- wp:post-date /-->
</div>
<!-- /wp:group -->
<!-- wp:shortcode -->
[wavira_player context="album" id="current" sticky="0"]
<!-- /wp:shortcode -->
<!-- wp:buttons -->
<div class="wp-block-buttons">
<!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/albums/">Browse all albums</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->
