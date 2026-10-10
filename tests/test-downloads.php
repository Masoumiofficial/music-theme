<?php
/**
 * Runtime verification of download authorization and counters (ADR 0013).
 *
 * @package Wavira\Tests
 */

use Wavira\Core\Content\MetaSchema;
use Wavira\Core\Downloads\Access;
use Wavira\Core\Downloads\Counter;
use Wavira\Core\Settings\Settings;

/**
 * Class Test_Downloads
 */
class Test_Downloads extends Wavira_Test_Case {

	/**
	 * Published track with both audio files.
	 *
	 * @param array<string, mixed> $extra Additional post arguments.
	 * @return int
	 */
	private function make_track( array $extra = array() ): int {
		$track_id = self::factory()->post->create(
			array_merge(
				array(
					'post_type'   => 'wavira_track',
					'post_status' => 'publish',
					'post_title'  => 'Downloadable',
				),
				$extra
			)
		);

		update_post_meta( $track_id, MetaSchema::AUDIO_128, 'https://example.com/track-128.mp3' );
		update_post_meta( $track_id, MetaSchema::AUDIO_320, 'https://example.com/track-320.mp3' );
		update_post_meta( $track_id, MetaSchema::FILE_SIZE_128, 1048576 );
		update_post_meta( $track_id, MetaSchema::FILE_SIZE_320, 4194304 );

		return $track_id;
	}

	/**
	 * The documented authorization chain (site setting, per-track opt-out, login).
	 *
	 * @return void
	 */
	public function test_access_rules() {
		$track_id = $this->make_track();

		$this->assertTrue( Access::can_download( $track_id ), 'published track with audio' );

		$draft_id = $this->make_track( array( 'post_status' => 'draft' ) );
		$this->assertFalse( Access::can_download( $draft_id ), 'drafts are never downloadable' );

		$silent_id = self::factory()->post->create(
			array(
				'post_type'   => 'wavira_track',
				'post_status' => 'publish',
			)
		);
		$this->assertFalse( Access::can_download( $silent_id ), 'a track without audio has nothing to download' );

		update_post_meta( $track_id, MetaSchema::DOWNLOAD_ENABLED, '0' );
		$this->assertFalse( Access::can_download( $track_id ), 'per-track opt-out wins' );
		delete_post_meta( $track_id, MetaSchema::DOWNLOAD_ENABLED );

		Settings::update( array( 'downloads_enabled' => false ) );
		$this->assertFalse( Access::can_download( $track_id ), 'global switch off' );
		Settings::update( array( 'downloads_enabled' => true ) );

		Settings::update( array( 'downloads_require_login' => true ) );
		$this->assertFalse( Access::can_download( $track_id ), 'login required, visitor logged out' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertTrue( Access::can_download( $track_id ), 'logged-in reader' );

		wp_set_current_user( 0 );
		Settings::update( array( 'downloads_require_login' => false ) );
	}

	/**
	 * The quality matrix lists available files with sizes.
	 *
	 * @return void
	 */
	public function test_matrix_lists_available_qualities() {
		$track_id = $this->make_track();
		$matrix   = Access::matrix( $track_id );

		$this->assertCount( 2, $matrix );
		$this->assertSame( 128, $matrix[0]['quality'] );
		$this->assertSame( 320, $matrix[1]['quality'] );
		$this->assertSame( '1 MB', $matrix[0]['size_label'] );
		$this->assertSame( 4194304, $matrix[1]['file_size'] );
	}

	/**
	 * Counters increment atomically and per quality.
	 *
	 * @return void
	 */
	public function test_counters_increment_and_read_back() {
		$track_id = $this->make_track();

		$this->assertSame( 0, Counter::total( $track_id ) );

		$this->assertSame( 1, Counter::increment( $track_id, 128 ) );
		$this->assertSame( 2, Counter::increment( $track_id, 128 ) );
		$this->assertSame( 3, Counter::increment( $track_id, 320 ) );

		$summary = Counter::summary( $track_id );

		$this->assertSame( 3, $summary['total'] );
		$this->assertSame( 2, $summary['by_quality'][128] );
		$this->assertSame( 1, $summary['by_quality'][320] );

		$album_id = self::factory()->post->create( array( 'post_type' => 'wavira_album' ) );
		$this->assertSame( 0, Counter::increment( $album_id, 128 ), 'only tracks carry download counters' );
	}

	/**
	 * The endpoint counts and returns the file URL as JSON on request.
	 *
	 * @return void
	 */
	public function test_download_endpoint_json_mode() {
		$track_id = $this->make_track();

		$response = $this->dispatch( '/wavira/v1/download/' . $track_id, array( 'redirect' => false ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'https://example.com/track-320.mp3', $data['url'], 'best available quality by default' );
		$this->assertSame( 320, $data['quality'] );
		$this->assertSame( 1, $data['count'] );

		$cheap = $this->dispatch(
			'/wavira/v1/download/' . $track_id,
			array(
				'quality'  => 128,
				'redirect' => false,
			)
		);

		$this->assertSame( 128, $cheap->get_data()['quality'] );
		$this->assertSame( 2, $cheap->get_data()['count'] );
		$this->assertSame( 1, Counter::for_quality( $track_id, 128 ) );
	}

	/**
	 * The default response is a redirect so PHP never proxies the file.
	 *
	 * @return void
	 */
	public function test_download_endpoint_redirects_by_default() {
		$track_id = $this->make_track();

		$response = $this->dispatch( '/wavira/v1/download/' . $track_id );

		$this->assertSame( 302, $response->get_status() );
		$this->assertSame( 'https://example.com/track-320.mp3', $response->get_headers()['Location'] );
		$this->assertSame( '1', $response->get_headers()['X-Wavira-Download-Count'] );
	}

	/**
	 * Forbidden downloads never leak a URL.
	 *
	 * @return void
	 */
	public function test_forbidden_download_is_refused() {
		$track_id = $this->make_track();

		Settings::update( array( 'downloads_enabled' => false ) );

		$response = $this->dispatch( '/wavira/v1/download/' . $track_id );

		$this->assertContains( $response->get_status(), array( 401, 403 ) );
		$this->assertSame( 0, Counter::total( $track_id ), 'refused requests are not counted' );

		Settings::update( array( 'downloads_enabled' => true ) );
	}

	/**
	 * The access filter can veto a download.
	 *
	 * @return void
	 */
	public function test_access_filter_can_veto() {
		$track_id = $this->make_track();

		add_filter( 'wavira_download_access', '__return_false' );
		$this->assertFalse( Access::can_download( $track_id ) );
		remove_filter( 'wavira_download_access', '__return_false' );

		$this->assertTrue( Access::can_download( $track_id ) );
	}

	/**
	 * Counted downloads fire an action for integrators.
	 *
	 * @return void
	 */
	public function test_counted_action_fires() {
		$track_id = $this->make_track();
		$seen     = array();

		$listener = static function ( $post_id, $quality, $by ) use ( &$seen ) {
			$seen[] = array( $post_id, $quality, $by );
		};

		add_action( 'wavira_download_counted', $listener, 10, 3 );
		Counter::increment( $track_id, 128 );
		remove_action( 'wavira_download_counted', $listener, 10 );

		$this->assertSame( array( array( $track_id, 128, 1 ) ), $seen );
	}
}
