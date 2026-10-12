<?php
/**
 * Hosting credits: which artists a work is credited to.
 *
 * A track, an album and a video all answer "who is this by?" the same way: the
 * primary artist plus the featured artists, in that order, deduplicated. Three
 * consumers need that answer — the structured-data graph, the document title and
 * any template that prints a credit line — and it must not be re-derived three
 * times with three chances to disagree (ARCHITECTURE §1).
 *
 * @package Wavira\Core\Content
 */

namespace Wavira\Core\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Class Credit
 */
final class Credit {

	/**
	 * Artist IDs credited on a post: primary first, then featured artists.
	 *
	 * Only published artists are returned, because a credit to a draft must not
	 * surface on a live page (and a search engine must not learn its name).
	 *
	 * @param int $post_id Post ID.
	 * @return int[] Unique, ordered, published artist IDs.
	 */
	public static function ids( int $post_id ): array {
		$ids = array();

		$primary = MetaValues::int( $post_id, MetaSchema::ARTIST );

		if ( $primary > 0 ) {
			$ids[] = $primary;
		}

		foreach ( MetaValues::ids( $post_id, MetaSchema::FEATURED_ARTISTS, PostTypes::ARTIST ) as $id ) {
			$ids[] = $id;
		}

		$published = array();

		foreach ( array_unique( $ids ) as $id ) {
			$post = get_post( $id );

			if ( $post instanceof \WP_Post && PostTypes::ARTIST === $post->post_type && 'publish' === $post->post_status ) {
				$published[] = (int) $id;
			}
		}

		return $published;
	}

	/**
	 * Display names of the credited artists, in credit order.
	 *
	 * @param int $post_id Post ID.
	 * @return string[] Artist names, empty when the work has no published credit.
	 */
	public static function names( int $post_id ): array {
		$names = array();

		foreach ( self::ids( $post_id ) as $id ) {
			$names[] = (string) get_the_title( $id );
		}

		return $names;
	}
}
