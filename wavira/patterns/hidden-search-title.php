<?php
/**
 * Title: Search — results title
 * Slug: wavira/hidden-search-title
 * Categories: hidden
 * Inserter: no
 *
 * The `<h1>` of the search template. Core's `query-title` block would print the
 * same sentence from *core's* catalogue, which is English on any site whose
 * language files have not been fetched — a fresh install, a Playground, a site
 * that has not run its translation update. The theme ships Persian in the
 * repository, so the sentence is built here from the theme's own catalogue and
 * is Persian on every site (ADR 0015).
 *
 * The query is escaped after it is formatted, not before: `sprintf()` cannot be
 * trusted with a value that has already been turned into entities, and the
 * search term is the one thing on this page a visitor controls.
 *
 * @package Wavira\Theme
 * @since   0.15.0
 */

defined( 'ABSPATH' ) || exit;

$wavira_term = get_search_query();

$wavira_title = '' === $wavira_term
	? __( 'Search results', 'wavira' )
	: sprintf(
		/* translators: %s: the search term the visitor typed. */
		__( 'Search results for: “%s”', 'wavira' ),
		$wavira_term
	);
?>

<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading"><?php echo esc_html( $wavira_title ); ?></h1>
<!-- /wp:heading -->
