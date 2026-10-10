<?php
/**
 * Title: No results
 * Slug: wavira/hidden-no-results
 * Categories: hidden
 * Inserter: no
 *
 * Referenced from `wp:query-no-results` in every archive, the search template
 * and the home template. It is a pattern (not template text) so the sentence is
 * translatable — see the note in `hidden-404.php`.
 *
 * @package Wavira\Theme
 * @since   0.7.0
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- wp:paragraph -->
<p><?php echo esc_html_x( 'Nothing published here yet.', 'message shown when a query returns no posts', 'wavira' ); ?></p>
<!-- /wp:paragraph -->
