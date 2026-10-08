<?php
/**
 * The settings screen in the dashboard (0.13.0).
 *
 * 0.12.0's panel lived only in the Customizer, and on a block theme that is a
 * panel most customers never see — WordPress hides Appearance → Customize for
 * block themes, and "the theme settings" is not where anybody looks for a screen
 * called Customize. The screen this file tests is the fix, so the tests are about
 * the things that make it *safe* to have a second door onto the same settings:
 *
 * - the screen really is registered, under Appearance, behind the same
 *   capability the Customizer uses;
 * - it renders **every** field of **every** tab, so no setting can exist in one
 *   door and not the other;
 * - a submitted tab writes only its own fields, a value equal to its default is
 *   removed rather than stored, and garbage is refused exactly as it is in the
 *   Customizer (the sanitizer is the same function);
 * - nothing reaches the page without escaping;
 * - the demo-import tab tells the truth about the plugin instead of offering a
 *   button that would fail.
 *
 * The theme is loaded from the repository the way `tests/test-theme-options.php`
 * does it (`WP_UnitTestCase` restores `$wp_filter` between tests, so the hooks
 * are re-registered by name in `set_up()`).
 *
 * @package Wavira\Tests
 */

/**
 * Class Test_Admin_Panel
 */
class Test_Admin_Panel extends Wavira_Test_Case {

	/**
	 * Load the theme's option layer and the screen once for the class.
	 *
	 * @return void
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		if ( ! defined( 'WAVIRA_THEME_DIR' ) ) {
			define( 'WAVIRA_THEME_DIR', trailingslashit( dirname( __DIR__ ) . '/wavira' ) );
			define( 'WAVIRA_THEME_URI', 'https://example.test/wp-content/themes/wavira/' );
			define( 'WAVIRA_THEME_VERSION', '0.13.0-test' );
		}

		// The screen registers itself on `admin_menu` and prints through
		// `submit_button()`; both live in wp-admin, which the front-end test
		// bootstrap does not load.
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';

		foreach ( array( 'helpers', 'options', 'site-defaults', 'customizer', 'admin-panel' ) as $file ) {
			$path = WAVIRA_THEME_DIR . 'inc/' . $file . '.php';

			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}
	}

	/**
	 * An administrator, and no theme mod left over from a previous test.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		foreach ( array_keys( wavira_options_schema() ) as $key ) {
			remove_theme_mod( 'wavira_' . $key );
		}
	}

	/**
	 * Leave no theme mod behind for the next test.
	 *
	 * @return void
	 */
	public function tear_down() {
		foreach ( array_keys( wavira_options_schema() ) as $key ) {
			remove_theme_mod( 'wavira_' . $key );
		}

		delete_transient( 'wavira_activated_' . get_current_user_id() );

		parent::tear_down();
	}

	/**
	 * Capture what a callback prints.
	 *
	 * @param callable $callback Callback to run.
	 * @return string Output.
	 */
	private function capture( callable $callback ): string {
		ob_start();
		$callback();

		return (string) ob_get_clean();
	}

	/**
	 * The screen is a submenu of Appearance, behind `edit_theme_options`.
	 *
	 * @return void
	 */
	public function test_the_settings_screen_is_registered_under_appearance() {
		$GLOBALS['submenu'] = array();

		wavira_admin_panel_register();

		$entry = null;

		foreach ( ( isset( $GLOBALS['submenu']['themes.php'] ) ? (array) $GLOBALS['submenu']['themes.php'] : array() ) as $item ) {
			if ( isset( $item[2] ) && wavira_admin_panel_slug() === $item[2] ) {
				$entry = $item;
			}
		}

		$this->assertIsArray( $entry, 'the screen has to appear under Appearance' );
		$this->assertSame( __( 'Wavira settings', 'wavira' ), $entry[0], 'the menu says what it is' );
		$this->assertSame( 'edit_theme_options', $entry[1], 'the same capability the Customizer uses' );
	}

	/**
	 * A user who cannot edit theme options does not get the menu entry.
	 *
	 * @return void
	 */
	public function test_a_user_without_the_capability_gets_no_screen() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$GLOBALS['submenu'] = array();

		wavira_admin_panel_register();

		$entries = isset( $GLOBALS['submenu']['themes.php'] ) ? (array) $GLOBALS['submenu']['themes.php'] : array();

		foreach ( $entries as $item ) {
			$this->assertNotSame( wavira_admin_panel_slug(), $item[2], 'a subscriber has no business here' );
		}
	}

	/**
	 * The tabs are the Customizer's sections plus the demo importer.
	 *
	 * The two doors have to show the same furniture: a section that exists in one
	 * and not the other is a setting a customer can only find by accident.
	 *
	 * @return void
	 */
	public function test_the_tabs_are_the_sections_plus_the_demo_tab() {
		$tabs     = wavira_admin_panel_tabs();
		$sections = wavira_customize_sections();

		foreach ( array_keys( $sections ) as $slug ) {
			$this->assertArrayHasKey( $slug, $tabs, "section {$slug} has a tab" );
			$this->assertSame( $sections[ $slug ]['title'], $tabs[ $slug ], "tab {$slug} reuses the section title" );
		}

		$this->assertArrayHasKey( 'demo', $tabs, 'the demo import has its own tab' );
		$this->assertSame( 'demo', array_key_last( $tabs ), 'and it is last: every other tab is a setting' );

		// No field may point at a tab that does not exist.
		foreach ( wavira_options_schema() as $key => $field ) {
			$this->assertArrayHasKey( $field['section'], $tabs, "{$key} lives in a tab that exists" );
		}
	}

	/**
	 * Every field renders, on its own tab, with the value it holds.
	 *
	 * @return void
	 */
	public function test_every_field_renders_on_its_tab() {
		set_theme_mod( 'wavira_container_width', 1400 );

		foreach ( wavira_options_schema() as $key => $field ) {
			$html = $this->capture(
				static function () use ( $key, $field ) {
					wavira_admin_panel_field( (string) $key, $field );
				}
			);

			$this->assertStringContainsString( 'name="wavira_' . $key . '"', $html, "{$key} is posted" );
			$this->assertStringContainsString( 'id="wavira-field-' . $key . '"', $html, "{$key} has a label target" );

			if ( 'container_width' === $key ) {
				$this->assertStringContainsString( 'value="1400"', $html, 'the saved value is what the field shows' );
			}
		}
	}

	/**
	 * Nothing a visitor can type is printed back unescaped.
	 *
	 * @return void
	 */
	public function test_no_stored_value_reaches_the_page_unescaped() {
		$nasty = '"><script>alert(1)</script>';

		set_theme_mod( 'wavira_top_bar_text', $nasty );
		set_theme_mod( 'wavira_footer_note', $nasty );

		$schema = wavira_options_schema();

		foreach ( array( 'top_bar_text', 'footer_note' ) as $key ) {
			$html = $this->capture(
				static function () use ( $key, $schema ) {
					wavira_admin_panel_field( $key, $schema[ $key ] );
				}
			);

			$this->assertStringNotContainsString( '<script>', $html, "{$key} cannot break out of the field" );
			$this->assertStringContainsString( '&lt;script&gt;', $html, "{$key} is escaped, not dropped" );
		}
	}

	/**
	 * Only the posted tab is written.
	 *
	 * @return void
	 */
	public function test_saving_one_tab_leaves_the_others_alone() {
		set_theme_mod( 'wavira_container_width', 1100 );

		// The type tab as a browser posts it: the choice fields holding what
		// they already hold, the checkbox on, one number changed — plus a field
		// that belongs to another tab, which this tab has no business writing.
		$changed = wavira_admin_panel_apply(
			'type',
			array(
				'wavira_font_family'     => 'vazirmatn',
				'wavira_font_base_size'  => '18',
				'wavira_heading_weight'  => '700',
				'wavira_font_preload'    => '1',
				'wavira_container_width' => '1500',
			)
		);

		$this->assertSame( 1, $changed, 'only the field that differs from its default was written' );
		$this->assertSame( 18, wavira_option( 'font_base_size' ) );
		$this->assertSame( 1100, wavira_option( 'container_width' ), 'the appearance tab was not posted, so it was not touched' );
	}

	/**
	 * A tab this screen does not have writes nothing at all.
	 *
	 * @return void
	 */
	public function test_an_unknown_tab_writes_nothing() {
		$changed = wavira_admin_panel_apply(
			'wavira_everything',
			array(
				'wavira_font_base_size' => '22',
				'wavira_top_bar_text'   => 'hello',
			)
		);

		$this->assertSame( 0, $changed );
		$this->assertSame( 16, wavira_option( 'font_base_size' ), 'the default is unchanged' );
		$this->assertSame( '', wavira_option( 'top_bar_text' ) );
	}

	/**
	 * A value equal to the declared default is removed, not stored.
	 *
	 * @return void
	 */
	public function test_a_default_value_is_not_stored() {
		// The header tab as a browser posts it: three switches are on by
		// default and the announcement bar is off, so switching it on is the one
		// difference on the tab.
		$changed = wavira_admin_panel_apply(
			'header',
			array(
				'wavira_sticky_header' => '1',
				'wavira_header_search' => '1',
				'wavira_player_bar'    => '1',
				'wavira_top_bar'       => '1',
			)
		);

		$this->assertSame( 1, $changed, 'only the announcement bar differs from its default' );
		$this->assertTrue( get_theme_mod( 'wavira_top_bar' ), 'a non-default value is stored' );

		// Back to the default. The announcement bar is unchecked now, so it is
		// not posted at all — absence is the value — and the switches that are
		// on by default are posted the way a browser posts them.
		$changed = wavira_admin_panel_apply(
			'header',
			array(
				'wavira_sticky_header' => '1',
				'wavira_header_search' => '1',
				'wavira_player_bar'    => '1',
			)
		);

		$this->assertSame( 1, $changed, 'only the announcement bar row is removed' );
		$this->assertSame(
			'missing',
			get_theme_mod( 'wavira_top_bar', 'missing' ),
			'the row is removed, not stored as false: the fallback is what comes back'
		);
		$this->assertFalse( wavira_option( 'top_bar' ), 'and the default applies again' );
	}

	/**
	 * A checkbox that is unchecked is a value, not silence.
	 *
	 * The one field type where an absent key means something: a browser does not
	 * post an unchecked box, so "not posted" is "off". A switch whose default is
	 * on therefore stores `false` when the owner clears it — the screen is not
	 * allowed to read that as "leave it alone", which would make the box
	 * impossible to turn off.
	 *
	 * @return void
	 */
	public function test_an_unchecked_box_whose_default_is_on_is_stored_as_off() {
		$changed = wavira_admin_panel_apply( 'header', array( 'wavira_top_bar' => '1' ) );

		$this->assertSame( 4, $changed, 'three switches went off, the announcement bar went on' );
		$this->assertFalse( wavira_option( 'sticky_header' ), 'the box was not posted, so it is off' );
		$this->assertTrue( wavira_option( 'top_bar' ) );
	}

	/**
	 * Garbage is refused the same way, because it is the same sanitizer.
	 *
	 * @return void
	 */
	public function test_the_screen_refuses_what_the_customizer_refuses() {
		wavira_admin_panel_apply(
			'appearance',
			array(
				'wavira_container_width' => 'not a number at all',
				'wavira_radius'          => '9999',
				'wavira_accent'          => 'javascript:alert(1)',
				'wavira_colour_mode'     => 'neon',
			)
		);

		$this->assertSame( 1200, wavira_option( 'container_width' ), 'a non-number falls back to the default' );
		$this->assertSame( 32, wavira_option( 'radius' ), 'a number is clamped to the declared maximum' );
		$this->assertSame( '', wavira_option( 'accent' ), 'a colour that is not a colour is refused' );
		$this->assertSame( 'auto', wavira_option( 'colour_mode' ), 'a choice outside the list is refused' );

		// Custom CSS cannot end the element it is printed in, in either door.
		wavira_admin_panel_apply( 'tools', array( 'wavira_custom_css' => '</style><script>x</script>' ) );

		$this->assertStringNotContainsString( '<', (string) wavira_option( 'custom_css' ) );
	}

	/**
	 * The whole screen renders, on every tab, without a fatal.
	 *
	 * @return void
	 */
	public function test_the_screen_renders_on_every_tab() {
		// `esc_url()` percent-encodes the brackets and writes `&` as `&#038;`,
		// so the expectation is the escaped value — the same string the screen
		// prints, and the one a browser follows.
		$custom = esc_url( admin_url( 'customize.php?autofocus[panel]=wavira' ) );

		try {
			foreach ( array_keys( wavira_admin_panel_tabs() ) as $tab ) {
				$_GET['tab'] = $tab;

				$html = $this->capture( 'wavira_admin_panel_render' );

				$this->assertStringContainsString( 'nav-tab-wrapper', $html );
				$this->assertStringContainsString( esc_url( wavira_admin_panel_url( $tab ) ), $html, "{$tab} is reachable from the nav" );
				$this->assertStringContainsString( $custom, $html, 'the live preview is one click away' );
			}
		} finally {
			// A test that leaves `$_GET` behind is a test that decides the next
			// one's behaviour — including when it fails.
			unset( $_GET['tab'] );
		}
	}

	/**
	 * The settings screen is where the Persian notice points.
	 *
	 * @return void
	 */
	public function test_the_persian_notice_is_shown_on_the_settings_screen() {
		$screens = wavira_persian_notice_screens();

		$this->assertContains( 'appearance_page_' . wavira_admin_panel_slug(), $screens, 'the settings screen' );
		$this->assertContains( 'tools_page_wavira-demo', $screens, 'the demo screen is under Tools, and its id says so' );
		$this->assertNotContains( 'appearance_page_wavira-demo', $screens, '0.12.0 guessed the wrong screen id here' );

		// The notice speaks only while the site language is not Persian yet, so
		// the locale is pinned instead of being whatever the test site happens
		// to run: a test that passes because the environment is English, and
		// fails on a Persian CI site, is a test that reports the environment.
		$pin_english = static function () {
			return 'en_US';
		};

		add_filter( 'locale', $pin_english );

		// A real screen, set the way WordPress sets one. Two reasons it cannot be
		// a stand-in object: `get_current_screen()` returns null unless the
		// global is a `WP_Screen` (since WordPress 6.8), and `is_admin()` calls
		// `$GLOBALS['current_screen']->in_admin()` whenever that global is set —
		// so a stub here would not only feed this test, it would break the next
		// one. The global is put back in `finally`, because the assertions below
		// can throw.
		$previous = isset( $GLOBALS['current_screen'] ) ? $GLOBALS['current_screen'] : null;

		set_current_screen( 'appearance_page_' . wavira_admin_panel_slug() );

		try {
			delete_user_meta( get_current_user_id(), 'wavira_persian_notice' );

			$html = $this->capture( 'wavira_persian_admin_notice' );

			$this->assertStringContainsString( esc_url( wavira_admin_panel_url() ), $html, 'the notice offers the settings screen' );
			$this->assertStringContainsString( esc_url( wavira_persian_setup_url() ), $html, 'and the Persian setup' );

			// A screen the notice does not belong to prints nothing at all.
			set_current_screen( 'edit-post' );

			$this->assertSame( '', $this->capture( 'wavira_persian_admin_notice' ), 'the notice stays off other screens' );
		} finally {
			if ( $previous ) {
				$GLOBALS['current_screen'] = $previous;
			} else {
				unset( $GLOBALS['current_screen'] );
			}

			remove_filter( 'locale', $pin_english );
		}
	}

	/**
	 * The demo tab states whether the plugin is there.
	 *
	 * In this suite the plugin is loaded, so the tab has to offer the real
	 * importer rather than an install link.
	 *
	 * @return void
	 */
	public function test_the_demo_tab_points_at_the_plugin_when_it_is_active() {
		$html = $this->capture( 'wavira_admin_panel_demo_tab' );

		if ( function_exists( 'wavira_core_is_active' ) && wavira_core_is_active() ) {
			$this->assertStringContainsString( 'tools.php?page=wavira-demo', $html, 'the importer is one click away' );
		} else {
			$this->assertStringContainsString( 'plugin-install.php?tab=upload', $html, 'the plugin can be installed from here' );
		}

		$this->assertStringContainsString( 'no audio or video', strtolower( $html ), 'the one thing the demo does not do is said out loud' );
		$this->assertStringContainsString( esc_url( wavira_persian_setup_url() ), $html, 'the Persian setup lives here too' );
	}

	/**
	 * Activating the theme leaves exactly one note, for one user, to be shown once.
	 *
	 * @return void
	 */
	public function test_activation_leaves_one_dismissible_note() {
		$key = 'wavira_activated_' . get_current_user_id();

		delete_transient( $key );

		wavira_admin_panel_activated();

		$this->assertSame( 'new', get_transient( $key ) );

		// A visitor without the capability gets nothing to dismiss.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$other = 'wavira_activated_' . get_current_user_id();

		delete_transient( $other );

		wavira_admin_panel_activated();

		$this->assertFalse( get_transient( $other ) );
	}
}
