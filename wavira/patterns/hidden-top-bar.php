<?php
/**
 * Title: Header — announcement bar
 * Slug: wavira/hidden-top-bar
 * Categories: hidden
 * Inserter: no
 *
 * Printed by the header template part, above the header itself. The bar exists
 * only when the site owner switched it on *and* wrote something to say: an empty
 * coloured strip would be a defect, not a feature. Links are rendered here, in
 * PHP, because a block template cannot decide whether a URL was filled in.
 *
 * @package Wavira\Theme
 * @since   0.12.0
 */

defined( 'ABSPATH' ) || exit;

$wavira_text = (string) wavira_option( 'top_bar_text' );
$wavira_url  = (string) wavira_option( 'top_bar_url' );

if ( ! wavira_option( 'top_bar' ) || ( '' === $wavira_text && '' === $wavira_url ) ) {
	return;
}

$wavira_label = '' !== $wavira_text ? esc_html( $wavira_text ) : esc_html( $wavira_url );
$wavira_line  = '' !== $wavira_url
	? '<a href="' . esc_url( $wavira_url ) . '">' . $wavira_label . '</a>'
	: $wavira_label;
?>

<!-- wp:group {"className":"wavira-topbar","layout":{"type":"default"}} -->
<div class="wp-block-group wavira-topbar">
<!-- wp:group {"className":"wavira-layout wavira-topbar__inner","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"center"}} -->
<div class="wp-block-group wavira-layout wavira-topbar__inner">
<!-- wp:paragraph -->
<p><?php echo wp_kses_post( $wavira_line ); ?></p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->
