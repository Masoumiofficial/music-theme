<?php
/**
 * Runtime verification of the music-news feed (phase 0.9.0).
 *
 * News is ordinary posts, so what needs proving is that the feed is a faithful,
 * bounded, public view of them: newest first, no drafts, no private post types,
 * an excerpt even when the editor wrote none, and the same card markup the rest
 * of the product uses.
 *
 * @package Wavira\Tests
 */

/**
 * Class Test_News
 */
class Test_News extends Wavira_Test_Case {

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
			define( 'WAVIRA_THEME_VERSION', '0.9.0-test' );
		}

		foreach ( array( 'helpers', 'markup', 'news' ) as $file ) {
			$path = WAVIRA_THEME_DIR . 'inc/' . $file . '.php';

			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}
	}

	/**
	 * Start from an empty post table.
	 *
	 * The test install ships a sample post dated "now", which is newer than every
	 * ordering fixture; the feed must be judged on the test's own posts only.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		if ( function_exists( '_delete_all_posts' ) ) {
			_delete_all_posts();
		}
	}

	/**
	 * Create a news post.
	 *
	 * @param string               $title  Post title.
	 * @param string               $date   Post date (ordering fixture).
	 * @param array<string, mixed> $args   Extra arguments.
	 * @return int Post ID.
	 */
	private function make_post( $title, $date = '2026-01-02 10:00:00', array $args = array() ) {
		return (int) self::factory()->post->create(
			array_merge(
				array(
					'post_type'   => 'post',
					'post_status' => 'publish',
					'post_title'  => $title,
					'post_date'   => $date,
				),
				$args
			)
		);
	}

	/**
	 * The feed is newest first, bounded, and never shows a draft.
	 *
	 * @return void
	 */
	public function test_feed_is_newest_first_and_skips_drafts() {
		$this->make_post( 'Older news', '2026-01-01 10:00:00' );
		$this->make_post( 'Newer news', '2026-03-01 10:00:00' );
		$this->make_post( 'Draft news', '2026-04-01 10:00:00', array( 'post_status' => 'draft' ) );

		$titles = wp_list_pluck( wavira_core_news_feed( array( 'limit' => 10 ) ), 'title' );

		$this->assertNotContains( 'Draft news', $titles );
		$this->assertLessThan(
			(int) array_search( 'Older news', $titles, true ),
			(int) array_search( 'Newer news', $titles, true ),
			'the feed is newest first'
		);
	}

	/**
	 * A category slug restricts the feed to that category.
	 *
	 * @return void
	 */
	public function test_feed_can_be_restricted_to_a_category() {
		$concerts = self::factory()->term->create( array( 'taxonomy' => 'category', 'name' => 'Concerts', 'slug' => 'concerts' ) );
		$in_studio = $this->make_post( 'In the studio', '2026-02-01 10:00:00' );
		$on_stage  = $this->make_post( 'On stage', '2026-02-02 10:00:00' );

		wp_set_post_terms( $on_stage, array( $concerts ), 'category' );
		$this->assertNotSame( $in_studio, $on_stage );

		$items = wavira_core_news_feed( array( 'category' => 'concerts' ) );

		$this->assertSame( array( 'On stage' ), wp_list_pluck( $items, 'title' ) );

		$missing = wavira_core_news_feed( array( 'category' => 'not-a-category' ) );
		$this->assertSame( array(), $missing, 'an unknown category yields no items, never every item' );
	}

	/**
	 * Every item carries the fields a card needs, and an excerpt always exists.
	 *
	 * @return void
	 */
	public function test_items_carry_card_fields_and_an_excerpt() {
		$with_excerpt = $this->make_post(
			'Has an excerpt',
			'2026-02-01 10:00:00',
			array(
				'post_excerpt' => 'A hand-written summary.',
				'post_content' => 'Body text that must not become the excerpt.',
			)
		);
		$this->make_post(
			'No excerpt',
			'2026-02-02 10:00:00',
			array(
				// The factory writes a default excerpt; clearing it is what makes the
				// content-derived excerpt reachable (a real editor leaves it empty).
				'post_excerpt' => '',
				'post_content' => 'The editor wrote only a body, so the feed trims one: ' . str_repeat( 'word ', 60 ),
			)
		);

		update_post_meta( $with_excerpt, '_thumbnail_id', $this->make_thumbnail( $with_excerpt ) );

		$items = wavira_core_news_feed( array( 'limit' => 5 ) );
		$by_title = array_column( $items, null, 'title' );

		$this->assertArrayHasKey( 'No excerpt', $by_title );
		$this->assertArrayHasKey( 'Has an excerpt', $by_title );

		$trimmed = $by_title['No excerpt'];
		$this->assertStringContainsString( 'The editor wrote only a body', $trimmed['excerpt'] );
		$this->assertStringNotContainsString( '<', $trimmed['excerpt'], 'an excerpt is plain text' );
		$this->assertNotSame( '', $trimmed['excerpt'], 'a post without an excerpt still gets one' );

		$hand = $by_title['Has an excerpt'];
		$this->assertSame( 'A hand-written summary.', $hand['excerpt'] );
		$this->assertNotSame( '', $hand['permalink'] );
		$this->assertNotSame( '', $hand['date_label'] );
		$this->assertGreaterThan( 0, $hand['date'] );
		$this->assertStringContainsString( 'news.jpg', $hand['thumbnail']['url'] );
		$this->assertNotSame( '', $hand['author']['name'] );
	}

	/**
	 * A private or unknown post type falls back to posts instead of leaking.
	 *
	 * @return void
	 */
	public function test_non_public_post_types_cannot_be_read() {
		$this->make_post( 'A public post', '2026-02-01 10:00:00' );

		register_post_type(
			'wavira_private_test',
			array(
				'public'   => false,
				'label'    => 'Private test type',
				'supports' => array( 'title' ),
			)
		);

		$secret = (int) self::factory()->post->create(
			array(
				'post_type'   => 'wavira_private_test',
				'post_status' => 'publish',
				'post_title'  => 'Internal note',
			)
		);

		$items = wavira_core_news_feed( array( 'post_type' => 'wavira_private_test' ) );

		$this->assertContains( 'A public post', wp_list_pluck( $items, 'title' ) );
		$this->assertNotContains( $secret, wp_list_pluck( $items, 'id' ) );
	}

	/**
	 * The theme renders the feed as cards, with the news classes and a `<time>`.
	 *
	 * @return void
	 */
	public function test_theme_markup_renders_news_cards() {
		$post = $this->make_post(
			'Concert announcement',
			'2026-02-05 10:00:00',
			array(
				'post_excerpt' => 'Tickets go on sale on Saturday.',
				'post_content' => 'Details inside.',
			)
		);

		$category = self::factory()->term->create( array( 'taxonomy' => 'category', 'name' => 'Concerts', 'slug' => 'concerts' ) );

		wp_set_post_terms( $post, array( $category ), 'category' );
		update_post_meta( $post, '_thumbnail_id', $this->make_thumbnail( $post ) );

		$markup = wavira_get_news(
			array(
				'limit'    => 1,
				'source'   => 'category',
				'category' => 'concerts',
				'class'    => 'wavira-news--cols-3',
			)
		);

		$this->assertStringContainsString( 'class="wavira-news wavira-news--cols-3"', $markup );
		$this->assertStringContainsString( 'wavira-news__list', $markup );
		$this->assertStringContainsString( 'wavira-card--news', $markup );
		$this->assertStringContainsString( 'Concert announcement', $markup );
		$this->assertStringContainsString( 'Tickets go on sale on Saturday.', $markup );
		$this->assertStringContainsString( '<time datetime=', $markup );
		$this->assertStringContainsString( 'Concerts', $markup, 'a news card names its category' );
		$this->assertStringContainsString( 'wavira-card__image', $markup );

		$this->assertSame( '', wavira_get_news( array( 'limit' => 5, 'category' => 'no-such-category' ) ) );
	}

	/**
	 * The news categories helper lists the categories that have posts.
	 *
	 * @return void
	 */
	public function test_news_categories_render_as_chips() {
		$category = self::factory()->term->create( array( 'taxonomy' => 'category', 'name' => 'Interviews' ) );
		$post     = $this->make_post( 'An interview', '2026-02-01 10:00:00' );

		wp_set_post_terms( $post, array( $category ), 'category' );

		$markup = wavira_get_news_categories( array( 'limit' => 5 ) );

		$this->assertStringContainsString( 'wavira-news__categories', $markup );
		$this->assertStringContainsString( 'Interviews', $markup );
		$this->assertStringContainsString( 'class="wavira-chip"', $markup );
	}

	/**
	 * An image for a post fixture.
	 *
	 * @param int $parent Parent post ID.
	 * @return int Attachment ID.
	 */
	private function make_thumbnail( $parent ) {
		$attachment = (int) self::factory()->attachment->create_object(
			'news.jpg',
			$parent,
			array( 'post_mime_type' => 'image/jpeg' )
		);

		// The fixture needs a URL the card can print; the file itself does not
		// exist and `Cover::url()` would fall through to the full-size chain.
		update_post_meta( $attachment, '_wp_attached_file', '2026/01/news.jpg' );

		return $attachment;
	}
}
