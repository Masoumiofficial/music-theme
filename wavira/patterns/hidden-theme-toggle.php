<?php
/**
 * Title: Colour mode toggle
 * Slug: wavira/hidden-theme-toggle
 * Categories: hidden
 * Inserter: no
 *
 * The button is a pattern (not template text) so its fallback label is
 * translatable; the theme script replaces that label with the active mode on
 * load (`data-wavira-theme-label`, `window.Wavira.theme`).
 *
 * @package Wavira\Theme
 * @since   0.7.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! wavira_option( 'show_theme_toggle' ) ) {
	return;
}
?>

<!-- wp:html -->
<button class="wavira-theme-toggle" type="button" data-wavira-theme-toggle aria-pressed="false">
<span data-wavira-theme-label><?php echo esc_html_x( 'Auto', 'colour-mode label shown until the theme script runs', 'wavira' ); ?></span>
</button>
<!-- /wp:html -->
