<?php
/**
 * Server render — genre chips block.
 *
 * @package Wavira\Theme
 * @since   0.7.0
 *
 * @var array $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

$wavira_markup = wavira_get_genre_chips(
	array(
		'limit'      => isset( $attributes['limit'] ) ? absint( $attributes['limit'] ) : 12,
		'orderby'    => isset( $attributes['orderby'] ) ? (string) $attributes['orderby'] : 'count',
		'order'      => isset( $attributes['order'] ) ? (string) $attributes['order'] : 'DESC',
		'show_count' => isset( $attributes['showCount'] ) && (bool) $attributes['showCount'],
	)
);

if ( '' === $wavira_markup ) {
	wavira_block_placeholder( __( 'No genres with published music yet.', 'wavira' ) );

	return;
}

echo $wavira_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- names and links escaped field by field inside wavira_get_genre_chips().
