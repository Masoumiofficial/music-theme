<?php
/**
 * Engagement (views/plays, likes) and Top Artists ranking endpoints.
 *
 * @package Wavira\Core\Rest
 */

namespace Wavira\Core\Rest;

use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use Wavira\Core\Content\PostTypes;
use Wavira\Core\Content\Stats;

defined( 'ABSPATH' ) || exit;

/**
 * Class StatsController
 */
final class StatsController extends AbstractController {

	/**
	 * Register routes under `wavira/v1`.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::API_NAMESPACE,
			'/stats/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_stats' ),
					'permission_callback' => array( $this, 'public_read' ),
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

		register_rest_route(
			self::API_NAMESPACE,
			'/stats/(?P<id>[\d]+)/view',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'record_view' ),
					'permission_callback' => array( $this, 'public_read' ),
					'args'                => array(
						'id'    => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
						'token' => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/stats/(?P<id>[\d]+)/like',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'toggle_like' ),
					'permission_callback' => array( $this, 'public_read' ),
					'args'                => array(
						'id'    => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
						'token' => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/artists/top',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'top_artists' ),
					'permission_callback' => array( $this, 'public_read' ),
					'args'                => array(
						'period' => array(
							'type'    => 'string',
							'default' => 'all',
							'enum'    => array( 'week', 'month', 'year', 'all' ),
						),
						'limit'  => array(
							'type'              => 'integer',
							'default'           => 8,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);
	}

	/**
	 * Read engagement stats for one music post.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_stats( $request ) {
		$post = $this->valid_music_post( (int) $request['id'] );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		return rest_ensure_response( Stats::breakdown( $post->ID ) );
	}

	/**
	 * Record a view/play start for one music post.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function record_view( $request ) {
		$post = $this->valid_music_post( (int) $request['id'] );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$token = isset( $request['token'] ) ? (string) $request['token'] : '';
		Stats::record_view( $post->ID, $token );

		return rest_ensure_response( Stats::breakdown( $post->ID, $token ) );
	}

	/**
	 * Toggle a like on one music post.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function toggle_like( $request ) {
		$post = $this->valid_music_post( (int) $request['id'] );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$token  = isset( $request['token'] ) ? (string) $request['token'] : '';
		$result = Stats::toggle_like( $post->ID, $token );
		$data   = Stats::breakdown( $post->ID, $token );

		$data['liked']       = (bool) $result['liked'];
		$data['likes']       = (int) $result['likes'];
		$data['likes_label'] = (string) $result['likes_label'];

		return rest_ensure_response( $data );
	}

	/**
	 * Ranked Top Artists for a time period.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function top_artists( $request ): WP_REST_Response {
		$period = isset( $request['period'] ) ? (string) $request['period'] : 'all';
		$limit  = isset( $request['limit'] ) ? (int) $request['limit'] : 8;
		$items  = Stats::top_artists( $period, $limit );

		return rest_ensure_response(
			array(
				'period' => $period,
				'count'  => count( $items ),
				'items'  => $items,
			)
		);
	}

	/**
	 * Validate that an ID points to a published music post.
	 *
	 * @param int $post_id Post ID.
	 * @return WP_Post|WP_Error
	 */
	private function valid_music_post( int $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || ! PostTypes::is_music_type( $post->post_type ) ) {
			return new WP_Error(
				'wavira_not_found',
				__( 'Music item not found.', 'wavira-core' ),
				array( 'status' => 404 )
			);
		}

		return $post;
	}
}
