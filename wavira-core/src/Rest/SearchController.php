<?php
/**
 * Search endpoints of the product API.
 *
 * `wavira/v1/search` returns the same lean item payload as the collection routes,
 * so the front end has one shape to render. `wavira/v1/search/suggest` powers
 * type-ahead boxes with grouped, minimal payloads (ADR 0006).
 *
 * @package Wavira\Core\Rest
 */

namespace Wavira\Core\Rest;

use WP_Post;
use WP_REST_Request;
use Wavira\Core\Content\PostTypes;
use Wavira\Core\Search\SearchService;

defined( 'ABSPATH' ) || exit;

/**
 * Class SearchController
 */
final class SearchController extends AbstractController {

	/**
	 * Presenters keyed by post type, created on demand.
	 *
	 * @var array<string, ContentController>
	 */
	private array $presenters = array();

	/**
	 * Register the search routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::API_NAMESPACE,
			'/search',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'read_permission' ),
					'args'                => $this->search_args(),
				),
			)
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/search/suggest',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_suggestions' ),
					'permission_callback' => array( $this, 'read_permission' ),
					'args'                => array(
						'term'  => array(
							'description'       => __( 'Search term.', 'wavira-core' ),
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'limit' => array(
							'description' => __( 'Maximum suggestions per entity.', 'wavira-core' ),
							'type'        => 'integer',
							'default'     => 6,
							'minimum'     => 1,
							'maximum'     => 12,
						),
					),
				),
			)
		);
	}

	/**
	 * Search endpoint.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return \WP_REST_Response
	 */
	public function get_items( $request ) {
		$result = SearchService::search(
			array(
				'term'     => (string) $request['term'],
				'type'     => (string) $request['type'],
				'page'     => (int) $request['page'],
				'per_page' => (int) $request['per_page'],
				'genre'    => (string) $request['genre'],
				'artist'   => (int) $request['artist'],
				'orderby'  => (string) $request['orderby'],
				'order'    => (string) $request['order'],
			)
		);

		$items = array();

		foreach ( $result['items'] as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$presenter = $this->presenter_for( (string) $post->post_type );

			$items[] = $presenter instanceof ContentController
				? $presenter->prepare_item( $post )
				: SearchService::summarize( $post );
		}

		return $this->paginated_response( $items, (int) $result['total'], (int) $result['pages'] );
	}

	/**
	 * Suggestion endpoint.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return \WP_REST_Response
	 */
	public function get_suggestions( $request ) {
		$groups = SearchService::suggest( (string) $request['term'], (int) $request['limit'] );

		$response = rest_ensure_response( $groups );
		$response->header( 'X-WP-Total', (string) array_sum( array_map( 'count', $groups ) ) );

		return $response;
	}

	/**
	 * Arguments of the search endpoint.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function search_args(): array {
		$args = $this->collection_args();

		$args['term'] = array(
			'description'       => __( 'Search term.', 'wavira-core' ),
			'type'              => 'string',
			'required'          => true,
			'sanitize_callback' => 'sanitize_text_field',
		);

		$args['type'] = array(
			'description' => __( 'Limit results to one entity type.', 'wavira-core' ),
			'type'        => 'string',
			'default'     => 'any',
			'enum'        => $this->type_choices(),
		);

		return $args;
	}

	/**
	 * Accepted values of the `type` argument: `any` plus public slugs.
	 *
	 * @return string[]
	 */
	private function type_choices(): array {
		$choices = array( 'any' );

		foreach ( PostTypes::all() as $post_type ) {
			$slug = PostTypes::slug_for( $post_type );

			if ( '' !== $slug ) {
				$choices[] = $slug;
			}
		}

		return $choices;
	}

	/**
	 * Presenter for a post type, created on demand.
	 *
	 * @param string $post_type Post type.
	 * @return ContentController|null
	 */
	private function presenter_for( string $post_type ) {
		if ( ! PostTypes::is_music_type( $post_type ) ) {
			return null;
		}

		if ( ! isset( $this->presenters[ $post_type ] ) ) {
			$this->presenters[ $post_type ] = new ContentController( $post_type );
		}

		return $this->presenters[ $post_type ];
	}
}
