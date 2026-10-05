<?php
/**
 * Download endpoint of the product API.
 *
 * `wavira/v1/download/{id}` is the only place that hands out an audio file URL:
 * it asks `Downloads\Access` for a decision, counts the download and then either
 * redirects the visitor to the file (the web server serves the bytes, so PHP
 * never proxies large files) or returns the URL as JSON for players and apps
 * (ADR 0013).
 *
 * Honest scope: this is authorization plus counting, not content protection.
 * Anyone who knows a public file URL can still fetch it; hotlink protection and
 * file-level rules belong to the site owner's server configuration.
 *
 * @package Wavira\Core\Rest
 */

namespace Wavira\Core\Rest;

use WP_REST_Request;
use WP_REST_Response;
use Wavira\Core\Content\MetaSchema;
use Wavira\Core\Content\MetaValues;
use Wavira\Core\Downloads\Access;
use Wavira\Core\Downloads\Counter;

defined( 'ABSPATH' ) || exit;

/**
 * Class DownloadController
 */
final class DownloadController extends AbstractController {

	/**
	 * Register the download route.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::API_NAMESPACE,
			'/download/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'handle' ),
					'permission_callback' => array( $this, 'download_permission' ),
					'args'                => array(
						'id'       => array(
							'description'       => __( 'Track post ID.', 'wavira-core' ),
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
						'quality'  => array(
							'description' => __( 'Audio quality in kbps. Omit to use the best available.', 'wavira-core' ),
							'type'        => 'integer',
							'default'     => 0,
							'enum'        => array( 0, 128, 320 ),
						),
						'redirect' => array(
							'description' => __( 'Redirect to the file (default) instead of returning its URL as JSON.', 'wavira-core' ),
							'type'        => 'boolean',
							'default'     => true,
						),
					),
				),
			)
		);
	}

	/**
	 * Whether this request may download the requested track.
	 *
	 * All rules live in `Downloads\Access`; this callback only translates its
	 * decision into an HTTP status.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return bool|\WP_Error
	 */
	public function download_permission( $request ) {
		if ( ! Access::can_download( (int) $request['id'] ) ) {
			return new \WP_Error(
				'wavira_download_forbidden',
				__( 'Downloads are not available for this track.', 'wavira-core' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Count and deliver one download.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function handle( $request ) {
		$post_id = (int) $request['id'];
		$quality = $this->resolve_quality( $post_id, (int) $request['quality'] );

		if ( 0 === $quality ) {
			return new \WP_Error(
				'wavira_download_missing_source',
				__( 'No downloadable audio file is available for this track.', 'wavira-core' ),
				array( 'status' => 404 )
			);
		}

		$url = MetaValues::url( $post_id, $this->meta_key_for( $quality ) );

		if ( '' === $url ) {
			return new \WP_Error(
				'wavira_download_missing_source',
				__( 'No downloadable audio file is available for this track.', 'wavira-core' ),
				array( 'status' => 404 )
			);
		}

		/**
		 * Fires right before a download is counted and delivered.
		 *
		 * Use for logging or external analytics. Never use it to promise content
		 * protection: the file URL becomes known to the client either way.
		 *
		 * @since 0.4.0
		 * @param int    $post_id Track post ID.
		 * @param int    $quality Audio quality in kbps.
		 * @param string $url     File URL that will be handed out.
		 */
		do_action( 'wavira_download_served', $post_id, $quality, $url );

		$count = Counter::increment( $post_id, $quality );

		if ( ! $request['redirect'] ) {
			return rest_ensure_response(
				array(
					'id'      => $post_id,
					'quality' => $quality,
					'url'     => $url,
					'count'   => $count,
				)
			);
		}

		$response = new WP_REST_Response( null, 302 );
		$response->header( 'Location', $url );
		$response->header( 'Cache-Control', 'no-store, max-age=0, must-revalidate' );
		$response->header( 'X-Wavira-Download-Count', (string) $count );

		return $response;
	}

	/**
	 * Pick the quality to deliver.
	 *
	 * A requested quality is honoured when the track has that file; otherwise the
	 * best (highest) available quality wins, then the lowest.
	 *
	 * @param int $post_id Track post ID.
	 * @param int $quality Requested quality (0 = best available).
	 * @return int Quality in kbps, 0 when the track has no audio file at all.
	 */
	private function resolve_quality( int $post_id, int $quality ): int {
		$available = array();

		foreach ( array( 320, 128 ) as $candidate ) {
			if ( '' !== MetaValues::url( $post_id, $this->meta_key_for( $candidate ) ) ) {
				$available[] = $candidate;
			}
		}

		if ( empty( $available ) ) {
			return 0;
		}

		if ( $quality > 0 && in_array( $quality, $available, true ) ) {
			return $quality;
		}

		return (int) $available[0];
	}

	/**
	 * Meta key that stores the audio file of one quality.
	 *
	 * @param int $quality Quality in kbps.
	 * @return string
	 */
	private function meta_key_for( int $quality ): string {
		$keys = array(
			128 => MetaSchema::AUDIO_128,
			320 => MetaSchema::AUDIO_320,
		);

		/**
		 * Filters the audio meta key per download quality.
		 *
		 * @since 0.4.0
		 * @param array<int, string> $keys Quality in kbps mapped to a meta key.
		 */
		$keys = (array) apply_filters( 'wavira_download_quality_sources', $keys );

		return isset( $keys[ $quality ] ) ? (string) $keys[ $quality ] : '';
	}
}
