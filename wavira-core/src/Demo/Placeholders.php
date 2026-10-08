<?php
/**
 * Generated placeholder media for the demo.
 *
 * The demo shipped no images and no audio at all, for a good reason: a URL that
 * 404s is worse than no URL (ADR 0010). The consequence was a demo site with no
 * cover art, nothing to play and nothing to download — the product looked broken
 * on the one screen a buyer judges it by.
 *
 * This class generates the files *here*, so nothing is fetched and nothing can
 * 404: a PNG cover per release (a gradient in the release's own colour), a PNG
 * photo set for the artist, a PNG poster for the video, and a short WAV tone per
 * track so the player, the queue, the download button and the counters all have
 * something real to work with.
 *
 * Everything is written with PHP only — no GD, no Imagick, no ffmpeg — because a
 * demo that needs an extension is a demo that fails on shared hosting. The files
 * are stored in the uploads directory as ordinary attachments, so a site owner can
 * replace them from the media library like any other image or audio file, and the
 * `_wavira_demo` marker means a forced re-import cleans up only what it created.
 *
 * @package Wavira\Core\Demo
 */

namespace Wavira\Core\Demo;

defined( 'ABSPATH' ) || exit;

/**
 * Class Placeholders
 */
final class Placeholders {

	/**
	 * Marker written into the meta of every generated attachment.
	 */
	public const MARKER = '_wavira_demo_media';

	/**
	 * Generate and attach a cover image for a post.
	 *
	 * @param int    $post_id Post to attach the image to.
	 * @param array  $palette Two RGB triples: start and end colour.
	 * @param string $seed    Text used to vary the pattern between covers.
	 * @param int    $size    Square edge in pixels.
	 * @return int Attachment ID, 0 on failure.
	 */
	public static function cover( int $post_id, array $palette, string $seed, int $size = 800 ): int {
		$png = self::png( $size, $size, $palette, $seed );

		if ( '' === $png ) {
			return 0;
		}

		$name = sprintf( 'wavira-demo-cover-%d.png', $post_id );
		$id   = self::store( $png, $name, 'image/png', true, $post_id );

		if ( $id < 1 ) {
			return 0;
		}

		self::make_alt( $id, __( 'Demo cover art', 'wavira-core' ) );

		set_post_thumbnail( $post_id, $id );

		return $id;
	}

	/**
	 * Generate and attach a gallery photo to a post.
	 *
	 * @param int    $post_id Post to attach the photo to.
	 * @param array  $palette Two RGB triples.
	 * @param string $seed    Text used to vary the pattern.
	 * @param int    $index   Photo number, used in the file name.
	 * @param int    $size    Image edge in pixels.
	 * @return int Attachment ID, 0 on failure.
	 */
	public static function photo( int $post_id, array $palette, string $seed, int $index, int $size = 1000 ): int {
		$png = self::png( (int) round( $size * 1.5 ), $size, $palette, $seed );

		if ( '' === $png ) {
			return 0;
		}

		$name = sprintf( 'wavira-demo-photo-%d-%d.png', $post_id, $index );
		$id   = self::store( $png, $name, 'image/png', true, $post_id );

		if ( $id < 1 ) {
			return 0;
		}

		self::make_alt(
			$id,
			sprintf(
				/* translators: %d: photo number in the demo gallery. */
				__( 'Demo gallery photo %d', 'wavira-core' ),
				$index
			)
		);

		return $id;
	}

	/**
	 * Generate and attach the audio file of a track.
	 *
	 * A short tone, not music: the point is that play, pause, seek, the queue, the
	 * download button and the counter can all be exercised on a fresh install, and
	 * that a visitor clicking play hears something instead of an error.
	 *
	 * @param int $track_id Track post ID.
	 * @param int $seconds  Length of the file.
	 * @param int $tone     Base frequency in Hz.
	 * @return array<string, mixed> `id`, `url`, `size`; empty when nothing was written.
	 */
	public static function audio( int $track_id, int $seconds = 6, int $tone = 440 ): array {
		$written = array();

		foreach ( array( 128, 320 ) as $kbps ) {
			$wav = self::wav( (float) $seconds, $tone, $kbps );

			if ( '' === $wav ) {
				continue;
			}

			$name = sprintf( 'wavira-demo-track-%d-%dkbps.wav', $track_id, $kbps );
			$id   = self::store( $wav, $name, 'audio/wav', false, $track_id );

			if ( $id < 1 ) {
				continue;
			}

			$path = (string) get_attached_file( $id );

			update_post_meta( $track_id, sprintf( 'wavira_audio_%d', $kbps ), (string) wp_get_attachment_url( $id ) );
			update_post_meta( $track_id, sprintf( 'wavira_file_size_%d', $kbps ), (int) ( file_exists( $path ) ? filesize( $path ) : 0 ) );

			$written[ $kbps ] = $id;
		}

		if ( empty( $written ) ) {
			return array();
		}

		$best = max( $written );
		$path = (string) get_attached_file( $best );

		return array(
			'id'   => (int) $best,
			'url'  => (string) wp_get_attachment_url( $best ),
			'size' => (int) ( file_exists( $path ) ? filesize( $path ) : 0 ),
		);
	}

	/**
	 * Generate and attach the master file of an album.
	 *
	 * @param int $album_id Album post ID.
	 * @param int $seconds  Length.
	 * @param int $tone     Base frequency in Hz.
	 * @return int Attachment ID, 0 on failure.
	 */
	public static function album_audio( int $album_id, int $seconds = 8, int $tone = 330 ): int {
		$wav = self::wav( (float) $seconds, $tone, 320 );

		if ( '' === $wav ) {
			return 0;
		}

		$id = self::store( $wav, sprintf( 'wavira-demo-album-%d.wav', $album_id ), 'audio/wav', false, $album_id );

		if ( $id < 1 ) {
			return 0;
		}

		update_post_meta( $album_id, 'wavira_album_audio_320', (string) wp_get_attachment_url( $id ) );

		return $id;
	}

	/**
	 * Store bytes in the uploads directory as an attachment.
	 *
	 * @param string $bytes    File contents.
	 * @param string $name     File name.
	 * @param string $mime     MIME type.
	 * @param bool   $is_image Whether the file is an image (a WAV is not).
	 * @return int Attachment ID, 0 on failure.
	 */
	private static function store( string $bytes, string $name, string $mime, bool $is_image = true, int $parent = 0 ): int {
		$upload = wp_upload_bits( $name, null, $bytes );

		if ( ! empty( $upload['error'] ) || empty( $upload['file'] ) ) {
			return 0;
		}

		$id = wp_insert_attachment(
			array(
				'post_mime_type' => $mime,
				'post_title'     => pathinfo( $name, PATHINFO_FILENAME ),
				'post_content'   => '',
				'post_status'    => 'inherit',
				// Attached to its post: the artist gallery *is* the attachments of
				// the artist post, so an unattached photo is invisible.
				'post_parent'    => $parent,
			),
			$upload['file']
		);

		if ( is_wp_error( $id ) || $id < 1 ) {
			return 0;
		}

		update_post_meta( (int) $id, self::MARKER, 1 );

		// Core's metadata generator understands PNG; a WAV has no sizes to guess
		// and a broken `_wp_attachment_metadata` is worse than none.
		if ( $is_image && file_exists( ABSPATH . 'wp-admin/includes/image.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';

			wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
		}

		return (int) $id;
	}

	/**
	 * Give an attachment an alt text so no image ships without one.
	 *
	 * @param int    $id Attachment ID.
	 * @param string $alt Alt text.
	 * @return void
	 */
	private static function make_alt( int $id, string $alt ): void {
		update_post_meta( $id, '_wp_attachment_image_alt', $alt );
	}

	/**
	 * A PNG file, built byte by byte.
	 *
	 * GD is an optional extension and the demo must not need it, so the encoder is
	 * here: the canvas is filled with a diagonal gradient plus a soft shape derived
	 * from the seed, the rows go through `zlib` (what `IDAT` holds) and every chunk
	 * gets its CRC. About forty lines against a dependency that fails on the host
	 * the demo is most likely to run on.
	 *
	 * @param int    $width   Width in pixels.
	 * @param int    $height  Height in pixels.
	 * @param array  $palette Two RGB triples.
	 * @param string $seed    Text that varies the pattern.
	 * @return string PNG bytes, empty string when zlib is unavailable.
	 */
	public static function png( int $width, int $height, array $palette, string $seed = '' ): string {
		if ( ! function_exists( 'gzcompress' ) || $width < 1 || $height < 1 ) {
			return '';
		}

		$start = self::rgb( $palette[0] ?? array( 30, 41, 59 ) );
		$end   = self::rgb( $palette[1] ?? array( 120, 90, 200 ) );
		$hash  = crc32( $seed );
		$rows  = '';

		for ( $y = 0; $y < $height; $y++ ) {
			$rows .= "\x00"; // Filter type 0 for this row.

			for ( $x = 0; $x < $width; $x++ ) {
				$t = ( $x / max( 1, $width - 1 ) + $y / max( 1, $height - 1 ) ) / 2;

				// A soft radial band, seeded per cover, so two covers never look
				// like the same picture with a different gradient.
				$cx    = 0.25 + ( ( $hash >> 8 ) % 50 ) / 100;
				$cy    = 0.25 + ( ( $hash >> 16 ) % 50 ) / 100;
				$dx    = ( $x / max( 1, $width - 1 ) ) - $cx;
				$dy    = ( $y / max( 1, $height - 1 ) ) - $cy;
				$band  = max( 0.0, 1.0 - sqrt( $dx * $dx + $dy * $dy ) * 1.6 );
				$shift = 0.45 * $band;

				$rows .= chr( self::mix( $start[0], $end[0], $t, $shift ) )
					. chr( self::mix( $start[1], $end[1], $t, $shift ) )
					. chr( self::mix( $start[2], $end[2], $t, $shift ) );
			}
		}

		$chunks = self::chunk( 'IHDR', pack( 'NNCCCCC', $width, $height, 8, 2, 0, 0, 0 ) )
			. self::chunk( 'IDAT', (string) gzcompress( $rows, 6 ) )
			. self::chunk( 'IEND', '' );

		return "\x89PNG\r\n\x1a\n" . $chunks;
	}

	/**
	 * A PCM WAV file: a short tone with a gentle envelope.
	 *
	 * 8 kHz mono 16-bit — small enough to keep in the uploads directory, real
	 * enough for every browser's `<audio>` element.
	 *
	 * @param float $seconds Length in seconds.
	 * @param int   $tone    Frequency in Hz.
	 * @param int   $kbps    Nominal bitrate; the second file is quieter, so the two
	 *                       qualities of one track are audibly different.
	 * @return string WAV bytes, empty string when nothing could be written.
	 */
	public static function wav( float $seconds, int $tone, int $kbps = 320 ): string {
		$rate  = 8000;
		$count = (int) round( max( 0.2, $seconds ) * $rate );

		if ( $count < 1 ) {
			return '';
		}

		$gain = 320 === $kbps ? 0.34 : 0.22;
		$data = '';

		for ( $i = 0; $i < $count; $i++ ) {
			$t = $i / $rate;

			// Two partials and a fade at both ends: a bare sine sounds like a test
			// tone, this sounds like a phone ringing in the next room — enough to
			// prove the player works, obviously not music.
			$value = sin( 2 * M_PI * $tone * $t ) * 0.7 + sin( 2 * M_PI * $tone * 1.5 * $t ) * 0.3;
			$fade  = min( 1.0, $t * 8 ) * min( 1.0, max( 0.0, ( $seconds - $t ) ) * 4 );
			$data .= pack( 'v', (int) round( max( -1, min( 1, $value * $gain * $fade ) ) * 32767 ) & 0xFFFF );
		}

		$size = strlen( $data );

		return 'RIFF' . pack( 'V', $size + 36 ) . 'WAVE'
			. 'fmt ' . pack( 'VvvVVvv', 16, 1, 1, $rate, $rate * 2, 2, 16 )
			. 'data' . pack( 'V', $size ) . $data;
	}

	/**
	 * A PNG chunk: length, type, data, CRC.
	 *
	 * @param string $type Four-character chunk type.
	 * @param string $data Chunk payload.
	 * @return string
	 */
	private static function chunk( string $type, string $data ): string {
		return pack( 'N', strlen( $data ) ) . $type . $data . pack( 'N', crc32( $type . $data ) );
	}

	/**
	 * One channel of one pixel.
	 *
	 * @param int   $start Start value.
	 * @param int   $end   End value.
	 * @param float $t     Position along the gradient (0…1).
	 * @param float $shift Brightness offset.
	 * @return int 0…255
	 */
	private static function mix( int $start, int $end, float $t, float $shift ): int {
		$value = $start + ( $end - $start ) * $t + $shift * 255;

		return (int) max( 0, min( 255, round( $value ) ) );
	}

	/**
	 * Normalise an RGB triple.
	 *
	 * @param array $rgb Three channels.
	 * @return array{0:int,1:int,2:int}
	 */
	private static function rgb( array $rgb ): array {
		return array(
			(int) ( $rgb[0] ?? 30 ),
			(int) ( $rgb[1] ?? 41 ),
			(int) ( $rgb[2] ?? 59 ),
		);
	}
}
