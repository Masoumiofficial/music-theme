<?php
/**
 * PHPUnit bootstrap for the Wavira integration suite.
 *
 * Loads the WordPress test library, then loads Wavira Core exactly the way a
 * real site would (`muplugins_loaded`), so every test runs against the plugin's
 * own boot sequence — requirements gate, autoloader, module registry and all.
 *
 * @package Wavira\Tests
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php — run bin/install-wp-tests.sh first."; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI bootstrap message.
	exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__ ) . '/wavira-core/wavira-core.php';
	}
);

require $_tests_dir . '/includes/bootstrap.php';

// After the test library: Wavira_Test_Case extends WP_UnitTestCase, which only
// exists once the library has been loaded.
require_once __DIR__ . '/wavira-test-case.php';
