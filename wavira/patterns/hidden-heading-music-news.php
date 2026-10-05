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

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading"><?php echo esc_html_x( 'Music news', 'heading above the news feed', 'wavira' ); ?></h2>
<!-- /wp:heading -->
