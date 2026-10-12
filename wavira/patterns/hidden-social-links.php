<?php
/**
 * Title: Footer — social networks
 * Slug: wavira/hidden-social-links
 * Categories: hidden
 * Inserter: no
 *
 * One row of icons, one per network the site owner filled in. Nothing is
 * printed for an empty field, and nothing at all is printed when every field is
 * empty — a footer full of dead links is worse than no social row.
 *
 * The icons are simple geometric glyphs drawn for this theme (assets/icons,
 * stroke-based, `currentColor`), not the networks' official artwork: nothing
 * trademarked is redistributed, and the accessible name comes from the label.
 *
 * @package Wavira\Theme
 * @since   0.12.0
 */

defined( 'ABSPATH' ) || exit;

$wavira_links = wavira_social_links();

if ( array() === $wavira_links ) {
	return;
}

$wavira_new_tab = (bool) wavira_option( 'social_new_tab' );
?>

<!-- wp:group {"className":"wavira-social","layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group wavira-social">
<!-- wp:html -->
<?php
foreach ( $wavira_links as $wavira_link ) {
	$wavira_icon = wavira_icon( $wavira_link['icon'] );

	if ( '' === $wavira_icon ) {
		continue;
	}

	printf(
		'<a class="wavira-social__link" href="%s" aria-label="%s"%s>%s</a>',
		esc_url( $wavira_link['url'] ),
		esc_attr( $wavira_link['label'] ),
		$wavira_new_tab ? ' target="_blank" rel="noopener"' : '',
		$wavira_icon // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme-owned SVG file, see wavira_icon().
	);
}
?>
<!-- /wp:html -->
</div>
<!-- /wp:group -->
