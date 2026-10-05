<?php
/**
 * Public function API of Wavira Core.
 *
 * These functions are the **only** supported way for a theme (or a third-party
 * plugin) to read Wavira data. The theme calls a function and never a class
 * name, so the plugin can rename or move its internals without breaking a
 * template — and a site can switch themes without touching music data
 * (ARCHITECTURE.md §2, ADR 0002).
 *
 * Every function degrades to a safe default when the plugin is inactive, when
 * requirements are unmet or when a service was removed by a filter, because a
 * missing music feature must never be a fatal error on the front end.
 *
 * @package Wavira\Core
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wavira_core_is_active' ) ) {
	/**
	 * Whether the Wavira Core boot sequence completed.
	 *
	 * Uses the plugin's own action rather than a class check: `wavira_core_booted`
	 * is the documented signal that content, settings and REST are live
	 * (ARCHITECTURE.md §4).
	 *
	 * @return bool
	 */
	function wavira_core_is_active() {
		return did_action( 'wavira_core_booted' ) > 0;
	}
}

if ( ! function_exists( 'wavira_core_get_setting' ) ) {
	/**
	 * Read one Wavira setting, with the plugin's defaults and read-time filter.
	 *
	 * @param string $key      Setting key.
	 * @param mixed  $fallback Value returned for a key outside the schema.
	 * @return mixed
	 */
	function wavira_core_get_setting( $key, $fallback = null ) {
		if ( ! class_exists( '\Wavira\Core\Settings\Settings' ) ) {
			return $fallback;
		}

		return \Wavira\Core\Settings\Settings::get( (string) $key, $fallback );
	}
}

if ( ! function_exists( 'wavira_core_related_posts' ) ) {
	/**
	 * Related items for one music post, scored and cached by the plugin.
	 *
	 * @param int $post_id Source post ID.
	 * @param int $limit   Maximum number of items (0 = site setting).
	 * @return \WP_Post[] Empty array when the plugin or the service is unavailable.
	 */
	function wavira_core_related_posts( $post_id, $limit = 0 ) {
		if ( ! function_exists( 'wavira_core_is_active' ) || ! wavira_core_is_active() ) {
			return array();
		}

		if ( ! class_exists( '\Wavira\Core\Related\RelatedService' ) ) {
			return array();
		}

		return (array) \Wavira\Core\Related\RelatedService::posts( (int) $post_id, (int) $limit );
	}
}

if ( ! function_exists( 'wavira_core_track_playback' ) ) {
	/**
	 * Playback payload of one track, as consumed by the player engine.
	 *
	 * A template uses this for the no-JavaScript fallback and for the Media
	 * Session description; the engine itself asks the REST route so one page can
	 * hold any number of players (ADR 0005 §1).
	 *
	 * @param int $post_id Track post ID.
	 * @return array<string, mixed> Empty array when the item is not a playable track.
	 */
	function wavira_core_track_playback( $post_id ) {
		if ( ! function_exists( 'wavira_core_is_active' ) || ! wavira_core_is_active() ) {
			return array();
		}

		if ( ! class_exists( '\\Wavira\\Core\\Player\\Payload' ) ) {
			return array();
		}

		return (array) \Wavira\Core\Player\Payload::for_track( (int) $post_id );
	}
}

if ( ! function_exists( 'wavira_core_enqueue_player' ) ) {
	/**
	 * Ask for the player bundle on this request.
	 *
	 * The bundle belongs to Wavira Core, so the theme enqueues the registered
	 * handle instead of pointing at a plugin path. Returns false when the bundle
	 * was never built (an un-built checkout), which lets a template fall back to
	 * server-rendered audio.
	 *
	 * @return bool Whether the engine will load on this request.
	 */
	function wavira_core_enqueue_player() {
		if ( ! function_exists( 'wp_script_is' ) || ! wp_script_is( 'wavira-player', 'registered' ) ) {
			return false;
		}

		wp_enqueue_script( 'wavira-player' );

		return true;
	}
}
