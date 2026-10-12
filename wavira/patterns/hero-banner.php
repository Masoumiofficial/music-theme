<?php
/**
 * Title: Banner
 * Slug: wavira/hero-banner
 * Categories: wavira-music
 * Description: A full-width banner with the site's name, its tagline and one button. The front page's heading.
 * Keywords: banner, hero, cover
 *
 * The banner carries the page's <h1> (the site title), which is why the front
 * page template has no visually hidden heading of its own: one page, one
 * heading. The button is a link, not a script — it points at the section that
 * plays, so a browser with nothing loaded still takes the visitor somewhere.
 *
 * @package Wavira\Theme
 * @since   0.15.0
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"className":"wavira-banner","layout":{"type":"default"}} -->
<div class="wp-block-group wavira-banner">
<!-- wp:group {"className":"wavira-banner__inner","layout":{"type":"default"}} -->
<div class="wp-block-group wavira-banner__inner">
<!-- wp:site-title {"level":1,"className":"wavira-banner__title"} /-->
<!-- wp:site-tagline {"className":"wavira-banner__tagline"} /-->
<!-- wp:buttons {"className":"wavira-banner__actions"} -->
<div class="wp-block-buttons wavira-banner__actions">
<!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#listen-now"><?php echo esc_html_x( 'Listen now', 'button label in the home page banner', 'wavira' ); ?></a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->
