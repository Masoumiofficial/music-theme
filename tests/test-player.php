<?php
/**
 * Runtime verification of the player data layer (phase 0.5.0).
 *
 * The engine itself is unit tested without WordPress (`tests/js/player.test.mjs`);
 * this file covers everything the engine receives: the playback payload, the
 * queue builders, the REST routes and the settings contract of ADR 0005.
 *
 * @package Wavira\Tests
 */

use Wavira\Core\Content\MetaSchema;
use Wavira\Core\Player\Assets;
use Wavira\Core\Player\Payload;
use Wavira\Core\Player\Queue;
use Wavira\Core\Rest\PlayerController;
use Wavira\Core\Settings\Settings;

/**
 * Class Test_Player
 */
class Test_Player extends Wavira_Test_Case {

	/**
	 * One published artist, album, genre and track with both audio qualities.
	 *
	 * @param array<string, mixed> $track_meta Extra meta for the track.
	 * @return array{artist: int, album: int, track: int, genre: int}
	 */
	private function seed_track( array $track_meta = array() ): array {
		$artist_id = self::factory()->post->create(
			array(
				'post_type'   => 'wavira_artist',
				'post_status' => 'publish',
				'post_title'  => 'Player Test Artist',
			)
		);

		$album_id = self::factory()->post->create(
			array(
				'post_type'   => 'wavira_album',
				'post_status' => 'publish',
				'post_title'  => 'Player Test Album',
			)
		);

		$track_id = self::factory()->post->create(
			array(
				'post_type'   => 'wavira_track',
				'post_status' => 'publish',
				'post_title'  => 'Player Test Track',
			)
		);

		update_post_meta( $track_id, MetaSchema::ARTIST, $artist_id );
		update_post_meta( $track_id, MetaSchema::ALBUM, $album_id );
		update_post_meta( $track_id, MetaSchema::AUDIO_128, 'https://example.com/audio-128.mp3' );
		update_post_meta( $track_id, MetaSchema::AUDIO_320, 'https://example.com/audio-320.mp3' );
		update_post_meta( $track_id, MetaSchema::DURATION, 245 );

		foreach ( $track_meta as $key => $value ) {
			update_post_meta( $track_id, $key, $value );
		}

		$term = wp_insert_term( 'Player Test Genre', 'wavira_genre' );
		wp_set_post_terms( $track_id, array( (int) $term['term_id'] ), 'wavira_genre' );

		return array(
			'artist' => $artist_id,
			'album'  => $album_id,
			'track'  => $track_id,
			'genre'  => (int) $term['term_id'],
		);
	}

	/**
	 * A published track without any audio source.
	 *
	 * @return int Track post ID.
	 */
	private function seed_silent_track(): int {
		return self::factory()->post->create(
			array(
				'post_type'   => 'wavira_track',
				'post_status' => 'publish',
				'post_title'  => 'Track Without Audio',
			)
		);
	}

	/**
	 * The payload carries everything the engine renders.
	 *
	 * @return void
	 */
	public function test_playback_payload_shape() {
		$ids = $this->seed_track(
			array(
				MetaSchema::EXPLICIT => true,
				MetaSchema::LYRICS   => '<p>First line</p>',
				MetaSchema::ISRC     => 'IRAAA0000001',
			)
		);

		$payload = Payload::for_track( $ids['track'] );

		$this->assertSame( $ids['track'], $payload['id'] );
		$this->assertSame( 'wavira_track', $payload['type'] );
		$this->assertSame( 'Player Test Track', $payload['title'] );
		$this->assertSame( 245, $payload['duration'] );
		$this->assertSame( '4:05', $payload['duration_label'] );
		$this->assertTrue( $payload['explicit'] );
		$this->assertTrue( $payload['has_lyrics'] );
		$this->assertStringContainsString( 'player-test-track', $payload['permalink'] );

		// Best quality first, and the server states which one it prefers.
		$this->assertSame(
			array( 320, 128 ),
			array_keys( $payload['sources'] )
		);
		$this->assertSame( 320, $payload['preferred'] );

		$this->assertSame( 'Player Test Artist', $payload['artist']['name'] );
		$this->assertSame( $ids['artist'], $payload['artist']['id'] );
		$this->assertSame( 'Player Test Album', $payload['album']['title'] );
		$this->assertSame( $ids['album'], $payload['album']['id'] );
		$this->assertSame( 'player-test-genre', $payload['genres'][0]['slug'] );

		// Media Session text comes from PHP so lock-screen and page agree.
		$this->assertSame( 'Player Test Track', $payload['media_session']['title'] );
		$this->assertSame( 'Player Test Artist', $payload['media_session']['artist'] );
		$this->assertSame( 'Player Test Album', $payload['media_session']['album'] );
		$this->assertIsArray( $payload['media_session']['artwork'] );
	}

	/**
	 * Artwork is included when the post has one, with responsive image data.
	 *
	 * @return void
	 */
	public function test_playback_payload_includes_artwork() {
		$ids = $this->seed_track();

		$this->assertSame( array(), Payload::for_track( $ids['track'] )['cover'] );

		$attachment_id = self::factory()->attachment->create(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Artwork',
				'post_status'    => 'inherit',
			)
		);

		set_post_thumbnail( $ids['track'], $attachment_id );
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', 'Cover alt text' );

		$cover = Payload::for_track( $ids['track'] )['cover'];

		$this->assertSame( $attachment_id, $cover['id'] );
		$this->assertNotSame( '', $cover['url'] );
		$this->assertSame( 'Cover alt text', $cover['alt'] );
		$this->assertIsString( $cover['srcset'] );
		$this->assertIsString( $cover['sizes'] );
	}

	/**
	 * Only published tracks with a source are playable.
	 *
	 * @return void
	 */
	public function test_playback_payload_is_empty_for_unplayable_items() {
		$ids   = $this->seed_track();
		$draft = self::factory()->post->create(
			array(
				'post_type'   => 'wavira_track',
				'post_status' => 'draft',
			)
		);

		$this->assertSame( array(), Payload::for_track( $ids['album'] ), 'albums are not playable' );
		$this->assertSame( array(), Payload::for_track( $draft ), 'drafts are not playable' );
		$this->assertSame( array(), Payload::for_track( 999999 ), 'unknown IDs are not playable' );
	}

	/**
	 * The preferred quality follows what the track actually has.
	 *
	 * @return void
	 */
	public function test_preferred_quality_falls_back_correctly() {
		$ids = $this->seed_track();
		update_post_meta( $ids['track'], MetaSchema::AUDIO_320, '' );

		$this->assertSame( 128, Payload::for_track( $ids['track'] )['preferred'] );

		delete_post_meta( $ids['track'], MetaSchema::AUDIO_128 );
		update_post_meta( $ids['track'], MetaSchema::AUDIO_EXTERNAL, 'https://example.com/external.mp3' );

		$external = Payload::for_track( $ids['track'] );

		$this->assertSame( array( 'external' => 'https://example.com/external.mp3' ), $external['sources'] );
		$this->assertSame( 0, $external['preferred'], 'an external source has no local quality' );
	}

	/**
	 * A site can extend the payload without patching the engine.
	 *
	 * @return void
	 */
	public function test_payload_filter_can_extend_a_track() {
		$ids = $this->seed_track();

		$add_source = static function ( $payload ) {
			$payload['sources']['hls'] = 'https://example.com/stream.m3u8';

			return $payload;
		};

		add_filter( 'wavira_track_playback_payload', $add_source );
		$payload = Payload::for_track( $ids['track'] );
		remove_filter( 'wavira_track_playback_payload', $add_source );

		$this->assertSame( 'https://example.com/stream.m3u8', $payload['sources']['hls'] );
		$this->assertArrayNotHasKey( 'hls', Payload::for_track( $ids['track'] )['sources'] );
	}

	/**
	 * Queues are built per context and keep the documented order.
	 *
	 * @return void
	 */
	public function test_queue_contexts() {
		$ids = $this->seed_track();

		$first  = self::factory()->post->create(
			array(
				'post_type'   => 'wavira_track',
				'post_status' => 'publish',
				'post_title'  => 'First Track',
			)
		);
		$second = self::factory()->post->create(
			array(
				'post_type'   => 'wavira_track',
				'post_status' => 'publish',
				'post_title'  => 'Second Track',
			)
		);

		foreach ( array( $first, $second ) as $track_id ) {
			update_post_meta( $track_id, MetaSchema::AUDIO_128, 'https://example.com/track.mp3' );
			update_post_meta( $track_id, MetaSchema::ARTIST, $ids['artist'] );
			update_post_meta( $track_id, MetaSchema::ALBUM, $ids['album'] );
			wp_set_post_terms( $track_id, array( $ids['genre'] ), 'wavira_genre' );
		}

		// The album's own tracklist is authoritative (ADR 0012).
		update_post_meta( $ids['album'], MetaSchema::TRACKLIST, array( $second, $ids['track'], $first ) );

		$this->assertSame(
			array( $second, $ids['track'], $first ),
			Queue::ids( 'album', array( 'id' => $ids['album'] ) ),
			'the album tracklist decides the order'
		);

		$artist_queue = Queue::ids( 'artist', array( 'id' => $ids['artist'] ) );

		$this->assertCount( 3, $artist_queue );
		$this->assertContains( $ids['track'], $artist_queue );

		$genre_queue = Queue::ids( 'genre', array( 'slug' => 'player-test-genre' ) );

		$this->assertCount( 3, $genre_queue );

		$this->assertCount( 3, Queue::ids( 'tracks', array() ) );
		$this->assertSame( array(), Queue::ids( 'nonsense', array() ), 'unknown contexts are empty, never a full catalogue' );

		$related = Queue::ids( 'related', array( 'id' => $ids['track'], 'limit' => 2 ) );

		$this->assertLessThanOrEqual( 2, count( $related ), 'a related queue honours its limit' );
	}

	/**
	 * Queues are bounded and never contain unplayable tracks.
	 *
	 * @return void
	 */
	public function test_queue_is_bounded_and_playable_only() {
		$ids = $this->seed_track();

		for ( $i = 0; $i < 4; $i++ ) {
			$track_id = self::factory()->post->create(
				array(
					'post_type'   => 'wavira_track',
					'post_status' => 'publish',
					'post_title'  => 'Playable ' . $i,
				)
			);

			update_post_meta( $track_id, MetaSchema::AUDIO_320, 'https://example.com/track-' . $i . '.mp3' );
		}

		$this->seed_silent_track();

		$items = Queue::items( 'tracks', array( 'limit' => 3 ) );

		$this->assertCount( 3, $items, 'the limit is honoured' );

		foreach ( $items as $item ) {
			$this->assertNotEmpty( $item['sources'], 'a queue never contains a track that cannot play' );
		}

		$this->assertLessThanOrEqual( Queue::MAX_ITEMS, count( Queue::items( 'tracks', array( 'limit' => 1000 ) ) ) );
		$this->assertGreaterThan( 0, count( Queue::items( 'tracks', array( 'limit' => 1000 ) ) ) );
	}

	/**
	 * A site can replace the items of a queue as one unit.
	 *
	 * @return void
	 */
	public function test_queue_filter_can_replace_items() {
		$ids = $this->seed_track();

		$filter = static function () use ( $ids ) {
			return array( Payload::for_track( $ids['track'] ) );
		};

		add_filter( 'wavira_player_queue_items', $filter );
		$items = Queue::items( 'tracks', array() );
		remove_filter( 'wavira_player_queue_items', $filter );

		$this->assertCount( 1, $items );
		$this->assertSame( $ids['track'], $items[0]['id'] );
	}

	/**
	 * The single-track route serves the payload with a short public cache.
	 *
	 * @return void
	 */
	public function test_player_track_route() {
		$ids      = $this->seed_track();
		$response = $this->dispatch( '/wavira/v1/player/tracks/' . $ids['track'] );
		$data     = $response->get_data();
		$headers  = $response->get_headers();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $ids['track'], $data['id'] );
		$this->assertSame( 'https://example.com/audio-320.mp3', $data['sources'][320] );
		$this->assertSame( 320, $data['preferred'] );
		$this->assertArrayHasKey( 'Cache-Control', $headers );
		$this->assertStringContainsString( 'max-age=' . PlayerController::CACHE_TTL, $headers['Cache-Control'] );
	}

	/**
	 * A published track without audio is served with empty sources, and only a
	 * missing or unplayable *entity* answers 404.
	 *
	 * @return void
	 */
	public function test_player_track_route_reports_missing_sources_honestly() {
		$silent   = $this->seed_silent_track();
		$response = $this->dispatch( '/wavira/v1/player/tracks/' . $silent );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'the track exists, so the route answers for it' );
		$this->assertSame( array(), $data['sources'], 'and says honestly that there is nothing to play' );
		$this->assertSame( 0, $data['preferred'] );

		$missing = $this->dispatch( '/wavira/v1/player/tracks/999999' );

		$this->assertSame( 404, $missing->get_status() );
		$this->assertSame( 'wavira_not_found', $missing->as_error()->get_error_code() );

		$ids  = $this->seed_track();
		$draft = self::factory()->post->create(
			array(
				'post_type'   => 'wavira_track',
				'post_status' => 'draft',
			)
		);

		$this->assertSame( 404, $this->dispatch( '/wavira/v1/player/tracks/' . $draft )->get_status() );
		$this->assertSame( 404, $this->dispatch( '/wavira/v1/player/tracks/' . $ids['album'] )->get_status(), 'other post types never answer on a track route' );
	}

	/**
	 * The queue route serves playable items and states its context.
	 *
	 * @return void
	 */
	public function test_player_queue_route() {
		$ids = $this->seed_track();

		$response = $this->dispatch(
			'/wavira/v1/player/queue',
			array(
				'context' => 'album',
				'id'      => $ids['album'],
			)
		);

		$data = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'album', $data['context'] );
		$this->assertGreaterThanOrEqual( 1, $data['count'] );
		$this->assertSame( $ids['track'], $data['items'][0]['id'] );
		$this->assertArrayHasKey( 'Cache-Control', $response->get_headers() );
	}

	/**
	 * A queue request without its source is rejected, not guessed.
	 *
	 * @return void
	 */
	public function test_player_queue_route_requires_a_source() {
		$no_id = $this->dispatch( '/wavira/v1/player/queue', array( 'context' => 'album' ) );

		$this->assertSame( 400, $no_id->get_status() );
		$this->assertSame( 'wavira_missing_source', $no_id->as_error()->get_error_code() );

		$no_slug = $this->dispatch( '/wavira/v1/player/queue', array( 'context' => 'genre' ) );

		$this->assertSame( 400, $no_slug->get_status() );

		$bad_context = $this->dispatch( '/wavira/v1/player/queue', array( 'context' => 'playlist' ) );

		$this->assertSame( 400, $bad_context->get_status() );
		$this->assertSame( 'rest_invalid_param', $bad_context->as_error()->get_error_code() );
	}

	/**
	 * The settings payload is the engine's entire configuration surface.
	 *
	 * @return void
	 */
	public function test_engine_settings_contract() {
		$settings = Assets::settings();

		$this->assertStringEndsWith( '%d', $settings['routes']['track'], 'the engine substitutes an ID, it does not build routes' );
		$this->assertStringContainsString( 'player/queue', $settings['routes']['queue'] );
		$this->assertSame( 1.0, $settings['defaults']['volume'] );
		$this->assertSame( 'off', $settings['defaults']['repeat'] );
		$this->assertFalse( $settings['defaults']['shuffle'] );
		$this->assertTrue( $settings['defaults']['advance'], 'a queue advances to the next track by default' );
		$this->assertFalse( $settings['defaults']['autoplayOnLoad'], 'page-load autoplay is opt-in' );
		$this->assertSame( 'wavira.player.', $settings['storage']['prefix'] );

		foreach ( array( 'player', 'play', 'pause', 'next', 'previous', 'seek', 'volume', 'mute', 'shuffle', 'repeatOne', 'queue', 'remove', 'error', 'ofTotal' ) as $key ) {
			$this->assertArrayHasKey( $key, $settings['strings'], "the engine needs the {$key} string" );
			$this->assertNotSame( '', $settings['strings'][ $key ] );
		}

		$filter = static function ( $value ) {
			$value['routes']['track'] = 'https://cdn.example.com/tracks/%d';

			return $value;
		};

		add_filter( 'wavira_player_settings', $filter );
		$filtered = Assets::settings();
		remove_filter( 'wavira_player_settings', $filter );

		$this->assertSame( 'https://cdn.example.com/tracks/%d', $filtered['routes']['track'] );
	}

	/**
	 * Player defaults follow the site settings.
	 *
	 * @return void
	 */
	public function test_engine_settings_follow_site_settings() {
		Settings::update(
			array(
				'player_default_volume' => 40,
				'player_autoplay'       => true,
				'player_sticky'         => false,
			)
		);

		$defaults = Assets::settings()['defaults'];

		$this->assertSame( 0.4, $defaults['volume'] );
		$this->assertTrue( $defaults['autoplayOnLoad'] );
		$this->assertFalse( $defaults['sticky'] );
	}

	/**
	 * The theme's legal surface exposes playback and the bundle handle.
	 *
	 * @return void
	 */
	public function test_public_api_exposes_playback_and_enqueue() {
		$this->assertTrue( function_exists( 'wavira_core_track_playback' ) );
		$this->assertTrue( function_exists( 'wavira_core_enqueue_player' ) );

		$ids      = $this->seed_track();
		$playback = wavira_core_track_playback( $ids['track'] );

		$this->assertSame( $ids['track'], $playback['id'] );
		$this->assertSame( array(), wavira_core_track_playback( 999999 ), 'unplayable items degrade to an empty array' );

		$this->assertIsBool( wavira_core_enqueue_player() );

		if ( ! file_exists( WAVIRA_CORE_DIR . Assets::BUNDLE ) ) {
			$this->assertFalse( wavira_core_enqueue_player(), 'without a built bundle the theme falls back to native audio' );

			return;
		}

		do_action( 'wp_enqueue_scripts' );

		$this->assertTrue( wp_script_is( Assets::HANDLE, 'registered' ), 'the built bundle registers its handle' );

		$inline = wp_scripts()->get_data( Assets::HANDLE, 'before' );
		$joined = is_array( $inline ) ? implode( '', $inline ) : (string) $inline;

		$this->assertStringContainsString( 'waviraPlayerSettings', $joined );
		$this->assertTrue( wavira_core_enqueue_player() );
		$this->assertTrue( wp_script_is( Assets::HANDLE, 'enqueued' ) );
	}

	/**
	 * The audio meta keys are mapped in exactly one place.
	 *
	 * @return void
	 */
	public function test_audio_key_mapping() {
		$this->assertSame( MetaSchema::AUDIO_128, MetaSchema::audio_key( 128 ) );
		$this->assertSame( MetaSchema::AUDIO_320, MetaSchema::audio_key( 320 ) );
		$this->assertSame( '', MetaSchema::audio_key( 96 ), 'unknown qualities return no key' );
	}

	/**
	 * No player payload leaks an absolute filesystem path or a private URL.
	 *
	 * @return void
	 */
	public function test_payload_contains_no_private_paths() {
		$ids     = $this->seed_track();
		$encoded = (string) wp_json_encode( Payload::for_track( $ids['track'] ) );

		$this->assertStringNotContainsString( ABSPATH, $encoded );
		$this->assertStringNotContainsString( 'file://', $encoded );
		$this->assertStringNotContainsString( WAVIRA_CORE_DIR, $encoded );
	}
}
