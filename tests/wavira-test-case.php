<?php
/**
 * Shared base class for the Wavira integration suite.
 *
 * The core test library calls `WP_UnitTestCase_Base::unregister_all_meta_keys()`
 * in `tear_down()`, so after the first test the plugin's meta registry (built
 * once during `init`) is empty and every later test would observe a state a live
 * site never has. This base class rebuilds exactly the plugin's own
 * registration before each test — same code path as a real request, no fixture
 * data invented by the test.
 *
 * @package Wavira\Tests
 */

/**
 * Class Wavira_Test_Case
 */
abstract class Wavira_Test_Case extends WP_UnitTestCase {

	/**
	 * Restore the plugin's registries that the core test library clears.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		// Meta keys only: post types and taxonomies survive the core test
		// library's tear_down, and re-registering them would make a failing test
		// harder to attribute. The plugin's own Meta::register_meta() is used on
		// purpose — a test that reimplements registration proves nothing.
		( new \Wavira\Core\Content\Meta() )->register_meta();
	}

	/**
	 * Dispatch one GET request against the product API.
	 *
	 * Parameters are set on the request object. A query string appended to the
	 * route string is not parsed by `WP_REST_Request`, so the route itself would
	 * fail to match and every dynamic assertion would read a 404 instead.
	 *
	 * @param string               $route  Route path.
	 * @param array<string, mixed> $params Query parameters.
	 * @return WP_REST_Response|WP_HTTP_Response
	 */
	protected function dispatch( string $route, array $params = array() ) {
		$request = new WP_REST_Request( 'GET', $route );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return rest_get_server()->dispatch( $request );
	}
}
