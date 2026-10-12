<?php
/**
 * Read-only REST projection of one music post type.
 *
 * @package Wavira\Core\Rest
 */

namespace Wavira\Core\Rest;

use Wavira\Core\Content\Cover;
use Wavira\Core\Content\MetaSchema;
use Wavira\Core\Content\MetaValues;
use Wavira\Core\Content\PostTypes;
use Wavira\Core\Content\QueryFilters;
use Wavira\Core\Content\Terms;
use Wavira\Core\Downloads\Access;
use Wavira\Core\Downloads\Counter;
use Wavira\Core\Player\Payload as PlayerPayload;
use Wavira\Core\Related\RelatedService;
use WP_Post;
use WP_Query;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Class ContentController
 */
final class ContentController extends AbstractController {

	/**
	 * Post type served by this controller.
	 *
	 * @var string
	 */
	private string $post_type;

	/**
	 * REST base for this post type.
	 *
	 * @var string
	 */
	private string $rest_base;

	/**
	 * Constructor.
	 *
	 * @param string $post_type Post type name.
	 */
	public function __construct( string $post_type ) {
		$this->post_type = $post_type;
		$this->rest_base = PostTypes::slug_for( $post_type );
	}

	/**
	 * Register collection and single-item routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		if ( '' === $this->rest_base ) {
			return;
		}

		register_rest_route(
			self::API_NAMESPACE,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'read_permission' ),
					'args'                => $this->collection_args(),
				),
			)
		);

		if ( RelatedService::supports( $this->post_type ) ) {
			register_rest_route(
				self::API_NAMESPACE,
				'/' . $this->rest_base . '/(?P<id>[\d]+)/related',
				array(
					array(
						'methods'             => 'GET',
						'callback'            => array( $this, 'get_related' ),
						'permission_callback' => array( $this, 'read_permission' ),
						'args'                => array(
							'id'    => array(
								'description'       => __( 'Unique identifier for the object.', 'wavira-core' ),
								'type'              => 'integer',
								'required'          => true,
								'sanitize_callback' => 'absint',
							),
							'limit' => array(
								'description' => __( 'Maximum number of related items.', 'wavira-core' ),
								'type'        => 'integer',
								'default'     => 0,
								'minimum'     => 0,
								'maximum'     => RelatedService::MAX_ITEMS,
							),
						),
					),
				)
			);
		}

		register_rest_route(
			self::API_NAMESPACE,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'read_permission' ),
					'args'                => array(
						'id' => array(
							'description'       => __( 'Unique identifier for the object.', 'wavira-core' ),
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
	 * Collection endpoint.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return \WP_REST_Response
	 */
	public function get_items( $request ) {
		$per_page = min( self::MAX_PER_PAGE, max( 1, (int) $request['per_page'] ) );
		$page     = max( 1, (int) $request['page'] );

		$args = array_merge(
			array(
				'post_type'           => $this->post_type,
				'post_status'         => 'publish',
				'posts_per_page'      => $per_page,
				'paged'               => $page,
				'ignore_sticky_posts' => true,
				's'                   => (string) $request['search'],
			),
			$this->order_args( (string) $request['orderby'], (string) $request['order'] ),
			$this->relation_args( $request )
		);

		$query = new WP_Query( $args );

		$items = array();

		foreach ( $query->posts as $post ) {
			if ( $post instanceof WP_Post ) {
				$items[] = $this->prepare_item( $post );
			}
		}

		return $this->paginated_response( $items, (int) $query->found_posts, (int) $query->max_num_pages );
	}

	/**
	 * Single-item endpoint.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( $request ) {
		$post = get_post( (int) $request['id'] );

		if ( ! $post instanceof WP_Post || $this->post_type !== $post->post_type || 'publish' !== $post->post_status ) {
			return $this->not_found( $this->post_type );
		}

		return rest_ensure_response( $this->prepare_item( $post ) );
	}

	/**
	 * Related-items endpoint.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return \WP_REST_Response
	 */
	public function get_related( $request ) {
		$post_id = (int) $request['id'];
		$post    = get_post( $post_id );

		if ( ! $post instanceof WP_Post || $this->post_type !== $post->post_type || 'publish' !== $post->post_status ) {
			return $this->not_found( $this->post_type );
		}

		$limit = (int) $request['limit'];
		$items = array();

		foreach ( RelatedService::posts( $post_id, $limit ) as $related ) {
			if ( $related instanceof WP_Post ) {
				$items[] = $this->prepare_item( $related );
			}
		}

		return $this->paginated_response( $items, count( $items ), 1 );
	}

	/**
	 * Translate request filters into query arguments.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return array<string, mixed>
	 */
	private function relation_args( $request ): array {
		return QueryFilters::args(
			array(
				'genre'    => (string) $request['genre'],
				'artist'   => (int) $request['artist'],
				'album'    => (int) $request['album'],
				'featured' => (bool) $request['featured'],
			),
			$this->post_type
		);
	}

	/**
	 * Build the lean public payload for one item.
	 *
	 * @param WP_Post $post Post object.
	 * @return array<string, mixed>
	 */
	public function prepare_item( WP_Post $post ): array {
		$post_id = (int) $post->ID;

		$payload = array(
			'id'        => $post_id,
			'type'      => $post->post_type,
			'slug'      => $post->post_name,
			'link'      => get_permalink( $post_id ),
			'title'     => get_the_title( $post_id ),
			'excerpt'   => get_the_excerpt( $post_id ),
			'date'      => mysql_to_rfc3339( $post->post_date_gmt ),
			'modified'  => mysql_to_rfc3339( $post->post_modified_gmt ),
			'cover'     => Cover::payload( $post_id ),
			'meta'      => $this->meta_payload( $post_id ),
			'relations' => $this->relations_payload( $post_id ),
			'genres'    => Terms::genres( $post_id ),
		);

		if ( PostTypes::TRACK === $post->post_type ) {
			$payload['player']    = $this->player_payload( $post_id );
			$payload['playback']  = PlayerPayload::for_track( $post_id );
			$payload['downloads'] = Access::matrix( $post_id );

			if ( Access::can_download( $post_id ) ) {
				$payload['download_stats'] = Counter::summary( $post_id );
			}
		}

		if ( PostTypes::ALBUM === $post->post_type ) {
			$payload['tracklist'] = array_map( 'absint', MetaValues::tracklist( $post_id ) );
		}

		if ( PostTypes::VIDEO === $post->post_type ) {
			$payload['video'] = $this->video_payload( $post_id );
		}

		/**
		 * Filters a prepared REST item of the product API.
		 *
		 * @since 0.3.0
		 * @param array<string, mixed> $payload Prepared payload.
		 * @param WP_Post              $post    Post object.
		 */
		return apply_filters( 'wavira_rest_item', $payload, $post );
	}

	/**
	 * Registered meta subset for this post type (typed reads via MetaValues).
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	private function meta_payload( int $post_id ): array {
		$post_type = get_post_type( $post_id );
		$schema    = MetaSchema::all();
		$payload   = array();

		foreach ( $schema as $key => $field ) {
			if ( ! in_array( $post_type, $field['entities'], true ) ) {
				continue;
			}

			switch ( $field['type'] ) {
				case 'integer':
					$payload[ $key ] = MetaValues::int( $post_id, $key );
					break;
				case 'boolean':
					$payload[ $key ] = MetaValues::bool( $post_id, $key );
					break;
				case 'array':
					$payload[ $key ] = MetaValues::ids( $post_id, $key );
					break;
				default:
					$payload[ $key ] = (string) get_post_meta( $post_id, $key, true );
					break;
			}
		}

		return $payload;
	}

	/**
	 * Relation payload with resolved display data.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	private function relations_payload( int $post_id ): array {
		$relations = array();
		$post_type = get_post_type( $post_id );

		$artist_id = MetaValues::int( $post_id, MetaSchema::ARTIST );

		if ( $artist_id && PostTypes::ARTIST === get_post_type( $artist_id ) ) {
			$relations['artist'] = array(
				'id'   => $artist_id,
				'slug' => get_post_field( 'post_name', $artist_id ),
				'name' => get_the_title( $artist_id ),
				'link' => get_permalink( $artist_id ),
			);
		}

		if ( in_array( $post_type, array( PostTypes::TRACK, PostTypes::VIDEO ), true ) ) {
			$album_id = MetaValues::int( $post_id, MetaSchema::ALBUM );

			if ( $album_id && PostTypes::ALBUM === get_post_type( $album_id ) ) {
				$relations['album'] = array(
					'id'    => $album_id,
					'slug'  => get_post_field( 'post_name', $album_id ),
					'title' => get_the_title( $album_id ),
					'link'  => get_permalink( $album_id ),
				);
			}
		}

		if ( in_array( $post_type, array( PostTypes::TRACK, PostTypes::ALBUM ), true ) ) {
			$relations['featured_artists'] = array_map(
				array( $this, 'artist_summary' ),
				MetaValues::featured_artists( $post_id )
			);
		}

		if ( PostTypes::ARTIST === $post_type ) {
			$relations['related'] = array_map(
				array( $this, 'artist_summary' ),
				MetaValues::related_artists( $post_id )
			);
		}

		return $relations;
	}

	/**
	 * Short artist representation used inside relation payloads.
	 *
	 * @param int $artist_id Artist post ID.
	 * @return array<string, mixed>
	 */
	public function artist_summary( int $artist_id ): array {
		return array(
			'id'   => $artist_id,
			'slug' => get_post_field( 'post_name', $artist_id ),
			'name' => get_the_title( $artist_id ),
			'link' => get_permalink( $artist_id ),
		);
	}


	/**
	 * Playback payload for a track (public audio sources only).
	 *
	 * @param int $post_id Track post ID.
	 * @return array<string, mixed>
	 */
	private function player_payload( int $post_id ): array {
		return array(
			'audio_128'      => MetaValues::url( $post_id, MetaSchema::AUDIO_128 ),
			'audio_320'      => MetaValues::url( $post_id, MetaSchema::AUDIO_320 ),
			'external'       => MetaValues::url( $post_id, MetaSchema::AUDIO_EXTERNAL ),
			'duration'       => MetaValues::int( $post_id, MetaSchema::DURATION ),
			'duration_label' => MetaValues::duration_label( $post_id ),
		);
	}

	/**
	 * Video payload: source type plus available qualities.
	 *
	 * @param int $post_id Video post ID.
	 * @return array<string, mixed>
	 */
	private function video_payload( int $post_id ): array {
		$source = MetaValues::text( $post_id, MetaSchema::VIDEO_SOURCE );

		return array(
			'source' => '' !== $source ? $source : 'self',
			'url'    => MetaValues::url( $post_id, MetaSchema::VIDEO_URL ),
			'480'    => MetaValues::url( $post_id, MetaSchema::VIDEO_480 ),
			'720'    => MetaValues::url( $post_id, MetaSchema::VIDEO_720 ),
			'1080'   => MetaValues::url( $post_id, MetaSchema::VIDEO_1080 ),
			'poster' => MetaValues::int( $post_id, MetaSchema::VIDEO_POSTER ),
		);
	}
}
