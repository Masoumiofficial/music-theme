<?php
/**
 * Shared REST behaviour: namespace, collection arguments and pagination.
 *
 * The `wavira/v1` namespace is the product API for the front end and headless
 * consumers. WordPress' own `/wp/v2/{rest_base}` routes remain available for
 * full CRUD; these routes are lean, cached-friendly, read-only projections of
 * the music model (ADR 0003).
 *
 * @package Wavira\Core\Rest
 */

namespace Wavira\Core\Rest;

use Wavira\Core\Content\OrderArgs;

use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Class AbstractController
 */
abstract class AbstractController {

	/**
	 * REST namespace for the product API.
	 *
	 * @var string
	 */
	public const API_NAMESPACE = 'wavira/v1';

	/**
	 * Highest allowed `per_page` value. Unbounded collections are forbidden.
	 *
	 * @var int
	 */
	public const MAX_PER_PAGE = 50;

	/**
	 * Register this controller's routes.
	 *
	 * @return void
	 */
	abstract public function register_routes(): void;

	/**
	 * Whether the current request may read public music data.
	 *
	 * Read-only routes over published content are intentionally public; write
	 * operations do not exist in this namespace and any future one must declare
	 * its own capability check (CODING-STANDARD S4).
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return bool
	 */
	public function read_permission( $request ): bool {
		unset( $request );

		return true;
	}

	/**
	 * Sanitize one slug argument.
	 *
	 * WordPress calls a REST argument sanitizer with `( $value, $request, $key )`,
	 * and `sanitize_title()` returns its **second** argument when the value is
	 * empty — so handing it to WordPress directly made every unfiltered
	 * collection request store the request object as the value, and casting it
	 * later raised "Object of class WP_REST_Request could not be converted to
	 * string" (caught by the integration suite). This one-argument wrapper keeps
	 * those extra arguments out while still using core's slug rules.
	 *
	 * @param mixed $value Raw argument value.
	 * @return string
	 */
	public function sanitize_slug( $value ): string {
		return (string) sanitize_title( (string) $value );
	}

	/**
	 * Collection arguments shared by every music endpoint.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function collection_args(): array {
		return array(
			'page'     => array(
				'description'       => __( 'Current page of the collection.', 'wavira-core' ),
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			),
			'per_page' => array(
				'description'       => __( 'Maximum number of items returned.', 'wavira-core' ),
				'type'              => 'integer',
				'default'           => 20,
				'minimum'           => 1,
				'maximum'           => self::MAX_PER_PAGE,
				'sanitize_callback' => 'absint',
			),
			'search'   => array(
				'description'       => __( 'Limit results to items matching a search term.', 'wavira-core' ),
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'orderby'  => array(
				'description' => __( 'Sort collection by field.', 'wavira-core' ),
				'type'        => 'string',
				'default'     => 'date',
				'enum'        => array( 'date', 'title', 'menu_order', 'modified', 'rand' ),
			),
			'order'    => array(
				'description' => __( 'Order direction.', 'wavira-core' ),
				'type'        => 'string',
				'default'     => 'desc',
				'enum'        => array( 'asc', 'desc' ),
			),
			'genre'    => array(
				'description'       => __( 'Limit results to a genre slug.', 'wavira-core' ),
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => array( $this, 'sanitize_slug' ),
			),
			'artist'   => array(
				'description'       => __( 'Limit results to an artist post ID.', 'wavira-core' ),
				'type'              => 'integer',
				'default'           => 0,
				'sanitize_callback' => 'absint',
			),
			'album'    => array(
				'description'       => __( 'Limit results to an album post ID.', 'wavira-core' ),
				'type'              => 'integer',
				'default'           => 0,
				'sanitize_callback' => 'absint',
			),
			'featured' => array(
				'description' => __( 'Limit results to featured items.', 'wavira-core' ),
				'type'        => 'boolean',
				'default'     => false,
			),
		);
	}

	/**
	 * Convert an `orderby` value into query arguments.
	 *
	 * Kept as a thin delegate so third-party controllers extending this class
	 * keep working; the vocabulary itself lives in Content\OrderArgs.
	 *
	 * @param string $orderby Requested field.
	 * @param string $order   Requested direction.
	 * @return array<string, string>
	 */
	protected function order_args( string $orderby, string $order ): array {
		return OrderArgs::get( $orderby, $order );
	}

	/**
	 * Build a REST response carrying the standard pagination headers.
	 *
	 * @param array $items Prepared items.
	 * @param int   $total Total items matching the query.
	 * @param int   $pages Total pages.
	 * @return WP_REST_Response
	 */
	protected function paginated_response( array $items, int $total, int $pages ): WP_REST_Response {
		$response = rest_ensure_response( $items );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $pages );

		return $response;
	}

	/**
	 * Standard "not found" error.
	 *
	 * @param string $what Entity label for the message.
	 * @return \WP_Error
	 */
	protected function not_found( string $what ) {
		return new \WP_Error(
			'wavira_not_found',
			sprintf(
				/* translators: %s: entity name, e.g. "track". */
				__( 'No %s found for the requested ID.', 'wavira-core' ),
				$what
			),
			array( 'status' => 404 )
		);
	}
}
