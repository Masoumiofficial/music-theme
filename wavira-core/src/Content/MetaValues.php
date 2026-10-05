<?php
/**
 * Typed readers for music meta.
 *
 * Services, REST controllers and the CLI read meta through these helpers so that
 * every read is type-safe and every escape happens exactly once, at render time.
 *
 * @package Wavira\Core\Content
 */

namespace Wavira\Core\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Class MetaValues
 */
final class MetaValues {

	/**
	 * Read an integer meta value.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key (use MetaSchema constants).
	 * @return int
	 */
	public static function int( int $post_id, string $key ): int {
		return (int) get_post_meta( $post_id, $key, true );
	}

	/**
	 * Read a boolean meta value.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @return bool
	 */
	public static function bool( int $post_id, string $key ): bool {
		return (bool) get_post_meta( $post_id, $key, true );
	}

	/**
	 * Read a URL meta value.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @return string Escaped URL, or an empty string.
	 */
	public static function url( int $post_id, string $key ): string {
		return esc_url( (string) get_post_meta( $post_id, $key, true ) );
	}

	/**
	 * Read a plain-text meta value.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @return string
	 */
	public static function text( int $post_id, string $key ): string {
		return (string) get_post_meta( $post_id, $key, true );
	}

	/**
	 * Read content HTML (already sanitized on save).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @return string
	 */
	public static function html( int $post_id, string $key ): string {
		return wp_kses_post( (string) get_post_meta( $post_id, $key, true ) );
	}

	/**
	 * Read a list of IDs, keeping only entries that exist and are published-or-editable.
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $key        Meta key.
	 * @param string $target_type Post type the IDs must belong to.
	 * @return int[]
	 */
	public static function ids( int $post_id, string $key, string $target_type = '' ): array {
		$ids = Meta::sanitize_ids( get_post_meta( $post_id, $key, true ) );

		if ( '' === $target_type ) {
			return $ids;
		}

		return array_values(
			array_filter(
				$ids,
				static function ( $id ) use ( $target_type ) {
					return $target_type === get_post_type( $id );
				}
			)
		);
	}

	/**
	 * Read the ordered tracklist of an album, newest-safe (missing tracks dropped).
	 *
	 * @param int $album_id Album post ID.
	 * @return int[]
	 */
	public static function tracklist( int $album_id ): array {
		return self::ids( $album_id, MetaSchema::TRACKLIST, PostTypes::TRACK );
	}

	/**
	 * Read an artist's related artists.
	 *
	 * @param int $artist_id Artist post ID.
	 * @return int[]
	 */
	public static function related_artists( int $artist_id ): array {
		return self::ids( $artist_id, MetaSchema::RELATED_ARTISTS, PostTypes::ARTIST );
	}

	/**
	 * Read a track's featured artists.
	 *
	 * @param int $track_id Track post ID.
	 * @return int[]
	 */
	public static function featured_artists( int $track_id ): array {
		return self::ids( $track_id, MetaSchema::FEATURED_ARTISTS, PostTypes::ARTIST );
	}

	/**
	 * Read every social link of an artist as an associative array.
	 *
	 * @param int $artist_id Artist post ID.
	 * @return array<string, array<string, string>> Each entry: url + network slug.
	 */
	public static function socials( int $artist_id ): array {
		$socials = array();

		foreach ( MetaSchema::social_keys() as $key ) {
			$url = (string) get_post_meta( $artist_id, $key, true );

			if ( '' === $url ) {
				continue;
			}

			$network = str_replace( 'wavira_social_', '', $key );

			$socials[ $network ] = array(
				'network' => $network,
				'url'     => esc_url( $url ),
			);
		}

		return $socials;
	}

	/**
	 * Duration in seconds formatted as m:ss (localised numerals).
	 *
	 * @param int $post_id Post ID.
	 * @return string Empty string when duration is unknown.
	 */
	public static function duration_label( int $post_id ): string {
		$seconds = self::int( $post_id, MetaSchema::DURATION );

		if ( $seconds <= 0 ) {
			return '';
		}

		$minutes = (int) floor( $seconds / 60 );
		$rest    = $seconds % 60;

		return sprintf(
			/* translators: 1: minutes, 2: seconds, zero-padded. */
			_x( '%1$s:%2$s', 'track duration', 'wavira-core' ),
			number_format_i18n( $minutes ),
			str_pad( (string) $rest, 2, '0', STR_PAD_LEFT )
		);
	}
}
