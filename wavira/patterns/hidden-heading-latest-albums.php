<?php
/**
 * Title: Heading — latest albums
 * Slug: wavira/hidden-heading-latest-albums
 * Categories: hidden
 * Inserter: no
 *
 * @package Wavira\Theme
 * @since   0.7.0
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- wp:group {"className":"wavira-section__head","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
<div class="wp-block-group wavira-section__head">
<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading"><?php echo esc_html_x( 'Latest albums', 'section heading on the home and 404 templates', 'wavira' ); ?></h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"className":"wavira-section__more"} -->
<p class="wavira-section__more"><a href="<?php echo esc_url( get_post_type_archive_link( 'wavira_album' ) ); ?>"><?php echo esc_html__( 'View all', 'wavira' ); ?></a></p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
