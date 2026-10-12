<?php
/**
 * Sorting vocabulary shared by REST controllers and the player queue.
 *
 * The REST API accepts a small, explicit set of `orderby` values and translates
 * them into query arguments here, so a request, the admin list and a player
 * queue cannot end up with three different ideas of "newest".
 *
 * @package Wavira\Core\Content
 */

namespace Wavira\Core\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Class OrderArgs
 */
final class OrderArgs {

	/**
	 * Allowed `orderby` values for collection endpoints.
	 *
	 * @var string[]
	 */
	public const ALLOWED = array( 'date', 'title', 'menu_order', 'modified', 'rand' );

	/**
	 * Convert an `orderby` value into query arguments.
	 *
	 * Unknown values fall back to newest first; `rand` never carries a
	 * direction because WordPress ignores it for random ordering.
	 *
	 * @param string $orderby Requested field.
	 * @param string $order   Requested direction (`asc` for anything else).
	 * @return array<string, string>
	 */
	public static function get( string $orderby, string $order ): array {
		$order = 'asc' === strtolower( $order ) ? 'ASC' : 'DESC';

		switch ( $orderby ) {
			case 'title':
				return array(
					'orderby' => 'title',
					'order'   => $order,
				);
			case 'menu_order':
				return array(
					'orderby' => 'menu_order',
					'order'   => $order,
				);
			case 'modified':
				return array(
					'orderby' => 'modified',
					'order'   => $order,
				);
			case 'rand':
				return array( 'orderby' => 'rand' );
			case 'date':
			default:
				return array(
					'orderby' => 'date',
					'order'   => $order,
				);
		}
	}
}
