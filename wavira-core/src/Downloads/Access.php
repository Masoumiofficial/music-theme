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

		$stored = get_post_meta( $post_id, MetaSchema::DOWNLOAD_ENABLED, true );

		if ( '' !== $stored && ! filter_var( $stored, FILTER_VALIDATE_BOOLEAN ) ) {
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
				'label'      => sprintf(
					/* translators: %d: audio bitrate in kbps. */
					__( '%d kbps', 'wavira-core' ),
					(int) $kbps
				),
				'url'        => esc_url( $quality['url'] ),
				'file_size'  => (int) $quality['size'],
				'size_label' => $quality['size'] > 0 ? size_format( (int) $quality['size'] ) : '',
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
