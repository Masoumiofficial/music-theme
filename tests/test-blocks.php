<?php
/**
 * Runtime verification of the 0.7.0 block layer on a real WordPress install.
 *
 * The blocks live in the theme (ARCHITECTURE §1: presentation), and the
 * WordPress test install does not contain the theme directory, so this suite
 * loads the theme's PHP from the repository with the same constants the theme
 * bootstrap defines. Everything after that is real: `register_block_type()`
 * reads the shipped `block.json` files, and `do_blocks()` renders through the
 * shipped `render.php` files.
 *
 * @package Wavira\Tests
 */

/**
 * Class Test_Blocks
 */
class Test_Blocks extends Wavira_Test_Case {

	/**
	 * Load the theme and register its surfaces once for the class.
	 *
	 * @return void
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::load_theme();
	}

	/**
	 * Load the theme's PHP the way the theme bootstrap does.
	 *
	 * @return void
	 */
	private static function load_theme() {
		if ( ! defined( 'WAVIRA_THEME_DIR' ) ) {
			define( 'WAVIRA_THEME_DIR', trailingslashit( dirname( __DIR__ ) . '/wavira' ) );
			define( 'WAVIRA_THEME_URI', 'https://example.test/wp-content/themes/wavira/' );
			define( 'WAVIRA_THEME_VERSION', '0.7.0-test' );
		}

		foreach ( array( 'helpers', 'markup', 'assets', 'player', 'shortcodes', 'blocks' ) as $file ) {
			$path = WAVIRA_THEME_DIR . 'inc/' . $file . '.php';

			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}

		if ( function_exists( 'wavira_register_blocks' ) ) {
			wavira_register_block_editor_script();
			wavira_register_blocks();
		}
	}

	/**
	 * Create an album with an explicit tracklist.
	 *
	 * @param int[] $track_ids Track IDs in playing order.
	 * @return int Album ID.
	 */
	private function make_album( array $track_ids = array() ) {
		$album_id = self::factory()->post->create(
			array(
				'post_type'   => 'wavira_album',
				'post_status' => 'publish',
				'post_title'  => 'Album fixture',
			)
		);

		if ( array() !== $track_ids ) {
			update_post_meta( $album_id, \Wavira\Core\Content\MetaSchema::TRACKLIST, $track_ids );
		}

		return (int) $album_id;
	}

	/**
	 * Create a track.
	 *
	 * @param string $title    Track title.
	 * @param string $status   Post status.
	 * @param array  $meta     Extra meta, keyed by schema constant value.
	 * @return int Track ID.
	 */
	private function make_track( $title = 'Track fixture', $status = 'publish', array $meta = array() ) {
		$track_id = self::factory()->post->create(
			array(
				'post_type'   => 'wavira_track',
				'post_status' => $status,
				'post_title'  => $title,
			)
		);

		foreach ( $meta as $key => $value ) {
			update_post_meta( $track_id, $key, $value );
		}

		return (int) $track_id;
	}

	/**
	 * The block metadata the theme ships matches the registrar.
	 *
	 * @return void
	 */
	public function test_block_metadata_is_complete() {
		$this->assertTrue( function_exists( 'wavira_block_names' ), 'the theme must expose its block list' );

		foreach ( wavira_block_names() as $directory ) {
			$path = WAVIRA_THEME_DIR . 'blocks/' . $directory . '/block.json';

			$this->assertFileExists( $path );

			$metadata = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- repository file under test.

			$this->assertIsArray( $metadata, "{$directory}/block.json must be valid JSON" );
			$this->assertSame( 'wavira/' . $directory, $metadata['name'] );
			$this->assertSame( 3, $metadata['apiVersion'] );
			$this->assertSame( 'file:./render.php', $metadata['render'] );
			$this->assertSame( 'wavira', $metadata['textdomain'] );
			$this->assertSame( 'wavira-music', $metadata['category'] );
			$this->assertArrayHasKey( 'attributes', $metadata );
			$this->assertFileExists( WAVIRA_THEME_DIR . 'blocks/' . $directory . '/render.php' );
		}
	}

	/**
	 * Every block is registered with the server renderer and the loop context.
	 *
	 * @return void
	 */
	public function test_blocks_are_registered() {
		$registry = WP_Block_Type_Registry::get_instance();

		foreach ( wavira_block_names() as $directory ) {
			$name = 'wavira/' . $directory;

			$this->assertTrue( $registry->is_registered( $name ), "{$name} should be registered" );

			$block = $registry->get_registered( $name );

			$this->assertNotNull( $block->render_callback, "{$name} must render on the server" );
			$this->assertSame( 'wavira-music', $block->category );
			$this->assertFalse( $block->supports['html'], "{$name} is dynamic: no static HTML may be stored" );
		}

		$this->assertContains( 'postId', $registry->get_registered( 'wavira/tracklist' )->uses_context );
		$this->assertContains( 'postId', $registry->get_registered( 'wavira/player' )->uses_context );
		$this->assertContains( 'postId', $registry->get_registered( 'wavira/video' )->uses_context );
	}

	/**
	 * The tracklist block renders the album's published tracks in order.
	 *
	 * @return void
	 */
	public function test_tracklist_block_renders_published_tracks_in_order() {
		$second  = $this->make_track( 'Second in the album' );
		$first   = $this->make_track( 'First in the album' );
		$draft   = $this->make_track( 'Never published', 'draft' );
		$album   = $this->make_album( array( $first, $second, $draft, 999999 ) );
		$markup  = do_blocks( '<!-- wp:wavira/tracklist {"albumId":' . $album . '} /-->' );

		$this->assertStringContainsString( 'class="wavira-tracklist"', $markup );
		$this->assertStringContainsString( 'First in the album', $markup );
		$this->assertStringContainsString( 'Second in the album', $markup );
		$this->assertStringNotContainsString( 'Never published', $markup, 'drafts must not leak into a public tracklist' );
		$this->assertLessThan(
			strpos( $markup, 'Second in the album' ),
			strpos( $markup, 'First in the album' ),
			'the album tracklist order is the editor order, not the date order'
		);
	}

	/**
	 * Stored text reaches the page escaped, never as markup.
	 *
	 * The fixtures are chosen to be identical whether or not the site runs KSES
	 * on save: `<b>` is in core's title allow-list, and the subtitle carries the
	 * two characters that break unescaped output (`&` and a double quote).
	 *
	 * @return void
	 */
	public function test_tracklist_block_escapes_stored_text() {
		$track = $this->make_track(
			'Bold <b>title</b>',
			'publish',
			array( \Wavira\Core\Content\MetaSchema::SUBTITLE => 'Tom & Jerry "Live"' )
		);
		$album = $this->make_album( array( $track ) );
		$html  = do_blocks( '<!-- wp:wavira/tracklist {"albumId":' . $album . '} /-->' );

		$this->assertStringContainsString( 'Bold &lt;b&gt;title&lt;/b&gt;', $html );
		$this->assertStringContainsString( 'Tom &amp; Jerry &quot;Live&quot;', $html );
		$this->assertStringNotContainsString( '<b>title</b>', $html, 'a stored tag must never be printed as markup' );
	}

	/**
	 * The optional subtitle travels from meta to markup.
	 *
	 * @return void
	 */
	public function test_tracklist_block_renders_the_subtitle() {
		$track = $this->make_track(
			'Main title',
			'publish',
			array( \Wavira\Core\Content\MetaSchema::SUBTITLE => 'feat. Demo Artist' )
		);
		$album = $this->make_album( array( $track ) );
		$html  = do_blocks( '<!-- wp:wavira/tracklist {"albumId":' . $album . '} /-->' );

		$this->assertStringContainsString( 'wavira-tracklist__subtitle', $html );
		$this->assertStringContainsString( 'feat. Demo Artist', $html );
	}

	/**
	 * An album with nothing to play renders nothing on the front end.
	 *
	 * @return void
	 */
	public function test_empty_album_renders_nothing() {
		$album = $this->make_album();

		$this->assertSame( '', do_blocks( '<!-- wp:wavira/tracklist {"albumId":' . $album . '} /-->' ) );
		$this->assertSame( '', do_blocks( '<!-- wp:wavira/video {"videoId":' . $album . '} /-->' ) );
	}

	/**
	 * The video block renders a hosted file and a fallback link.
	 *
	 * @return void
	 */
	public function test_video_block_renders_hosted_and_embedded_sources() {
		$hosted = self::factory()->post->create(
			array(
				'post_type'   => 'wavira_video',
				'post_status' => 'publish',
				'post_title'  => 'Hosted video fixture',
			)
		);

		update_post_meta( $hosted, \Wavira\Core\Content\MetaSchema::VIDEO_720, 'https://example.com/clip-720.mp4' );

		$html = do_blocks( '<!-- wp:wavira/video {"videoId":' . $hosted . '} /-->' );

		$this->assertStringContainsString( '<video', $html );
		$this->assertStringContainsString( 'https://example.com/clip-720.mp4', $html );
		$this->assertStringContainsString( 'class="wavira-video-frame"', $html );

		$provider = self::factory()->post->create(
			array(
				'post_type'   => 'wavira_video',
				'post_status' => 'publish',
				'post_title'  => 'Provider video fixture',
			)
		);

		update_post_meta( $provider, \Wavira\Core\Content\MetaSchema::VIDEO_SOURCE, 'youtube' );
		update_post_meta( $provider, \Wavira\Core\Content\MetaSchema::VIDEO_URL, 'https://www.youtube.com/watch?v=wavira-demo' );

		// The provider is unreachable in the test environment (and must be: a unit
		// test never calls the network), so this asserts the degraded path — the
		// one a visitor gets when the embed provider is down or blocked.
		$offline = static function () {
			return new WP_Error( 'http_request_failed', 'Blocked during tests.' );
		};

		add_filter( 'pre_http_request', $offline );

		$linked = do_blocks( '<!-- wp:wavira/video {"videoId":' . $provider . '} /-->' );

		remove_filter( 'pre_http_request', $offline );

		// A provider WordPress cannot embed still gets a link, never an empty box.
		$this->assertStringContainsString( 'https://www.youtube.com/watch?v=wavira-demo', $linked );
		$this->assertStringContainsString( 'wavira-video-link', $linked );
	}

	/**
	 * The genre-chips block lists terms, most used first, and nothing else.
	 *
	 * @return void
	 */
	public function test_genre_chips_block_lists_terms() {
		$this->assertSame( '', do_blocks( '<!-- wp:wavira/genre-chips /-->' ), 'no terms means no output' );

		$popular = self::factory()->term->create(
			array(
				'taxonomy' => 'wavira_genre',
				'name'     => 'Popular genre',
			)
		);
		$rare    = self::factory()->term->create(
			array(
				'taxonomy' => 'wavira_genre',
				'name'     => 'Rare genre',
			)
		);

		wp_set_object_terms( $this->make_track( 'Track A' ), array( $popular ), 'wavira_genre' );
		wp_set_object_terms( $this->make_track( 'Track B' ), array( $popular ), 'wavira_genre' );
		wp_set_object_terms( $this->make_track( 'Track C' ), array( $rare ), 'wavira_genre' );

		$html = do_blocks( '<!-- wp:wavira/genre-chips {"limit":10,"showCount":true} /-->' );

		$this->assertStringContainsString( 'class="wavira-chip"', $html );
		$this->assertStringContainsString( 'Popular genre (2)', $html );
		$this->assertStringContainsString( 'Rare genre (1)', $html );
		$this->assertLessThan(
			strpos( $html, 'Rare genre' ),
			strpos( $html, 'Popular genre' ),
			'orderby=count must put the most used genre first'
		);
	}

	/**
	 * The player block emits the documented mount contract for a singular album.
	 *
	 * @return void
	 */
	public function test_player_block_emits_the_mount_contract() {
		$track = $this->make_track( 'Playable fixture' );
		$album = $this->make_album( array( $track ) );

		update_post_meta( $track, \Wavira\Core\Content\MetaSchema::AUDIO_320, 'https://example.com/track-320.mp3' );

		$this->go_to( get_permalink( $album ) );

		$html = do_blocks( '<!-- wp:wavira/player {"context":"album"} /-->' );

		$this->assertStringContainsString( 'data-wavira-player="1"', $html );
		$this->assertStringContainsString( 'data-context="album"', $html );
		$this->assertStringContainsString( 'data-id="' . $album . '"', $html );
		$this->assertStringContainsString( 'class="wavira-player', $html );
	}

	/**
	 * Block and shortcode output are the same markup (one implementation).
	 *
	 * @return void
	 */
	public function test_blocks_and_shortcodes_share_the_markup() {
		$track = $this->make_track(
			'Shared fixture',
			'publish',
			array(
				\Wavira\Core\Content\MetaSchema::DURATION    => 215,
				\Wavira\Core\Content\MetaSchema::SUBTITLE    => 'live take',
			)
		);
		$album = $this->make_album( array( $track ) );

		$block      = do_blocks( '<!-- wp:wavira/tracklist {"albumId":' . $album . '} /-->' );
		$shortcode  = do_shortcode( '[wavira_tracklist id="' . $album . '"]' );

		$this->assertSame( $block, $shortcode, 'a tracklist must not have two implementations' );
		$this->assertStringContainsString( '3:35', $block, 'the duration label is part of the shared markup' );
	}
}
