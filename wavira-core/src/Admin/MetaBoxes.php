<?php
/**
 * Admin meta boxes for Tracks, Albums, Music Videos and Artists.
 *
 * Exposes the registered `MetaSchema` fields inside the WordPress post editor
 * with nonce verification, capability checks and `Meta` schema sanitization.
 *
 * @package Wavira\Core\Admin
 */

namespace Wavira\Core\Admin;

use WP_Post;
use Wavira\Core\Content\Meta;
use Wavira\Core\Content\MetaSchema;
use Wavira\Core\Content\MetaValues;
use Wavira\Core\Content\PostTypes;
use Wavira\Core\Content\Stats;
use Wavira\Core\Contracts\Registrable;

defined( 'ABSPATH' ) || exit;

/**
 * Class MetaBoxes
 */
final class MetaBoxes implements Registrable {

	/**
	 * Nonce action name.
	 *
	 * @var string
	 */
	public const NONCE_ACTION = 'wavira_save_music_meta';

	/**
	 * Nonce field name.
	 *
	 * @var string
	 */
	public const NONCE_NAME = '_wavira_meta_nonce';

	/**
	 * Register hooks for the post editor.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_boxes' ) );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
	}

	/**
	 * Register meta boxes for each music post type.
	 *
	 * @return void
	 */
	public function add_boxes(): void {
		add_meta_box(
			'wavira-track-meta',
			__( 'Track, Stream, Download & Podcast Details', 'wavira-core' ),
			array( $this, 'render_track_box' ),
			PostTypes::TRACK,
			'normal',
			'high'
		);

		add_meta_box(
			'wavira-album-meta',
			__( 'Album, Tracklist & ZIP Download Details', 'wavira-core' ),
			array( $this, 'render_album_box' ),
			PostTypes::ALBUM,
			'normal',
			'high'
		);

		add_meta_box(
			'wavira-video-meta',
			__( 'Music Video Stream & Multi-Quality Downloads', 'wavira-core' ),
			array( $this, 'render_video_box' ),
			PostTypes::VIDEO,
			'normal',
			'high'
		);

		add_meta_box(
			'wavira-artist-meta',
			__( 'Artist Profile & Social Channels', 'wavira-core' ),
			array( $this, 'render_artist_box' ),
			PostTypes::ARTIST,
			'normal',
			'high'
		);

		foreach ( PostTypes::all() as $post_type ) {
			add_meta_box(
				'wavira-stats-meta',
				__( 'Views, Likes & Admin Counter Offsets', 'wavira-core' ),
				array( $this, 'render_stats_box' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Render the Track & Podcast meta box.
	 *
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	public function render_track_box( WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$artist_id   = MetaValues::int( $post->ID, MetaSchema::ARTIST );
		$album_id    = MetaValues::int( $post->ID, MetaSchema::ALBUM );
		$featured    = implode( ', ', MetaValues::ids( $post->ID, MetaSchema::FEATURED_ARTISTS ) );
		$dl_enabled  = MetaValues::bool( $post->ID, MetaSchema::DOWNLOAD_ENABLED, true );
		$is_explicit = MetaValues::bool( $post->ID, MetaSchema::EXPLICIT );
		$is_featured = MetaValues::bool( $post->ID, MetaSchema::FEATURED );
		$in_index    = MetaValues::bool( $post->ID, MetaSchema::IN_INDEX_PLAYER );
		?>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="wavira_artist"><?php esc_html_e( 'Primary artist', 'wavira-core' ); ?></label></th>
					<td>
						<?php $this->render_post_select( MetaSchema::ARTIST, PostTypes::ARTIST, $artist_id ); ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wavira_featured_artists"><?php esc_html_e( 'Featured artist IDs (comma-separated)', 'wavira-core' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" id="wavira_featured_artists" name="wavira_meta[<?php echo esc_attr( MetaSchema::FEATURED_ARTISTS ); ?>]" value="<?php echo esc_attr( $featured ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wavira_credit_label"><?php esc_html_e( 'Credit display line (optional override)', 'wavira-core' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" id="wavira_credit_label" name="wavira_meta[<?php echo esc_attr( MetaSchema::CREDIT_LABEL ); ?>]" value="<?php echo esc_attr( MetaValues::text( $post->ID, MetaSchema::CREDIT_LABEL ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wavira_album"><?php esc_html_e( 'Parent album', 'wavira-core' ); ?></label></th>
					<td>
						<?php $this->render_post_select( MetaSchema::ALBUM, PostTypes::ALBUM, $album_id ); ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wavira_subtitle"><?php esc_html_e( 'Subtitle / short note', 'wavira-core' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" id="wavira_subtitle" name="wavira_meta[<?php echo esc_attr( MetaSchema::SUBTITLE ); ?>]" value="<?php echo esc_attr( MetaValues::text( $post->ID, MetaSchema::SUBTITLE ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wavira_audio_external"><?php esc_html_e( 'Online playback stream URL (independent from downloads)', 'wavira-core' ); ?></label></th>
					<td>
						<input type="url" class="large-text code" id="wavira_audio_external" name="wavira_meta[<?php echo esc_attr( MetaSchema::AUDIO_EXTERNAL ); ?>]" value="<?php echo esc_attr( MetaValues::url( $post->ID, MetaSchema::AUDIO_EXTERNAL ) ); ?>" placeholder="https://example.com/stream.mp3" />
						<p class="description"><?php esc_html_e( 'When filled, the online player streams from this URL; when empty, it falls back to the 320 or 128 kbps file below.', 'wavira-core' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wavira_audio_320"><?php esc_html_e( 'Download URL — 320 kbps (MP3)', 'wavira-core' ); ?></label></th>
					<td>
						<input type="url" class="large-text code" id="wavira_audio_320" name="wavira_meta[<?php echo esc_attr( MetaSchema::AUDIO_320 ); ?>]" value="<?php echo esc_attr( MetaValues::url( $post->ID, MetaSchema::AUDIO_320 ) ); ?>" placeholder="https://example.com/track-320.mp3" />
						<p class="description">
							<label for="wavira_file_size_320"><?php esc_html_e( 'File size 320 (bytes, optional):', 'wavira-core' ); ?></label>
							<input type="number" class="small-text" id="wavira_file_size_320" min="0" name="wavira_meta[<?php echo esc_attr( MetaSchema::FILE_SIZE_320 ); ?>]" value="<?php echo esc_attr( (string) MetaValues::int( $post->ID, MetaSchema::FILE_SIZE_320 ) ); ?>" />
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wavira_audio_128"><?php esc_html_e( 'Download URL — 128 kbps (MP3)', 'wavira-core' ); ?></label></th>
					<td>
						<input type="url" class="large-text code" id="wavira_audio_128" name="wavira_meta[<?php echo esc_attr( MetaSchema::AUDIO_128 ); ?>]" value="<?php echo esc_attr( MetaValues::url( $post->ID, MetaSchema::AUDIO_128 ) ); ?>" placeholder="https://example.com/track-128.mp3" />
						<p class="description">
							<label for="wavira_file_size_128"><?php esc_html_e( 'File size 128 (bytes, optional):', 'wavira-core' ); ?></label>
							<input type="number" class="small-text" id="wavira_file_size_128" min="0" name="wavira_meta[<?php echo esc_attr( MetaSchema::FILE_SIZE_128 ); ?>]" value="<?php echo esc_attr( (string) MetaValues::int( $post->ID, MetaSchema::FILE_SIZE_128 ) ); ?>" />
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wavira_duration"><?php esc_html_e( 'Duration (seconds)', 'wavira-core' ); ?></label></th>
					<td>
						<input type="number" class="small-text" id="wavira_duration" min="0" name="wavira_meta[<?php echo esc_attr( MetaSchema::DURATION ); ?>]" value="<?php echo esc_attr( (string) MetaValues::int( $post->ID, MetaSchema::DURATION ) ); ?>" />
						<label for="wavira_release_date"><?php esc_html_e( 'Release date (YYYY-MM-DD):', 'wavira-core' ); ?></label>
						<input type="date" id="wavira_release_date" name="wavira_meta[<?php echo esc_attr( MetaSchema::RELEASE_DATE ); ?>]" value="<?php echo esc_attr( MetaValues::text( $post->ID, MetaSchema::RELEASE_DATE ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Track flags', 'wavira-core' ); ?></th>
					<td>
						<input type="hidden" name="wavira_bool_present[<?php echo esc_attr( MetaSchema::DOWNLOAD_ENABLED ); ?>]" value="1" />
						<label><input type="checkbox" name="wavira_meta[<?php echo esc_attr( MetaSchema::DOWNLOAD_ENABLED ); ?>]" value="1" <?php checked( $dl_enabled ); ?> /> <?php esc_html_e( 'Offer download buttons when files exist', 'wavira-core' ); ?></label><br />
						<input type="hidden" name="wavira_bool_present[<?php echo esc_attr( MetaSchema::FEATURED ); ?>]" value="1" />
						<label><input type="checkbox" name="wavira_meta[<?php echo esc_attr( MetaSchema::FEATURED ); ?>]" value="1" <?php checked( $is_featured ); ?> /> <?php esc_html_e( 'Featured in Hero / Spotlight', 'wavira-core' ); ?></label><br />
						<input type="hidden" name="wavira_bool_present[<?php echo esc_attr( MetaSchema::IN_INDEX_PLAYER ); ?>]" value="1" />
						<label><input type="checkbox" name="wavira_meta[<?php echo esc_attr( MetaSchema::IN_INDEX_PLAYER ); ?>]" value="1" <?php checked( $in_index ); ?> /> <?php esc_html_e( 'Include in front-page player queue', 'wavira-core' ); ?></label><br />
						<input type="hidden" name="wavira_bool_present[<?php echo esc_attr( MetaSchema::EXPLICIT ); ?>]" value="1" />
						<label><input type="checkbox" name="wavira_meta[<?php echo esc_attr( MetaSchema::EXPLICIT ); ?>]" value="1" <?php checked( $is_explicit ); ?> /> <?php esc_html_e( 'Explicit content badge', 'wavira-core' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wavira_episode"><?php esc_html_e( 'Podcast episode number (optional)', 'wavira-core' ); ?></label></th>
					<td>
						<input type="number" class="small-text" id="wavira_episode" min="0" name="wavira_meta[<?php echo esc_attr( MetaSchema::EPISODE ); ?>]" value="<?php echo esc_attr( (string) MetaValues::int( $post->ID, MetaSchema::EPISODE ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wavira_chapters"><?php esc_html_e( 'Podcast / Mix timestamped tracklist (one per line, e.g. 00:00 Intro)', 'wavira-core' ); ?></label></th>
					<td>
						<textarea class="large-text code" rows="4" id="wavira_chapters" name="wavira_meta[<?php echo esc_attr( MetaSchema::CHAPTERS ); ?>]"><?php echo esc_textarea( MetaValues::html( $post->ID, MetaSchema::CHAPTERS ) ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wavira_lyrics"><?php esc_html_e( 'Lyrics / extra notes', 'wavira-core' ); ?></label></th>
					<td>
						<textarea class="large-text" rows="5" id="wavira_lyrics" name="wavira_meta[<?php echo esc_attr( MetaSchema::LYRICS ); ?>]"><?php echo esc_textarea( MetaValues::html( $post->ID, MetaSchema::LYRICS ) ); ?></textarea>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render the Album meta box.
	 *
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	public function render_album_box( WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$artist_id   = MetaValues::int( $post->ID, MetaSchema::ARTIST );
		$featured    = implode( ', ', MetaValues::ids( $post->ID, MetaSchema::FEATURED_ARTISTS ) );
		$tracklist   = implode( ', ', MetaValues::tracklist( $post->ID ) );
		$album_type  = MetaValues::text( $post->ID, MetaSchema::ALBUM_TYPE );
		$dl_enabled  = MetaValues::bool( $post->ID, MetaSchema::DOWNLOAD_ENABLED, true );
		$is_featured = MetaValues::bool( $post->ID, MetaSchema::FEATURED );
		?>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="wavira_artist"><?php esc_html_e( 'Primary artist', 'wavira-core' ); ?></label></th>
					<td><?php $this->render_post_select( MetaSchema::ARTIST, PostTypes::ARTIST, $artist_id ); ?></td>
				</tr>
				<tr>
					<th scope="row"><label for="wavira_featured_artists"><?php esc_html_e( 'Featured artist IDs (comma-separated)', 'wavira-core' ); ?></label></th>
					<td><input type="text" class="regular-text" id="wavira_featured_artists" name="wavira_meta[<?php echo esc_attr( MetaSchema::FEATURED_ARTISTS ); ?>]" value="<?php echo esc_attr( $featured ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="wavira_credit_label"><?php esc_html_e( 'Credit display line', 'wavira-core' ); ?></label></th>
					<td><input type="text" class="regular-text" id="wavira_credit_label" name="wavira_meta[<?php echo esc_attr( MetaSchema::CREDIT_LABEL ); ?>]" value="<?php echo esc_attr( MetaValues::text( $post->ID, MetaSchema::CREDIT_LABEL ) ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="wavira_album_type"><?php esc_html_e( 'Release type', 'wavira-core' ); ?></label></th>
					<td>
						<select id="wavira_album_type" name="wavira_meta[<?php echo esc_attr( MetaSchema::ALBUM_TYPE ); ?>]">
							<option value="album" <?php selected( $album_type, 'album' ); ?>><?php esc_html_e( 'Album', 'wavira-core' ); ?></option>
							<option value="ep" <?php selected( $album_type, 'ep' ); ?>><?php esc_html_e( 'EP', 'wavira-core' ); ?></option>
							<option value="single" <?php selected( $album_type, 'single' ); ?>><?php esc_html_e( 'Single', 'wavira-core' ); ?></option>
							<option value="compilation" <?php selected( $album_type, 'compilation' ); ?>><?php esc_html_e( 'Compilation', 'wavira-core' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wavira_tracklist"><?php esc_html_e( 'Ordered tracklist (track IDs in playing order)', 'wavira-core' ); ?></label></th>
					<td>
						<input type="text" class="large-text code" id="wavira_tracklist" name="wavira_meta[<?php echo esc_attr( MetaSchema::TRACKLIST ); ?>]" value="<?php echo esc_attr( $tracklist ); ?>" />
						<p class="description"><?php esc_html_e( 'Enter published track IDs separated by commas in the exact album order.', 'wavira-core' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wavira_album_audio_320"><?php esc_html_e( 'Full album ZIP download — 320 kbps', 'wavira-core' ); ?></label></th>
					<td><input type="url" class="large-text code" id="wavira_album_audio_320" name="wavira_meta[<?php echo esc_attr( MetaSchema::ALBUM_AUDIO_320 ); ?>]" value="<?php echo esc_attr( MetaValues::url( $post->ID, MetaSchema::ALBUM_AUDIO_320 ) ); ?>" placeholder="https://example.com/album-320.zip" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="wavira_album_audio_128"><?php esc_html_e( 'Full album ZIP download — 128 kbps', 'wavira-core' ); ?></label></th>
					<td><input type="url" class="large-text code" id="wavira_album_audio_128" name="wavira_meta[<?php echo esc_attr( MetaSchema::ALBUM_AUDIO_128 ); ?>]" value="<?php echo esc_attr( MetaValues::url( $post->ID, MetaSchema::ALBUM_AUDIO_128 ) ); ?>" placeholder="https://example.com/album-128.zip" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Album flags', 'wavira-core' ); ?></th>
					<td>
						<input type="hidden" name="wavira_bool_present[<?php echo esc_attr( MetaSchema::DOWNLOAD_ENABLED ); ?>]" value="1" />
						<label><input type="checkbox" name="wavira_meta[<?php echo esc_attr( MetaSchema::DOWNLOAD_ENABLED ); ?>]" value="1" <?php checked( $dl_enabled ); ?> /> <?php esc_html_e( 'Offer album download buttons when files exist', 'wavira-core' ); ?></label><br />
						<input type="hidden" name="wavira_bool_present[<?php echo esc_attr( MetaSchema::FEATURED ); ?>]" value="1" />
						<label><input type="checkbox" name="wavira_meta[<?php echo esc_attr( MetaSchema::FEATURED ); ?>]" value="1" <?php checked( $is_featured ); ?> /> <?php esc_html_e( 'Featured in Hero / Spotlight', 'wavira-core' ); ?></label>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render the Music Video meta box.
	 *
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	public function render_video_box( WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$artist_id   = MetaValues::int( $post->ID, MetaSchema::ARTIST );
		$album_id    = MetaValues::int( $post->ID, MetaSchema::ALBUM );
		$source      = MetaValues::text( $post->ID, MetaSchema::VIDEO_SOURCE );
		$dl_enabled  = MetaValues::bool( $post->ID, MetaSchema::DOWNLOAD_ENABLED, true );
		$is_featured = MetaValues::bool( $post->ID, MetaSchema::FEATURED );
		?>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="wavira_artist"><?php esc_html_e( 'Primary artist', 'wavira-core' ); ?></label></th>
					<td><?php $this->render_post_select( MetaSchema::ARTIST, PostTypes::ARTIST, $artist_id ); ?></td>
				</tr>
				<tr>
					<th scope="row"><label for="wavira_album"><?php esc_html_e( 'Related album', 'wavira-core' ); ?></label></th>
					<td><?php $this->render_post_select( MetaSchema::ALBUM, PostTypes::ALBUM, $album_id ); ?></td>
				</tr>
				<tr>
					<th scope="row"><label for="wavira_video_source"><?php esc_html_e( 'Online playback source', 'wavira-core' ); ?></label></th>
					<td>
						<select id="wavira_video_source" name="wavira_meta[<?php echo esc_attr( MetaSchema::VIDEO_SOURCE ); ?>]">
							<option value="self" <?php selected( $source, 'self' ); ?>><?php esc_html_e( 'Direct hosted video file (MP4 / WebM)', 'wavira-core' ); ?></option>
							<option value="aparat" <?php selected( $source, 'aparat' ); ?>><?php esc_html_e( 'Aparat', 'wavira-core' ); ?></option>
							<option value="youtube" <?php selected( $source, 'youtube' ); ?>><?php esc_html_e( 'YouTube', 'wavira-core' ); ?></option>
							<option value="vimeo" <?php selected( $source, 'vimeo' ); ?>><?php esc_html_e( 'Vimeo', 'wavira-core' ); ?></option>
							<option value="other" <?php selected( $source, 'other' ); ?>><?php esc_html_e( 'Other external provider', 'wavira-core' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wavira_video_url"><?php esc_html_e( 'Online stream / embed URL', 'wavira-core' ); ?></label></th>
					<td><input type="url" class="large-text code" id="wavira_video_url" name="wavira_meta[<?php echo esc_attr( MetaSchema::VIDEO_URL ); ?>]" value="<?php echo esc_attr( MetaValues::url( $post->ID, MetaSchema::VIDEO_URL ) ); ?>" placeholder="https://example.com/video-stream.mp4" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="wavira_video_2160"><?php esc_html_e( 'Download URL — 4K (2160p)', 'wavira-core' ); ?></label></th>
					<td><input type="url" class="large-text code" id="wavira_video_2160" name="wavira_meta[<?php echo esc_attr( MetaSchema::VIDEO_2160 ); ?>]" value="<?php echo esc_attr( MetaValues::url( $post->ID, MetaSchema::VIDEO_2160 ) ); ?>" placeholder="https://example.com/video-2160.mp4" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="wavira_video_1080"><?php esc_html_e( 'Download URL — Full HD (1080p)', 'wavira-core' ); ?></label></th>
					<td><input type="url" class="large-text code" id="wavira_video_1080" name="wavira_meta[<?php echo esc_attr( MetaSchema::VIDEO_1080 ); ?>]" value="<?php echo esc_attr( MetaValues::url( $post->ID, MetaSchema::VIDEO_1080 ) ); ?>" placeholder="https://example.com/video-1080.mp4" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="wavira_video_720"><?php esc_html_e( 'Download URL — HD (720p)', 'wavira-core' ); ?></label></th>
					<td><input type="url" class="large-text code" id="wavira_video_720" name="wavira_meta[<?php echo esc_attr( MetaSchema::VIDEO_720 ); ?>]" value="<?php echo esc_attr( MetaValues::url( $post->ID, MetaSchema::VIDEO_720 ) ); ?>" placeholder="https://example.com/video-720.mp4" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="wavira_video_480"><?php esc_html_e( 'Download URL — SD (480p)', 'wavira-core' ); ?></label></th>
					<td><input type="url" class="large-text code" id="wavira_video_480" name="wavira_meta[<?php echo esc_attr( MetaSchema::VIDEO_480 ); ?>]" value="<?php echo esc_attr( MetaValues::url( $post->ID, MetaSchema::VIDEO_480 ) ); ?>" placeholder="https://example.com/video-480.mp4" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="wavira_duration"><?php esc_html_e( 'Duration (seconds)', 'wavira-core' ); ?></label></th>
					<td><input type="number" class="small-text" id="wavira_duration" min="0" name="wavira_meta[<?php echo esc_attr( MetaSchema::DURATION ); ?>]" value="<?php echo esc_attr( (string) MetaValues::int( $post->ID, MetaSchema::DURATION ) ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Video flags', 'wavira-core' ); ?></th>
					<td>
						<input type="hidden" name="wavira_bool_present[<?php echo esc_attr( MetaSchema::DOWNLOAD_ENABLED ); ?>]" value="1" />
						<label><input type="checkbox" name="wavira_meta[<?php echo esc_attr( MetaSchema::DOWNLOAD_ENABLED ); ?>]" value="1" <?php checked( $dl_enabled ); ?> /> <?php esc_html_e( 'Offer video download buttons when files exist', 'wavira-core' ); ?></label><br />
						<input type="hidden" name="wavira_bool_present[<?php echo esc_attr( MetaSchema::FEATURED ); ?>]" value="1" />
						<label><input type="checkbox" name="wavira_meta[<?php echo esc_attr( MetaSchema::FEATURED ); ?>]" value="1" <?php checked( $is_featured ); ?> /> <?php esc_html_e( 'Featured in Hero / Spotlight', 'wavira-core' ); ?></label>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render the Artist meta box.
	 *
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	public function render_artist_box( WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$verified = MetaValues::bool( $post->ID, MetaSchema::VERIFIED );
		$related  = implode( ', ', MetaValues::ids( $post->ID, MetaSchema::RELATED_ARTISTS ) );
		?>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Verification', 'wavira-core' ); ?></th>
					<td>
						<input type="hidden" name="wavira_bool_present[<?php echo esc_attr( MetaSchema::VERIFIED ); ?>]" value="1" />
						<label><input type="checkbox" name="wavira_meta[<?php echo esc_attr( MetaSchema::VERIFIED ); ?>]" value="1" <?php checked( $verified ); ?> /> <?php esc_html_e( 'Verified artist badge', 'wavira-core' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wavira_country"><?php esc_html_e( 'Country / Origin', 'wavira-core' ); ?></label></th>
					<td><input type="text" class="regular-text" id="wavira_country" name="wavira_meta[<?php echo esc_attr( MetaSchema::COUNTRY ); ?>]" value="<?php echo esc_attr( MetaValues::text( $post->ID, MetaSchema::COUNTRY ) ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="wavira_website"><?php esc_html_e( 'Official website', 'wavira-core' ); ?></label></th>
					<td><input type="url" class="large-text code" id="wavira_website" name="wavira_meta[<?php echo esc_attr( MetaSchema::WEBSITE ); ?>]" value="<?php echo esc_attr( MetaValues::url( $post->ID, MetaSchema::WEBSITE ) ); ?>" /></td>
				</tr>
				<?php foreach ( MetaSchema::social_keys() as $social_key ) : ?>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( $social_key ); ?>"><?php echo esc_html( ucfirst( str_replace( 'wavira_social_', '', $social_key ) ) ); ?></label></th>
						<td><input type="url" class="large-text code" id="<?php echo esc_attr( $social_key ); ?>" name="wavira_meta[<?php echo esc_attr( $social_key ); ?>]" value="<?php echo esc_attr( MetaValues::url( $post->ID, $social_key ) ); ?>" /></td>
					</tr>
				<?php endforeach; ?>
				<tr>
					<th scope="row"><label for="wavira_related_artists"><?php esc_html_e( 'Related artist IDs (comma-separated)', 'wavira-core' ); ?></label></th>
					<td><input type="text" class="regular-text" id="wavira_related_artists" name="wavira_meta[<?php echo esc_attr( MetaSchema::RELATED_ARTISTS ); ?>]" value="<?php echo esc_attr( $related ); ?>" /></td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render the Engagement & Admin Counter Offsets side box.
	 *
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	public function render_stats_box( WP_Post $post ): void {
		$stats = Stats::breakdown( $post->ID );
		?>
		<p>
			<strong><?php esc_html_e( 'Recorded views:', 'wavira-core' ); ?></strong>
			<?php echo esc_html( (string) $stats['recorded_views'] ); ?>
			(<?php esc_html_e( 'Public total:', 'wavira-core' ); ?> <strong><?php echo esc_html( (string) $stats['views'] ); ?></strong>)
		</p>
		<p>
			<label for="wavira_views_offset"><?php esc_html_e( 'Admin views offset:', 'wavira-core' ); ?></label><br />
			<input type="number" class="small-text" id="wavira_views_offset" min="0" name="wavira_meta[<?php echo esc_attr( MetaSchema::VIEWS_OFFSET ); ?>]" value="<?php echo esc_attr( (string) $stats['views_offset'] ); ?>" />
		</p>
		<hr />
		<p>
			<strong><?php esc_html_e( 'Recorded likes:', 'wavira-core' ); ?></strong>
			<?php echo esc_html( (string) $stats['recorded_likes'] ); ?>
			(<?php esc_html_e( 'Public total:', 'wavira-core' ); ?> <strong><?php echo esc_html( (string) $stats['likes'] ); ?></strong>)
		</p>
		<p>
			<label for="wavira_likes_offset"><?php esc_html_e( 'Admin likes offset:', 'wavira-core' ); ?></label><br />
			<input type="number" class="small-text" id="wavira_likes_offset" min="0" name="wavira_meta[<?php echo esc_attr( MetaSchema::LIKES_OFFSET ); ?>]" value="<?php echo esc_attr( (string) $stats['likes_offset'] ); ?>" />
		</p>
		<?php
	}

	/**
	 * Save meta box fields on `save_post`.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public function save( int $post_id, $post ): void {
		if ( ! $post instanceof WP_Post || ! PostTypes::is_music_type( $post->post_type ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		$nonce = isset( $_POST[ self::NONCE_NAME ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_NAME ] ) ) : '';

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$schema       = MetaSchema::all();
		$posted       = isset( $_POST['wavira_meta'] ) && is_array( $_POST['wavira_meta'] ) ? wp_unslash( $_POST['wavira_meta'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each key is sanitized against MetaSchema below.
		$bool_present = isset( $_POST['wavira_bool_present'] ) && is_array( $_POST['wavira_bool_present'] ) ? wp_unslash( $_POST['wavira_bool_present'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- presence flags only.

		foreach ( $schema as $key => $field ) {
			if ( ! in_array( $post->post_type, $field['entities'], true ) ) {
				continue;
			}

			if ( isset( $field['rest'] ) && false === $field['rest'] ) {
				continue;
			}

			if ( 'bool' === $field['sanitize'] ) {
				if ( ! isset( $bool_present[ $key ] ) ) {
					continue;
				}

				$checked = isset( $posted[ $key ] ) && Meta::sanitize_bool( $posted[ $key ] );
				update_post_meta( $post_id, $key, $checked ? '1' : '' );
				continue;
			}

			if ( ! array_key_exists( $key, $posted ) ) {
				continue;
			}

			$raw = $posted[ $key ];

			switch ( $field['sanitize'] ) {
				case 'id':
					update_post_meta( $post_id, $key, Meta::sanitize_id( $raw ) );
					break;
				case 'ids':
					update_post_meta( $post_id, $key, Meta::sanitize_ids( $raw ) );
					break;
				case 'url':
					update_post_meta( $post_id, $key, Meta::sanitize_url( $raw ) );
					break;
				case 'int':
					update_post_meta( $post_id, $key, Meta::sanitize_int( $raw ) );
					break;
				case 'date':
					update_post_meta( $post_id, $key, Meta::sanitize_date( $raw ) );
					break;
				case 'html':
					update_post_meta( $post_id, $key, Meta::sanitize_html( $raw ) );
					break;
				case 'enum':
					$enum = isset( $field['enum'] ) ? (array) $field['enum'] : array();
					update_post_meta( $post_id, $key, Meta::sanitize_enum( $raw, $enum ) );
					break;
				default:
					update_post_meta( $post_id, $key, Meta::sanitize_text( $raw ) );
					break;
			}
		}
	}

	/**
	 * Render a `<select>` of published posts for an entity relation.
	 *
	 * @param string $meta_key  Meta key name.
	 * @param string $post_type Target post type.
	 * @param int    $selected  Currently selected post ID.
	 * @return void
	 */
	private function render_post_select( string $meta_key, string $post_type, int $selected ): void {
		$items = get_posts(
			array(
				'post_type'              => $post_type,
				'post_status'            => 'publish',
				'posts_per_page'         => 200,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		?>
		<select id="<?php echo esc_attr( $meta_key ); ?>" name="wavira_meta[<?php echo esc_attr( $meta_key ); ?>]">
			<option value="0"><?php esc_html_e( '— None —', 'wavira-core' ); ?></option>
			<?php foreach ( $items as $item ) : ?>
				<?php if ( $item instanceof WP_Post ) : ?>
					<option value="<?php echo esc_attr( (string) $item->ID ); ?>" <?php selected( $selected, $item->ID ); ?>>
						<?php echo esc_html( get_the_title( $item ) . ' (#' . $item->ID . ')' ); ?>
					</option>
				<?php endif; ?>
			<?php endforeach; ?>
		</select>
		<?php
	}
}
