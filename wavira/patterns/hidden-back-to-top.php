<?php
/**
 * Title: Footer — back to top
 * Slug: wavira/hidden-back-to-top
 * Categories: hidden
 * Inserter: no
 *
 * A button that starts hidden and is revealed by the theme script once the page
 * is scrolled. Without JavaScript it stays hidden — a control that does nothing
 * would be a worse answer than no control — and with `prefers-reduced-motion`
 * the script scrolls without animation instead of refusing to work.
 *
 * @package Wavira\Theme
 * @since   0.12.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! wavira_option( 'back_to_top' ) ) {
	return;
}

$wavira_icon = wavira_icon( 'arrow-up' );
?>

<!-- wp:html -->
<button class="wavira-to-top" type="button" hidden data-wavira-to-top aria-label="<?php echo esc_attr__( 'Back to top', 'wavira' ); ?>"><?php echo $wavira_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme-owned SVG file, see wavira_icon(). ?></button>
<!-- /wp:html -->
