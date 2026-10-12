<?php
/**
 * How the settings option is registered, and what the REST API is told about it.
 *
 * The `wp-render` job runs a real WordPress with `WP_DEBUG` on and reads the
 * debug log, which is how this file came to exist: registering the option as an
 * `array` with an object schema made core log a doing-it-wrong notice on every
 * request ("you must specify the schema for each array item"), and a notice in
 * the log is a defect rather than a warning to live with.
 *
 * @package Wavira\Tests
 */

use Wavira\Core\Settings\Settings;
use Wavira\Core\Settings\SettingsSchema;

/**
 * Class Test_Settings_Registration
 */
class Test_Settings_Registration extends Wavira_Test_Case {

	/**
	 * Collect `_doing_it_wrong()` calls made while a callback runs.
	 *
	 * @param callable $callback Code under test.
	 * @return string[] One message per call.
	 */
	private function collect_doing_it_wrong( callable $callback ) {
		$messages = array();

		$collector = static function ( $function, $message ) use ( &$messages ) {
			$messages[] = $function . ': ' . $message;
		};

		add_action( 'doing_it_wrong_run', $collector, 10, 2 );

		try {
			$callback();
		} finally {
			remove_action( 'doing_it_wrong_run', $collector, 10 );
		}

		return $messages;
	}

	/**
	 * Registering the option says nothing to the debug log.
	 *
	 * @return void
	 */
	public function test_registering_the_setting_does_not_do_it_wrong() {
		$settings = new Settings();

		$this->assertSame(
			array(),
			$this->collect_doing_it_wrong( array( $settings, 'register_setting' ) ),
			'register_setting() must not warn: the option is a keyed object, not a list.'
		);
	}

	/**
	 * The registration describes the value as an object with a schema per key.
	 *
	 * @return void
	 */
	public function test_the_registered_type_matches_the_stored_value() {
		$settings = new Settings();
		$settings->register_setting();

		$registered = get_registered_settings();
		$args       = $registered[ SettingsSchema::OPTION ];

		$this->assertSame( 'object', $args['type'] );
		$this->assertIsArray( $args['show_in_rest'] );
		$this->assertSame( 'object', $args['show_in_rest']['schema']['type'] );

		// The value really is a map: an associative array of setting name to value.
		$this->assertIsArray( $args['default'] );
		$this->assertNotSame( array(), $args['default'] );
		$this->assertIsString( array_key_first( $args['default'] ) );
	}

	/**
	 * Every field in the schema is exposed to REST, and with the right type.
	 *
	 * Drift here is silent: the settings screen keeps working while the REST API
	 * silently drops a key.
	 *
	 * @return void
	 */
	public function test_the_rest_schema_covers_every_field() {
		$schema     = SettingsSchema::rest_schema();
		$properties = $schema['properties'];

		// How a stored type travels over REST. A field type that is not in this map
		// is one the API was never told about.
		$rest_types = array(
			'boolean' => 'boolean',
			'integer' => 'integer',
			'array'   => 'array',
			'html'    => 'string',
			'url'     => 'string',
			'string'  => 'string',
		);

		foreach ( SettingsSchema::all() as $key => $field ) {
			$this->assertArrayHasKey( $key, $properties, "{$key} is missing from the REST schema" );

			$this->assertArrayHasKey(
				$field['type'],
				$rest_types,
				"the test does not know how a `{$field['type']}` field travels over REST — add it to rest_schema()"
			);

			$this->assertSame( $rest_types[ $field['type'] ], $properties[ $key ]['type'], "{$key} has the wrong REST type" );

			if ( 'array' === $field['type'] ) {
				// Core asks for the item schema of a list; without it the REST
				// request cannot validate a single element.
				$this->assertArrayHasKey( 'items', $properties[ $key ], "{$key} is a list without an item schema" );
			}
		}

		$this->assertSame( count( SettingsSchema::all() ), count( $properties ) );
	}

	/**
	 * The schema is valid for the REST infrastructure, and it survives a round trip.
	 *
	 * @return void
	 */
	public function test_the_schema_round_trips_through_rest_sanitisation() {
		$schema   = SettingsSchema::rest_schema();
		$defaults = SettingsSchema::defaults();

		$this->assertTrue(
			rest_validate_value_from_schema( $defaults, $schema ),
			'the defaults do not satisfy the schema the API is given'
		);

		$sanitized = rest_sanitize_value_from_schema( $defaults, $schema );

		$this->assertIsArray( $sanitized );
		$this->assertSame( array_keys( $defaults ), array_keys( $sanitized ) );

		foreach ( $defaults as $key => $value ) {
			$this->assertSame( $value, $sanitized[ $key ], "{$key} changed while passing through the REST schema" );
		}
	}
}
