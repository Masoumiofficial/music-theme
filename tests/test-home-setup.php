<?php
/**
 * The home-page setup and the settings-screen switches added in 0.17.0.
 *
 * The screen can now set Settings → Reading in one nonced action, and a front
 * page that is not chosen is reported rather than guessed at. These tests pin
 * the behaviour that matters to a site owner:
 * - the action is refused for anyone who may not change Reading;
 * - "a static page with no page picked" is reported as a problem, because it
 *   shows the blog listing instead of the designed front page;
 * - the setup creates the pages only when they are missing, reuses a page that
 *   already has the name, and never replaces a page that is already chosen;
 * - a switch that is turned back on to its default is removed, not kept as
 *   `false` (the bug that kept "Download cards" off after it had been switched
 *   off once);
 * - the reduce-motion switch reaches the body class.
 *
 * @package Wavira\Tests
 */

/**
 * Class Test_Home_Setup
 */
class Test_Home_Setup extends Wavira_Test_Case {

	/**
	 * Load the theme code the screen uses, the way test-admin-panel.php does.
	 *
	 * @return void
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		if ( ! defined( 'WAVIRA_THEME_DIR' ) ) {
			define( 'WAVIRA_THEME_DIR', trailingslashit( dirname( __DIR__ ) . '/wavira' ) );
			define( 'WAVIRA_THEME_URI', 'https://example.test/wp-content/themes/wavira/' );
			define( 'WAVIRA_THEME_VERSION', '0.17.0-test' );
		}

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
	 * An administrator, and a clean Reading screen.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		update_option( 'show_on_front', 'posts' );
		update_option( 'page_on_front', 0 );
		update_option( 'page_for_posts', 0 );

		foreach ( array_keys( wavira_options_schema() ) as $key ) {
			remove_theme_mod( 'wavira_' . $key );
		}
	}

	/**
	 * A subscriber cannot change the reading settings through the button.
	 *
	 * @return void
	 */
	public function test_the_setup_is_refused_without_manage_options() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->expectException( 'WPDieException' );

		wavira_handle_home_setup();
	}

	/**
	 * "A static page" with no page picked is not a home page: the status says so.
	 *
	 * @return void
	 */
	public function test_the_status_flags_a_static_front_page_with_no_page_chosen() {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', 0 );

		$this->assertFalse( wavira_home_status()['static'], 'no page is chosen, so the designed home page is not shown' );
	}

	/**
	 * The setup creates the two pages, empty, and sets both options.
	 *
	 * @return void
	 */
	public function test_the_setup_creates_and_sets_the_home_and_posts_pages() {
		$this->assertTrue( wavira_setup_home_pages() );

		$this->assertSame( 'page', get_option( 'show_on_front' ) );

		$front = (int) get_option( 'page_on_front' );
		$posts = (int) get_option( 'page_for_posts' );

		$this->assertGreaterThan( 0, $front );
		$this->assertGreaterThan( 0, $posts );
		$this->assertNotSame( $front, $posts, 'the home page and the posts page are different pages' );
		$this->assertSame( '', get_post_field( 'post_content', $front ), 'the home page is empty: the template draws it' );
		$this->assertTrue( wavira_home_status()['static'] );
	}

	/**
	 * A page the site already has with the same name is reused, not duplicated.
	 *
	 * @return void
	 */
	public function test_the_setup_reuses_an_existing_page_with_the_same_title() {
		$existing = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => __( 'Home', 'wavira' ),
			)
		);

		$this->assertTrue( wavira_setup_home_pages() );
		$this->assertSame( $existing, (int) get_option( 'page_on_front' ) );
	}

	/**
	 * A page that is already chosen stays chosen.
	 *
	 * @return void
	 */
	public function test_the_setup_keeps_a_page_that_is_already_chosen() {
		$chosen = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Our own start page',
			)
		);

		update_option( 'page_on_front', $chosen );

		$this->assertTrue( wavira_setup_home_pages() );
		$this->assertSame( $chosen, (int) get_option( 'page_on_front' ) );
	}

	/**
	 * A switch that goes back to its default is removed, even when it was stored
	 * as `false` on the way there.
	 *
	 * @return void
	 */
	public function test_a_switch_turned_back_on_is_removed_rather_than_kept_off() {
		set_theme_mod( 'wavira_show_downloads', false );

		wavira_admin_panel_apply( 'music', array( 'wavira_show_downloads' => '1' ) );

		$this->assertNull( get_theme_mod( 'wavira_show_downloads', null ), 'the default is not stored' );
		$this->assertTrue( wavira_option( 'show_downloads', false ) );
	}

	/**
	 * The reduce-motion switch adds its body class, and only while it is on.
	 *
	 * @return void
	 */
	public function test_reduce_motion_reaches_the_body_class() {
		$this->assertNotContains( 'wavira-reduce-motion', wavira_option_body_classes( array() ) );

		set_theme_mod( 'wavira_reduce_motion', true );

		$this->assertContains( 'wavira-reduce-motion', wavira_option_body_classes( array() ) );
	}
}
