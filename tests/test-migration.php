<?php
/**
 * Runtime verification of the legacy migration tool (phase 0.11.0).
 *
 * `docs/MIGRATION-BLUEPRINT.md` §7 is the acceptance list this file implements:
 * a dry run that writes nothing, counts that reconcile, slugs that survive,
 * sources and lyrics that arrive, the artist duplicate merged, and a rollback
 * that puts the site back. The fixtures are the legacy *shape* (a `post` with
 * `musics_type` and the audited meta keys) — never the legacy code, which is a
 * black box (ADR 0001).
 *
 * @package Wavira\Tests
 */

use Wavira\Core\Content\MetaSchema;
use Wavira\Core\Content\PostTypes;
use Wavira\Core\Content\Taxonomies;
use Wavira\Core\Migration\LegacySchema;
use Wavira\Core\Migration\Migrator;

/**
 * Class Test_Migration
 */
class Test_Migration extends Wavira_Test_Case {

	/**
	 * Legacy posts created by the current test, so nothing leaks into the next.
	 *
	 * @var int[]
	 */
	private $posts = array();

	/**
	 * Stand in for the legacy site: the artist taxonomy is registered by the
	 * legacy theme, which the new product does not ship, so a fixture has to
	 * declare it (a test that skipped it would silently prove nothing).
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		if ( ! taxonomy_exists( LegacySchema::ARTIST_TAX ) ) {
			register_taxonomy( LegacySchema::ARTIST_TAX, array( 'post' ), array( 'public' => false ) );
		}
	}

	/**
	 * Remove the fixtures a test created.
	 *
	 * @return void
	 */
	public function tear_down() {
		foreach ( $this->posts as $id ) {
			wp_delete_post( (int) $id, true );
		}

		$this->posts = array();

		delete_option( LegacySchema::REPORT_OPTION );
		remove_all_filters( 'wavira_core_migration_log' );

		parent::tear_down();
	}

	/**
	 * Create one legacy post.
	 *
	 * @param array<string, mixed> $meta Legacy meta values.
	 * @param string               $title Post title.
	 * @return int Post ID.
	 */
	private function legacy_post( array $meta, string $title = 'Legacy track' ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_name'   => sanitize_title( $title ),
			)
		);

		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, (string) $key, $value );
		}

		$this->posts[] = (int) $id;

		return (int) $id;
	}

	/**
	 * One realistic legacy track: an artist credit, both bitrates, lyrics with a
	 * hostile tag, the featured flag, a view counter and a slider image URL.
	 *
	 * @param string $artist Artist credit.
	 * @return int Post ID.
	 */
	private function legacy_track( string $artist = 'آرمان راد' ): int {
		return $this->legacy_post(
			array(
				LegacySchema::TYPE_META => 'mp3',
				'artist'                => $artist,
				'song'                  => 'نام دیگر',
				'music128'              => 'https://cdn.example.test/rain-128.mp3',
				'music320'              => 'https://cdn.example.test/rain-320.mp3',
				'music_text'            => '<p>متن ترانه</p><script>alert(1)</script>',
				'vip_song'              => '1',
				'plym'                  => '1',
				'views'                 => '4211',
				'vip_img'               => 'https://cdn.example.test/cover.jpg',
			),
			'راه بارانی'
		);
	}

	/**
	 * The schema contract: every field the map promises exists in MetaSchema.
	 *
	 * The document gate (`tools/check-mapping.mjs`) checks the blueprint against
	 * the schema; this checks the *code* that will run — a map that names a key
	 * no constant declares would otherwise fail on a customer's site, mid-run.
	 *
	 * @return void
	 */
	public function test_every_target_key_is_a_schema_constant() {
		$schema   = MetaSchema::all();
		$promised = array( MetaSchema::CREDIT_LABEL, MetaSchema::SUBTITLE, MetaSchema::ARTIST, MetaSchema::TRACKLIST );

		foreach ( array_keys( LegacySchema::sources() ) as $source ) {
			foreach ( array_keys( LegacySchema::kinds( $source ) ) as $kind ) {
				foreach ( LegacySchema::fields( $kind, $source ) as $map ) {
					$promised[] = $map[0];
				}
			}

			foreach ( LegacySchema::album_rows( $source )['map'] as $target ) {
				$promised[] = $target;
			}
		}

		foreach ( LegacySchema::artist_term_fields() as $target ) {
			$promised[] = $target;
		}

		foreach ( LegacySchema::album_row_fields() as $target ) {
			if ( 'title' === $target ) {
				continue;
			}

			$promised[] = $target;
		}

		foreach ( array_unique( $promised ) as $key ) {
			$this->assertArrayHasKey( $key, $schema, $key . ' is not in MetaSchema::all()' );
		}
	}

	/**
	 * M1 — a dry run writes nothing at all.
	 *
	 * @return void
	 */
	public function test_dry_run_writes_nothing() {
		$id       = $this->legacy_track();
		$before   = get_post_meta( $id );
		$migrator = new Migrator();

		$report = $migrator->dry_run();

		$this->assertTrue( $report['dry_run'] );
		$this->assertGreaterThanOrEqual( 1, (int) $report['migrated'], 'the plan names the posts it would migrate' );
		$this->assertSame( 'post', get_post_type( $id ), 'a dry run never changes a post type' );
		$this->assertSame( $before, get_post_meta( $id ), 'a dry run never writes meta' );
		$this->assertSame( array(), get_option( LegacySchema::REPORT_OPTION, array() ), 'a dry run stores no report' );
	}

	/**
	 * M3, M4, M5, M6 — one track arrives complete, with its slug.
	 *
	 * @return void
	 */
	public function test_track_is_migrated_with_its_sources_lyrics_and_slug() {
		$id   = $this->legacy_track();
		$slug = (string) get_post_field( 'post_name', $id );

		$report = ( new Migrator() )->run();

		$this->assertSame( PostTypes::TRACK, get_post_type( $id ) );
		$this->assertSame( $slug, (string) get_post_field( 'post_name', $id ), 'the slug is the SEO value and must survive' );
		$this->assertSame( 'publish', (string) get_post_field( 'post_status', $id ), 'the tool never changes publication' );

		$this->assertSame( 'https://cdn.example.test/rain-128.mp3', get_post_meta( $id, MetaSchema::AUDIO_128, true ) );
		$this->assertSame( 'https://cdn.example.test/rain-320.mp3', get_post_meta( $id, MetaSchema::AUDIO_320, true ) );
		$this->assertTrue( (bool) get_post_meta( $id, MetaSchema::FEATURED, true ), 'vip_song becomes a real boolean' );
		$this->assertTrue( (bool) get_post_meta( $id, MetaSchema::IN_INDEX_PLAYER, true ) );
		$this->assertSame( 'نام دیگر', get_post_meta( $id, MetaSchema::SUBTITLE, true ) );
		$this->assertSame( 'آرمان راد', get_post_meta( $id, MetaSchema::CREDIT_LABEL, true ) );

		$lyrics = (string) get_post_meta( $id, MetaSchema::LYRICS, true );

		$this->assertStringContainsString( 'متن ترانه', $lyrics, 'lyrics arrive' );
		$this->assertStringNotContainsString( '<script', $lyrics, 'and arrive through the KSES allow-list' );

		// The audit trail: every value is still there, exactly as it was.
		$raw = get_post_meta( $id, LegacySchema::RAW, true );

		$this->assertIsArray( $raw );
		$this->assertSame( '4211', (string) $raw['views'], 'a deferred field is copied, not reinterpreted' );
		$this->assertSame( 'https://cdn.example.test/cover.jpg', $raw['vip_img'], 'a remote slider image stays in the raw audit meta' );
		$this->assertSame( '', (string) get_post_meta( $id, MetaSchema::COVER, true ), 'and is never faked into an attachment ID' );

		$this->assertIsArray( get_post_meta( $id, LegacySchema::BACKUP, true ), 'the backup is written before anything else' );
		$this->assertSame( LegacySchema::VERSION, get_post_meta( $id, LegacySchema::MARKER, true ) );
		$this->assertGreaterThanOrEqual( 1, (int) $report['migrated'] );
		$this->assertStringContainsString( 'vip_img', implode( "\n", (array) $report['needs_review'] ), 'the operator is told about the image' );
	}

	/**
	 * The reported invariant: running twice changes nothing the second time.
	 *
	 * @return void
	 */
	public function test_migration_is_idempotent() {
		$id       = $this->legacy_track();
		$migrator = new Migrator();

		$first = $migrator->run();
		$snap  = get_post_meta( $id );

		$second = $migrator->run();

		$this->assertGreaterThanOrEqual( 1, (int) $first['migrated'] );
		$this->assertSame( 0, (int) $second['migrated'], 'a migrated post is not a legacy post any more' );
		$this->assertSame( $snap, get_post_meta( $id ), 'the second run writes nothing' );
		$this->assertCount(
			1,
			array_filter(
				array_keys( $snap ),
				static function ( $key ) {
					return LegacySchema::BACKUP === $key;
				}
			),
			'the backup is not overwritten with the migrated state'
		);
	}

	/**
	 * An artist the site never had is queued, never invented.
	 *
	 * @return void
	 */
	public function test_unknown_artist_is_queued_for_review() {
		$id = $this->legacy_track( 'ناشناس و همکاران' );

		$report = ( new Migrator() )->run();

		$this->assertSame( 'ناشناس و همکاران', get_post_meta( $id, MetaSchema::CREDIT_LABEL, true ), 'the credit text is kept' );
		$this->assertSame( '', (string) get_post_meta( $id, MetaSchema::ARTIST, true ), 'no entity is invented for it' );
		$this->assertSame( 'artist', get_post_meta( $id, LegacySchema::REVIEW, true ) );
		$this->assertStringContainsString( 'no artist entity matches', implode( "\n", (array) $report['needs_review'] ) );
	}

	/**
	 * The artist directory is built from the legacy terms, and duplicates merge.
	 *
	 * @return void
	 */
	public function test_artist_directory_is_built_and_duplicates_merge() {
		$term = wp_insert_term(
			'آرمان راد',
			LegacySchema::ARTIST_TAX,
			array(
				'slug'        => 'arman-rad',
				'description' => 'زیست‌نامه',
			)
		);
		$this->assertIsArray( $term, 'the legacy taxonomy has to exist for this test to mean anything' );

		update_term_meta( (int) $term['term_id'], 'afacebook', 'https://facebook.com/arman' );
		update_term_meta( (int) $term['term_id'], 'atelegram', 'https://t.me/arman' );

		$id = $this->legacy_track();

		( new Migrator() )->run();

		$artist = get_page_by_path( 'arman-rad', OBJECT, PostTypes::ARTIST );

		$this->assertInstanceOf( WP_Post::class, $artist, 'the term became an artist post' );
		$this->assertSame( 'زیست‌نامه', $artist->post_content, 'the term description became the biography' );
		$this->assertSame( 'https://facebook.com/arman', get_post_meta( $artist->ID, MetaSchema::SOCIAL_FACEBOOK, true ) );
		$this->assertSame( 'https://t.me/arman', get_post_meta( $artist->ID, MetaSchema::SOCIAL_TELEGRAM, true ) );
		$this->assertSame( $artist->ID, (int) get_post_meta( $id, MetaSchema::ARTIST, true ), 'the credit is linked to the entity' );
		$this->assertSame( '', (string) get_post_meta( $id, LegacySchema::REVIEW, true ), 'and needs no review' );

		// A second run merges instead of duplicating.
		$report = ( new Migrator() )->run();

		$this->assertSame( 0, (int) $report['artists']['created'], 'no second artist is created' );
		$this->assertSame( 1, (int) $report['artists']['merged'] );

		$this->posts[] = $artist->ID;
	}

	/**
	 * M2 — the album repeater becomes ordered child tracks, once.
	 *
	 * @return void
	 */
	public function test_album_repeater_expands_to_ordered_tracks_once() {
		// The album names an artist, and the legacy term exists, so the children
		// have something to inherit instead of a review flag.
		$term = wp_insert_term( 'آرمان راد', LegacySchema::ARTIST_TAX, array( 'slug' => 'arman-rad' ) );
		$this->assertIsArray( $term );

		$album = $this->legacy_post(
			array(
				LegacySchema::TYPE_META => 'album',
				'artist'                => 'آرمان راد',
				'album128'              => 'https://cdn.example.test/album-128.zip',
				'album'                 => array(
					array(
						'song_names'   => 'قطعهٔ یک',
						'albumlink128' => 'https://cdn.example.test/one-128.mp3',
						'albumlink320' => 'https://cdn.example.test/one-320.mp3',
					),
					array(
						'song_names'   => 'قطعهٔ دو',
						'albumlink128' => 'https://cdn.example.test/two-128.mp3',
					),
				),
			),
			'شب‌های تهران'
		);

		( new Migrator() )->run();

		$this->assertSame( PostTypes::ALBUM, get_post_type( $album ) );

		$tracklist = get_post_meta( $album, MetaSchema::TRACKLIST, true );

		$this->assertIsArray( $tracklist );
		$this->assertCount( 2, $tracklist );

		foreach ( $tracklist as $index => $track ) {
			$this->posts[] = (int) $track;

			$this->assertSame( PostTypes::TRACK, get_post_type( (int) $track ) );
			$this->assertSame( $album, (int) get_post_meta( (int) $track, MetaSchema::ALBUM, true ) );
			$this->assertSame( $index, (int) get_post_field( 'menu_order', (int) $track ), 'the tracklist keeps its order' );
			$this->assertSame( 'آرمان راد', (string) get_post_meta( (int) $track, MetaSchema::CREDIT_LABEL, true ), 'a child track carries the album credit' );
			$this->assertSame( PostTypes::ARTIST, get_post_type( (int) get_post_meta( (int) $track, MetaSchema::ARTIST, true ) ), 'a child inherits the album artist link' );
		}

		$this->assertSame( 'قطعهٔ یک', get_the_title( (int) $tracklist[0] ) );
		$this->assertSame( 'https://cdn.example.test/one-320.mp3', get_post_meta( (int) $tracklist[0], MetaSchema::AUDIO_320, true ) );
		$this->assertSame( 'https://cdn.example.test/two-128.mp3', get_post_meta( (int) $tracklist[1], MetaSchema::AUDIO_128, true ) );

		$first = $tracklist;

		( new Migrator() )->run();

		$this->assertSame( $first, get_post_meta( $album, MetaSchema::TRACKLIST, true ), 'a second run creates no duplicate tracks' );
	}

	/**
	 * The legacy category becomes a genre, and the category itself survives.
	 *
	 * @return void
	 */
	public function test_categories_are_copied_into_genres() {
		$id   = $this->legacy_track();
		$term = self::factory()->term->create(
			array(
				'taxonomy' => 'category',
				'name'     => 'پاپ',
				'slug'     => 'pop',
			)
		);

		wp_set_object_terms( $id, array( (int) $term ), 'category' );

		( new Migrator() )->run();

		$genres = wp_get_post_terms( $id, Taxonomies::GENRE, array( 'fields' => 'slugs' ) );

		$this->assertSame( array( 'pop' ), $genres, 'the genre keeps the legacy slug' );
		$this->assertNotSame( array(), wp_get_post_terms( $id, 'category', array( 'fields' => 'slugs' ) ), 'the editorial category is not taken away' );
	}

	/**
	 * The rollback restores the site: type, meta, and no created tracks left.
	 *
	 * @return void
	 */
	public function test_rollback_restores_the_legacy_state() {
		$id    = $this->legacy_track();
		$album = $this->legacy_post(
			array(
				LegacySchema::TYPE_META => 'album',
				'album'                 => array(
					array(
						'song_names'   => 'قطعه',
						'albumlink128' => 'https://cdn.example.test/one-128.mp3',
					),
				),
			),
			'آلبوم'
		);

		$migrator = new Migrator();

		$migrator->run();

		$tracklist = (array) get_post_meta( $album, MetaSchema::TRACKLIST, true );
		$child     = (int) $tracklist[0];

		$this->assertSame( PostTypes::ALBUM, get_post_type( $album ) );

		$report = $migrator->rollback( array( 'batch' => 50 ) );

		$this->assertGreaterThanOrEqual( 2, (int) $report['restored'] );
		$this->assertSame( 1, (int) $report['deleted'], 'the track the tool created is removed' );
		$this->assertSame( 'post', get_post_type( $id ), 'the legacy post type is back' );
		$this->assertSame( 'post', get_post_type( $album ) );
		$this->assertSame( 'mp3', get_post_meta( $id, LegacySchema::TYPE_META, true ), 'the legacy meta is back' );
		$this->assertSame( 'https://cdn.example.test/rain-128.mp3', get_post_meta( $id, 'music128', true ) );
		$this->assertSame( '', (string) get_post_meta( $id, MetaSchema::AUDIO_128, true ), 'a key the tool filled is gone' );
		$this->assertSame( '', (string) get_post_meta( $id, LegacySchema::BACKUP, true ), 'and so is the bookkeeping' );
		$this->assertNull( get_post( $child ), 'the created track is deleted' );
	}

	/**
	 * The report is stored, so `wp wavira migrate --status` has something to say.
	 *
	 * @return void
	 */
	public function test_report_is_stored_after_a_run() {
		$this->legacy_track();

		$report = ( new Migrator() )->run();
		$stored = ( new Migrator() )->report();

		$this->assertSame( LegacySchema::VERSION, $stored['version'] );
		$this->assertSame( $report['migrated'], $stored['migrated'] );
		$this->assertFalse( $stored['dry_run'] );
	}

	/**
	 * A post whose `musics_type` the map does not know is reported, not forced.
	 *
	 * @return void
	 */
	public function test_unknown_kind_is_reported_and_left_alone() {
		$id = $this->legacy_post(
			array(
				LegacySchema::TYPE_META => 'playlist',
			),
			'Odd one'
		);

		$report = ( new Migrator() )->run();

		$this->assertSame( 'post', get_post_type( $id ), 'an unknown kind is not converted' );
		$this->assertArrayHasKey( 'playlist', (array) $report['unmapped'] );
		$this->assertGreaterThanOrEqual( 1, (int) $report['unmapped']['playlist'] );
	}

	/**
	 * The two source maps never claim the same `musics_type` value.
	 *
	 * They share the meta key (`musics_type`) but not its vocabulary: the legacy
	 * theme writes `mp3`/`mp4`/`album`, the publishing plugin `musicss*`. A
	 * collision would make `--source` meaningless and silently convert a site
	 * with the wrong field map — the one failure mode a shared engine has.
	 *
	 * @return void
	 */
	public function test_the_two_sources_do_not_share_a_kind_vocabulary() {
		$legacy    = LegacySchema::kinds( LegacySchema::SOURCE_LEGACY );
		$publisher = LegacySchema::kinds( LegacySchema::SOURCE_MUSIC_PUBLISHER );

		$this->assertSame( array(), array_intersect_key( $legacy, $publisher ) );
		$this->assertArrayHasKey( 'mp3', $legacy );
		$this->assertArrayHasKey( 'musicss_remix', $publisher );
	}

	/**
	 * A publishing-plugin track arrives with its kind, its sources and its credit.
	 *
	 * Fixture: the shape `class-smp-post-handler.php` writes — `post` +
	 * `musics_type`, `music128`/`music320`, `music_txt`, `art_name`,
	 * `track_name`, `fifu_image_url`, `online_ply` — plus the role taxonomies.
	 *
	 * @return void
	 */
	public function test_publisher_track_arrives_with_its_kind_and_credits() {
		foreach ( LegacySchema::contributor_taxonomies( LegacySchema::SOURCE_MUSIC_PUBLISHER ) as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				register_taxonomy( $taxonomy, array( 'post' ), array( 'public' => false ) );
			}
		}

		$term = wp_insert_term( 'آرمان راد', LegacySchema::ARTIST_TAX, array( 'slug' => 'arman-rad' ) );
		$this->assertIsArray( $term );

		$id = $this->legacy_post(
			array(
				LegacySchema::TYPE_META => 'musicss_remix',
				'art_name'              => 'آرمان راد',
				'artist_en'             => 'Arman Rad',
				'track_name'            => 'نسخهٔ ریمیکس',
				'music128'              => 'https://cdn.example.test/remix-128.mp3',
				'music320'              => 'https://cdn.example.test/remix-320.mp3',
				'music_txt'             => '<p>متن</p>',
				'online_ply'            => 'on',
				'slider_song'           => '1',
				'music320_video'        => 'https://cdn.example.test/odd.mp3',
				'talbume320'            => 'https://cdn.example.test/albums/320/',
			),
			'راه بارانی (ریمیکس)'
		);

		foreach ( array( 'songwriter', 'mixmaster' ) as $taxonomy ) {
			wp_set_object_terms( $id, array( 'مهسا کیان' ), $taxonomy );
		}

		( new Migrator() )->source( LegacySchema::SOURCE_MUSIC_PUBLISHER )->run();

		$this->assertSame( PostTypes::TRACK, get_post_type( $id ) );
		$this->assertSame( 'https://cdn.example.test/remix-320.mp3', get_post_meta( $id, MetaSchema::AUDIO_320, true ) );
		$this->assertSame( 'https://cdn.example.test/remix-128.mp3', get_post_meta( $id, MetaSchema::AUDIO_128, true ) );
		$this->assertSame( 'آرمان راد', get_post_meta( $id, MetaSchema::CREDIT_LABEL, true ) );
		$this->assertSame( 'نسخهٔ ریمیکس', get_post_meta( $id, MetaSchema::SUBTITLE, true ) );
		$this->assertTrue( (bool) get_post_meta( $id, MetaSchema::FEATURED, true ) );
		$this->assertTrue( (bool) get_post_meta( $id, MetaSchema::IN_INDEX_PLAYER, true ), '"on" is a real boolean, not a truthy string' );

		$this->assertSame(
			array( 'remix' ),
			wp_get_post_terms( $id, Taxonomies::KIND, array( 'fields' => 'slugs' ) ),
			'musicss_remix lands in the remix kind, not in a generic track bucket'
		);

		$this->assertSame( LegacySchema::SOURCE_MUSIC_PUBLISHER, get_post_meta( $id, LegacySchema::SOURCE_META, true ) );

		$raw = get_post_meta( $id, LegacySchema::RAW, true );

		$this->assertIsArray( $raw );
		$this->assertSame( 'Arman Rad', $raw['artist_en'], 'a second-language field is audited, never dropped' );
		$this->assertSame( 'https://cdn.example.test/odd.mp3', $raw['music320_video'], 'the video MP3 has no v1 field but stays recoverable' );
		$this->assertSame( 'مهسا کیان', $raw['contributors']['songwriter'][0], 'a role credit is kept as data' );

		$report = ( new Migrator() )->source( LegacySchema::SOURCE_MUSIC_PUBLISHER )->report();

		$this->assertStringContainsString( 'credits name', implode( "\n", (array) $report['needs_review'] ), 'and the operator is told to place it by hand' );
	}

	/**
	 * A publishing-plugin album expands from `album_dl` rows, keyed by the row.
	 *
	 * @return void
	 */
	public function test_publisher_album_rows_expand_to_ordered_tracks() {
		$id = $this->legacy_post(
			array(
				LegacySchema::TYPE_META => 'musicss_album',
				'art_name'              => 'آرمان راد',
				'album320'              => 'https://cdn.example.test/album-320.zip',
				'album_dl'              => array(
					array(
						'title'     => 'قطعهٔ یک',
						'al_url128' => 'https://cdn.example.test/one-128.mp3',
						'al_url320' => 'https://cdn.example.test/one-320.mp3',
					),
					array(
						'title'     => 'قطعهٔ دو',
						'al_url128' => 'https://cdn.example.test/two-128.mp3',
					),
				),
			),
			'شب‌های تهران'
		);

		( new Migrator() )->source( LegacySchema::SOURCE_MUSIC_PUBLISHER )->run();

		$this->assertSame( PostTypes::ALBUM, get_post_type( $id ) );
		$this->assertSame( 'https://cdn.example.test/album-320.zip', get_post_meta( $id, MetaSchema::ALBUM_AUDIO_320, true ) );

		$tracklist = get_post_meta( $id, MetaSchema::TRACKLIST, true );

		$this->assertIsArray( $tracklist );
		$this->assertCount( 2, $tracklist, 'both rows become tracks' );
		$this->assertSame( 'قطعهٔ یک', get_the_title( (int) $tracklist[0] ) );
		$this->assertSame( 'قطعهٔ دو', get_the_title( (int) $tracklist[1] ) );
		$this->assertSame( 'https://cdn.example.test/one-320.mp3', get_post_meta( (int) $tracklist[0], MetaSchema::AUDIO_320, true ) );

		foreach ( $tracklist as $track ) {
			$this->posts[] = (int) $track;
		}
	}

	/**
	 * `--source` decides which map runs, and never converts the other one.
	 *
	 * @return void
	 */
	public function test_a_source_only_converts_its_own_vocabulary() {
		$legacy = $this->legacy_track();

		$this->legacy_post(
			array(
				LegacySchema::TYPE_META => 'musicss_nohe',
				'art_name'              => 'کربلایی',
				'music320'              => 'https://cdn.example.test/noha-320.mp3',
			),
			'نوحه'
		);

		$report = ( new Migrator() )->source( LegacySchema::SOURCE_MUSIC_PUBLISHER )->run();

		$this->assertSame( 'post', get_post_type( $legacy ), 'a legacy mp3 is not touched by the publishing-plugin map' );
		$this->assertGreaterThanOrEqual( 1, (int) $report['migrated'], 'and the plugin content is converted' );
	}

	/**
	 * The kinds of the publishing plugin normalise to the product's vocabulary.
	 *
	 * @return void
	 */
	public function test_publisher_kinds_normalise_to_kind_terms() {
		$expected = array(
			'musicss'         => 'music',
			'musicss_remix'   => 'remix',
			'musicss_nohe'    => 'noha',
			'musicss_podcast' => 'podcast',
		);

		foreach ( $expected as $kind => $term ) {
			$this->assertSame( $term, LegacySchema::kind_term( $kind, LegacySchema::SOURCE_MUSIC_PUBLISHER ), $kind );
		}

		$this->assertSame( '', LegacySchema::kind_term( 'musicss_video', LegacySchema::SOURCE_MUSIC_PUBLISHER ), 'a video is its own post type' );
		$this->assertSame( '', LegacySchema::kind_term( 'musicss_album', LegacySchema::SOURCE_MUSIC_PUBLISHER ) );
		$this->assertSame( 'music', LegacySchema::kind_term( 'mp3', LegacySchema::SOURCE_LEGACY ) );
		$this->assertSame(
			'',
			\Wavira\Core\Content\Taxonomies::normalize_kind( 'musicss_unknown_thing' ),
			'a value the alias map does not know stays unmapped'
		);
	}

	/**
	 * The detection profile names the source it profiled.
	 *
	 * @return void
	 */
	public function test_detection_reports_the_selected_source() {
		$this->legacy_post(
			array(
				LegacySchema::TYPE_META => 'musicss_podcast',
				'artist_en'             => 'Arman Rad',
				'music320'              => 'https://cdn.example.test/ep-1.mp3',
			),
			'قسمت یک'
		);

		$profile = ( new Migrator() )->source( LegacySchema::SOURCE_MUSIC_PUBLISHER )->detect();

		$this->assertSame( LegacySchema::SOURCE_MUSIC_PUBLISHER, $profile['source'] );
		$this->assertSame( 1, (int) $profile['legacy']['musicss_podcast'] );
		$this->assertArrayHasKey( 'artist_en', $profile['deferred'], 'the deferred profile of the source is what is counted' );
	}

	/**
	 * An unknown `--source` value is refused, not quietly ignored.
	 *
	 * @return void
	 */
	public function test_an_unknown_source_is_refused_and_keeps_the_default() {
		$migrator = new Migrator();

		$this->assertSame( LegacySchema::SOURCE_LEGACY, $migrator->current_source(), 'the default source is the audited theme' );
		$this->assertFalse( LegacySchema::has_source( 'musicpress' ) );
		$this->assertSame( LegacySchema::SOURCE_LEGACY, $migrator->source( 'musicpress' )->current_source(), 'an unknown slug cannot repoint the engine' );
	}

	/**
	 * The CLI exposes the source, and refuses an unknown one.
	 *
	 * @return void
	 */
	public function test_cli_accepts_the_source_option() {
		$cli = (string) file_get_contents( dirname( __DIR__ ) . '/wavira-core/src/Admin/Cli.php' );

		$this->assertStringContainsString( '--source=<source>', $cli );
		$this->assertStringContainsString( 'LegacySchema::has_source', $cli );
		$this->assertStringContainsString( '$migrator->source( $source )', $cli );
	}

	/**
	 * The command is registered only inside WP-CLI, and points at the tool.
	 *
	 * @return void
	 */
	public function test_cli_wiring_is_present_in_the_source() {
		$cli = (string) file_get_contents( dirname( __DIR__ ) . '/wavira-core/src/Admin/Cli.php' );

		// No interpolation here: a double-quoted needle containing `$this` was
		// the bug this test found in itself (PHP stringifies the test case).
		$this->assertStringContainsString( "WP_CLI::add_command( 'wavira migrate'", $cli );
		$this->assertStringContainsString( "'migrate' ) )", $cli );
		$this->assertStringContainsString( 'new Migrator()', $cli );
		$this->assertStringContainsString( '--dry-run', $cli );
	}
}
