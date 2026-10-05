<?php
/**
 * Title: 404 — page not found
 * Slug: wavira/hidden-404
 * Categories: hidden
 * Inserter: no
 *
 * Block templates are static HTML, so any text inside a `.html` template is
 * untranslatable. Every visible string therefore lives in a PHP pattern and the
 * template references it (`wp:pattern`), exactly as the core themes do.
 *
 * @package Wavira\Theme
 * @since   0.7.0
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading"><?php echo esc_html_x( 'Page not found', 'heading of the 404 template', 'wavira' ); ?></h1>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p><?php echo esc_html_x( 'The page you were looking for does not exist. Try a search, or browse the latest releases.', 'message of the 404 template', 'wavira' ); ?></p>
<!-- /wp:paragraph -->
