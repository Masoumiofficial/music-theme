<?php
/**
 * Related music: the cards that keep a single page from being a dead end.
 *
 * @package Wavira\Theme
 * @since   0.15.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wavira_get_related' ) ) {
	/**
	 * Cards for the items related to one post.
	 *
	 * The scoring lives in the plugin (`RelatedService`) and stays there: shared
	 * genre, the same artist, the same album, weighted and cached (ADR 0009).
	 * This function only turns what it returns into the card every other grid in
	 * the theme renders, so a related track still carries its play and download
	 * buttons — a list of titles would be a dead end of a different shape.
	 *
	 * The heading is printed here rather than by the template on purpose. A
	 * small catalogue legitimately has nothing related to a brand new track, and
	 * a heading the visitor can read with nothing under it is a worse defect
	 * than no section at all.
	 *
	 * @param array<string, mixed> $args {
	 *     Optional. Section arguments.
	 *
	 *     @type int    $post_id Source post ID (default: the post being viewed).
	 *     @type int    $limit   Items to show (default 6, maximum 12).
	 *     @type string $title   Section heading. Empty string prints no heading.
	 *     @type string $class   Extra classes for the section.
	 * }
	 * @return string Markup, empty string when nothing is related.
	 */
	function wavira_get_related( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'post_id' => 0,
				'limit'   => 6,
				'title'   => '',
				'class'   => '',
			)
		);

		$post_id = absint( $args['post_id'] );

		if ( $post_id < 1 ) {
			$post_id = (int) get_the_ID();
		}

		if ( $post_id < 1 || ! function_exists( 'wavira_related_posts' ) ) {
			return '';
		}

		$limit = (int) min( 12, max( 1, absint( $args['limit'] ) ) );
		$items = wavira_related_posts( $post_id, $limit );

		if ( array() === $items ) {
			return '';
		}

		$title   = (string) $args['title'];
		$classes = 'wavira-section wavira-related';

		if ( '' !== (string) $args['class'] ) {
			$classes .= ' ' . (string) $args['class'];
		}

		$html = '<section class="' . esc_attr( $classes ) . '">';

		if ( '' !== $title && function_exists( 'wavira_get_section_head' ) ) {
			$html .= wavira_get_section_head( $title );
		}

		$html .= '<ul class="wavira-cards">';

		foreach ( $items as $item ) {
			if ( ! $item instanceof WP_Post ) {
				continue;
			}

			$item_id   = (int) $item->ID;
			$timestamp = (int) get_post_timestamp( $item );
			$credits   = function_exists( 'wavira_core_credit_names' ) ? (array) wavira_core_credit_names( $item_id ) : array();
			$cover     = function_exists( 'wavira_core_cover_image' ) ? (array) wavira_core_cover_image( $item_id ) : array();

			$html .= '<li class="wavira-cards__item">';
			$html .= wavira_get_card(
				array(
					'id'         => $item_id,
					'type'       => (string) $item->post_type,
					'title'      => get_the_title( $item ),
					'permalink'  => get_permalink( $item ),
					'subtitle'   => implode( ' · ', array_filter( array_map( 'strval', $credits ) ) ),
					'date'       => $timestamp,
					'date_label' => $timestamp > 0 && function_exists( 'wavira_core_date_label' ) ? wavira_core_date_label( $timestamp ) : '',
					'image'      => $cover,
					'class'      => 'wavira-card--related',
				)
			);
			$html .= '</li>';
		}

		return $html . '</ul></section>';
	}
}
