<?php
/**
 * Title: Search — form
 * Slug: wavira/hidden-search-form
 * Categories: hidden
 * Inserter: no
 *
 * The search form of the search and 404 templates. It is the same core block the
 * header uses, with the same Persian label, placeholder and button, but it is
 * *not* behind the header switch: a search page whose form can be turned off is
 * a page that cannot do its one job.
 *
 * @package Wavira\Theme
 * @since   0.15.0
 */

defined( 'ABSPATH' ) || exit;

$wavira_search = function_exists( 'wavira_search_block_attrs' )
	? wavira_search_block_attrs( array( 'className' => 'wavira-search__form' ) )
	: array( 'className' => 'wavira-search__form' );
?>

<!-- wp:search <?php
// wp_json_encode(), not esc_attr(): this is a block delimiter's attribute
// JSON, and escaping its quotes into &quot; makes the parser drop every
// attribute — which is how the label, the placeholder and the button of
// this form printed in English for two releases.
echo wp_json_encode( $wavira_search, JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a block comment is not HTML; wp_json_encode() is its encoder.
?> /-->
