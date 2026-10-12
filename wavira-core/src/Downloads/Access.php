<?php
/**
 * Download access control.
 *
 * Honest security model (brief §23, SECURITY-AUDIT §3): a WordPress site cannot
 * absolutely prevent a determined visitor from saving a public audio file, so
 * this class implements *authorization* — never a DRM claim. What it guarantees
 * is that no download URL is handed out by the API unless the site's rules allow it.
 *
 * @package Wavira\Core\Downloads
 */

namespace Wavira\Core\Downloads;

use Wavira\Core\Content\Dates;
use Wavira\Core\Content\MetaSchema;
use Wavira\Core\Content\MetaValues;
use Wavira\Core\Content\PostTypes;
use Wavira\Core\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class Access
 */
final class Access {

	/**
	 * Whether download links may be exposed for a track.
	 *
	 * Rules, in order:
	 * 1. the post must be a published track;
	 * 2. global downloads must be enabled;
	 * 3. the track must have at least one audio source;
	 * 4. per-track opt-out (`wavira_download_enabled` = false) always wins;
	 * 5. when the site requires login, a logged-in reader is required.
	 *
	 * @param int $post_id Track post ID.
	 * @return bool
	 */
	public static function can_download( int $post_id ): bool {
		$post = get_post( $post_id );

		if ( ! $post || PostTypes::TRACK !== $post->post_type || 'publish' !== $post->post_status ) {
			return false;
		}

		$allowed = (bool) Settings::get( 'downloads_enabled', true );

		$has_source = '' !== MetaValues::url( $post_id, MetaSchema::AUDIO_128 )
			|| '' !== MetaValues::url( $post_id, MetaSchema::AUDIO_320 );

		if ( ! $has_source ) {
			$allowed = false;
		}

		// An explicit opt-out must win even though `false` is stored as an empty
		// string in the database: reading the value alone cannot tell "opt out"
		// from "never set", so the existence of the row is the signal (the
		// integration suite caught the difference).
		if ( metadata_exists( 'post', $post_id, MetaSchema::DOWNLOAD_ENABLED )
			&& ! MetaValues::bool( $post_id, MetaSchema::DOWNLOAD_ENABLED ) ) {
			$allowed = false;
		}

		if ( $allowed && Settings::get( 'downloads_require_login', false ) ) {
			$allowed = is_user_logged_in() && current_user_can( 'read' );
		}

		/**
		 * Filters whether download links are exposed for a track.
		 *
		 * Use for membership plugins, token gates or per-track rules. This is an
		 * authorization decision only — it is not, and must not be presented as,
		 * content protection.
		 *
		 * @since 0.3.0
		 * @param bool $allowed Whether downloads are allowed.
		 * @param int  $post_id Track post ID.
		 */
		return (bool) apply_filters( 'wavira_download_access', $allowed, $post_id );
	}

	/**
	 * Whether a post of *any* downloadable type may expose a download link.
	 *
	 * `can_download()` answers for a track and stays the narrow, documented
	 * question; this is the one the download endpoint and the download button ask,
	 * because the product grew sections (ADR 0023):
	 *
	 * - **track** — published, global downloads on, at least one audio file, the
	 *   per-track opt-out respected, the login rule respected;
	 * - **album** — published, global downloads on, the album's own master file
	 *   present, the per-album opt-out respected;
	 * - **video** — published, global downloads on, and a *hosted* file: an embed
	 *   has nothing to hand out, so an embed is never a download;
	 * - **image** — an attachment whose parent (when it has one) is published, so
	 *   cover art can be downloaded but a private file cannot.
	 *
	 * @param int $post_id Post ID of any type.
	 * @return bool
	 */
	public static function allows( int $post_id ): bool {
		$type = Sources::type( $post_id );

		if ( Sources::TRACK === $type ) {
			return self::can_download( $post_id );
		}

		if ( '' === $type ) {
			return false;
		}

		if ( ! Settings::get( 'downloads_enabled', true ) ) {
			return false;
		}

		$allowed = '' !== self::file_url( $post_id );

		if ( Sources::IMAGE === $type ) {
			$allowed = $allowed && self::image_is_public( $post_id );
		}

		// The same per-post opt-out a track has, for the same reason: `false` and
		// "never set" are indistinguishable in the value alone.
		if ( Sources::IMAGE !== $type
			&& metadata_exists( 'post', $post_id, MetaSchema::DOWNLOAD_ENABLED )
			&& ! MetaValues::bool( $post_id, MetaSchema::DOWNLOAD_ENABLED ) ) {
			$allowed = false;
		}

		if ( $allowed && Settings::get( 'downloads_require_login', false ) ) {
			$allowed = is_user_logged_in() && current_user_can( 'read' );
		}

		/**
		 * Filters whether download links are exposed for a post.
		 *
		 * The type-aware companion of `wavira_download_access`, which is kept for
		 * the track case it has always answered.
		 *
		 * @since 0.15.0
		 * @param bool   $allowed Whether downloads are allowed.
		 * @param int    $post_id Post ID.
		 * @param string $type    `track`, `album`, `video` or `image`.
		 */
		return (bool) apply_filters( 'wavira_download_access_post', $allowed, $post_id, $type );
	}

	/**
	 * The file a non-track post offers, at its best quality.
	 *
	 * @param int $post_id Post ID.
	 * @return string URL, empty string when there is nothing to hand out.
	 */
	private static function file_url( int $post_id ): string {
		$resolved = Sources::resolve( $post_id );

		return (string) ( $resolved['url'] ?? '' );
	}

	/**
	 * Whether an attachment may be downloaded.
	 *
	 * @param int $post_id Attachment ID.
	 * @return bool
	 */
	private static function image_is_public( int $post_id ): bool {
		$parent = (int) get_post_field( 'post_parent', $post_id );

		if ( $parent < 1 ) {
			return true;
		}

		$post = get_post( $parent );

		return $post instanceof \WP_Post && 'publish' === $post->post_status;
	}

	/**
	 * The quality matrix for a track.
	 *
	 * @param int $post_id Track post ID.
	 * @return array<int, array<string, mixed>> List of qualities with url, label and size.
	 */
	public static function matrix( int $post_id ): array {
		if ( ! self::can_download( $post_id ) ) {
			return array();
		}

		$qualities = array(
			128 => array(
				'url'  => MetaValues::url( $post_id, MetaSchema::AUDIO_128 ),
				'size' => MetaValues::int( $post_id, MetaSchema::FILE_SIZE_128 ),
			),
			320 => array(
				'url'  => MetaValues::url( $post_id, MetaSchema::AUDIO_320 ),
				'size' => MetaValues::int( $post_id, MetaSchema::FILE_SIZE_320 ),
			),
		);

		$matrix = array();

		foreach ( $qualities as $kbps => $quality ) {
			if ( '' === $quality['url'] ) {
				continue;
			}

			$matrix[] = array(
				'quality'    => (int) $kbps,
				'label'      => Dates::digits(
					sprintf(
						/* translators: %d: audio bitrate in kbps. */
						__( '%d kbps', 'wavira-core' ),
						(int) $kbps
					)
				),
				'url'        => esc_url( $quality['url'] ),
				'file_size'  => (int) $quality['size'],
				'size_label' => $quality['size'] > 0 ? Dates::digits( (string) size_format( (int) $quality['size'] ) ) : '',
			);
		}

		/**
		 * Filters the download quality matrix of a track.
		 *
		 * Future qualities (FLAC/WAV/external mirrors) plug in here without
		 * touching the API or the templates.
		 *
		 * @since 0.3.0
		 * @param array $matrix  Prepared quality rows.
		 * @param int   $post_id Track post ID.
		 */
		return apply_filters( 'wavira_download_quality_matrix', $matrix, $post_id );
	}
}
