<?php
/**
 * The theme options panel on a real WordPress (0.12.0).
 *
 * The test install does not contain the theme directory, so the theme's PHP is
 * loaded from the repository with the same constants its bootstrap defines — the
 * pattern `tests/test-blocks.php` already uses. Everything after that is real:
 * `WP_Customize_Manager` is core's own, `get_theme_mod()` is core's own, and the
 * patterns are included exactly the way core includes a theme pattern file.
 *
 * What is asserted here is the part of the panel that can be wrong quietly:
 * a field nothing reads, a setting without a sanitizer, a pattern that prints
 * markup for a switch that is off, a value that reaches the page unescaped.
 *
 * @package Wavira\Tests
 */

/**
 * Class Test_Theme_Options
 */
class Test_Theme_Options extends Wavira_Test_Case {

	/**
	 * Site options the Persian setup changes, restored after every test.
	 *
	 * @var string[]
	 */
	const SITE_OPTIONS = array( 'WPLANG', 'timezone_string', 'start_of_week', 'date_format', 'time_format' );

	/**
	 * Values those options had before the test.
	 *
	 * @var array<string, mixed>
	 */
	private $site_options = array();

	/**
	 * Load the theme's option layer once for the class.
	 *
	 * @return void
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		if ( ! defined( 'WAVIRA_THEME_DIR' ) ) {
			define( 'WAVIRA_THEME_DIR', trailingslashit( dirname( __DIR__ ) . '/wavira' ) );
			define( 'WAVIRA_THEME_URI', 'https://example.test/wp-content/themes/wavira/' );
			define( 'WAVIRA_THEME_VERSION', '0.12.0-test' );
		}

		foreach ( array( 'helpers', 'options', 'site-defaults', 'customizer', 'assets' ) as $file ) {
			$path = WAVIRA_THEME_DIR . 'inc/' . $file . '.php';

			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}
	}

	/**
	 * Re-register the theme's hooks before each test.
	 *
	 * The core test library restores `$wp_filter` after every test to a snapshot
	 * taken before this file included the theme, so a hook the theme registered
	 * at include time is gone by the second test — the functions stay, the
	 * registrations do not. These are the theme's own callbacks and priorities,
	 * re-added rather than re-included (including the files again would
	 * redeclare their functions).
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		add_filter( 'body_class', 'wavira_option_body_classes' );
		add_filter( 'render_block', 'wavira_filter_player_bar', 10, 2 );
		add_filter( 'get_custom_logo', 'wavira_custom_logo' );
		add_action( 'customize_register', 'wavira_customize_register' );

		// The Persian setup writes site options, not theme mods. A test that left
		// the site in Persian would change what every later test measures.
		foreach ( self::SITE_OPTIONS as $option ) {
			$this->site_options[ $option ] = get_option( $option );
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

		foreach ( $this->site_options as $option => $value ) {
			if ( false === $value ) {
				delete_option( $option );
			} else {
				update_option( $option, $value );
			}
		}

		$this->site_options = array();

		parent::tear_down();
	}

	/**
	 * The schema is the single source of truth, and it is complete.
	 *
	 * @return void
	 */
	public function test_schema_every_field_is_renderable_and_known() {
		$schema   = wavira_options_schema();
		$sections = array_keys( wavira_customize_sections() );

		$this->assertGreaterThan( 20, count( $schema ), 'the panel is not the three fields of a starter theme' );

		foreach ( $schema as $key => $field ) {
			$this->assertIsString( $key );

			foreach ( array( 'default', 'type', 'control', 'section', 'label' ) as $required ) {
				$this->assertArrayHasKey( $required, $field, "{$key}: {$required}" );
			}

			$this->assertArrayHasKey( $field['section'], array_flip( $sections ), "{$key} lives in a declared section" );
			$this->assertNotSame( '', (string) $field['label'], "{$key} has a label" );
			$this->assertContains(
				$field['control'],
				array( 'text', 'textarea', 'number', 'url', 'checkbox', 'select', 'color', 'media', 'code' ),
				"{$key}: known control type"
			);
		}
	}

	/**
	 * Every type in the schema has a sanitizer that holds the line.
	 *
	 * @return void
	 */
	public function test_sanitizers_never_store_what_arrived() {
		$schema = wavira_options_schema();

		foreach ( $schema as $key => $field ) {
			$clean = wavira_sanitize_value( $field, 'not a value at all' );
			$type  = gettype( $clean );

			switch ( $field['type'] ) {
				case 'int':
					$this->assertSame( $field['default'], $clean, "{$key}: garbage becomes the default" );
					break;
				case 'bool':
					$this->assertIsBool( $clean, "{$key} is a boolean" );
					break;
				case 'choice':
					$this->assertArrayHasKey( (string) $clean, $field['choices'], "{$key}: garbage becomes a declared choice" );
					break;
				case 'color':
					$this->assertSame( $field['default'], $clean, "{$key}: garbage becomes the default" );
					break;
				case 'attachment':
					$this->assertSame( 0, $clean, "{$key}: garbage becomes zero" );
					break;
				default:
					$this->assertIsString( $clean, "{$key} is a string ({$type})" );
			}
		}

		$this->assertSame(
			(int) $schema['logo_width']['max'],
			wavira_sanitize_value( $schema['logo_width'], '900' ),
			'numbers are clamped to the declared maximum'
		);
		$this->assertSame(
			(int) $schema['logo_width']['min'],
			wavira_sanitize_value( $schema['logo_width'], '-10' ),
			'numbers are clamped to the declared minimum'
		);
		$this->assertSame( '#5636d6', wavira_sanitize_value( $schema['accent'], '#5636d6' ) );
		$this->assertSame( '', wavira_sanitize_value( $schema['accent'], 'not-a-colour' ) );
		$this->assertFalse( wavira_sanitize_value( $schema['sticky_header'], 'maybe' ) );
		$this->assertTrue( wavira_sanitize_value( $schema['sticky_header'], '1' ) );
	}

	/**
	 * The free-CSS field cannot end the element it is printed in.
	 *
	 * @return void
	 */
	public function test_custom_css_cannot_break_out_of_its_style_element() {
		$schema = wavira_options_schema();
		$hostile = 'body{color:red}</style><script>alert(1)</script>';

		$clean = wavira_sanitize_value( $schema['custom_css'], $hostile );

		$this->assertStringNotContainsString( '</style', $clean );
		$this->assertStringNotContainsString( '<', $clean );
		$this->assertStringContainsString( 'body{color:red}', $clean, 'the CSS itself survives' );

		$stack = wavira_sanitize_value( $schema['font_stack'], '"X";}html{display:none}' );

		$this->assertStringNotContainsString( ';', $stack );
		$this->assertStringNotContainsString( '{', $stack );
		$this->assertStringNotContainsString( '}', $stack );
	}

	/**
	 * An unknown key never invents a value.
	 *
	 * @return void
	 */
	public function test_unknown_options_do_not_invent_values() {
		$this->assertNull( wavira_option( 'does_not_exist' ) );
		$this->assertSame( 'fallback', wavira_option( 'does_not_exist', 'fallback' ) );
		$this->assertSame( 'nope', wavira_option_default( 'does_not_exist', 'nope' ) );
	}

	/**
	 * A default site prints no option CSS at all.
	 *
	 * The single most useful assertion about this feature: a site that changed
	 * nothing pays nothing.
	 *
	 * @return void
	 */
	public function test_a_default_site_gets_no_extra_css() {
		$this->assertSame( '', wavira_option_css() );
		$this->assertSame( array(), wavira_social_links() );
	}

	/**
	 * Every switch reaches the page, and only when it is set.
	 *
	 * @return void
	 */
	public function test_settings_reach_the_front_end() {
		set_theme_mod( 'wavira_accent', '#123456' );
		set_theme_mod( 'wavira_container_width', 1080 );
		set_theme_mod( 'wavira_heading_weight', '800' );
		set_theme_mod( 'wavira_sticky_header', false );
		set_theme_mod( 'wavira_card_shadow', false );

		$css = wavira_option_css();

		$this->assertStringContainsString( '--wp--preset--color--primary:#123456', $css );
		$this->assertStringContainsString( '--wavira-container:1080px', $css );
		$this->assertStringContainsString( '--wavira-cover-shadow:none', $css );
		$this->assertStringContainsString( 'body.wavira-heading-800 h2', $css, 'heading weight is a scoped rule' );
		$this->assertStringNotContainsString( '!important', $css );

		$classes = apply_filters( 'body_class', array() );

		$this->assertContains( 'wavira-not-sticky', $classes );
		$this->assertContains( 'wavira-heading-800', $classes );
		$this->assertNotContains( 'wavira-no-title', $classes, 'an untouched switch adds no class' );

		set_theme_mod( 'wavira_show_site_title', false );

		$this->assertContains( 'wavira-no-title', apply_filters( 'body_class', array() ) );
	}

	/**
	 * The colour mode is the printed fallback, not a hard-coded `auto`.
	 *
	 * @return void
	 */
	public function test_the_colour_mode_setting_is_what_the_page_starts_with() {
		$this->assertSame( 'auto', wavira_theme_settings()['colourMode'] );

		set_theme_mod( 'wavira_colour_mode', 'dark' );

		$this->assertSame( 'dark', wavira_theme_settings()['colourMode'] );

		ob_start();
		wavira_print_colour_mode();
		$script = (string) ob_get_clean();

		$this->assertStringContainsString( '"dark"', $script );
	}

	/**
	 * The font switch decides the stack and the preload together.
	 *
	 * @return void
	 */
	public function test_the_font_switch_decides_stack_and_preload() {
		$this->assertTrue( wavira_preloads_font() );
		$this->assertStringContainsString( 'Vazirmatn', wavira_font_stack() );

		set_theme_mod( 'wavira_font_family', 'system' );

		$this->assertFalse( wavira_preloads_font(), 'a font nothing uses is not preloaded' );
		$this->assertStringNotContainsString( 'Vazirmatn', wavira_font_stack() );

		set_theme_mod( 'wavira_font_family', 'custom' );
		set_theme_mod( 'wavira_font_stack', '"IRANSans", Tahoma, sans-serif' );

		$this->assertSame( '"IRANSans", Tahoma, sans-serif', wavira_font_stack() );
		$this->assertStringContainsString( '--wp--preset--font-family--body:', wavira_option_css() );

		remove_theme_mod( 'wavira_font_stack' );

		$this->assertStringContainsString( 'Vazirmatn', wavira_font_stack(), 'custom without a stack falls back to the bundled font' );

		set_theme_mod( 'wavira_font_family', 'vazirmatn' );
		set_theme_mod( 'wavira_font_preload', false );

		$this->assertFalse( wavira_preloads_font() );
	}

	/**
	 * A social field is a link, or it renders as nothing.
	 *
	 * Two guarantees, in the two places they exist. The Customizer refuses a
	 * scheme the site did not ask for (that is `wavira_sanitize_value()`), and
	 * whatever else ends up in the option — another plugin, an import, a
	 * database edit — is escaped where it is printed. `set_theme_mod()` writes
	 * the raw value on purpose: a test that sanitized first would prove nothing
	 * about the second guarantee.
	 *
	 * @return void
	 */
	public function test_social_links_are_safe_and_optional() {
		$schema = wavira_options_schema();

		$this->assertSame( '', wavira_sanitize_value( $schema['social_telegram'], 'javascript:alert(1)' ), 'the Customizer refuses a foreign scheme' );

		set_theme_mod( 'wavira_social_instagram', 'https://instagram.com/wavira' );
		set_theme_mod( 'wavira_social_telegram', 'javascript:alert(1)' );

		$links = wavira_social_links();

		$this->assertCount( 2, $links, 'the option holds what was stored' );
		$this->assertSame( 'https://instagram.com/wavira', $links[0]['url'] );
		$this->assertSame( 'instagram', $links[0]['icon'] );

		$row = $this->pattern_output( 'hidden-social-links' );

		$this->assertStringContainsString( 'https://instagram.com/wavira', $row );
		$this->assertStringNotContainsString( 'javascript:', $row, 'the link is escaped where it is printed' );
	}

	/**
	 * The four optional surfaces, each rendered through its own file.
	 *
	 * @return void
	 */
	public function test_optional_surfaces_render_only_when_they_are_on() {
		// Back to top: on by default, off on request.
		$this->assertStringContainsString( 'data-wavira-to-top', $this->pattern_output( 'hidden-back-to-top' ) );

		set_theme_mod( 'wavira_back_to_top', false );

		$this->assertSame( '', $this->pattern_output( 'hidden-back-to-top' ) );

		// Theme toggle: printed by default, silent when hidden.
		$this->assertStringContainsString( 'data-wavira-theme-toggle', $this->pattern_output( 'hidden-theme-toggle' ) );

		set_theme_mod( 'wavira_show_theme_toggle', false );

		$this->assertSame( '', $this->pattern_output( 'hidden-theme-toggle' ) );

		// Announcement bar: needs the switch and something to say.
		$this->assertSame( '', $this->pattern_output( 'hidden-top-bar' ) );

		set_theme_mod( 'wavira_top_bar', true );
		set_theme_mod( 'wavira_top_bar_text', 'آلبوم تازه' );
		set_theme_mod( 'wavira_top_bar_url', 'https://example.test/album' );

		$bar = $this->pattern_output( 'hidden-top-bar' );

		$this->assertStringContainsString( 'wavira-topbar', $bar );
		$this->assertStringContainsString( 'https://example.test/album', $bar );
		$this->assertStringContainsString( 'آلبوم تازه', $bar );

		set_theme_mod( 'wavira_top_bar_text', '' );

		$this->assertStringContainsString( 'https://example.test/album', $this->pattern_output( 'hidden-top-bar' ), 'the link alone keeps the bar' );

		set_theme_mod( 'wavira_top_bar', false );

		$this->assertSame( '', $this->pattern_output( 'hidden-top-bar' ) );

		// Search: the core block, only when the switch is on.
		$this->assertStringContainsString( 'wp:search', $this->pattern_output( 'hidden-header-search' ) );

		set_theme_mod( 'wavira_header_search', false );

		$this->assertSame( '', $this->pattern_output( 'hidden-header-search' ) );

		// Social row: one icon per filled field.
		$this->assertSame( '', $this->pattern_output( 'hidden-social-links' ) );

		set_theme_mod( 'wavira_social_telegram', 'https://t.me/wavira' );

		$social = $this->pattern_output( 'hidden-social-links' );

		$this->assertStringContainsString( 'https://t.me/wavira', $social );
		$this->assertStringContainsString( 'aria-label="' . esc_attr( __( 'Telegram', 'wavira' ) ) . '"', $social );
		$this->assertStringContainsString( 'rel="noopener"', $social );
		$this->assertStringContainsString( '<svg', $social, 'the icon ships with the theme' );
	}

	/**
	 * The player bar is left out of the page, not hidden with CSS.
	 *
	 * @return void
	 */
	public function test_the_player_bar_can_be_removed_from_the_output() {
		// The wiring first: core's `render_block` filter, priority 10, two
		// arguments. Dispatching through `apply_filters()` here would run every
		// other `render_block` callback in core — which is core's business, and
		// which changes between releases (as it did between 6.7 and 7.1).
		$this->assertSame( 10, has_filter( 'render_block', 'wavira_filter_player_bar' ) );

		$part = array(
			'blockName' => 'core/template-part',
			'attrs'     => array( 'slug' => 'player-bar' ),
		);

		$this->assertSame( 'rendered', wavira_filter_player_bar( 'rendered', $part ) );

		set_theme_mod( 'wavira_player_bar', false );

		$this->assertSame( '', wavira_filter_player_bar( 'rendered', $part ) );
		$this->assertSame(
			'rendered',
			wavira_filter_player_bar( 'rendered', array( 'blockName' => 'core/template-part', 'attrs' => array( 'slug' => 'header' ) ) ),
			'other template parts are untouched'
		);
		$this->assertSame(
			'rendered',
			wavira_filter_player_bar( 'rendered', array( 'blockName' => 'core/paragraph', 'attrs' => array( 'slug' => 'player-bar' ) ) ),
			'a block that is not a template part is untouched'
		);
	}

	/**
	 * The Customizer panel is registered from the schema.
	 *
	 * @return void
	 */
	public function test_the_customizer_panel_matches_the_schema() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// WordPress loads the Customizer in the admin, not on a front-end
		// request — and the suite boots a front-end request. The manager's own
		// constructor pulls in the panels, sections and controls it needs.
		if ( ! class_exists( 'WP_Customize_Manager' ) ) {
			require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
		}

		$wp_customize = new WP_Customize_Manager();
		do_action( 'customize_register', $wp_customize );

		$panel = $wp_customize->get_panel( 'wavira' );

		$this->assertNotNull( $panel, 'the panel exists' );
		$this->assertSame( 'edit_theme_options', $panel->capability );

		foreach ( wavira_customize_sections() as $slug => $section ) {
			$this->assertNotNull( $wp_customize->get_section( 'wavira_' . $slug ), "section {$slug}" );
		}

		foreach ( wavira_options_schema() as $key => $field ) {
			$setting = $wp_customize->get_setting( 'wavira_' . $key );
			$control = $wp_customize->get_control( 'wavira_' . $key );

			$this->assertNotNull( $setting, "setting {$key}" );
			$this->assertNotNull( $control, "control {$key}" );
			$this->assertSame( 'wavira_sanitize_option', $setting->sanitize_callback, "{$key}: one sanitizer for every field" );
			$this->assertSame( 'theme_mod', $setting->type );
			$this->assertSame( 'edit_theme_options', $setting->capability );
			$this->assertSame( 'wavira_' . $field['section'], $control->section, "{$key} sits in its declared section" );
			$this->assertSame( $field['label'], $control->label, "{$key} shows its schema label" );
		}

		// The sanitizer runs against the real setting, not a copy of the rules.
		$accent = $wp_customize->get_setting( 'wavira_accent' );

		$this->assertSame( '#0a0b0c', $accent->sanitize( '#0a0b0c' ) );
		$this->assertSame( '', $accent->sanitize( 'red; background:url(x)' ) );
	}

	/**
	 * The Persian action is nonced, and the site defaults it applies are real.
	 *
	 * The language-pack download is not exercised here: it needs the network and
	 * the host decides. The branch is covered by `wavira_has_core_language_pack()`
	 * below, which is what decides whether a download is attempted at all.
	 *
	 * @return void
	 */
	public function test_persian_setup_is_a_guarded_admin_action() {
		$setup   = wavira_persian_setup_url();
		$dismiss = wavira_persian_dismiss_url();

		$this->assertStringContainsString( 'action=wavira_persian_setup', $setup );
		$this->assertStringContainsString( '_wpnonce=', $setup );
		$this->assertStringContainsString( 'action=wavira_persian_dismiss', $dismiss );
		$this->assertStringContainsString( '_wpnonce=', $dismiss );
		$this->assertNotSame( $setup, $dismiss );

		ob_start();
		wavira_persian_admin_notice();
		$notice = (string) ob_get_clean();

		$this->assertSame( '', $notice, 'a user without the capability is not told anything' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// WordPress refuses to store a locale it has no translation for
		// (`sanitize_option()` asks `get_available_languages()`, which reads this
		// directory), so a site that is about to become Persian needs the pack to
		// exist first — which is the order the theme itself uses. The file only
		// has to be there: nothing loads it in this test.
		$pack = trailingslashit( WP_LANG_DIR ) . 'fa_IR.mo';
		$had  = file_exists( $pack );

		if ( ! $had ) {
			wp_mkdir_p( WP_LANG_DIR );
			file_put_contents( $pack, '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture.
		}

		$notes = wavira_apply_persian_defaults();

		if ( ! $had ) {
			wp_delete_file( $pack );
		}

		$this->assertIsArray( $notes );
		$this->assertNotEmpty( $notes, 'the site owner is told what happened' );

		$this->assertSame( 'fa_IR', (string) get_option( 'WPLANG' ) );
		$this->assertSame( 'Asia/Tehran', (string) get_option( 'timezone_string' ) );
		$this->assertSame( 6, (int) get_option( 'start_of_week' ) );
		$this->assertSame( 'j F Y', (string) get_option( 'date_format' ) );
	}

	/**
	 * A missing language pack is reported, not faked.
	 *
	 * The half of the action that does not need the network still runs — and the
	 * locale is the half WordPress itself refuses, so the site owner is told why
	 * rather than being left with a dashboard that is still English. The download
	 * is switched off through the documented filter: a build has no network, and
	 * reaching wordpress.org is not what this test is about.
	 *
	 * @return void
	 */
	public function test_a_missing_language_pack_is_reported_rather_than_faked() {
		if ( wavira_has_core_language_pack() ) {
			$this->markTestSkipped( 'this install already ships a Persian translation of WordPress' );
		}

		add_filter( 'wavira_download_core_language_pack', '__return_false' );

		$notes = wavira_apply_persian_defaults();

		remove_filter( 'wavira_download_core_language_pack', '__return_false' );

		$this->assertStringContainsString(
			'The Persian translation of WordPress itself is not installed',
			implode( ' ', $notes ),
			'a skipped download is reported, not silent'
		);

		$this->assertNotSame( 'fa_IR', (string) get_option( 'WPLANG' ), 'WordPress does not store a locale it has no translation for' );
		$this->assertSame( 'Asia/Tehran', (string) get_option( 'timezone_string' ) );
		$this->assertSame( 6, (int) get_option( 'start_of_week' ) );
		$this->assertSame( 'j F Y', (string) get_option( 'date_format' ) );
	}

	/**
	 * The preview's list is the schema's list.
	 *
	 * A field that promises `postMessage` and has nothing for the preview to do
	 * would be a silent no-op: the panel would look live and not be. The two
	 * declarations are therefore asserted against each other, in both
	 * directions.
	 *
	 * @return void
	 */
	public function test_the_preview_list_matches_the_schema() {
		$schema = wavira_options_schema();
		$live   = wavira_customize_live_map();

		foreach ( $schema as $key => $field ) {
			$transport = isset( $field['transport'] ) ? (string) $field['transport'] : 'refresh';

			if ( 'postMessage' === $transport ) {
				$this->assertArrayHasKey( $key, $live, "{$key}: postMessage without a preview entry" );
				$this->assertArrayHasKey( 'mode', $live[ $key ], "{$key}: the preview needs to know what to do" );
				$this->assertContains(
					$live[ $key ]['mode'],
					array( 'var', 'bool-class', 'root-font', 'weight', 'css' ),
					"{$key}: a mode the preview script implements"
				);
				$this->assertSame( $field['default'], $live[ $key ]['default'], "{$key}: the preview knows the default" );
			} else {
				$this->assertSame( 'refresh', $transport, "{$key}: the only two transports" );
				$this->assertArrayNotHasKey( $key, $live, "{$key}: refresh means the server renders it" );
			}
		}

		// The variable names the preview writes are the ones the server prints,
		// because both read this list. Every one of them is moved away from its
		// default first: a default value is not printed at all.
		set_theme_mod( 'wavira_logo_width', 200 );
		set_theme_mod( 'wavira_accent', '#123456' );
		set_theme_mod( 'wavira_container_width', 1080 );
		set_theme_mod( 'wavira_radius', 4 );
		set_theme_mod( 'wavira_card_shadow', false );
		set_theme_mod( 'wavira_card_min_width', 10 );
		set_theme_mod( 'wavira_social_size', 30 );

		$css = wavira_option_css();

		foreach ( array( 'logo_width', 'accent', 'container_width', 'radius', 'card_shadow', 'card_min_width', 'social_size' ) as $key ) {
			$this->assertArrayHasKey( 'css', $live[ $key ], "{$key} drives a CSS variable" );
			$this->assertStringContainsString( $live[ $key ]['css'] . ':', $css, "{$key}: the server prints the same property" );
		}
	}

	/**
	 * The front page promotes the site title to the page's `<h1>`.
	 *
	 * The header renders it as a paragraph on purpose — on a single view the post
	 * title is the `<h1>` — which left the front page with no first-level heading
	 * at all (`page-has-heading-one`, and the sections hanging off nothing).
	 * `render_block_data` changes the block's `level` attribute before it renders,
	 * so core builds the tag itself rather than this theme editing core's markup,
	 * and the class's CSS pins what the `h1` element rule would otherwise change
	 * so the header does not move.
	 *
	 * @return void
	 */
	public function test_the_front_page_promotes_the_site_title() {
		$block = array(
			'blockName' => 'core/site-title',
			'attrs'     => array( 'level' => 0 ),
		);

		$this->go_to( home_url( '/' ) );

		$this->assertTrue( is_front_page(), 'the query is the front page' );
		$this->assertSame( 1, wavira_promote_front_page_title( $block )['attrs']['level'], 'the site title is the h1 there' );

		$page = self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'A page' ) );

		$this->go_to( get_permalink( $page ) );

		$this->assertFalse( is_front_page(), 'the query is a page now' );
		$this->assertSame( 0, wavira_promote_front_page_title( $block )['attrs']['level'], 'and a paragraph everywhere else' );

		$this->assertSame(
			array( 'blockName' => 'core/navigation', 'attrs' => array( 'level' => 0 ) ),
			wavira_promote_front_page_title( array( 'blockName' => 'core/navigation', 'attrs' => array( 'level' => 0 ) ) ),
			'another block is left alone'
		);

		$this->assertSame(
			array( 'blockName' => 'core/site-title' ),
			wavira_promote_front_page_title( array( 'blockName' => 'core/site-title' ) ),
			'so is a site title without the attribute'
		);
	}

	/**
	 * The dark-mode logo is an addition to core's markup, never a replacement.
	 *
	 * @return void
	 */
	public function test_the_dark_logo_is_added_beside_core_markup() {
		$light = '<a class="custom-logo-link" href="https://example.test/"><img class="custom-logo" src="light.png" alt="Wavira" /></a>';

		$this->assertSame( $light, apply_filters( 'get_custom_logo', $light ), 'no dark logo chosen, no change' );

		$attachment = (int) self::factory()->attachment->create_object(
			'dark.png',
			0,
			array( 'post_mime_type' => 'image/png' )
		);

		// A real image file is not needed to check the markup: core filters the
		// `<img>` element it builds, at the end of the function, whether or not
		// the attachment can be resized.
		$image = static function ( $html, $attachment_id ) use ( $attachment ) {
			return $attachment === (int) $attachment_id
				? '<img class="custom-logo wavira-logo--dark" src="https://example.test/dark.png" alt="Wavira" />'
				: $html;
		};

		add_filter( 'wp_get_attachment_image', $image, 10, 2 );

		set_theme_mod( 'wavira_dark_logo', $attachment );

		$html = (string) apply_filters( 'get_custom_logo', $light );

		remove_filter( 'wp_get_attachment_image', $image );

		$this->assertStringContainsString( 'wavira-logo--dark', $html, 'the second image is marked for the dark palette' );
		$this->assertStringContainsString( 'wavira-logo--light', $html, 'and core\'s own image keeps its place' );
		$this->assertStringContainsString( 'https://example.test/dark.png', $html );
		$this->assertStringContainsString( '<span class="wavira-logo">', $html );
		$this->assertStringContainsString( 'alt="Wavira"', $html, 'core\'s alt text is not thrown away' );

		// When core cannot produce an image — a deleted file, a broken upload —
		// the logo markup is left as it was, rather than wrapped around an empty
		// string. The filter is core's own end of `wp_get_attachment_image()`.
		$nothing = static function ( $html, $attachment_id ) use ( $attachment ) {
			return $attachment === (int) $attachment_id ? '' : $html;
		};

		add_filter( 'wp_get_attachment_image', $nothing, 10, 2 );

		$this->assertSame( $light, apply_filters( 'get_custom_logo', $light ) );

		remove_filter( 'wp_get_attachment_image', $nothing );
	}

	/**
	 * The core language pack check reads the directory it is given.
	 *
	 * @return void
	 */
	public function test_the_language_pack_check_is_a_file_question() {
		$dir = wp_tempnam( 'wavira-lang' );

		@unlink( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- wp_tempnam() creates the file; the check is about the directory.
		wp_mkdir_p( $dir );

		$this->assertFalse( wavira_has_core_language_pack( $dir ) );

		file_put_contents( $dir . '/fa_IR.mo', '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture.
		$this->assertTrue( wavira_has_core_language_pack( $dir ) );

		wp_delete_file( $dir . '/fa_IR.mo' );
		file_put_contents( $dir . '/fa_IR-abc123.l10n.php', '<?php return array();' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture.
		$this->assertTrue( wavira_has_core_language_pack( $dir ), 'modern WordPress stores fa_IR-<hash>.l10n.php' );

		wp_delete_file( $dir . '/fa_IR-abc123.l10n.php' );
		rmdir( $dir );
	}

	/**
	 * Include a pattern file the way core includes a theme pattern.
	 *
	 * @param string $slug Pattern slug.
	 * @return string Rendered pattern content.
	 */
	private function pattern_output( string $slug ): string {
		$path = WAVIRA_THEME_DIR . 'patterns/' . $slug . '.php';

		$this->assertFileExists( $path );

		ob_start();
		include $path;

		return (string) ob_get_clean();
	}
}
