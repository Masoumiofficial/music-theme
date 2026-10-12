<?php
/**
 * Title: Search — no results
 * Slug: wavira/hidden-search-no-results
 * Categories: hidden
 * Inserter: no
 *
 * What `wp:query-no-results` prints on a search with no hits. The shared
 * no-results pattern says “nothing published here yet”, which is the truth of an
 * *archive* and a lie on a search: the site is full, the visitor's word simply
 * matched nothing, and a sentence that blames the catalogue for the visitor's
 * spelling is the wrong answer to a question. So this one names the term and
 * suggests the next step.
 *
 * @package Wavira\Theme
 * @since   0.15.0
 */

defined( 'ABSPATH' ) || exit;

$wavira_term = get_search_query();

$wavira_message = '' === $wavira_term
	? __( 'Nothing matched your search. Try another word, or browse the latest releases.', 'wavira' )
	: sprintf(
		/* translators: %s: the search term that matched nothing. */
		__( 'Nothing matched “%s”. Try another word, or browse the latest releases.', 'wavira' ),
		$wavira_term
	);
?>

<!-- wp:paragraph -->
<p><?php echo esc_html( $wavira_message ); ?></p>
<!-- /wp:paragraph -->
