<?php
/**
 * Title: Genre chips
 * Slug: wavira/genre-chips
 * Categories: wavira-music
 * Description: A row of genre links for navigation by mood and style.
 * Keywords: genres, taxonomy, chips, navigation
 *
 * @package Wavira\Theme
 * @since   0.6.0
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"className":"wavira-surface","layout":{"type":"constrained"}} -->
<div class="wp-block-group wavira-surface">
<!-- wp:heading {"level":2} -->
<h2><?php echo esc_html_x( 'Browse by genre', 'heading of the genre chips pattern', 'wavira' ); ?></h2>
<!-- /wp:heading -->
<!-- wp:wavira/genre-chips {"limit":20,"orderby":"count","order":"DESC"} /-->
</div>
<!-- /wp:group -->
