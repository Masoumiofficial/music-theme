<?php
/**
 * Runtime verification of the performance layer (phase 0.10.0).
 *
 * `tools/check-perf.mjs` measures the bundles; it cannot measure what a request
 * asks a visitor to download or whether the theme fights core's image
 * optimisation. Those are the claims here: no third-party hint, no override of
 * core's `loading`/`fetchpriority` decision, the player bundle only on request,
 * and the theme script deferred.
 *
 * @package Wavira\Tests
 */

/**
 * Class Test_Performance
 */
class Test_Performance extends Wavira_Test_Case {

	/**
	 * Load the theme's performance and asset PHP once for the class.
	 *
	 * @return void
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		if ( ! defined( 'WAVIRA_THEME_DIR' ) ) {
			define( 'WAVIRA_THEME_DIR', trailingslashit( dirname( __DIR__ ) . '/wavira' ) );
			define( 'WAVIRA_THEME_URI', 'https://example.test/wp-content/themes/wavira/' );
			define( 'WAVIRA_THEME_VERSION', '0.10.0-test' );
		}

		foreach ( array( 'helpers', 'markup', 'hooks', 'performance', 'assets' ) as $file ) {
			$path = WAVIRA_THEME_DIR . 'inc/' . $file . '.php';

			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}
	}

	/**
	 * The emoji CDN hint is the one third-party request WordPress adds.
	 *
	 * @return void
	 */
	public function test_sw_org_resource_hint_is_removed() {
		$hints = array(
			array( 'href' => 'https://s.w.org/' ),
			array( 'href' => 'https://example.org/' ),
		);

		$kept = wavira_resource_hints( $hints, 'dns-prefetch' );

		$this->assertCount( 1, $kept );
		$this->assertSame( 'https://example.org/', $kept[0]['href'] );

		// Other relation types are not this filter's business.
		$this->assertSame( $hints, wavira_resource_hints( $hints, 'preconnect' ) );
	}

	/**
	 * The theme adds no loading attribute of its own.
	 *
	 * If this ever regresses (`'loading' => 'lazy'` on the hero image), core's
	 * LCP promotion is cancelled silently — the exact regression 0.10.0 removed —
	 * so the test compares the theme's output with core's output for the same
	 * arguments: they must be identical, attribute for attribute.
	 *
	 * @return void
	 */
	public function test_theme_images_leave_loading_attributes_to_core() {
		$post       = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$attachment = self::factory()->attachment->create_object(
			'cover.jpg',
			$post,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A cover',
			)
		);

		update_post_meta( $attachment, '_wp_attachment_image_alt', 'Demo alt' );

		$args = array(
			'class' => 'wavira-card__image',
			'alt'   => 'Demo alt',
		);

		$theme = wavira_get_image( array( 'id' => $attachment, 'alt' => 'Demo alt' ), 'thumbnail', 'wavira-card__image' );
		$core  = wp_get_attachment_image( $attachment, 'thumbnail', false, $args );

		$this->assertSame( $core, $theme, 'wavira_get_image() adds nothing of its own to a core image' );
		$this->assertStringContainsString( 'wavira-card__image', $theme );
		$this->assertStringContainsString( 'Demo alt', $theme );
	}

	/**
	 * A payload without an attachment still renders, with the documented fallback.
	 *
	 * @return void
	 */
	public function test_bare_url_images_still_render_lazily() {
		$markup = wavira_get_image(
			array(
				'url' => 'https://example.org/cover.jpg',
				'alt' => '',
			),
			'thumbnail',
			'wavira-gallery__image'
		);

		$this->assertStringContainsString( 'src="https://example.org/cover.jpg"', $markup );
		$this->assertStringContainsString( 'loading="lazy"', $markup );

		$this->assertSame( '', wavira_get_image( array(), 'thumbnail', 'x' ) );
	}

	/**
	 * The emoji script and styles are gone from the front end and the admin.
	 *
	 * @return void
	 */
	public function test_emoji_assets_are_removed_everywhere() {
		// A hook cannot be asserted here: WordPress's test case snapshots
		// `$wp_filter` once per process and restores it after every test
		// (`_backup_hooks()` behind a static flag), so a theme file loaded after
		// the first test can never leave a callback behind. The *effect* of the
		// two functions is asserted directly, and the wiring is locked in the
		// source below — which is where a live site reads it from.
		$this->assertTrue( function_exists( 'wavira_disable_emoji_assets' ) );
		$this->assertTrue( function_exists( 'wavira_disable_emojis_everywhere' ) );

		$hooks = (string) file_get_contents( WAVIRA_THEME_DIR . 'inc/hooks.php' );
		$perf  = (string) file_get_contents( WAVIRA_THEME_DIR . 'inc/performance.php' );

		$this->assertStringContainsString( "add_action( 'init', 'wavira_disable_emoji_assets' )", $hooks );
		$this->assertStringContainsString( "add_action( 'init', 'wavira_disable_emojis_everywhere', 20 )", $perf );

		wavira_disable_emoji_assets();
		wavira_disable_emojis_everywhere();

		$this->assertFalse( has_action( 'wp_head', 'print_emoji_detection_script' ) );
		$this->assertFalse( has_action( 'wp_print_styles', 'print_emoji_styles' ) );
		$this->assertFalse( has_action( 'admin_print_scripts', 'print_emoji_detection_script' ) );
		$this->assertFalse( has_action( 'admin_print_styles', 'print_emoji_styles' ) );
		$this->assertFalse( has_filter( 'wp_mail', 'wp_staticize_emoji_for_email' ) );
	}

	/**
	 * The player bundle loads on request, never on every page.
	 *
	 * The un-built case is the other half of the contract: with no built file the
	 * handle is not registered, and asking for it reports false instead of
	 * printing a 404 to every visitor.
	 *
	 * @return void
	 */
	public function test_player_bundle_loads_only_on_request() {
		if ( ! wp_script_is( 'wavira-player', 'registered' ) ) {
			$this->assertFalse( wavira_core_enqueue_player(), 'an un-built checkout reports false' );

			return;
		}

		wp_dequeue_script( 'wavira-player' );
		wp_dequeue_style( 'wavira-player' );

		$this->assertFalse( wp_script_is( 'wavira-player', 'enqueued' ) );

		$this->assertTrue( wavira_core_enqueue_player() );
		$this->assertTrue( wp_script_is( 'wavira-player', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'wavira-player', 'enqueued' ), 'the player stylesheet comes with it' );
	}

	/**
	 * The theme script is deferred, and an un-built theme enqueues nothing.
	 *
	 * @return void
	 */
	public function test_theme_script_is_deferred_when_built() {
		wavira_enqueue_assets();

		$built = file_exists( WAVIRA_THEME_DIR . 'assets/dist/theme.js' );

		if ( ! $built ) {
			$this->assertFalse( wp_script_is( 'wavira-theme', 'registered' ), 'an un-built theme registers no script' );

			return;
		}

		$this->assertTrue( wp_script_is( 'wavira-theme', 'enqueued' ) );

		$registered = wp_scripts()->registered;

		$this->assertArrayHasKey( 'wavira-theme', $registered );
		$this->assertSame( 'defer', $registered['wavira-theme']->extra['strategy'] ?? '', 'the theme script is deferred' );
	}
}
