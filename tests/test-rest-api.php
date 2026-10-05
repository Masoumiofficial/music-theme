<?php
/**
 * Runtime verification of the `wavira/v1` product API.
 *
 * @package Wavira\Tests
 */

use Wavira\Core\Content\MetaSchema;

/**
 * Class Test_Rest_Api
 */
class Test_Rest_Api extends Wavira_Test_Case {

	/**
	 * A published track with audio, a genre and an artist.
	 *
	 * @return array{track: int, artist: int, album: int, genre: int}
	 */
	private function seed_track(): array {
		$artist_id = self::factory()->post->create(
			array(
				'post_type'   => 'wavira_artist',
				'post_status' => 'publish',
				'post_title'  => 'Test Artist',
			)
		);

		$album_id = self::factory()->post->create(
			array(
				'post_type'   => 'wavira_album',
				'post_status' => 'publish',
				'post_title'  => 'Test Album',
			)
		);

		$track_id = self::factory()->post->create(
			array(
				'post_type'   => 'wavira_track',
				'post_status' => 'publish',
				'post_title'  => 'Midnight Test Track',
			)
		);

		update_post_meta( $track_id, MetaSchema::ARTIST, $artist_id );
		update_post_meta( $track_id, MetaSchema::ALBUM, $album_id );
		update_post_meta( $track_id, MetaSchema::AUDIO_128, 'https://example.com/audio-128.mp3' );
		update_post_meta( $track_id, MetaSchema::DURATION, 245 );

		$term = wp_insert_term( 'Ambient Test', 'wavira_genre' );
		wp_set_post_terms( $track_id, array( (int) $term['term_id'] ), 'wavira_genre' );

		return array(
			'track'  => $track_id,
			'artist' => $artist_id,
			'album'  => $album_id,
			'genre'  => (int) $term['term_id'],
		);
	}

	/**
	 * The collection route answers with pagination headers.
	 *
	 * @return void
	 */
	public function test_collection_route_and_pagination_headers() {
		$ids = $this->seed_track();

		$response = $this->dispatch( '/wavira/v1/tracks' );

		$this->assertSame( 200, $response->get_status() );

		$headers = $response->get_headers();

		$this->assertSame( '1', $headers['X-WP-Total'] );
		$this->assertSame( '1', $headers['X-WP-TotalPages'] );

		$data = $response->get_data();

		$this->assertCount( 1, $data );
		$this->assertSame( $ids['track'], $data[0]['id'] );
		$this->assertSame( 'wavira_track', $data[0]['type'] );
	}

	/**
	 * The single-item payload carries the documented sections.
	 *
	 * @return void
	 */
	public function test_item_payload_shape() {
		$ids = $this->seed_track();

		$response = $this->dispatch( '/wavira/v1/tracks/' . $ids['track'] );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );

		foreach ( array( 'id', 'type', 'slug', 'link', 'title', 'cover', 'meta', 'relations', 'genres', 'player', 'downloads' ) as $key ) {
			$this->assertArrayHasKey( $key, $data, "item payload key: {$key}" );
		}

		$this->assertSame( 'https://example.com/audio-128.mp3', $data['player']['audio_128'] );
		$this->assertSame( 245, $data['player']['duration'] );
		$this->assertSame( 245, $data['meta'][ MetaSchema::DURATION ] );
		$this->assertSame( $ids['artist'], $data['relations']['artist']['id'] );
		$this->assertSame( $ids['album'], $data['relations']['album']['id'] );
		$this->assertSame( 'Ambient Test', $data['genres'][0]['name'] );
		$this->assertSame( 128, $data['downloads'][0]['quality'] );
	}

	/**
	 * Unknown IDs produce a 404 with a stable error code.
	 *
	 * @return void
	 */
	public function test_unknown_id_returns_404() {
		$response = $this->dispatch( '/wavira/v1/tracks/123456' );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'wavira_not_found', $response->as_error()->get_error_code() );
	}

	/**
	 * A draft track is never exposed by the product API.
	 *
	 * @return void
	 */
	public function test_draft_items_are_not_public() {
		$track_id = self::factory()->post->create(
			array(
				'post_type'   => 'wavira_track',
				'post_status' => 'draft',
				'post_title'  => 'Unreleased',
			)
		);

		$this->assertSame( 404, $this->dispatch( '/wavira/v1/tracks/' . $track_id )->get_status() );
		$this->assertSame( '0', $this->dispatch( '/wavira/v1/tracks' )->get_headers()['X-WP-Total'] );
	}

	/**
	 * Search finds published music by term.
	 *
	 * @return void
	 */
	public function test_search_route() {
		$ids = $this->seed_track();

		self::factory()->post->create(
			array(
				'post_type'   => 'wavira_track',
				'post_status' => 'publish',
				'post_title'  => 'Unrelated Song',
			)
		);

		$response = $this->dispatch( '/wavira/v1/search', array( 'term' => 'Midnight' ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 1, $data );
		$this->assertSame( $ids['track'], $data[0]['id'] );
		$this->assertSame( '1', $response->get_headers()['X-WP-Total'] );
	}

	/**
	 * The search term is required and per_page is never unbounded.
	 *
	 * @return void
	 */
	public function test_search_arguments_are_bounded() {
		$this->seed_track();

		$this->assertSame( 400, $this->dispatch( '/wavira/v1/search' )->get_status() );

		$response = $this->dispatch(
			'/wavira/v1/search',
			array(
				'term'     => 'Track',
				'per_page' => 500,
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertLessThanOrEqual( 50, count( $response->get_data() ) );
	}

	/**
	 * Suggestions are grouped by public slug.
	 *
	 * @return void
	 */
	public function test_suggestions_route() {
		$this->seed_track();

		$response = $this->dispatch( '/wavira/v1/search/suggest', array( 'term' => 'Test Artist' ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'artists', $data );
		$this->assertArrayHasKey( 'tracks', $data );
		$this->assertSame( 'Test Artist', $data['artists'][0]['title'] );
	}

	/**
	 * Related items are exposed for a track.
	 *
	 * @return void
	 */
	public function test_related_route() {
		$ids = $this->seed_track();

		$sibling_id = self::factory()->post->create(
			array(
				'post_type'   => 'wavira_track',
				'post_status' => 'publish',
				'post_title'  => 'Sibling Track',
			)
		);

		wp_set_post_terms( $sibling_id, array( $ids['genre'] ), 'wavira_genre' );

		$response = $this->dispatch( '/wavira/v1/tracks/' . $ids['track'] . '/related', array( 'limit' => 5 ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );

		$related_ids = wp_list_pluck( $data, 'id' );

		$this->assertContains( $sibling_id, $related_ids );
		$this->assertNotContains( $ids['track'], $related_ids, 'an item is never related to itself' );
	}

	/**
	 * Genre routes answer with the term payload.
	 *
	 * @return void
	 */
	public function test_genres_route() {
		$ids = $this->seed_track();

		$response = $this->dispatch( '/wavira/v1/genres' );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertNotEmpty( $data );
		$this->assertSame( $ids['genre'], $data[0]['id'] );

		$single = $this->dispatch( '/wavira/v1/genres/' . $ids['genre'] );

		$this->assertSame( 200, $single->get_status() );
		$this->assertSame( 'Ambient Test', $single->get_data()['name'] );
	}
}
