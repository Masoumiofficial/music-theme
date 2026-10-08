<?php
/**
 * What a post offers to download.
 *
 * The download endpoint started as "hand out a track's audio file" and the
 * product grew sections: an album has its own master file, a hosted video has a
 * file, and a cover or a gallery photo is a file too. One class answers "what
 * file does this post offer, and at which qualities?" so the endpoint, the
 * public API and the theme's download button all agree (ADR 0023).
 *
 * @package Wavira\Core\Downloads
 */

namespace Wavira\Core\Downloads;

use WP_Post;
use Wavira\Core\Content\MetaSchema;
use Wavira\Core\Content\MetaValues;
use Wavira\Core\Content\PostTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Class Sources
 */
final class Sources {

	/** A downloadable audio/video file. */
	public const TRACK = 'track';

	/** An album's own master file. */
	public const ALBUM = 'album';

	/** A hosted video file (never a provider embed). */
	public const VIDEO = 'video';

	/** An image: a cover or a gallery photo. */
	public const IMAGE = 'image';

	/**
	 * The kinds of file a post type may offer, in the order they are offered.
	 *
	 * Quality means kbps for audio and the frame height for video. Both are
	 * "the number a visitor recognises", which is why one word covers them.
	 *
	 * @return array<int, int> Qualities, best first.
	 */
	public static function qualities( string $type ): array {
		switch ( $type ) {
			case self::TRACK:
				return array( 320, 128 );
			case self::ALBUM:
				return array( 320, 128 );
			case self::VIDEO:
				return array( 1080, 720, 480 );
			default:
				return array( 0 );
		}
	}

	/**
	 * Meta key that holds one quality of a type.
	 *
	 * @param string $type    One of the class constants.
	 * @param int    $quality Quality (kbps or frame height); 0 for images.
	 * @return string Meta key, empty string when the pair has no key.
	 */
	public static function meta_key( string $type, int $quality ): string {
		$keys = array(
			self::TRACK => array(
				128 => MetaSchema::AUDIO_128,
				320 => MetaSchema::AUDIO_320,
			),
			self::ALBUM => array(
				128 => MetaSchema::ALBUM_AUDIO_128,
				320 => MetaSchema::ALBUM_AUDIO_320,
			),
			self::VIDEO => array(
				480  => MetaSchema::VIDEO_480,
				720  => MetaSchema::VIDEO_720,
				1080 => MetaSchema::VIDEO_1080,
			),
		);

		/**
		 * Filters the meta key that stores one quality of one download type.
		 *
		 * Use when a site stores its files under its own key, e.g. a CDN plugin
		 * that copies audio to its own meta field.
		 *
		 * @since 0.15.0
		 * @param string $key     Meta key, empty string when unknown.
		 * @param string $type    Download type (`track`, `album`, `video`, `image`).
		 * @param int    $quality Quality in kbps or frame height.
		 */
		return (string) apply_filters( 'wavira_download_meta_key', $keys[ $type ][ $quality ] ?? '', $type, $quality );
	}

	/**
	 * What kind of file a post offers, if any.
	 *
	 * @param int $post_id Post ID.
	 * @return string One of the class constants, or an empty string.
	 */
	public static function type( int $post_id ): string {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return '';
		}

		switch ( $post->post_type ) {
			case PostTypes::TRACK:
				return self::TRACK;
			case PostTypes::ALBUM:
				return self::ALBUM;
			case PostTypes::VIDEO:
				return self::VIDEO;
			case 'attachment':
				return self::IMAGE;
		}

		return '';
	}

	/**
	 * Every quality of a post that has a file, best first.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int, array<string, mixed>> Each: `quality`, `url`, `type`, `label`.
	 */
	public static function available( int $post_id ): array {
		$type = self::type( $post_id );

		if ( '' === $type ) {
			return array();
		}

		if ( self::IMAGE === $type ) {
			$url = wp_get_attachment_url( $post_id );

			return $url
				? array(
					array(
						'quality' => 0,
						'url'     => (string) $url,
						'type'    => self::IMAGE,
						'label'   => '',
					),
				)
				: array();
		}

		$available = array();

		foreach ( self::qualities( $type ) as $quality ) {
			$url = MetaValues::url( $post_id, self::meta_key( $type, $quality ) );

			if ( '' === $url ) {
				continue;
			}

			$available[] = array(
				'quality' => $quality,
				'url'     => $url,
				'type'    => $type,
				'label'   => self::quality_label( $type, $quality ),
			);
		}

		return $available;
	}

	/**
	 * One file to hand out: the requested quality when it exists, else the best.
	 *
	 * @param int $post_id Post ID.
	 * @param int $quality Requested quality (0 = best available).
	 * @return array<string, mixed> `quality`, `url`, `type`, `label`; empty array when there is no file.
	 */
	public static function resolve( int $post_id, int $quality = 0 ): array {
		$available = self::available( $post_id );

		if ( empty( $available ) ) {
			return array();
		}

		foreach ( $available as $candidate ) {
			if ( $quality > 0 && (int) $candidate['quality'] === $quality ) {
				return $candidate;
			}
		}

		return $available[0];
	}

	/**
	 * Human label for a quality, e.g. `320 kbps` or `720p`.
	 *
	 * @param string $type    Download type.
	 * @param int    $quality Quality.
	 * @return string Label, empty string for images.
	 */
	public static function quality_label( string $type, int $quality ): string {
		if ( self::VIDEO === $type ) {
			/* translators: %d: video frame height in pixels, e.g. 720. */
			return sprintf( __( '%d pixels', 'wavira-core' ), $quality );
		}

		if ( self::IMAGE === $type || $quality < 1 ) {
			return '';
		}

		/* translators: %d: audio bitrate in kbps. */
		return sprintf( __( '%d kbps', 'wavira-core' ), $quality );
	}

	/**
	 * A public download URL for a post, or an empty string.
	 *
	 * Points at `wavira/v1/download/{id}` so every download is authorized and
	 * counted in one place; the endpoint redirects to the file itself.
	 *
	 * @param int $post_id Post ID.
	 * @param int $quality Requested quality (0 = best available).
	 * @return string URL, empty string when the post has nothing to download.
	 */
	public static function url( int $post_id, int $quality = 0 ): string {
		if ( ! Access::allows( $post_id ) ) {
			return '';
		}

		$args = array();

		if ( $quality > 0 ) {
			$args['quality'] = $quality;
		}

		return (string) add_query_arg( $args, rest_url( 'wavira/v1/download/' . $post_id ) );
	}
}
