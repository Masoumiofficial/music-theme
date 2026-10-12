<?php
/**
 * Player routes: the data the front-end engine plays.
 *
 * The engine never builds a URL and never reads a template — it asks these
 * routes for a playback payload and renders whatever comes back (ADR 0005 §6).
 * Both routes are read-only, public, and describe published items only.
 *
 * @package Wavira\Core\Rest
 */

namespace Wavira\Core\Rest;

use Wavira\Core\Content\OrderArgs;
use Wavira\Core\Player\Payload;
use Wavira\Core\Player\Queue;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Class PlayerController
 */
final class PlayerController extends AbstractController {

	/**
	 * How long a player response may be reused by a client or a shared cache.
	 *
	 * The payload describes published content and carries no user data, so a
	 * short public lifetime removes repeat questions without making an edited
	 * track invisible.
	 *
	 * @var int
	 */
	public const CACHE_TTL = 60;

	/**
	 * Register the player routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::API_NAMESPACE,
			'/player/tracks/(?P<id>[\d]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_track' ),
				'permission_callback' => array( $this, 'read_permission' ),
				'args'                => array(
					'id' => array(
						'description'       => __( 'Track post ID.', 'wavira-core' ),
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/player/queue',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_queue' ),
				'permission_callback' => array( $this, 'read_permission' ),
				'args'                => array(
					'context' => array(
						'description' => __( 'What the queue is built from.', 'wavira-core' ),
						'type'        => 'string',
						'default'     => 'tracks',
						'enum'        => Queue::CONTEXTS,
					),
					'id'      => array(
						'description'       => __( 'Source post ID for album, artist and related queues.', 'wavira-core' ),
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'slug'    => array(
						'description'       => __( 'Genre slug for a genre queue.', 'wavira-core' ),
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => array( $this, 'sanitize_slug' ),
					),
					'limit'   => array(
						'description'       => __( 'Maximum number of tracks in the queue.', 'wavira-core' ),
						'type'              => 'integer',
						'default'           => 0,
						'minimum'           => 0,
						'maximum'           => Queue::MAX_ITEMS,
						'sanitize_callback' => 'absint',
					),
					'orderby' => array(
						'description' => __( 'Sort collection by field.', 'wavira-core' ),
						'type'        => 'string',
						'default'     => 'date',
						'enum'        => OrderArgs::ALLOWED,
					),
					'order'   => array(
						'description' => __( 'Order direction.', 'wavira-core' ),
						'type'        => 'string',
						'default'     => 'desc',
						'enum'        => array( 'asc', 'desc' ),
					),
				),
			)
		);
	}

	/**
	 * Playback payload of one track.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function get_track( $request ) {
		$payload = Payload::for_track( absint( $request['id'] ) );

		if ( array() === $payload ) {
			return $this->not_found( 'track' );
		}

		return $this->cacheable( rest_ensure_response( $payload ) );
	}

	/**
	 * Playable items of one context.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function get_queue( $request ) {
		$context = (string) $request['context'];
		$args    = array(
			'id'      => (int) $request['id'],
			'slug'    => (string) $request['slug'],
			'limit'   => (int) $request['limit'],
			'orderby' => (string) $request['orderby'],
			'order'   => (string) $request['order'],
		);

		$source = $this->source_error( $context, $args );

		if ( null !== $source ) {
			return $source;
		}

		$items = Queue::items( $context, $args );

		$response = rest_ensure_response(
			array(
				'context' => $context,
				'count'   => count( $items ),
				'items'   => $items,
			)
		);

		return $this->cacheable( $response );
	}

	/**
	 * Guard the contexts that cannot work without a source.
	 *
	 * Returning an empty queue for a malformed request would hide a template
	 * bug; a 400 says what is missing while leaking nothing.
	 *
	 * @param string               $context Queue context.
	 * @param array<string, mixed> $args    Request arguments.
	 * @return \WP_Error|null Error when the request cannot be answered.
	 */
	private function source_error( string $context, array $args ) {
		$requires_id = in_array( $context, array( 'album', 'artist', 'related' ), true );

		if ( $requires_id && (int) $args['id'] < 1 ) {
			return new \WP_Error(
				'wavira_missing_source',
				sprintf(
					/* translators: %s: queue context, e.g. "album". */
					__( 'A %s queue needs the ID of its source item.', 'wavira-core' ),
					$context
				),
				array( 'status' => 400 )
			);
		}

		if ( 'genre' === $context && '' === (string) $args['slug'] ) {
			return new \WP_Error(
				'wavira_missing_source',
				__( 'A genre queue needs a genre slug.', 'wavira-core' ),
				array( 'status' => 400 )
			);
		}

		return null;
	}

	/**
	 * Attach the shared player cache policy to a response.
	 *
	 * @param WP_REST_Response $response Response to send.
	 * @return WP_REST_Response
	 */
	private function cacheable( WP_REST_Response $response ): WP_REST_Response {
		$response->header( 'Cache-Control', 'public, max-age=' . self::CACHE_TTL );

		return $response;
	}
}
