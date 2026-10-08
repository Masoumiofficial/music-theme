<?php
/**
 * Runtime verification of the theme's download and gallery surfaces (0.15.0).
 *
 * The rule the whole release rests on is “no file, no link”: a download that
 * leads to an error page is worse than a page without one, and on a fresh
 * install a track with no audio *is* the normal state. This suite holds the
 * theme to that rule from both sides — the silence and the link — with the
 * theme's own PHP loaded from the repository (the pattern is
 * `tests/test-blocks.php`). The plugin supplies the payload, the theme decides
 * what a visitor sees, and the `wp-render` job verifies the same thing end to
 * end on a real install with the demo's generated media.
 *
 * @package Wavira\Tests
 */

use Wavira\Core\Content\MetaSchema;

/**
 * Class Test_Theme_Downloads
 */
class Test_Theme_Downloads extends Wavira_Test_Case {

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

		foreach ( array( 'helpers', 'markup', 'artists', 'downloads' ) as $file ) {
			$path = WAVIRA_THEME_DIR . 'inc/' . $file . '.php';

			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}
	}

	/**
	 * Create a published post of a music type.
	 *
	 * @param string $post_type Post type.
	 * @param string $title     Title.
	 * @return int Post ID.
	 */
	private function make_post( string $post_type, string $title ) {
		return self::factory()->post->create(
			array(
				'post_type'   => $post_type,
				'post_status' => 'publish',
				'post_title'  => $title,
			)
		);
	}

	/**
	 * A size the site knows is a short string; a size it does not know is silence.
	 *
	 * Printing “0 KB” for an unknown file would be a number the site invented.
	 *
	 * @return void
	 */
	public function test_size_labels_are_short_and_never_invented() {
		$this->assertSame( '', wavira_download_size_label( 0 ) );
		$this->assertSame( '', wavira_download_size_label( -12 ) );
		$this->assertSame( '2 KB', wavira_download_size_label( 2048 ) );
		$this->assertSame( '3.5 MB', wavira_download_size_label( 3670016 ) );
	}

	/**
	 * The button names what it downloads, and falls back to the act itself.
	 *
	 * @return void
	 */
	public function test_the_button_names_what_it_downloads() {
		$this->assertSame( 'Download the track', wavira_download_type_label( 0, 'track' ) );
		$this->assertSame( 'Download the album', wavira_download_type_label( 0, 'album' ) );
		$this->assertSame( 'Download the video', wavira_download_type_label( 0, 'video' ) );
		$this->assertSame( 'Download the image', wavira_download_type_label( 0, 'image' ) );
		$this->assertSame( 'Download', wavira_download_type_label( 0, 'something-else' ) );
	}

	/**
	 * A post with no file offers nothing — in every variant.
	 *
	 * @return void
	 */
	public function test_a_post_without_a_file_prints_nothing() {
		$track = $this->make_post( 'wavira_track', 'Track with no audio' );

		$this->assertSame( array(), wavira_download_qualities( $track ) );
		$this->assertSame( '', wavira_get_download( $track ) );
		$this->assertSame( '', wavira_get_download( $track, 'link' ) );
		$this->assertSame( '', wavira_get_download( $track, 'list', 0, '', true ) );
		$this->assertSame( '', wavira_get_download( 999999 ) );

		ob_start();
		wavira_download( $track, 'button' );
		$this->assertSame( '', ob_get_clean(), 'the printing helper prints nothing at all' );
	}

	/**
	 * A track with audio offers its qualities, its sizes and a link that is the
	 * authorized route — not the file URL the plugin stores.
	 *
	 * @return void
	 */
	public function test_a_track_with_audio_offers_its_qualities() {
		$track = $this->make_post( 'wavira_track', 'Track with two files' );

		update_post_meta( $track, MetaSchema::AUDIO_128, 'https://example.test/track-128.mp3' );
		update_post_meta( $track, MetaSchema::AUDIO_320, 'https://example.test/track-320.mp3' );
		update_post_meta( $track, MetaSchema::FILE_SIZE_320, 3670016 );

		$qualities = wavira_download_qualities( $track );

		$this->assertCount( 2, $qualities );
		$this->assertSame( array( 320, 128 ), array_column( $qualities, 'quality' ), 'best quality first' );

		$list = wavira_get_download( $track, 'list', 0, '', true );

		$this->assertStringContainsString( 'wavira-download--list', $list );
		$this->assertStringContainsString( '320 kbps', $list );
		$this->assertStringContainsString( '128 kbps', $list );
		$this->assertStringContainsString( '3.5 MB', $list );
		$this->assertStringContainsString( 'wavira/v1/download/' . $track, $list );
		$this->assertStringNotContainsString( 'track-320.mp3', $list, 'the file URL is never printed' );

		$button = wavira_get_download( $track );

		$this->assertStringContainsString( 'wavira-download--button', $button );
		$this->assertStringContainsString( 'Download the track', $button );
		$this->assertStringContainsString( '320 kbps', $button );
		$this->assertStringContainsString( 'download', $button );
	}

	/**
	 * A track that opted out offers nothing, even with files present.
	 *
	 * @return void
	 */
	public function test_the_per_post_opt_out_wins() {
		$track = $this->make_post( 'wavira_track', 'Track with downloads off' );

		update_post_meta( $track, MetaSchema::AUDIO_320, 'https://example.test/track-320.mp3' );

		$this->assertNotSame( '', wavira_get_download( $track ) );

		// `false` is stored as an empty string, so the row's existence is the
		// signal — the same trap the plugin's own suite covers.
		update_post_meta( $track, MetaSchema::DOWNLOAD_ENABLED, '' );

		$this->assertSame( array(), wavira_download_qualities( $track ) );
		$this->assertSame( '', wavira_get_download( $track ) );
	}

	/**
	 * An album offers its own master file, under the album's name.
	 *
	 * @return void
	 */
	public function test_an_album_offers_its_master_file() {
		$album = $this->make_post( 'wavira_album', 'Album with a master file' );

		update_post_meta( $album, MetaSchema::ALBUM_AUDIO_128, 'https://example.test/album-128.mp3' );

		$html = wavira_get_download( $album );

		$this->assertStringContainsString( 'Download the album', $html );
		$this->assertStringContainsString( 'wavira/v1/download/' . $album, $html );
	}

	/**
	 * An embedded video is not a download: there is no file to hand out.
	 *
	 * @return void
	 */
	public function test_an_embedded_video_is_not_a_download() {
		$video = $this->make_post( 'wavira_video', 'Embedded video' );

		update_post_meta( $video, MetaSchema::VIDEO_SOURCE, 'embed' );
		update_post_meta( $video, MetaSchema::VIDEO_URL, 'https://www.youtube.com/watch?v=0123456789' );

		$this->assertSame( array(), wavira_download_qualities( $video ) );
		$this->assertSame( '', wavira_get_download( $video ) );

		// A hosted file on the same post is a download again.
		update_post_meta( $video, MetaSchema::VIDEO_720, 'https://example.test/video-720.mp4' );

		$html = wavira_get_download( $video );

		$this->assertStringContainsString( 'Download the video', $html );
		$this->assertStringContainsString( '720 pixels', $html );
	}

	/**
	 * A post with no photos gets no gallery section — not an empty one.
	 *
	 * @return void
	 */
	public function test_a_post_without_photos_renders_no_gallery() {
		$album = $this->make_post( 'wavira_album', 'Album with no photos' );

		$this->assertSame( array(), wavira_get_photos( $album ) );
		$this->assertSame( '', wavira_get_post_gallery( $album ) );
		$this->assertSame( array(), wavira_get_photos( 0 ) );
	}

	/**
	 * Photos are the published images attached to the post being viewed, with
	 * their caption and alt text — the payload the gallery and its lightbox print.
	 *
	 * @return void
	 */
	public function test_photos_come_from_the_post_with_their_caption_and_alt_text() {
		$artist = $this->make_post( 'wavira_artist', 'Photographed artist' );

		$attachment = self::factory()->attachment->create_object(
			'stage.jpg',
			$artist,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'On stage in Tehran',
			)
		);

		update_post_meta( $attachment, '_wp_attachment_image_alt', 'A singer on stage' );

		$photos = wavira_get_photos( $artist );

		$this->assertCount( 1, $photos );
		$this->assertSame( (int) $attachment, $photos[0]['id'] );
		$this->assertSame( 'On stage in Tehran', $photos[0]['caption'] );
		$this->assertSame( 'A singer on stage', $photos[0]['alt'] );

		$html = wavira_get_post_gallery( $artist );

		$this->assertStringContainsString( 'wavira-gallery__items', $html );
		$this->assertStringContainsString( 'On stage in Tehran', $html );
		$this->assertStringContainsString( 'A singer on stage', $html );
	}
}
