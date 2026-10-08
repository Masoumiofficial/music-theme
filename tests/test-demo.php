<?php
/**
 * Runtime verification of the demo content path (phase 0.11.0).
 *
 * The demo has three doors — `wp wavira seed`, the admin screen, and the WXR
 * export — and this file is what keeps them the same door: the installer, the
 * fixture and the export are exercised here on a real WordPress install, and the
 * admin screen and the CLI command are asserted to call the installer rather
 * than to reimplement it.
 *
 * @package Wavira\Tests
 */

use Wavira\Core\Content\MetaSchema;
use Wavira\Core\Content\PostTypes;
use Wavira\Core\Content\Taxonomies;
use Wavira\Core\Demo\Exporter;
use Wavira\Core\Demo\Fixtures;
use Wavira\Core\Demo\Installer;
use Wavira\Core\Demo\Placeholders;

/**
 * Class Test_Demo
 */
class Test_Demo extends Wavira_Test_Case {

	/**
	 * Post IDs created by the current test.
	 *
	 * @var int[]
	 */
	private $posts = array();

	/**
	 * Track every post the demo created, so nothing leaks into the next test.
	 *
	 * @return void
	 */
	public function tear_down() {
		// The generated files first: `wp_delete_post()` does not take an
		// attachment with it, so a class that installed the demo five times would
		// leave five catalogues' worth of covers and tones in the uploads folder —
		// and the next test's count of "what the import made" would be somebody
		// else's leftovers (found by exactly that assertion).
		foreach ( $this->demo_media() as $attachment ) {
			wp_delete_attachment( (int) $attachment, true );
		}

		foreach ( $this->posts as $id ) {
			wp_delete_post( (int) $id, true );
		}

		$this->posts = array();

		parent::tear_down();
	}

	/**
	 * Post IDs of one type that the demo marker claims.
	 *
	 * @return int[]
	 */
	private function demo_ids(): array {
		$ids = get_posts(
			array(
				'post_type'      => array( PostTypes::ARTIST, PostTypes::ALBUM, PostTypes::TRACK, PostTypes::VIDEO ),
				'post_status'    => 'any',
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'meta_key'       => Installer::MARKER, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- test fixture lookup.
				'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- same.
			)
		);

		return array_map( 'intval', $ids );
	}

	/**
	 * The Persian catalogue installs complete: artist, releases, tracks, kinds.
	 *
	 * @return void
	 */
	public function test_persian_demo_installs_the_full_catalogue() {
		$report = Installer::install( array( 'site' => false ) );

		$this->posts = array_merge( $this->posts, $this->demo_ids() );

		$this->assertFalse( $report['skipped'] );
		$this->assertSame( 2, count( $report['releases'] ) );
		$this->assertSame( 5, count( $report['tracks'] ), 'four songs and the remix of one of them' );
		$this->assertGreaterThan( 0, (int) $report['video'] );

		$this->assertSame( PostTypes::ARTIST, get_post_type( (int) $report['artist'] ) );
		$this->assertSame( 'آرمان راد', get_the_title( (int) $report['artist'] ) );

		$kinds = array();

		foreach ( $report['tracks'] as $track ) {
			$this->assertSame( PostTypes::TRACK, get_post_type( (int) $track ) );
			$this->assertSame( (int) $report['artist'], (int) get_post_meta( (int) $track, MetaSchema::ARTIST, true ), 'every track credits the artist' );
			$this->assertGreaterThan( 0, (int) get_post_meta( (int) $track, MetaSchema::DURATION, true ) );
			$this->assertNotSame( '', (string) get_post_meta( (int) $track, MetaSchema::LYRICS, true ) );
			$this->assertNotSame( array(), wp_get_post_terms( (int) $track, Taxonomies::GENRE, array( 'fields' => 'slugs' ) ), 'a demo track has a genre' );

			$terms = wp_get_post_terms( (int) $track, Taxonomies::KIND, array( 'fields' => 'slugs' ) );

			$this->assertCount( 1, $terms, 'a demo track has exactly one kind' );

			$kinds[] = (string) $terms[0];
		}

		$this->assertContains( 'remix', $kinds, 'the demo shows a remix as a kind of its own' );
		$this->assertContains( 'music', $kinds );

		$this->assertSame( 'other', (string) get_post_meta( (int) $report['video'], MetaSchema::VIDEO_SOURCE, true ), 'the demo video does not pretend to be a file it does not have' );
	}

	/**
	 * The demo's media is its own, local, attached, and counted.
	 *
	 * Until 0.15.0 the catalogue carried no audio at all, because a demo whose
	 * player points at a 404 looks broken on the first click a buyer makes
	 * (ADR 0010). ADR 0024 answers that by *generating* the files instead of
	 * borrowing them: what must never appear is a remote URL or a file that
	 * belongs to somebody else.
	 *
	 * @return void
	 */
	public function test_demo_media_is_generated_locally_and_counted() {
		$report = Installer::install( array( 'site' => false ) );

		$this->posts = array_merge( $this->posts, $this->demo_ids() );

		$upload = wp_upload_dir();
		$count  = 0;

		foreach ( $report['tracks'] as $track ) {
			foreach ( array( MetaSchema::AUDIO_128, MetaSchema::AUDIO_320 ) as $key ) {
				$url = (string) get_post_meta( (int) $track, $key, true );

				$this->assertNotSame( '', $url, 'a demo track has audio to play and to download' );
				$this->assertStringStartsWith( $upload['baseurl'], $url, 'the file is on this site, not somewhere else' );

				++$count;
			}
		}

		foreach ( $report['releases'] as $release ) {
			$url = (string) get_post_meta( (int) $release, MetaSchema::ALBUM_AUDIO_320, true );

			$this->assertNotSame( '', $url, 'a demo album has a master file to download' );
			$this->assertStringStartsWith( $upload['baseurl'], $url );
		}

		// The report counts generated files so the admin notice and the CLI can
		// say how much they made — and so a run that generated nothing is
		// visible instead of silent.
		$this->assertGreaterThan( 0, (int) $report['media'] );
		$this->assertSame( (int) $report['media'], count( $this->demo_media() ) );
	}

	/**
	 * Every generated file is marked as the demo's own.
	 *
	 * `remove_previous()` deletes exactly what the importer created, so a second
	 * import cannot leave orphans and an uninstall cannot take a real site's
	 * uploads with it (ADR 0024).
	 *
	 * @return int[] Attachment IDs.
	 */
	private function demo_media() {
		return get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => Placeholders::MARKER, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- test fixture lookup.
				'no_found_rows'  => true,
			)
		);
	}

	/**
	 * Importing twice changes nothing; a forced import replaces its own posts.
	 *
	 * This is what makes the admin button safe to press twice: the second press
	 * cannot silently double the catalogue, and the forced run cannot delete a
	 * post the site owner wrote.
	 *
	 * @return void
	 */
	public function test_demo_is_idempotent_and_forced_import_replaces_only_its_own() {
		$first = Installer::install( array( 'site' => false ) );

		$this->posts = array_merge( $this->posts, $this->demo_ids() );

		$marks = get_posts(
			array(
				'post_type'      => PostTypes::TRACK,
				'posts_per_page' => 100,
				'fields'         => 'ids',
			)
		);

		$second = Installer::install( array( 'site' => false ) );

		$this->assertTrue( $second['skipped'], 'a site with tracks is left alone' );
		$this->assertSame( count( $marks ), count( get_posts( array( 'post_type' => PostTypes::TRACK, 'posts_per_page' => 100, 'fields' => 'ids' ) ) ) );

		// A post the owner wrote must survive a forced import.
		$owner = self::factory()->post->create(
			array(
				'post_type'   => PostTypes::TRACK,
				'post_status' => 'publish',
				'post_title'  => 'ترانهٔ خودم',
			)
		);

		$third = Installer::install( array( 'force' => true, 'site' => false ) );

		$this->assertGreaterThan( 0, (int) $third['replaced'], 'the previous demo was removed' );
		$this->assertSame( PostTypes::TRACK, get_post_type( $owner ), 'the owner wrote it, the importer does not touch it' );
		$this->assertSame( count( $first['tracks'] ), count( $third['tracks'] ), 'the same catalogue is installed again' );
		$this->assertNotSame( (int) $first['artist'], (int) $third['artist'], 'the demo posts are new posts, not edited ones' );

		$this->posts = array_merge( $this->posts, $this->demo_ids(), array( (int) $owner ) );
	}

	/**
	 * The English fixture leaves the site's locale alone.
	 *
	 * @return void
	 */
	public function test_english_fixture_does_not_touch_site_options() {
		$before = array(
			'timezone_string' => get_option( 'timezone_string' ),
			'start_of_week'   => get_option( 'start_of_week' ),
			'date_format'     => get_option( 'date_format' ),
		);

		$report = Installer::install( array( 'english' => true ) );

		$this->posts = array_merge( $this->posts, $this->demo_ids() );

		$this->assertSame( $before['timezone_string'], get_option( 'timezone_string' ) );
		$this->assertSame( $before['start_of_week'], get_option( 'start_of_week' ) );
		$this->assertSame( $before['date_format'], get_option( 'date_format' ) );
		$this->assertSame( 0, (int) $report['menu'], 'the English fixture creates no menu' );
		$this->assertSame( 3, count( $report['tracks'] ) );
	}

	/**
	 * The Persian demo, with the site step on, applies the Iranian defaults.
	 *
	 * @return void
	 */
	public function test_persian_demo_applies_the_iranian_defaults() {
		$report = Installer::install();

		$this->posts = array_merge( $this->posts, $this->demo_ids() );

		$this->assertGreaterThan( 0, (int) $report['menu'], 'the primary menu is created' );
		$this->assertSame( 'Asia/Tehran', (string) get_option( 'timezone_string' ) );
		$this->assertSame( 6, (int) get_option( 'start_of_week' ), 'the Iranian week starts on Saturday' );
		$this->assertSame( 'j F Y', (string) get_option( 'date_format' ), 'Jalali dates on the front end' );

		$locations = (array) get_theme_mod( 'nav_menu_locations', array() );

		$this->assertArrayHasKey( 'primary', $locations );
	}

	/**
	 * The fixture itself holds what the installer promises to read.
	 *
	 * A fixture with a missing key would fail mid-import on a customer's site;
	 * checking the shape here keeps that a test failure instead.
	 *
	 * @return void
	 */
	public function test_fixture_shape_is_complete() {
		foreach ( array( 'persian', 'english' ) as $name ) {
			$fixture = 'persian' === $name ? Fixtures::persian() : Fixtures::english();

			foreach ( array( 'artist', 'releases', 'video', 'site', 'menu' ) as $key ) {
				$this->assertArrayHasKey( $key, $fixture, "{$name} fixture: {$key}" );
			}

			$this->assertArrayHasKey( 'title', $fixture['artist'] );

			foreach ( $fixture['releases'] as $release ) {
				foreach ( array( 'title', 'type', 'date', 'release_date', 'genre', 'tracks' ) as $key ) {
					$this->assertArrayHasKey( $key, $release, "{$name} release: {$key}" );
				}

				foreach ( $release['tracks'] as $track ) {
					foreach ( array( 'title', 'date', 'genre', 'kind', 'duration', 'lyrics' ) as $key ) {
						$this->assertArrayHasKey( $key, $track, "{$name} track: {$key}" );
					}

					$this->assertNotSame( '', Taxonomies::normalize_kind( (string) $track['kind'] ), 'every fixture kind is a known kind' );
				}
			}
		}
	}

	/**
	 * The export writes a WXR document to a file, and a second run in the same
	 * request is refused instead of fatally redeclaring Core's `wxr_cdata()`.
	 *
	 * One export per request is all WordPress allows — the suite runs in a single
	 * process, so this method is the only place that may start one.
	 *
	 * @return void
	 */
	public function test_export_produces_a_wxr_document() {
		Installer::install( array( 'english' => true, 'site' => false ) );

		$this->posts = array_merge( $this->posts, $this->demo_ids() );

		$path   = wp_tempnam( 'wavira-export' );
		$result = Exporter::to_file( (string) $path );

		$this->assertTrue( $result['ok'], (string) $result['reason'] );
		$this->assertGreaterThan( 0, (int) $result['bytes'] );

		$document = (string) file_get_contents( (string) $path );

		$this->assertStringContainsString( '<rss version="2.0"', $document );
		$this->assertStringContainsString( 'wxr_version', $document );
		$this->assertStringContainsString( 'Demo Artist', $document, 'the content is in the document' );

		wp_delete_file( (string) $path );

		$again = Exporter::xml();

		$this->assertSame( '0', $again['ok'], 'a second export in one request is refused, never fatal' );
		$this->assertSame( '', $again['xml'] );
		$this->assertStringContainsString( 'once per request', $again['reason'] );
	}

	/**
	 * Exports are named, not anonymous.
	 *
	 * @return void
	 */
	public function test_export_file_name_is_a_safe_xml_name() {
		$name = Exporter::file_name();

		$this->assertStringEndsWith( '.xml', $name );
		$this->assertStringStartsWith( 'wavira-content-', $name );
		$this->assertSame( $name, sanitize_file_name( $name ) );
	}

	/**
	 * The admin screen and the CLI both run the installer, and both are guarded.
	 *
	 * A screen that reimplemented the import would be a second demo that drifts
	 * from the first; this asserts the single path, the capability check, and
	 * the nonce — the three things a hand-written admin action usually gets
	 * wrong.
	 *
	 * @return void
	 */
	public function test_admin_screen_and_cli_share_the_installer() {
		$page = (string) file_get_contents( dirname( __DIR__ ) . '/wavira-core/src/Admin/DemoPage.php' );
		$cli  = (string) file_get_contents( dirname( __DIR__ ) . '/wavira-core/src/Admin/Cli.php' );

		$this->assertStringContainsString( 'Installer::install(', $page );
		$this->assertStringContainsString( 'admin_post_', $page );
		$this->assertStringContainsString( 'check_admin_referer', $page );
		$this->assertStringContainsString( "CAPABILITY = 'manage_options'", $page );
		$this->assertStringContainsString( 'current_user_can( self::CAPABILITY )', $page );
		$this->assertStringContainsString( 'wp_nonce_field', $page );

		$this->assertStringContainsString( 'Installer::install(', $cli );
		$this->assertStringContainsString( "'wavira export-demo'", $cli );
	}

	/**
	 * The demo screen is registered by the plugin, not bolted on by the theme.
	 *
	 * @return void
	 */
	public function test_demo_screen_is_a_plugin_module() {
		$plugin = (string) file_get_contents( dirname( __DIR__ ) . '/wavira-core/src/Plugin.php' );

		$this->assertStringContainsString( 'new DemoPage()', $plugin );

		$modules = \Wavira\Core\Plugin::instance()->modules();

		$this->assertContains( 'Wavira\Core\Admin\DemoPage', array_map( 'get_class', $modules ) );
	}
}
