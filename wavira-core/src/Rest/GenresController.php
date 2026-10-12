<?php
/**
 * Read-only REST projection of the genre taxonomy.
 *
 * @package Wavira\Core\Rest
 */

namespace Wavira\Core\Rest;

use Wavira\Core\Content\Taxonomies;
use WP_REST_Request;
use WP_Term;

defined( 'ABSPATH' ) || exit;

/**
 * Class GenresController
 */
final class GenresController extends AbstractController {

	/**
	 * Register collection and single-term routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::API_NAMESPACE,
			'/genres',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'read_permission' ),
					'args'                => array(
						'per_page'   => array(
							'type'              => 'integer',
							'default'           => 50,
							'minimum'           => 1,
							'maximum'           => 200,
							'sanitize_callback' => 'absint',
						),
						'page'       => array(
							'type'              => 'integer',
							'default'           => 1,
							'minimum'           => 1,
							'sanitize_callback' => 'absint',
						),
						'hide_empty' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
				),
			)
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/genres/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'read_permission' ),
					'args'                => array(
						'id' => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);
	}

	/**
	 * Genre collection.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return \WP_REST_Response
	 */
	public function get_items( $request ) {
		$per_page = min( 200, max( 1, (int) $request['per_page'] ) );
		$page     = max( 1, (int) $request['page'] );
		$offset   = ( $page - 1 ) * $per_page;

		$terms = get_terms(
			array(
				'taxonomy'   => Taxonomies::GENRE,
				'hide_empty' => (bool) $request['hide_empty'],
				'number'     => $per_page,
				'offset'     => $offset,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) ) {
			return rest_ensure_response( array() );
		}

		$total_terms = wp_count_terms(
			array(
				'taxonomy'   => Taxonomies::GENRE,
				'hide_empty' => (bool) $request['hide_empty'],
			)
		);

		$total = is_wp_error( $total_terms ) ? count( $terms ) : (int) $total_terms;
		$pages = (int) ceil( $total / $per_page );

		return $this->paginated_response(
			array_map( array( $this, 'prepare_term' ), $terms ),
			$total,
			max( 1, $pages )
		);
	}

	/**
	 * Single genre.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( $request ) {
		$term = get_term( (int) $request['id'], Taxonomies::GENRE );

		if ( ! $term instanceof WP_Term ) {
			return $this->not_found( 'genre' );
		}

		return rest_ensure_response( $this->prepare_term( $term ) );
	}

	/**
	 * Public payload for a genre term.
	 *
	 * @param WP_Term $term Term object.
	 * @return array<string, mixed>
	 */
	public function prepare_term( WP_Term $term ): array {
		return array(
			'id'          => (int) $term->term_id,
			'slug'        => $term->slug,
			'name'        => $term->name,
			'description' => $term->description,
			'parent'      => (int) $term->parent,
			'count'       => (int) $term->count,
			'link'        => get_term_link( $term ),
		);
	}
}
