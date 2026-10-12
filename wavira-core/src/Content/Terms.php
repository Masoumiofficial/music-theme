<?php
/**
 * Term payload helpers shared by the REST controllers and the player.
 *
 * @package Wavira\Core\Content
 */

namespace Wavira\Core\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Class Terms
 */
final class Terms {

	/**
	 * Genre terms of a post as lean payloads.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int, array<string, mixed>> Empty array when the taxonomy or the terms are missing.
	 */
	public static function genres( int $post_id ): array {
		if ( ! taxonomy_exists( Taxonomies::GENRE ) ) {
			return array();
		}

		$terms = get_the_terms( $post_id, Taxonomies::GENRE );

		if ( ! is_array( $terms ) ) {
			return array();
		}

		return array_values(
			array_map(
				static function ( $term ) {
					return array(
						'id'   => (int) $term->term_id,
						'slug' => $term->slug,
						'name' => $term->name,
						'link' => get_term_link( $term ),
					);
				},
				$terms
			)
		);
	}
}
