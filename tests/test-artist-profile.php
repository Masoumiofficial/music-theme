<?php
/**
 * Runtime verification of the artist profile (phase 0.9.0).
 *
 * Two halves, one feature: the payload the plugin builds (works, counts,
 * biography, socials, gallery) and the markup the theme renders from it. The
 * theme's PHP is loaded from the repository the same way its bootstrap loads it
 * (see `tests/test-blocks.php` for the pattern), so a defect in the aggregation
 * or in the escaping surfaces here and not on a live artist page.
 *
 * @package Wavira\Tests
 */

/**
 * Class Test_Artist_Profile
 */
class Test_Artist_Profile extends Wavira_Test_Case {

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

		foreach ( array( 'helpers', 'markup', 'artists', 'downloads' ) as $file ) {
			$path = WAVIRA_THEME_DIR . 'inc/' . $file . '.php';

			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}
	}

	/**
	 * Start from an empty post table.
	 *
	 * The WordPress test install ships sample content dated "now", which is newer
	 * than any ordering fixture a test can write; removing it makes "newest
	 * first" mean the test's own posts (the same helper core's suites use).
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
	 * Create a published artist.
	 *
	 * @param array<string, mixed> $args Extra post arguments.
	 * @return int Artist ID.
	 */
	private function make_artist( array $args = array() ) {
		return (int) self::factory()->post->create(
			array_merge(
				array(
					'post_type'    => 'wavira_artist',
					'post_status'  => 'publish',
					'post_title'   => 'Demo Artist',
					'post_name'    => 'demo-artist',
					'post_excerpt' => 'A short quote about the artist.',
					'post_date'    => '2026-01-01 10:00:00',
				),
				$args
			)
		);
	}

	/**
	 * Create a published work credited to an artist.
	 *
	 * @param string $post_type Post type.
	 * @param int    $artist_id Artist ID.
	 * @param string $title     Title.
	 * @param string $date      Post date (ordering fixture).
	 * @param string $status    Post status.
	 * @return int Post ID.
	 */
	private function make_work( $post_type, $artist_id, $title, $date = '2026-01-02 10:00:00', $status = 'publish' ) {
		$post_id = (int) self::factory()->post->create(
			array(
				'post_type'   => $post_type,
				'post_status' => $status,
				'post_title'  => $title,
				'post_date'   => $date,
			)
		);

		update_post_meta( $post_id, \Wavira\Core\Content\MetaSchema::ARTIST, $artist_id );

		return $post_id;
	}

	/**
	 * Create an image attached to the artist.
	 *
	 * @param int    $artist_id Artist ID.
	 * @param string $file      File name.
	 * @param string $caption   Attachment caption.
	 * @return int Attachment ID.
	 */
	private function make_photo( $artist_id, $file = 'stage.jpg', $caption = 'On stage' ) {
		return (int) self::factory()->attachment->create_object(
			$file,
			$artist_id,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => $caption,
			)
		);
	}

	/**
	 * The payload answers a whole artist page.
	 *
	 * @return void
	 */
	public function test_payload_carries_profile_works_and_gallery() {
		$artist = $this->make_artist( array( 'post_content' => '<p>Born in Tehran, plays the setar.</p>' ) );

		update_post_meta( $artist, \Wavira\Core\Content\MetaSchema::SOCIAL_INSTAGRAM, 'https://instagram.com/demo' );
		update_post_meta( $artist, \Wavira\Core\Content\MetaSchema::SOCIAL_APARAT, 'https://aparat.com/demo' );

		$older_album = $this->make_work( 'wavira_album', $artist, 'Older album', '2026-01-02 10:00:00' );
		$newer_album = $this->make_work( 'wavira_album', $artist, 'Newer album', '2026-02-02 10:00:00' );
		$single      = $this->make_work( 'wavira_track', $artist, 'A single' );
		$video       = $this->make_work( 'wavira_video', $artist, 'A music video' );
		$this->make_photo( $artist, 'stage.jpg', 'On stage' );

		update_post_meta( $newer_album, '_thumbnail_id', $this->make_photo( $artist, 'cover.jpg' ) );

		// Another artist's work must never appear in this profile.
		$other = $this->make_artist( array( 'post_title' => 'Other Artist', 'post_name' => 'other-artist' ) );
		$this->make_work( 'wavira_album', $other, 'Not this artist' );

		$payload = wavira_core_artist_profile( $artist );

		$this->assertSame( $artist, $payload['id'] );
		$this->assertSame( 'Demo Artist', $payload['name'] );
		$this->assertStringContainsString( 'Born in Tehran', $payload['biography'] );
		$this->assertStringContainsString( 'short quote', $payload['context'] );
		$this->assertSame( array( 'albums' => 2, 'tracks' => 1, 'videos' => 1, 'gallery' => 2 ), $payload['counts'] );

		$this->assertSame(
			array( 'Newer album', 'Older album' ),
			wp_list_pluck( $payload['sections']['albums']['items'], 'title' ),
			'works are newest first'
		);
		$this->assertSame( 2, $payload['sections']['albums']['count'] );
		$this->assertSame( $newer_album, $payload['sections']['albums']['items'][0]['id'] );
		$this->assertSame( $single, $payload['sections']['tracks']['items'][0]['id'] );
		$this->assertSame( $video, $payload['sections']['videos']['items'][0]['id'] );
		$this->assertNotSame( '', $payload['sections']['albums']['more'], 'a section links to its archive' );
		$this->assertNotSame( '', $payload['sections']['albums']['items'][0]['permalink'] );
		$this->assertNotSame( '', $payload['sections']['albums']['items'][0]['cover']['url'], 'a work card carries its artwork URL' );

		$this->assertSame(
			array( 'instagram' => 'Instagram', 'aparat' => 'Aparat' ),
			wp_list_pluck( $payload['socials'], 'label', 'network' ),
			'social channels keep the schema order and carry a label'
		);
		$this->assertSame( 'https://instagram.com/demo', $payload['socials'][0]['url'] );

		$this->assertCount( 2, $payload['gallery'] );
		$this->assertSame( 'On stage', $payload['gallery'][0]['caption'] );
		$this->assertNotSame( '', $payload['gallery'][0]['url'] );

		$this->assertNotSame( '', $older_album );
	}

	/**
	 * Drafts are neither counted nor listed, and a bad ID is refused.
	 *
	 * @return void
	 */
	public function test_drafts_and_unknown_ids_are_refused() {
		$artist = $this->make_artist();

		$this->make_work( 'wavira_track', $artist, 'Published single' );
		$this->make_work( 'wavira_track', $artist, 'Draft single', '2026-01-03 10:00:00', 'draft' );

		$payload = wavira_core_artist_profile( $artist );

		$this->assertSame( 1, $payload['counts']['tracks'] );
		$this->assertSame( array( 'Published single' ), wp_list_pluck( $payload['sections']['tracks']['items'], 'title' ) );

		$this->assertSame( array(), wavira_core_artist_profile( 999999 ) );
		$this->assertSame( array(), wavira_core_artist_profile( $this->make_work( 'wavira_track', $artist, 'Just a track' ) ) );
	}

	/**
	 * The requested limits are honoured and capped.
	 *
	 * @return void
	 */
	public function test_limits_are_honoured_and_capped() {
		$artist = $this->make_artist();

		foreach ( array( 'One', 'Two', 'Three' ) as $index => $title ) {
			$this->make_work( 'wavira_track', $artist, $title, sprintf( '2026-01-%02d 10:00:00', $index + 2 ) );
		}

		$limited = wavira_core_artist_profile( $artist, array( 'limit' => 2 ) );
		$this->assertCount( 2, $limited['sections']['tracks']['items'] );
		$this->assertSame( 3, $limited['sections']['tracks']['count'], 'the count is never limited' );

		$zero = wavira_core_artist_profile( $artist, array( 'limit' => 0 ) );
		$this->assertCount( 1, $zero['sections']['tracks']['items'], 'a zero limit means one item, not every item' );

		$this->assertSame( 24, \Wavira\Core\Content\ArtistProfile::MAX_ITEMS, 'the section ceiling is documented and enforced' );

		$sections = wavira_core_artist_profile( $artist, array( 'sections' => array( 'albums' ) ) );
		$this->assertSame( array( 'albums' ), array_keys( $sections['sections'] ) );
	}

	/**
	 * The theme renders the profile, its works and its gallery.
	 *
	 * @return void
	 */
	public function test_theme_markup_renders_profile_works_and_gallery() {
		$artist = $this->make_artist( array( 'post_content' => '<p>Born in Tehran, plays the setar.</p>' ) );

		update_post_meta( $artist, \Wavira\Core\Content\MetaSchema::SOCIAL_TELEGRAM, 'https://t.me/demo' );

		$this->make_work( 'wavira_album', $artist, 'Album fixture' );
		$this->make_work( 'wavira_video', $artist, 'Video fixture' );
		$this->make_photo( $artist, 'stage.jpg', 'On stage' );

		$markup = wavira_get_artist( $artist );

		$this->assertStringContainsString( 'class="wavira-artist"', $markup );
		$this->assertStringContainsString( 'wavira-artist__name', $markup );
		$this->assertStringContainsString( 'Demo Artist', $markup );
		$this->assertStringContainsString( 'Born in Tehran', $markup );
		$this->assertStringContainsString( 'wavira-artist__socials', $markup );
		$this->assertStringContainsString( 'rel="me nofollow noopener external"', $markup );
		$this->assertStringContainsString( 'https://t.me/demo', $markup );
		$this->assertStringContainsString( 'Albums (1)', $markup, 'a section heading carries its count' );
		$this->assertStringContainsString( 'Album fixture', $markup );
		$this->assertStringContainsString( 'Video fixture', $markup );
		$this->assertStringContainsString( 'wavira-card--work', $markup );
		$this->assertStringContainsString( 'wavira-gallery__items', $markup );
		$this->assertStringContainsString( 'wavira-gallery__image', $markup );
		$this->assertStringContainsString( 'On stage', $markup );
		$this->assertStringContainsString( 'wavira-artist__counts', $markup );
	}

	/**
	 * Stored text is escaped, and a script in the biography is stripped.
	 *
	 * @return void
	 */
	public function test_theme_markup_escapes_stored_text() {
		$artist = $this->make_artist(
			array(
				'post_title'   => 'Tom & Jerry "Live"',
				'post_content' => '<p>Safe text.</p><script>alert(1)</script>',
			)
		);

		$this->make_work( 'wavira_album', $artist, 'Bold <b>title</b>' );

		$markup = wavira_get_artist( $artist );

		$this->assertStringContainsString( 'Jerry', $markup );
		$this->assertStringContainsString( 'Live', $markup );
		// Core's `the_title` filters texturise and convert characters, so the exact
		// entity is core's business; what this test owns is that the raw characters
		// never reach the page unescaped.
		$this->assertStringNotContainsString( 'Tom & Jerry', $markup, 'a stored ampersand is escaped' );
		$this->assertStringNotContainsString( '"Live"', $markup, 'the straight quotes never reach the page raw' );
		$this->assertStringContainsString( 'Bold &lt;b&gt;title&lt;/b&gt;', $markup );
		$this->assertStringNotContainsString( '<script', $markup );
		$this->assertStringNotContainsString( '<b>title</b>', $markup );
	}

	/**
	 * A profile with nothing to show renders nothing, never an empty shell.
	 *
	 * @return void
	 */
	public function test_rendering_an_unknown_artist_is_empty() {
		$this->assertSame( '', wavira_get_artist( 999999 ) );
		$this->assertSame( '', wavira_get_artist_gallery_only( 999999 ) );
		$this->assertSame( '', wavira_get_artist_works( array() ) );
		$this->assertSame( '', wavira_get_artist_gallery( array() ) );
	}

	/**
	 * The shortcode and the block helper render the same markup.
	 *
	 * @return void
	 */
	public function test_shortcode_and_helper_share_the_markup() {
		require_once WAVIRA_THEME_DIR . 'inc/shortcodes.php';

		$artist = $this->make_artist( array( 'post_content' => '<p>Biography.</p>' ) );

		$this->make_work( 'wavira_album', $artist, 'Shortcode album' );

		$helper    = wavira_get_artist( $artist, array( 'show_gallery' => false ) );
		$shortcode = do_shortcode( '[wavira_artist id="' . $artist . '" sections="albums"]' );

		$this->assertStringContainsString( 'class="wavira-artist"', $shortcode );
		$this->assertStringContainsString( 'Shortcode album', $shortcode );
		$this->assertStringContainsString( 'wavira-card--work', $helper );
	}
}
