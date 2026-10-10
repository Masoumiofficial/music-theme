<?php
/**
 * Title: Heading — music news
 * Slug: wavira/hidden-heading-music-news
 * Categories: hidden
 * Inserter: no
 *
 * Referenced by the blog templates (`home.html`, `archive.html`) and available to
 * any pattern that lists news, so the heading is translated like every other
 * string of the product — a block template cannot run PHP (see hidden-404.php).
 *
 * @package Wavira\Theme
 * @since   0.9.0
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- wp:group {"className":"wavira-section__head","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
<div class="wp-block-group wavira-section__head">
<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading"><?php echo esc_html_x( 'Music news', 'heading above the news feed', 'wavira' ); ?></h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"className":"wavira-section__more"} -->
<p class="wavira-section__more"><a href="<?php echo esc_url( get_permalink( (int) get_option( 'page_for_posts' ) ) ?: home_url( '/' ) ); ?>"><?php echo esc_html__( 'View all', 'wavira' ); ?></a></p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
