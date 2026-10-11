<?php
/**
 * Title: Header — search
 * Slug: wavira/hidden-header-search
 * Categories: hidden
 * Inserter: no
 *
 * The WordPress search block, placed in the header by the header template part
 * when the site owner keeps the switch on. It is the core block and not a
 * hand-written form: the same REST-free behaviour, the same styling hooks, and
 * the same block a customer can move in the Site Editor.
 *
 * @package Wavira\Theme
 * @since   0.12.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! wavira_option( 'header_search' ) ) {
	return;
}

$wavira_search = function_exists( 'wavira_search_block_attrs' )
	? wavira_search_block_attrs( array( 'className' => 'wavira-header__search' ) )
	: array( 'className' => 'wavira-header__search' );
?>

<!-- wp:search <?php
// wp_json_encode(), not esc_attr(): this is a block delimiter's attribute
// JSON, and escaping its quotes into &quot; makes the parser drop every
// attribute — which is how the label, the placeholder and the button of
// this form printed in English for two releases.
echo wp_json_encode( $wavira_search, JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a block comment is not HTML; wp_json_encode() is its encoder.
?> /-->
