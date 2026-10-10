<?php
/**
 * Runtime verification of meta sanitizers, typed readers and the settings schema.
 *
 * @package Wavira\Tests
 */

use Wavira\Core\Content\Meta;
use Wavira\Core\Content\MetaSchema;
use Wavira\Core\Content\MetaValues;
use Wavira\Core\Settings\Settings;
use Wavira\Core\Settings\SettingsSchema;

/**
 * Class Test_Meta_Settings
 */
class Test_Meta_Settings extends Wavira_Test_Case {

	/**
	 * Relation IDs are sanitised, deduplicated and freed of zeros.
	 *
	 * @return void
	 */
	public function test_id_list_sanitizer() {
		$this->assertSame( array( 5, 9 ), Meta::sanitize_ids( array( '5', 0, '9', 5 ) ) );
		$this->assertSame( array( 3, 4 ), Meta::sanitize_ids( '3, 4' ) );
		$this->assertSame( array(), Meta::sanitize_ids( 'not-a-list' ) );
		$this->assertSame( 12, Meta::sanitize_id( '12 things' ) );
		$this->assertSame( 0, Meta::sanitize_id( array() ) );
	}

	/**
	 * URLs are stored clean and protocols outside the allow list are dropped.
	 *
	 * @return void
	 */
	public function test_url_and_enum_sanitizers() {
		$this->assertSame( 'https://example.com/a.mp3', Meta::sanitize_url( 'https://example.com/a.mp3' ) );
		$this->assertSame( '', Meta::sanitize_url( 'javascript:alert(1)' ) );
		$this->assertSame( 'album', Meta::sanitize_enum( 'album', array( 'album', 'single' ) ) );
		$this->assertSame( '', Meta::sanitize_enum( 'mixtape', array( 'album', 'single' ) ), 'unknown values are dropped, never silently coerced' );
	}

	/**
	 * Descriptions keep a small, safe HTML subset; everything else is stripped.
	 *
	 * @return void
	 */
	public function test_html_sanitizer_is_restricted() {
		$clean = Meta::sanitize_html( '<p>Hello</p><script>alert(1)</script><strong>World</strong>' );

		$this->assertStringContainsString( 'Hello', $clean );
		$this->assertStringContainsString( '<strong>World</strong>', $clean );
		$this->assertStringNotContainsString( '<script', $clean );
	}

	/**
	 * Typed readers return the documented types.
	 *
	 * @return void
	 */
	public function test_meta_values_are_typed() {
		$track_id = self::factory()->post->create(
			array(
				'post_type'   => 'wavira_track',
				'post_status' => 'publish',
				'post_title'  => 'Typed track',
			)
		);

		update_post_meta( $track_id, MetaSchema::DURATION, '245' );
		update_post_meta( $track_id, MetaSchema::EXPLICIT, '1' );
		update_post_meta( $track_id, MetaSchema::AUDIO_128, 'https://example.com/128.mp3' );

		$this->assertSame( 245, MetaValues::int( $track_id, MetaSchema::DURATION ) );
		$this->assertTrue( MetaValues::bool( $track_id, MetaSchema::EXPLICIT ) );
		$this->assertSame( 'https://example.com/128.mp3', MetaValues::url( $track_id, MetaSchema::AUDIO_128 ) );
		$this->assertSame( '4:05', MetaValues::duration_label( $track_id ) );
	}

	/**
	 * Tracklist order survives storage (ADR 0012).
	 *
	 * @return void
	 */
	public function test_tracklist_keeps_its_order() {
		$album_id = self::factory()->post->create(
			array(
				'post_type'   => 'wavira_album',
				'post_status' => 'publish',
			)
		);

		$tracks = array();

		foreach ( array( 'One', 'Two', 'Three' ) as $title ) {
			$tracks[] = self::factory()->post->create(
				array(
					'post_type'   => 'wavira_track',
					'post_status' => 'publish',
					'post_title'  => $title,
				)
			);
		}

		// Stored out of order and with an ID that is not a track: the reader keeps
		// list order and drops foreign IDs (relations are typed, ADR 0012).
		update_post_meta( $album_id, MetaSchema::TRACKLIST, array( $tracks[2], $tracks[0], '999999', $tracks[1] ) );

		$this->assertSame( array( $tracks[2], $tracks[0], $tracks[1] ), MetaValues::tracklist( $album_id ) );
	}

	/**
	 * Settings defaults exist for every documented key.
	 *
	 * @return void
	 */
	public function test_settings_defaults() {
		$this->assertSame( 20, Settings::get( 'tracks_per_page' ) );
		$this->assertSame( 8, Settings::get( 'related_limit' ) );
		$this->assertTrue( Settings::get( 'downloads_enabled' ) );
		$this->assertFalse( Settings::get( 'downloads_require_login' ) );
		$this->assertNull( Settings::get( 'missing_key' ) );
		$this->assertSame( 'fallback', Settings::get( 'missing_key', 'fallback' ) );
	}

	/**
	 * Unknown keys are dropped and integers are clamped to their bounds.
	 *
	 * @return void
	 */
	public function test_settings_sanitizer_bounds_and_unknown_keys() {
		$clean = Settings::update(
			array(
				'tracks_per_page'       => 5000,
				'related_limit'         => 1,
				'player_default_volume' => -20,
				'bogus_key'             => 'should be dropped',
			)
		);

		$this->assertSame( 60, $clean['tracks_per_page'] );
		$this->assertSame( 3, $clean['related_limit'] );
		$this->assertSame( 0, $clean['player_default_volume'] );
		$this->assertArrayNotHasKey( 'bogus_key', $clean );

		// The sanitized array is what is persisted, not the raw input.
		$stored = get_option( SettingsSchema::OPTION );
		$this->assertArrayNotHasKey( 'bogus_key', $stored );
	}

	/**
	 * Ads HTML keeps a safe subset only (it is printed on the front end).
	 *
	 * @return void
	 */
	public function test_ads_html_is_kses_filtered() {
		$clean = Settings::update( array( 'ads_html' => '<a href="https://example.com" onclick="evil()">buy</a><iframe src="x"></iframe>' ) );

		$this->assertStringContainsString( '<a href="https://example.com">buy</a>', $clean['ads_html'] );
		$this->assertStringNotContainsString( 'onclick', $clean['ads_html'] );
		$this->assertStringNotContainsString( '<iframe', $clean['ads_html'] );
	}
}
