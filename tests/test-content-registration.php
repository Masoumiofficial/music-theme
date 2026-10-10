<?php
/**
 * Runtime verification of the 0.3.0 data model on a real WordPress install.
 *
 * @package Wavira\Tests
 */

/**
 * Class Test_Content_Registration
 */
class Test_Content_Registration extends Wavira_Test_Case {

	/**
	 * The four music post types exist.
	 *
	 * @return void
	 */
	public function test_music_post_types_are_registered() {
		foreach ( array( 'wavira_artist', 'wavira_album', 'wavira_track', 'wavira_video' ) as $post_type ) {
			$this->assertTrue( post_type_exists( $post_type ), "{$post_type} should be registered" );
		}
	}

	/**
	 * Public slugs match the owner-approved permalink decision (ADR 0011).
	 *
	 * @return void
	 */
	public function test_post_type_slugs_match_the_approved_permalinks() {
		$expected = array(
			'wavira_artist' => 'artists',
			'wavira_album'  => 'albums',
			'wavira_track'  => 'tracks',
			'wavira_video'  => 'videos',
		);

		foreach ( $expected as $post_type => $slug ) {
			$object = get_post_type_object( $post_type );

			$this->assertNotNull( $object );
			$this->assertSame( $slug, $object->rewrite['slug'], "{$post_type} rewrite slug" );
			$this->assertFalse( $object->rewrite['with_front'], "{$post_type} must not inherit the front base" );
			$this->assertSame( $slug, $object->has_archive, "{$post_type} archive slug" );
			$this->assertTrue( $object->show_in_rest, "{$post_type} is exposed to the REST API (ADR 0003)" );
		}
	}

	/**
	 * The genre taxonomy is always available and uses the approved slug.
	 *
	 * @return void
	 */
	public function test_genre_taxonomy_is_registered() {
		$this->assertTrue( taxonomy_exists( 'wavira_genre' ) );

		$taxonomy = get_taxonomy( 'wavira_genre' );

		$this->assertSame( 'genres', $taxonomy->rewrite['slug'] );
		$this->assertTrue( $taxonomy->show_in_rest );
		$this->assertTrue( $taxonomy->hierarchical );
	}

	/**
	 * The kind taxonomy is always available, flat, and attached to tracks only.
	 *
	 * It exists so imported content keeps its nature: a remix, a noha and a
	 * podcast episode are all audio, and without this taxonomy every one of them
	 * would be indistinguishable from a song in the archive.
	 *
	 * @return void
	 */
	public function test_kind_taxonomy_is_registered_for_tracks() {
		$this->assertTrue( taxonomy_exists( 'wavira_kind' ) );

		$taxonomy = get_taxonomy( 'wavira_kind' );

		$this->assertSame( array( 'wavira_track' ), $taxonomy->object_type, 'a video and an album already are their own type' );
		$this->assertSame( 'kinds', $taxonomy->rewrite['slug'] );
		$this->assertSame( 'kinds', $taxonomy->rest_base );
		$this->assertTrue( $taxonomy->show_in_rest );
		$this->assertFalse( $taxonomy->hierarchical, 'a kind is a label, not a tree' );
		$this->assertTrue( $taxonomy->publicly_queryable, 'a kind archive is a real surface: /kinds/noha/ for a Persian site' );
	}

	/**
	 * The kind vocabulary is the product's own, and normalises source values.
	 *
	 * @return void
	 */
	public function test_kind_vocabulary_matches_the_importable_types() {
		$kinds = \Wavira\Core\Content\Taxonomies::kinds();

		foreach ( array( 'music', 'remix', 'noha', 'podcast' ) as $slug ) {
			$this->assertArrayHasKey( $slug, $kinds );
		}

		$this->assertSame( 'remix', \Wavira\Core\Content\Taxonomies::normalize_kind( 'musicss_remix' ) );
		$this->assertSame( 'noha', \Wavira\Core\Content\Taxonomies::normalize_kind( 'musicss_nohe' ) );
		$this->assertSame( 'music', \Wavira\Core\Content\Taxonomies::normalize_kind( 'musicss' ) );
		$this->assertSame( '', \Wavira\Core\Content\Taxonomies::normalize_kind( 'something_else' ), 'an unknown value invents no term' );
	}

	/**
	 * Every kind term exists after the seeder ran.
	 *
	 * @return void
	 */
	public function test_kind_terms_are_created_once() {
		\Wavira\Core\Content\Taxonomies::ensure_kind_terms();
		\Wavira\Core\Content\Taxonomies::ensure_kind_terms();

		$terms = get_terms(
			array(
				'taxonomy'   => \Wavira\Core\Content\Taxonomies::KIND,
				'hide_empty' => false,
				'fields'     => 'slugs',
			)
		);

		$this->assertIsArray( $terms );
		$this->assertSame( array(), array_diff( array_keys( \Wavira\Core\Content\Taxonomies::kinds() ), $terms ) );
		$this->assertSame( count( array_unique( $terms ) ), count( $terms ), 'a second call creates no duplicate' );
	}

	/**
	 * Optional taxonomies follow their settings switch.
	 *
	 * @return void
	 */
	public function test_optional_taxonomies_follow_settings() {
		$taxonomies = new \Wavira\Core\Content\Taxonomies();

		$this->assertTrue( taxonomy_exists( 'wavira_mood' ), 'moods are enabled by default' );
		$this->assertTrue( taxonomy_exists( 'wavira_genre' ), 'genre is never optional' );

		\Wavira\Core\Settings\Settings::update( array( 'enable_mood' => false ) );
		unregister_taxonomy( 'wavira_mood' );
		$taxonomies->register_taxonomies();

		$this->assertFalse( taxonomy_exists( 'wavira_mood' ), 'the setting switch removes the taxonomy' );
		$this->assertTrue( taxonomy_exists( 'wavira_genre' ), 'genre survives with every optional taxonomy off' );

		// Leave the site as the other tests expect to find it.
		\Wavira\Core\Settings\Settings::update( array( 'enable_mood' => true ) );
		$taxonomies->register_taxonomies();

		$this->assertTrue( taxonomy_exists( 'wavira_mood' ) );
	}

	/**
	 * Every schema key is registered with a sanitizer and an auth callback.
	 *
	 * @return void
	 */
	public function test_meta_keys_are_registered_from_the_schema() {
		$schema = \Wavira\Core\Content\MetaSchema::all();

		$this->assertGreaterThanOrEqual( 40, count( $schema ), 'the documented registry size' );

		foreach ( $schema as $key => $field ) {
			foreach ( $field['entities'] as $post_type ) {
				$this->assertTrue(
					registered_meta_key_exists( 'post', $key, $post_type ),
					"{$key} should be registered for {$post_type}"
				);

				$registered = get_registered_meta_keys( 'post', $post_type )[ $key ];

				$this->assertIsCallable( $registered['sanitize_callback'] );
				$this->assertIsCallable( $registered['auth_callback'] );
				$this->assertTrue( $registered['single'] );
			}
		}
	}

	/**
	 * Download counters are plugin-written metrics, never REST-writable (ADR 0013).
	 *
	 * @return void
	 */
	public function test_download_counters_are_not_rest_writable() {
		$registered = get_registered_meta_keys( 'post', 'wavira_track' );

		foreach ( array( 'wavira_download_count', 'wavira_download_count_128', 'wavira_download_count_320' ) as $key ) {
			$this->assertArrayHasKey( $key, $registered );
			$this->assertFalse( $registered[ $key ]['show_in_rest'], "{$key} must not be writable through the API" );
		}
	}

	/**
	 * The legacy field names of the audited theme are not reused.
	 *
	 * @return void
	 */
	public function test_legacy_meta_names_are_absent() {
		$legacy = array( 'music128', 'music320', 'album128', 'album320', 'video1080', 'vip_img', 'aimg2' );
		$schema = array_keys( \Wavira\Core\Content\MetaSchema::all() );

		foreach ( $legacy as $key ) {
			$this->assertNotContains( $key, $schema, "legacy key {$key} must not be part of the new model" );
		}
	}
}
