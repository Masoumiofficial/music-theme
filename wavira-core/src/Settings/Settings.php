<?php
/**
 * Registers the settings option and reads values safely (ADR 0004).
 *
 * The theme never owns settings: it reads them through Wavira Core helpers.
 *
 * @package Wavira\Core\Settings
 */

namespace Wavira\Core\Settings;

use Wavira\Core\Contracts\Registrable;

defined( 'ABSPATH' ) || exit;

/**
 * Class Settings
 */
final class Settings implements Registrable {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_setting' ), 3 );
	}

	/**
	 * Register the option with sanitization and REST exposure.
	 *
	 * @return void
	 */
	public function register_setting(): void {
		register_setting(
			'wavira',
			SettingsSchema::OPTION,
			array(
				'type'              => 'array',
				'label'             => __( 'Wavira settings', 'wavira-core' ),
				'description'       => __( 'Music product settings: appearance, content, player, downloads and data handling.', 'wavira-core' ),
				'sanitize_callback' => array( SettingsSchema::class, 'sanitize' ),
				'default'           => SettingsSchema::defaults(),
				'show_in_rest'      => array( 'schema' => SettingsSchema::rest_schema() ),
			)
		);
	}

	/**
	 * Read one setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $fallback Value returned when the key is unknown or unset.
	 * @return mixed
	 */
	public static function get( string $key, $fallback = null ) {
		$schema = SettingsSchema::all();

		if ( ! isset( $schema[ $key ] ) ) {
			return $fallback;
		}

		$fallback = null === $fallback ? $schema[ $key ]['default'] : $fallback;
		$options  = get_option( SettingsSchema::OPTION, array() );

		if ( ! is_array( $options ) || ! array_key_exists( $key, $options ) ) {
			return $fallback;
		}

		/**
		 * Filters a single setting value at read time.
		 *
		 * @since 0.3.0
		 * @param mixed  $value Setting value.
		 * @param string $key   Setting key.
		 */
		return apply_filters( 'wavira_setting', $options[ $key ], $key );
	}

	/**
	 * Read every setting merged over the schema defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		$options = get_option( SettingsSchema::OPTION, array() );

		return wp_parse_args( is_array( $options ) ? $options : array(), SettingsSchema::defaults() );
	}

	/**
	 * Persist a set of values through the schema sanitizer.
	 *
	 * Used by the settings screen, the REST controller and the migration tool —
	 * never called with raw user input that has not passed a permission check.
	 *
	 * @param array<string, mixed> $values Values to merge into the stored option.
	 * @return array<string, mixed> The stored settings.
	 */
	public static function update( array $values ): array {
		$merged = array_merge( self::all(), $values );
		$clean  = SettingsSchema::sanitize( $merged );

		update_option( SettingsSchema::OPTION, $clean, false );

		return $clean;
	}
}
