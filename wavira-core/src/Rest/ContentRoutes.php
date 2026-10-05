<?php
/**
 * Registers every product REST route.
 *
 * @package Wavira\Core\Rest
 */

namespace Wavira\Core\Rest;

use Wavira\Core\Content\PostTypes;
use Wavira\Core\Contracts\Registrable;

defined( 'ABSPATH' ) || exit;

/**
 * Class ContentRoutes
 */
final class ContentRoutes implements Registrable {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the routes of the `wavira/v1` namespace.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		foreach ( PostTypes::all() as $post_type ) {
			( new ContentController( $post_type ) )->register_routes();
		}

		( new GenresController() )->register_routes();
		( new SearchController() )->register_routes();
		( new PlayerController() )->register_routes();
		( new DownloadController() )->register_routes();
	}
}
