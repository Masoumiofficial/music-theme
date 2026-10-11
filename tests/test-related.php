<?php
/**
 * Runtime verification of the related-music section (phase 0.15.0).
 *
 * The plugin scores related items and this repository already proves that
 * (tests/test-search-related.php). What needs proving here is the theme's half:
 * that a related list becomes the same card every other grid renders — with its
 * play and download actions — and that an empty result prints nothing at all,
 * heading included, because a section title with nothing under it is a defect.
 *
 * @package Wavira\Tests
 */

use Wavira\Core\Support\Cache;

/**
 * Class Test_Related
 */
class Test_Related extends Wavira_Test_Case {

	/**
	 * Load the theme's PHP once for the class.
	 *
	 * @return void
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		if ( ! defined( 'WAVIRA_THEME_DIR' ) ) {
			define( 'WAVIRA_THEME_DIR', trailingslashit( dirname( __DIR__ ) . '/wavira' ) );
			define( 'WAVIRA_THEME_URI', 'https://example.test/wp-content/themes/wavira/' );
			define( 'WAVIRA_THEME_VERSION', '0.15.0-test' );
		}

		foreach ( array( 'helpers', 'markup', 'cards', 'related' ) as $file ) {
			$path = WAVIRA_THEME_DIR . 'inc/' . $file . '.php';

			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}
	}

	/**
	 * Start from an empty cache: the related service memoises per post.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		Cache::flush();
	}

	/**
	 * Create a published track, optionally in a genre.
	 *
	 * @param string $title    Track title.
	 * @param int    $genre_id Genre term ID (0 = none).
	 * @return int
	 */
	private function make_track( string $title, int $genre_id = 0 ): int {
		$track_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'wavira_track',
				'post_status' => 'publish',
				'post_title'  => $title,
			)
		);

		if ( $genre_id > 0 ) {
			wp_set_post_terms( $track_id, array( $genre_id ), 'wavira_genre' );
		}

		return $track_id;
	}

	/**
	 * Create a genre term.
	 *
	 * @param string $name Term name.
	 * @return int
	 */
	private function make_genre( string $name ): int {
		$term = wp_insert_term( $name, 'wavira_genre' );

		return (int) $term['term_id'];
	}

	/**
	 * A track with nothing beside it prints no section and no heading.
	 *
	 * @return void
	 */
	public function test_nothing_related_prints_no_heading() {
		$track = $this->make_track( 'تنها' );

		$this->assertSame(
			'',
			wavira_get_related(
				array(
					'post_id' => $track,
					'title'   => 'آهنگ‌های مشابه',
				)
			),
			'an empty related list must not leave its heading behind'
		);
	}

	/**
	 * Related tracks render as the theme's cards, under the heading passed in.
	 *
	 * @return void
	 */
	public function test_related_tracks_render_as_cards() {
		$genre  = $this->make_genre( 'امبینت' );
		$source = $this->make_track( 'مبدأ', $genre );

		$this->make_track( 'ادامه', $genre );

		Cache::flush();

		$html = wavira_get_related(
			array(
				'post_id' => $source,
				'title'   => 'آهنگ‌های مشابه',
			)
		);

		$this->assertNotSame( '', $html, 'a shared genre is a related item' );
		$this->assertStringContainsString( 'wavira-section wavira-related', $html );
		$this->assertStringContainsString( 'آهنگ‌های مشابه', $html, 'the heading comes from the caller' );
		$this->assertStringContainsString( 'ادامه', $html );
		$this->assertStringContainsString( 'wavira-card--related', $html );
		$this->assertStringNotContainsString( 'مبدأ', $html, 'a track is never related to itself' );
	}

	/**
	 * The limit bounds the cards, whatever the catalogue holds.
	 *
	 * @return void
	 */
	public function test_the_limit_bounds_the_cards() {
		$genre  = $this->make_genre( 'سنتی' );
		$source = $this->make_track( 'ریشه', $genre );

		for ( $i = 1; $i <= 4; $i++ ) {
			$this->make_track( 'شاخه ' . $i, $genre );
		}

		Cache::flush();

		$html = wavira_get_related(
			array(
				'post_id' => $source,
				'limit'   => 2,
			)
		);

		$this->assertSame( 2, substr_count( $html, 'wavira-cards__item' ), 'two cards were asked for' );
		$this->assertStringNotContainsString( 'wavira-section__head', $html, 'no title was passed, so no heading is printed' );
	}

	/**
	 * With no post ID the section follows the post being viewed.
	 *
	 * @return void
	 */
	public function test_it_follows_the_post_being_viewed() {
		$genre  = $this->make_genre( 'جز' );
		$source = $this->make_track( 'بداهه', $genre );

		$this->make_track( 'هم‌نوازی', $genre );

		Cache::flush();

		$this->go_to( get_permalink( $source ) );

		$html = wavira_get_related( array( 'title' => 'آهنگ‌های مشابه' ) );

		$this->assertStringContainsString( 'هم‌نوازی', $html );
		$this->assertStringNotContainsString( 'بداهه', $html );
	}
}
