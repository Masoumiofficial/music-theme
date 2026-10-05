<?php
/**
 * Registers every music meta key with type, sanitizer, auth callback and REST
 * schema, and provides the sanitizers used at write time.
 *
 * @package Wavira\Core\Content
 */

namespace Wavira\Core\Content;

use Wavira\Core\Contracts\Registrable;

defined( 'ABSPATH' ) || exit;

/**
 * Class Meta
 */
final class Meta implements Registrable {

	/**
	 * Register hooks. Meta is registered after post types (priority 2).
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_meta' ), 2 );
	}

	/**
	 * Register all schema keys for their owning post types.
	 *
	 * @return void
	 */
	public function register_meta(): void {
		foreach ( MetaSchema::all() as $key => $field ) {
			foreach ( $field['entities'] as $post_type ) {
				register_post_meta( $post_type, $key, $this->args_for( $field ) );
			}
		}
	}

	/**
	 * Build register_post_meta() arguments from a schema entry.
	 *
	 * @param array<string, mixed> $field Schema entry.
	 * @return array<string, mixed>
	 */
	private function args_for( array $field ): array {
		$schema = array( 'type' => $field['type'] );

		if ( isset( $field['rest_items'] ) ) {
			$schema['items'] = array( 'type' => $field['rest_items'] );
		}

		if ( isset( $field['enum'] ) ) {
			$schema['enum'] = $field['enum'];
		}

		$args = array(
			'type'              => $field['type'],
			'single'            => true,
			'show_in_rest'      => array( 'schema' => $schema ),
			'sanitize_callback' => $this->sanitizer_for( $field ),
			'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
				unset( $allowed, $meta_key );

				return current_user_can( 'edit_post', (int) $post_id );
			},
		);

		// Plugin-written metrics (download counters) stay out of the REST API:
		// they are read through the product payloads and can never be edited
		// by a client (ADR 0013).
		if ( isset( $field['rest'] ) && false === $field['rest'] ) {
			$args['show_in_rest'] = false;
		}

		return $args;
	}

	/**
	 * Resolve the sanitizer callable for a schema entry.
	 *
	 * @param array<string, mixed> $field Schema entry.
	 * @return callable
	 */
	private function sanitizer_for( array $field ): callable {
		$enum = isset( $field['enum'] ) ? (array) $field['enum'] : array();

		switch ( $field['sanitize'] ) {
			case 'id':
				return array( self::class, 'sanitize_id' );
			case 'ids':
				return array( self::class, 'sanitize_ids' );
			case 'url':
				return array( self::class, 'sanitize_url' );
			case 'bool':
				return array( self::class, 'sanitize_bool' );
			case 'int':
				return array( self::class, 'sanitize_int' );
			case 'date':
				return array( self::class, 'sanitize_date' );
			case 'html':
				return array( self::class, 'sanitize_html' );
			case 'enum':
				return static function ( $value ) use ( $enum ) {
					return self::sanitize_enum( $value, $enum );
				};
			case 'text':
			default:
				return array( self::class, 'sanitize_text' );
		}
	}

	/* Sanitizers ----------------------------------------------------------------- */

	/**
	 * Sanitize a single object ID (0 when empty or invalid).
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public static function sanitize_id( $value ): int {
		return absint( is_scalar( $value ) ? $value : 0 );
	}

	/**
	 * Sanitize a list of object IDs, preserving order and dropping duplicates.
	 *
	 * @param mixed $value Raw value.
	 * @return int[]
	 */
	public static function sanitize_ids( $value ): array {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY );
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		$ids = array_map( 'absint', $value );
		$ids = array_filter( $ids );

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Sanitize a URL.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_url( $value ): string {
		return is_scalar( $value ) ? esc_url_raw( trim( (string) $value ) ) : '';
	}

	/**
	 * Sanitize a boolean (accepts 1/'1'/'true'/'yes'/'on').
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	public static function sanitize_bool( $value ): bool {
		return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Sanitize a non-negative integer.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public static function sanitize_int( $value ): int {
		return absint( is_scalar( $value ) ? $value : 0 );
	}

	/**
	 * Sanitize a human-readable date into Y-m-d, or an empty string.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_date( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		$timestamp = strtotime( $value );

		return false === $timestamp ? '' : gmdate( 'Y-m-d', $timestamp );
	}

	/**
	 * Sanitize lyric/content HTML with a conservative allow-list.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_html( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		return wp_kses_post( (string) $value );
	}

	/**
	 * Sanitize plain text.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_text( $value ): string {
		return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	}

	/**
	 * Sanitize a value against an allow-list.
	 *
	 * @param mixed $value Raw value.
	 * @param array $allowed Allowed values.
	 * @return string
	 */
	public static function sanitize_enum( $value, array $allowed ): string {
		$value = is_scalar( $value ) ? sanitize_key( (string) $value ) : '';

		return in_array( $value, $allowed, true ) ? $value : '';
	}
}
