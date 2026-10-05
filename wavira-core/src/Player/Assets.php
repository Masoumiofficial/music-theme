<?php
/**
 * Registers the front-end player bundle and its server-side settings.
 *
 * The engine is an asset of the *plugin*: playback is product behaviour, not
 * presentation, so a site keeps its player when the theme changes (ADR 0002).
 * The theme only asks for the registered handle when it renders a mount point —
 * the bundle is never loaded on a page that has no player (ADR 0005 §8).
 *
 * @package Wavira\Core\Player
 */

namespace Wavira\Core\Player;

use Wavira\Core\Contracts\Registrable;
use Wavira\Core\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class Assets
 */
final class Assets implements Registrable {

	/**
	 * Script handle the theme (and the block editor) enqueue.
	 *
	 * @var string
	 */
	public const HANDLE = 'wavira-player';

	/**
	 * Relative path of the built bundle inside the plugin.
	 *
	 * @var string
	 */
	public const BUNDLE = 'assets/dist/core.js';

	/**
	 * Register the hooks that publish the player bundle.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_script' ), 5 );
	}

	/**
	 * Register the bundle (never force-enqueue it).
	 *
	 * An un-built checkout keeps working: with no dist file the handle stays
	 * unregistered and the theme's mount point degrades to its `<audio>`
	 * fallback instead of requesting a 404.
	 *
	 * @return void
	 */
	public function register_script(): void {
		$path = WAVIRA_CORE_DIR . self::BUNDLE;

		if ( ! file_exists( $path ) ) {
			return;
		}

		wp_register_script(
			self::HANDLE,
			WAVIRA_CORE_URI . self::BUNDLE,
			array(),
			(string) filemtime( $path ),
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);

		wp_add_inline_script(
			self::HANDLE,
			'window.waviraPlayerSettings = ' . wp_json_encode( self::settings() ) . ';',
			'before'
		);
	}

	/**
	 * Everything the engine needs from PHP: routes, defaults and strings.
	 *
	 * The engine must never build a URL of its own, so the server hands it route
	 * *templates* and the engine only substitutes an ID. Strings live here so the
	 * player speaks the site's language without a second translation source.
	 *
	 * @return array<string, mixed>
	 */
	public static function settings(): array {
		$settings = array(
			'version'  => defined( 'WAVIRA_CORE_VERSION' ) ? WAVIRA_CORE_VERSION : '0.5.0',
			'routes'   => array(
				'track' => rest_url( 'wavira/v1/player/tracks/' ) . '%d',
				'queue' => rest_url( 'wavira/v1/player/queue' ),
			),
			'defaults' => array(
				'volume'         => self::default_volume(),
				'repeat'         => 'off',
				'shuffle'        => false,
				'seekStep'       => 5,
				'volumeStep'     => 0.05,
				'context'        => 'tracks',
				'limit'          => 0,
				'advance'        => true,
				'autoplayOnLoad' => (bool) Settings::get( 'player_autoplay', false ),
				'sticky'         => (bool) Settings::get( 'player_sticky', true ),
			),
			'storage'  => array(
				'prefix' => 'wavira.player.',
			),
			'strings'  => self::strings(),
		);

		/**
		 * Filters the player settings passed to the front-end engine.
		 *
		 * @since 0.5.0
		 * @param array<string, mixed> $settings Player settings.
		 */
		return (array) apply_filters( 'wavira_player_settings', $settings );
	}

	/**
	 * Default volume as a 0–1 float.
	 *
	 * @return float
	 */
	private static function default_volume(): float {
		$percent = (int) Settings::get( 'player_default_volume', 100 );
		$percent = max( 0, min( 100, $percent ) );

		return round( $percent / 100, 2 );
	}

	/**
	 * Translatable strings used by the engine and its controls.
	 *
	 * @return array<string, string>
	 */
	private static function strings(): array {
		return array(
			'player'       => __( 'Audio player', 'wavira-core' ),
			'play'         => __( 'Play', 'wavira-core' ),
			'pause'        => __( 'Pause', 'wavira-core' ),
			'next'         => __( 'Next track', 'wavira-core' ),
			'previous'     => __( 'Previous track', 'wavira-core' ),
			'seek'         => __( 'Seek', 'wavira-core' ),
			'volume'       => __( 'Volume', 'wavira-core' ),
			'mute'         => __( 'Mute', 'wavira-core' ),
			'unmute'       => __( 'Unmute', 'wavira-core' ),
			'shuffle'      => __( 'Shuffle', 'wavira-core' ),
			'repeat'       => __( 'Repeat mode', 'wavira-core' ),
			'repeatOff'    => __( 'Repeat off', 'wavira-core' ),
			'repeatAll'    => __( 'Repeat all', 'wavira-core' ),
			'repeatOne'    => __( 'Repeat one', 'wavira-core' ),
			'queue'        => __( 'Play queue', 'wavira-core' ),
			/* translators: %s: track title. */
			'remove'       => __( 'Remove from the queue: %s', 'wavira-core' ),
			'loading'      => __( 'Loading track…', 'wavira-core' ),
			'buffering'    => __( 'Buffering…', 'wavira-core' ),
			'error'        => __( 'This track could not be played.', 'wavira-core' ),
			'empty'        => __( 'There is nothing to play here.', 'wavira-core' ),
			/* translators: %s: track title. */
			'nowPlaying'   => __( 'Now playing: %s', 'wavira-core' ),
			/* translators: 1: position in the queue, 2: queue length. */
			'ofTotal'      => __( 'Track %1$d of %2$d', 'wavira-core' ),
			'openTrack'    => __( 'Open the track page', 'wavira-core' ),
			/* translators: %s: track title. */
			'removedTrack' => __( 'Removed from the queue: %s', 'wavira-core' ),
			'blocked'      => __( 'Playback needs a tap on the play button first.', 'wavira-core' ),
		);
	}
}
