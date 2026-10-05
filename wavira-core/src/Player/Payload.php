<?php
/**
 * Playback payload of one track.
 *
 * The player engine never builds a URL and never guesses a title: it receives
 * exactly this structure from the REST API and from the server-rendered card
 * (ADR 0005 §6). One builder for both keeps the card, the player bar and the
 * album view consistent.
 *
 * Honest scope: the sources are public file URLs. Playing a track and obtaining
 * its file are the same permission — access control for downloads is a separate
 * decision (`Downloads\Access`, ADR 0013).
 *
 * @package Wavira\Core\Player
 */

namespace Wavira\Core\Player;

use Wavira\Core\Content\Cover;
use Wavira\Core\Content\MetaSchema;
use Wavira\Core\Content\MetaValues;
use Wavira\Core\Content\PostTypes;
use Wavira\Core\Content\Terms;

use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Class Payload
 */
final class Payload {

	/**
	 * Quality the engine should prefer when it is available.
	 *
	 * @var int
	 */
	public const PREFERRED_QUALITY = 320;

	/**
	 * Playback payload of a track.
	 *
	 * @param int $post_id Track post ID.
	 * @return array<string, mixed> Empty array when the post is not a published track.
	 */
	public static function for_track( int $post_id ): array {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || PostTypes::TRACK !== $post->post_type || 'publish' !== $post->post_status ) {
			return array();
		}

		$title     = (string) get_the_title( $post_id );
		$sources   = self::sources( $post_id );
		$artist    = self::artist( $post_id );
		$album     = self::album( $post_id );
		$cover     = Cover::payload( $post_id );
		$permalink = (string) get_permalink( $post_id );

		$payload = array(
			'id'             => $post_id,
			'type'           => $post->post_type,
			'title'          => $title,
			'permalink'      => $permalink,
			'duration'       => MetaValues::int( $post_id, MetaSchema::DURATION ),
			'duration_label' => MetaValues::duration_label( $post_id ),
			'explicit'       => MetaValues::bool( $post_id, MetaSchema::EXPLICIT ),
			'has_lyrics'     => '' !== MetaValues::html( $post_id, MetaSchema::LYRICS ),
			'artist'         => $artist,
			'album'          => $album,
			'cover'          => $cover,
			'genres'         => Terms::genres( $post_id ),
			'sources'        => $sources,
			'preferred'      => self::preferred_quality( $sources ),
			'media_session'  => self::media_session( $title, $artist, $album, $cover ),
		);

		/**
		 * Filters the playback payload of a track.
		 *
		 * Add alternate sources, chapters or watermark data here. The engine
		 * treats the payload as the single source of truth, so anything added
		 * here is available to every view without touching the JS.
		 *
		 * @since 0.5.0
		 * @param array<string, mixed> $payload Playback payload.
		 * @param int                  $post_id Track post ID.
		 */
		return (array) apply_filters( 'wavira_track_playback_payload', $payload, $post_id );
	}

	/**
	 * Audio sources of a track, best quality first.
	 *
	 * @param int $post_id Track post ID.
	 * @return array<int|string, string> Quality (or `external`) mapped to a URL.
	 */
	private static function sources( int $post_id ): array {
		$sources = array();

		foreach ( array( 320, 128 ) as $quality ) {
			$url = MetaValues::url( $post_id, MetaSchema::audio_key( $quality ) );

			if ( '' !== $url ) {
				$sources[ $quality ] = $url;
			}
		}

		$external = MetaValues::url( $post_id, MetaSchema::AUDIO_EXTERNAL );

		if ( '' !== $external ) {
			$sources['external'] = $external;
		}

		return $sources;
	}

	/**
	 * Preferred quality for a source map.
	 *
	 * @param array<int|string, string> $sources Source map.
	 * @return int 320 or 128 when available, 0 when only an external source exists.
	 */
	private static function preferred_quality( array $sources ): int {
		foreach ( array( self::PREFERRED_QUALITY, 128 ) as $quality ) {
			if ( isset( $sources[ $quality ] ) ) {
				return (int) $quality;
			}
		}

		return 0;
	}

	/**
	 * Primary artist of a track as a lean payload.
	 *
	 * @param int $post_id Track post ID.
	 * @return array<string, mixed> Empty array when the artist relation is missing.
	 */
	private static function artist( int $post_id ): array {
		$artist_id = MetaValues::int( $post_id, MetaSchema::ARTIST );

		if ( ! $artist_id || PostTypes::ARTIST !== get_post_type( $artist_id ) || 'publish' !== get_post_status( $artist_id ) ) {
			return array();
		}

		return array(
			'id'        => $artist_id,
			'name'      => (string) get_the_title( $artist_id ),
			'permalink' => (string) get_permalink( $artist_id ),
		);
	}

	/**
	 * Parent album of a track as a lean payload.
	 *
	 * @param int $post_id Track post ID.
	 * @return array<string, mixed> Empty array when the album relation is missing.
	 */
	private static function album( int $post_id ): array {
		$album_id = MetaValues::int( $post_id, MetaSchema::ALBUM );

		if ( ! $album_id || PostTypes::ALBUM !== get_post_type( $album_id ) || 'publish' !== get_post_status( $album_id ) ) {
			return array();
		}

		return array(
			'id'        => $album_id,
			'title'     => (string) get_the_title( $album_id ),
			'permalink' => (string) get_permalink( $album_id ),
		);
	}

	/**
	 * Media Session metadata for the browser's own controls.
	 *
	 * Returned from PHP so the lock-screen text matches the site exactly, and so
	 * a site can change it through the payload filter instead of patching JS.
	 *
	 * @param string               $title  Track title.
	 * @param array<string, mixed> $artist Artist payload.
	 * @param array<string, mixed> $album  Album payload.
	 * @param array<string, mixed> $cover  Cover payload.
	 * @return array<string, mixed>
	 */
	private static function media_session( string $title, array $artist, array $album, array $cover ): array {
		$artwork = array();

		if ( ! empty( $cover['url'] ) ) {
			$mime = (string) get_post_mime_type( (int) $cover['id'] );

			$artwork[] = array(
				'src'   => (string) $cover['url'],
				'sizes' => (int) $cover['width'] > 0 && (int) $cover['height'] > 0
					? (int) $cover['width'] . 'x' . (int) $cover['height']
					: '',
				'type'  => '' !== $mime ? $mime : 'image/jpeg',
			);
		}

		return array(
			'title'   => $title,
			'artist'  => isset( $artist['name'] ) ? (string) $artist['name'] : '',
			'album'   => isset( $album['title'] ) ? (string) $album['title'] : '',
			'artwork' => $artwork,
		);
	}
}
