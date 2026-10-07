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

$wavira_search = array(
	'label'         => __( 'Search', 'wavira' ),
	'placeholder'   => __( 'Search music…', 'wavira' ),
	'buttonText'    => __( 'Search', 'wavira' ),
	'buttonUseIcon' => true,
	'className'     => 'wavira-header__search',
);
?>

<!-- wp:search <?php echo esc_attr( (string) wp_json_encode( $wavira_search ) ); ?> /-->
