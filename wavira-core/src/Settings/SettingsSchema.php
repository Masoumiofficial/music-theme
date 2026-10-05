<?php
/**
 * The single settings schema (ADR 0004): one option, typed fields, one sanitizer.
 *
 * @package Wavira\Core\Settings
 */

namespace Wavira\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class SettingsSchema
 */
final class SettingsSchema {

	/**
	 * Option name holding the whole settings array.
	 *
	 * @var string
	 */
	public const OPTION = 'wavira_settings';

	/**
	 * Every setting: type, default, label and optional constraints.
	 *
	 * Supported types: boolean, integer, string, url, array(string), html.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function all(): array {
		return array(
			/* Appearance ------------------------------------------------------- */
			'dark_toggle'              => array(
				'type'    => 'boolean',
				'default' => true,
				'label'   => __( 'Show the light/dark switch', 'wavira-core' ),
			),
			'dark_default'             => array(
				'type'    => 'boolean',
				'default' => false,
				'label'   => __( 'Start in dark mode', 'wavira-core' ),
			),

			/* Content ---------------------------------------------------------- */
			'tracks_per_page'          => array(
				'type'    => 'integer',
				'default' => 20,
				'min'     => 6,
				'max'     => 60,
				'label'   => __( 'Tracks per archive page', 'wavira-core' ),
			),
			'related_limit'            => array(
				'type'    => 'integer',
				'default' => 8,
				'min'     => 3,
				'max'     => 24,
				'label'   => __( 'Related items per block', 'wavira-core' ),
			),
			'enable_mood'              => array(
				'type'    => 'boolean',
				'default' => true,
				'label'   => __( 'Enable the Mood taxonomy', 'wavira-core' ),
			),
			'enable_language'          => array(
				'type'    => 'boolean',
				'default' => true,
				'label'   => __( 'Enable the Language taxonomy', 'wavira-core' ),
			),
			'enable_label'             => array(
				'type'    => 'boolean',
				'default' => true,
				'label'   => __( 'Enable the Label taxonomy', 'wavira-core' ),
			),
			'enable_year'              => array(
				'type'    => 'boolean',
				'default' => false,
				'label'   => __( 'Enable the Release-year taxonomy', 'wavira-core' ),
			),

			/* Player ----------------------------------------------------------- */
			'player_sticky'            => array(
				'type'    => 'boolean',
				'default' => true,
				'label'   => __( 'Show the sticky player bar', 'wavira-core' ),
			),
			'player_autoplay'          => array(
				'type'    => 'boolean',
				'default' => false,
				'label'   => __( 'Autoplay the next track in a queue', 'wavira-core' ),
			),
			'player_default_volume'    => array(
				'type'    => 'integer',
				'default' => 100,
				'min'     => 0,
				'max'     => 100,
				'label'   => __( 'Default player volume (percent)', 'wavira-core' ),
			),

			/* Downloads -------------------------------------------------------- */
			'downloads_enabled'        => array(
				'type'    => 'boolean',
				'default' => true,
				'label'   => __( 'Offer audio downloads', 'wavira-core' ),
			),
			'downloads_require_login'  => array(
				'type'    => 'boolean',
				'default' => false,
				'label'   => __( 'Require a logged-in user to download', 'wavira-core' ),
			),

			/* Footer / social --------------------------------------------------- */
			'copyright'                => array(
				'type'    => 'string',
				'default' => '',
				'label'   => __( 'Footer copyright text', 'wavira-core' ),
			),
			'socials'                  => array(
				'type'    => 'array',
				'items'   => 'url',
				'default' => array(),
				'label'   => __( 'Global social profile URLs', 'wavira-core' ),
			),

			/* Advertising ------------------------------------------------------- */
			'ads_html'                 => array(
				'type'    => 'html',
				'default' => '',
				'label'   => __( 'Ad slot HTML (above the player)', 'wavira-core' ),
			),

			/* Data -------------------------------------------------------------- */
			'remove_data_on_uninstall' => array(
				'type'    => 'boolean',
				'default' => false,
				'label'   => __( 'Delete all music content when the plugin is uninstalled', 'wavira-core' ),
			),
		);
	}

	/**
	 * Default values for every setting.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		$defaults = array();

		foreach ( self::all() as $key => $field ) {
			$defaults[ $key ] = $field['default'];
		}

		return $defaults;
	}

	/**
	 * Sanitize the whole settings array.
	 *
	 * Unknown keys are dropped (no shadow fields can be stored), each value is
	 * sanitized by its declared type and clamped to its bounds.
	 *
	 * @param mixed $input Raw input from the settings form or REST.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$clean    = array();

		foreach ( self::all() as $key => $field ) {
			$raw = array_key_exists( $key, $input ) ? $input[ $key ] : $defaults[ $key ];

			switch ( $field['type'] ) {
				case 'boolean':
					$clean[ $key ] = filter_var( $raw, FILTER_VALIDATE_BOOLEAN );
					break;

				case 'integer':
					$value = absint( is_scalar( $raw ) ? $raw : 0 );

					if ( isset( $field['min'] ) ) {
						$value = max( (int) $field['min'], $value );
					}
					if ( isset( $field['max'] ) ) {
						$value = min( (int) $field['max'], $value );
					}

					$clean[ $key ] = $value;
					break;

				case 'url':
					$clean[ $key ] = esc_url_raw( is_scalar( $raw ) ? trim( (string) $raw ) : '' );
					break;

				case 'html':
					$clean[ $key ] = wp_kses_post( is_scalar( $raw ) ? (string) $raw : '' );
					break;

				case 'array':
					$items = is_array( $raw ) ? $raw : preg_split( '/[\r\n,]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY );
					$items = array_map( 'esc_url_raw', array_map( 'trim', (array) $items ) );

					$clean[ $key ] = array_values( array_filter( $items ) );
					break;

				case 'string':
				default:
					$clean[ $key ] = sanitize_text_field( is_scalar( $raw ) ? (string) $raw : '' );
					break;
			}
		}

		/**
		 * Filters the sanitized settings array before it is stored.
		 *
		 * @since 0.3.0
		 * @param array<string, mixed> $clean   Sanitized settings.
		 * @param array<string, mixed> $input   Raw input.
		 */
		return apply_filters( 'wavira_settings_sanitized', $clean, $input );
	}

	/**
	 * REST schema for the settings object (used by register_setting).
	 *
	 * @return array<string, mixed>
	 */
	public static function rest_schema(): array {
		$properties = array();

		foreach ( self::all() as $key => $field ) {
			switch ( $field['type'] ) {
				case 'boolean':
					$properties[ $key ] = array( 'type' => 'boolean' );
					break;
				case 'integer':
					$properties[ $key ] = array( 'type' => 'integer' );
					break;
				case 'array':
					$properties[ $key ] = array(
						'type'  => 'array',
						'items' => array( 'type' => 'string' ),
					);
					break;
				default:
					$properties[ $key ] = array( 'type' => 'string' );
					break;
			}
		}

		return array(
			'type'       => 'object',
			'properties' => $properties,
		);
	}
}
