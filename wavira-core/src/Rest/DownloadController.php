<?php
/**
 * Download endpoint of the product API.
 *
 * `wavira/v1/download/{id}` is the only place that hands out a file URL: it asks
 * `Downloads\Access` for a decision, counts the download and then either redirects
 * the visitor to the file (the web server serves the bytes, so PHP never proxies
 * large files) or returns the URL as JSON for players and apps (ADR 0013).
 *
 * One route, four kinds of file (ADR 0023). The post ID decides: a track hands out
 * its audio at the requested bitrate, an album its own master file, a hosted video
 * its file, an attachment the image itself. An embed is never a download — there
 * is nothing to hand out — and the endpoint says so with a 404 rather than a
 * broken link.
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
use Wavira\Core\Downloads\Access;
use Wavira\Core\Downloads\Counter;
use Wavira\Core\Downloads\Sources;

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
							'description'       => __( 'Post ID: a track, an album, a video or a cover/gallery image.', 'wavira-core' ),
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
						'quality'  => array(
							'description' => __( 'Audio bitrate in kbps or video height in pixels. Omit to use the best available.', 'wavira-core' ),
							'type'        => 'integer',
							'default'     => 0,
							'enum'        => array( 0, 128, 320, 480, 720, 1080 ),
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
		if ( Access::allows( (int) $request['id'] ) ) {
			return true;
		}

		// Nothing to hand out is a 404; not allowed to hand it out is a 401/403.
		// The distinction matters to a client and it is free: the file either
		// exists or the rules said no.
		$missing = array() === Sources::available( (int) $request['id'] );

		return new \WP_Error(
			$missing ? 'wavira_download_missing_source' : 'wavira_download_forbidden',
			$missing
				? __( 'There is no downloadable file for this post.', 'wavira-core' )
				: __( 'Downloads are not available for this post.', 'wavira-core' ),
			array( 'status' => $missing ? 404 : rest_authorization_required_code() )
		);
	}

	/**
	 * Count and deliver one download.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function handle( $request ) {
		$post_id = (int) $request['id'];
		$file    = Sources::resolve( $post_id, (int) $request['quality'] );

		if ( empty( $file['url'] ) ) {
			return new \WP_Error(
				'wavira_download_missing_source',
				__( 'There is no downloadable file for this post.', 'wavira-core' ),
				array( 'status' => 404 )
			);
		}

		$url     = (string) $file['url'];
		$type    = (string) $file['type'];
		$quality = (int) $file['quality'];

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
		/**
		 * Fires right before a download is counted and delivered.
		 *
		 * The type-aware companion of `wavira_download_served`, which stays for the
		 * track case it has always carried.
		 *
		 * @since 0.15.0
		 * @param int    $post_id Post ID.
		 * @param string $type    `track`, `album`, `video` or `image`.
		 * @param string $url     File URL that will be handed out.
		 */
		do_action( 'wavira_download_served_post', $post_id, $type, $url );

		do_action( 'wavira_download_served', $post_id, $quality, $url );

		$count = Counter::increment( $post_id, $quality );

		if ( ! $request['redirect'] ) {
			return rest_ensure_response(
				array(
					'id'      => $post_id,
					'type'    => $type,
					'quality' => $quality,
					'label'   => (string) ( $file['label'] ?? '' ),
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

}
