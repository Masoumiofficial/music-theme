<?php
/**
 * Runtime verification of the SEO layer (phase 0.10.0).
 *
 * Two claims are worth proving against real WordPress: the music structured data
 * describes the content the product stores (artist, album, duration, release
 * date, providers), and the cooperation rules hold — no graph for posts an SEO
 * plugin owns, nothing printed when a plugin is active, and every switch a site
 * owner was promised.
 *
 * @package Wavira\Tests
 */

/**
 * Class Test_Seo
 */
class Test_Seo extends Wavira_Test_Case {

	/**
	 * Load the theme's SEO and performance PHP once for the class.
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

		foreach ( array( 'performance', 'seo' ) as $file ) {
			$path = WAVIRA_THEME_DIR . 'inc/' . $file . '.php';

			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}
	}

	/**
	 * Start from an empty post table.
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
					'post_excerpt' => 'A short artist biography.',
					'post_date'    => '2026-01-01 10:00:00',
				),
				$args
			)
		);
	}

	/**
	 * Create a published music post credited to an artist.
	 *
	 * @param string               $post_type Post type.
	 * @param int                  $artist_id Artist ID.
	 * @param array<string, mixed> $args      Extra post arguments.
	 * @return int Post ID.
	 */
	private function make_work( $post_type, $artist_id, array $args = array() ) {
		$post_id = (int) self::factory()->post->create(
			array_merge(
				array(
					'post_type'   => $post_type,
					'post_status' => 'publish',
					'post_title'  => 'Demo work',
				),
				$args
			)
		);

		update_post_meta( $post_id, \Wavira\Core\Content\MetaSchema::ARTIST, $artist_id );

		return $post_id;
	}

	/**
	 * The artist node describes the artist and its profiles.
	 *
	 * @return void
	 */
	public function test_artist_graph_is_a_music_group_with_profiles() {
		$artist = $this->make_artist();

		update_post_meta( $artist, \Wavira\Core\Content\MetaSchema::SOCIAL_TELEGRAM, 'https://t.me/demo' );
		update_post_meta( $artist, \Wavira\Core\Content\MetaSchema::WEBSITE, 'https://demo.example' );

		$graph = wavira_core_structured_data( $artist );

		$this->assertCount( 1, $graph );

		$node = $graph[0];

		$this->assertSame( 'MusicGroup', $node['@type'] );
		$this->assertSame( 'Demo Artist', $node['name'] );
		$this->assertSame( get_permalink( $artist ), $node['url'] );
		$this->assertSame( 'A short artist biography.', $node['description'] );
		$this->assertStringContainsString( '#musicgroup', $node['@id'] );
		$this->assertContains( 'https://t.me/demo', $node['sameAs'] );
		$this->assertContains( 'https://demo.example', $node['sameAs'] );
	}

	/**
	 * The album node lists only published tracks and keeps the release date.
	 *
	 * @return void
	 */
	public function test_album_graph_lists_published_tracks() {
		$artist  = $this->make_artist();
		$album   = $this->make_work( 'wavira_album', $artist, array( 'post_title' => 'Demo album' ) );
		$track   = $this->make_work( 'wavira_track', $artist, array( 'post_title' => 'First track' ) );
		$draft   = $this->make_work(
			'wavira_track',
			$artist,
			array(
				'post_title'  => 'Unreleased track',
				'post_status' => 'draft',
			)
		);

		update_post_meta( $album, \Wavira\Core\Content\MetaSchema::TRACKLIST, array( $track, $draft ) );
		update_post_meta( $track, \Wavira\Core\Content\MetaSchema::ALBUM, $album );
		update_post_meta( $album, \Wavira\Core\Content\MetaSchema::RELEASE_DATE, '2024-03-05' );

		$node = wavira_core_structured_data( $album )[0];

		$this->assertSame( 'MusicAlbum', $node['@type'] );
		$this->assertSame( '2024-03-05T00:00:00+00:00', $node['datePublished'] );
		$this->assertSame( 1, $node['numTracks'], 'a draft is not a track of the album' );
		$this->assertSame( 'MusicGroup', $node['byArtist']['@type'] );
		$this->assertSame( 'Demo Artist', $node['byArtist']['name'] );
		$this->assertSame( 'First track', $node['track'][0]['name'] );
		$this->assertStringContainsString( '#musicrecording', $node['track'][0]['@id'] );
	}

	/**
	 * The track node carries duration, album and credit.
	 *
	 * @return void
	 */
	public function test_track_graph_carries_duration_and_album() {
		$artist = $this->make_artist();
		$album  = $this->make_work( 'wavira_album', $artist, array( 'post_title' => 'Demo album' ) );
		$track  = $this->make_work(
			'wavira_track',
			$artist,
			array(
				'post_title'   => 'First track',
				'post_excerpt' => 'The opening track.',
			)
		);

		update_post_meta( $track, \Wavira\Core\Content\MetaSchema::ALBUM, $album );
		update_post_meta( $track, \Wavira\Core\Content\MetaSchema::DURATION, 222 );
		update_post_meta( $track, \Wavira\Core\Content\MetaSchema::ISRC, 'IRAA1A2600001' );

		$node = wavira_core_structured_data( $track )[0];

		$this->assertSame( 'MusicRecording', $node['@type'] );
		$this->assertSame( 'PT3M42S', $node['duration'] );
		$this->assertSame( 'IRAA1A2600001', $node['isrcCode'] );
		$this->assertSame( 'Demo album', $node['inAlbum']['name'] );
		$this->assertSame( 'Demo Artist', $node['byArtist']['name'] );
		$this->assertSame( 'The opening track.', $node['description'] );
	}

	/**
	 * A hosted video exposes its file; a provider URL becomes an embed URL.
	 *
	 * @return void
	 */
	public function test_video_graph_maps_hosted_and_provider_sources() {
		$artist  = $this->make_artist();
		$hosted  = $this->make_work( 'wavira_video', $artist, array( 'post_title' => 'Hosted video' ) );
		$youtube = $this->make_work( 'wavira_video', $artist, array( 'post_title' => 'YouTube video' ) );

		update_post_meta( $hosted, \Wavira\Core\Content\MetaSchema::VIDEO_720, 'https://cdn.example/video-720.mp4' );
		update_post_meta( $youtube, \Wavira\Core\Content\MetaSchema::VIDEO_URL, 'https://www.youtube.com/watch?v=AbC123' );

		$file_node = wavira_core_structured_data( $hosted )[0];
		$embed     = wavira_core_structured_data( $youtube )[0];

		$this->assertSame( 'MusicVideoObject', $file_node['@type'] );
		$this->assertSame( 'https://cdn.example/video-720.mp4', $file_node['contentUrl'] );
		$this->assertSame( 'https://www.youtube.com/embed/AbC123', $embed['embedUrl'] );
		$this->assertArrayNotHasKey( 'contentUrl', $embed );
	}

	/**
	 * Providers the product documents map; an unknown one keeps its URL.
	 *
	 * @return void
	 */
	public function test_embed_url_handles_short_and_unknown_providers() {
		$this->assertSame( 'https://www.youtube.com/embed/Ty2', \Wavira\Core\Seo\StructuredData::embed_url( 'https://youtu.be/Ty2' ) );
		$this->assertSame( 'https://www.youtube.com/embed/AbC123', \Wavira\Core\Seo\StructuredData::embed_url( 'https://music.youtube.com/watch?v=AbC123' ) );
		$this->assertSame(
			'https://www.aparat.com/video/video/embed/videohash/demo1/vt/frame',
			\Wavira\Core\Seo\StructuredData::embed_url( 'https://www.aparat.com/v/demo1' )
		);
		$this->assertSame( '', \Wavira\Core\Seo\StructuredData::embed_url( 'https://vimeo.com/12345' ) );
	}

	/**
	 * Posts, pages and unpublished music produce no graph at all.
	 *
	 * @return void
	 */
	public function test_only_published_music_produces_a_graph() {
		$post   = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$draft  = $this->make_artist( array( 'post_status' => 'draft' ) );
		$artist = $this->make_artist();

		$this->assertSame( array(), wavira_core_structured_data( $post ), 'a blog post belongs to the SEO plugin' );
		$this->assertSame( array(), wavira_core_structured_data( $draft ), 'a draft must not be described' );
		$this->assertSame( array(), wavira_core_structured_data( 999999 ) );
		$this->assertNotSame( array(), wavira_core_structured_data( $artist ) );
	}

	/**
	 * A site can switch the graph off with one filter.
	 *
	 * @return void
	 */
	public function test_structured_data_can_be_disabled() {
		$artist = $this->make_artist();

		$this->assertNotSame( array(), wavira_core_structured_data( $artist ) );

		add_filter( 'wavira_core_structured_data_enabled', '__return_false' );

		$this->assertSame( array(), wavira_core_structured_data( $artist ) );
		$this->assertSame( '', wavira_get_structured_data_markup( $artist ) );

		remove_filter( 'wavira_core_structured_data_enabled', '__return_false' );
	}

	/**
	 * The printed markup is one JSON-LD script tag that cannot be escaped out of.
	 *
	 * @return void
	 */
	public function test_markup_embeds_json_ld_and_cannot_be_closed_early() {
		$artist = $this->make_artist( array( 'post_title' => 'Closer </script> Artist' ) );

		$markup = wavira_get_structured_data_markup( $artist );

		$this->assertStringStartsWith( '<script type="application/ld+json">', $markup );
		$this->assertStringEndsWith( '</script>', $markup );
		$this->assertSame( 1, substr_count( $markup, '</script>' ), 'a title cannot close the tag' );
		$this->assertStringContainsString( 'schema.org', $markup );
		$this->assertStringContainsString( '\u003C/script\u003E', $markup );
	}

	/**
	 * The cooperation switch: no SEO plugin detected, the filter decides.
	 *
	 * @return void
	 */
	public function test_seo_plugin_detection_defaults_to_false_and_follows_the_filter() {
		$this->assertFalse( wavira_core_seo_plugin_active() );
		$this->assertFalse( wavira_has_seo_plugin() );

		add_filter( 'wavira_core_seo_plugin_active', '__return_true' );

		$this->assertTrue( wavira_core_seo_plugin_active() );
		$this->assertTrue( wavira_has_seo_plugin(), 'the theme follows the plugin' );

		add_filter( 'wavira_theme_seo_plugin_active', '__return_false' );

		$this->assertFalse( wavira_has_seo_plugin(), 'a theme-level override wins' );
	}

	/**
	 * The fallback meta tags print when nothing else does, and nothing when a
	 * plugin is active.
	 *
	 * @return void
	 */
	public function test_social_meta_is_a_fallback_only() {
		$post = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_title'   => 'A news post',
				'post_excerpt' => 'The summary a share card should use.',
			)
		);

		$this->go_to( get_permalink( $post ) );

		ob_start();
		wavira_print_social_meta();
		$markup = (string) ob_get_clean();

		$this->assertStringContainsString( '<meta name="description" content="The summary a share card should use." />', $markup );
		$this->assertStringContainsString( '<meta property="og:type" content="article" />', $markup );
		$this->assertStringContainsString( '<meta property="og:locale"', $markup );
		$this->assertStringContainsString( '<meta name="twitter:card"', $markup );
		$this->assertStringContainsString( 'article:published_time', $markup );

		add_filter( 'wavira_theme_seo_plugin_active', '__return_true' );

		ob_start();
		wavira_print_social_meta();
		$with_plugin = (string) ob_get_clean();

		$this->assertSame( '', $with_plugin, 'a SEO plugin owns the meta tags; the product stays out of the way' );
	}

	/**
	 * A music single's title carries the artist; other titles are untouched.
	 *
	 * @return void
	 */
	public function test_document_title_parts_add_the_artist_to_music_singles() {
		$artist = $this->make_artist();
		$track  = $this->make_work(
			'wavira_track',
			$artist,
			array(
				'post_title' => 'First track',
				'post_name'  => 'first-track',
			)
		);
		$post   = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'A news post',
				'post_name'   => 'a-news-post',
			)
		);

		$this->go_to( get_permalink( $track ) );

		$parts = wavira_document_title_parts( array( 'title' => 'First track' ) );

		$this->assertSame( 'First track · Demo Artist', $parts['title'] );

		$this->go_to( get_permalink( $post ) );

		$untouched = wavira_document_title_parts( array( 'title' => 'A news post' ) );

		$this->assertSame( 'A news post', $untouched['title'] );
	}

	/**
	 * The credit helper is what both the schema and the title read.
	 *
	 * @return void
	 */
	public function test_credit_names_are_ordered_and_published_only() {
		$primary  = $this->make_artist();
		$featured = $this->make_artist( array( 'post_title' => 'Featured Artist' ) );
		$draft    = $this->make_artist(
			array(
				'post_title'  => 'Unreleased Artist',
				'post_status' => 'draft',
			)
		);
		$track    = $this->make_work( 'wavira_track', $primary, array( 'post_title' => 'First track' ) );

		update_post_meta( $track, \Wavira\Core\Content\MetaSchema::FEATURED_ARTISTS, array( $featured, $draft, $primary ) );

		$this->assertSame( array( 'Demo Artist', 'Featured Artist' ), wavira_core_credit_names( $track ) );
	}
}
