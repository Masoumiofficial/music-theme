<?php
/**
 * The single source of truth for every registered meta key of the music model.
 *
 * Nothing in the product may read or write music meta through a literal string:
 * code uses the constants below, and registration/validation/accessors all derive
 * from this schema (ADR 0003, ADR 0012).
 *
 * @package Wavira\Core\Content
 */

namespace Wavira\Core\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Class MetaSchema
 */
final class MetaSchema {

	/* Relations ------------------------------------------------------------------ */
	public const ARTIST           = 'wavira_artist';
	public const FEATURED_ARTISTS = 'wavira_featured_artists';
	public const ALBUM            = 'wavira_album';
	public const TRACKLIST        = 'wavira_tracklist';
	public const RELATED_ARTISTS  = 'wavira_related_artists';
	public const CREDIT_LABEL     = 'wavira_credit_label';

	/* Track audio ---------------------------------------------------------------- */
	public const AUDIO_128          = 'wavira_audio_128';
	public const AUDIO_320          = 'wavira_audio_320';
	public const AUDIO_EXTERNAL     = 'wavira_audio_external';
	public const DOWNLOAD_ENABLED   = 'wavira_download_enabled';
	public const FILE_SIZE_128      = 'wavira_file_size_128';
	public const FILE_SIZE_320      = 'wavira_file_size_320';
	public const DURATION           = 'wavira_duration';
	public const LYRICS             = 'wavira_lyrics';
	public const ISRC               = 'wavira_isrc';
	public const EXPLICIT           = 'wavira_explicit';
	public const VERSION_NOTE       = 'wavira_version_note';

	/* Editorial / listing -------------------------------------------------------- */
	public const RELEASE_DATE      = 'wavira_release_date';
	public const FEATURED          = 'wavira_featured';
	public const IN_INDEX_PLAYER   = 'wavira_in_index_player';
	public const COVER             = 'wavira_cover';

	/* Album ---------------------------------------------------------------------- */
	public const ALBUM_TYPE        = 'wavira_album_type';
	public const CATALOG_NUMBER    = 'wavira_catalog_number';

	/* Video ---------------------------------------------------------------------- */
	public const VIDEO_SOURCE      = 'wavira_video_source';
	public const VIDEO_URL         = 'wavira_video_url';
	public const VIDEO_480         = 'wavira_video_480';
	public const VIDEO_720         = 'wavira_video_720';
	public const VIDEO_1080        = 'wavira_video_1080';
	public const VIDEO_POSTER      = 'wavira_video_poster';

	/* Artist --------------------------------------------------------------------- */
	public const ARTIST_IMAGE      = 'wavira_artist_image';
	public const ARTIST_COVER      = 'wavira_artist_cover';
	public const VERIFIED          = 'wavira_verified';
	public const COUNTRY           = 'wavira_country';
	public const WEBSITE           = 'wavira_website';
	public const SOCIAL_FACEBOOK   = 'wavira_social_facebook';
	public const SOCIAL_INSTAGRAM  = 'wavira_social_instagram';
	public const SOCIAL_TELEGRAM   = 'wavira_social_telegram';
	public const SOCIAL_X          = 'wavira_social_x';
	public const SOCIAL_YOUTUBE    = 'wavira_social_youtube';
	public const SOCIAL_APARAT     = 'wavira_social_aparat';

	/**
	 * All meta keys with their type, sanitizer, REST shape and owning post types.
	 *
	 * `sanitize` maps to a method on Meta; `rest_items` is used when the value is
	 * an array of integers. `enum` restricts allowed string values.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function all(): array {
		$track = array( PostTypes::TRACK );
		$album = array( PostTypes::ALBUM );
		$video = array( PostTypes::VIDEO );
		$artist = array( PostTypes::ARTIST );

		return array(
			/* Relations --------------------------------------------------------- */
			self::ARTIST           => array(
				'type'     => 'integer',
				'sanitize' => 'id',
				'entities' => array_merge( $track, $album, $video ),
			),
			self::FEATURED_ARTISTS => array(
				'type'       => 'array',
				'rest_items' => 'integer',
				'sanitize'   => 'ids',
				'entities'   => array_merge( $track, $album ),
			),
			self::ALBUM            => array(
				'type'     => 'integer',
				'sanitize' => 'id',
				'entities' => array_merge( $track, $video ),
			),
			self::TRACKLIST        => array(
				'type'       => 'array',
				'rest_items' => 'integer',
				'sanitize'   => 'ids',
				'entities'   => $album,
			),
			self::RELATED_ARTISTS  => array(
				'type'       => 'array',
				'rest_items' => 'integer',
				'sanitize'   => 'ids',
				'entities'   => $artist,
			),
			self::CREDIT_LABEL     => array(
				'type'     => 'string',
				'sanitize' => 'text',
				'entities' => array_merge( $track, $album, $video ),
			),

			/* Track audio ------------------------------------------------------- */
			self::AUDIO_128        => array(
				'type'     => 'string',
				'sanitize' => 'url',
				'entities' => $track,
			),
			self::AUDIO_320        => array(
				'type'     => 'string',
				'sanitize' => 'url',
				'entities' => $track,
			),
			self::AUDIO_EXTERNAL   => array(
				'type'     => 'string',
				'sanitize' => 'url',
				'entities' => $track,
			),
			self::DOWNLOAD_ENABLED => array(
				'type'     => 'boolean',
				'sanitize' => 'bool',
				'entities' => $track,
			),
			self::FILE_SIZE_128    => array(
				'type'     => 'integer',
				'sanitize' => 'int',
				'entities' => $track,
			),
			self::FILE_SIZE_320    => array(
				'type'     => 'integer',
				'sanitize' => 'int',
				'entities' => $track,
			),
			self::DURATION         => array(
				'type'     => 'integer',
				'sanitize' => 'int',
				'entities' => array_merge( $track, $video ),
			),
			self::LYRICS           => array(
				'type'     => 'string',
				'sanitize' => 'html',
				'entities' => $track,
			),
			self::ISRC             => array(
				'type'     => 'string',
				'sanitize' => 'text',
				'entities' => $track,
			),
			self::EXPLICIT         => array(
				'type'     => 'boolean',
				'sanitize' => 'bool',
				'entities' => $track,
			),
			self::VERSION_NOTE     => array(
				'type'     => 'string',
				'sanitize' => 'text',
				'entities' => $track,
			),

			/* Editorial / listing ----------------------------------------------- */
			self::RELEASE_DATE     => array(
				'type'     => 'string',
				'sanitize' => 'date',
				'entities' => array_merge( $track, $album, $video ),
			),
			self::FEATURED         => array(
				'type'     => 'boolean',
				'sanitize' => 'bool',
				'entities' => array_merge( $track, $album, $video ),
			),
			self::IN_INDEX_PLAYER  => array(
				'type'     => 'boolean',
				'sanitize' => 'bool',
				'entities' => $track,
			),
			self::COVER            => array(
				'type'     => 'integer',
				'sanitize' => 'id',
				'entities' => array_merge( $track, $album, $video, $artist ),
			),

			/* Album ------------------------------------------------------------- */
			self::ALBUM_TYPE       => array(
				'type'     => 'string',
				'sanitize' => 'enum',
				'enum'     => array( 'album', 'single', 'ep', 'compilation' ),
				'entities' => $album,
			),
			self::CATALOG_NUMBER   => array(
				'type'     => 'string',
				'sanitize' => 'text',
				'entities' => $album,
			),

			/* Video ------------------------------------------------------------- */
			self::VIDEO_SOURCE     => array(
				'type'     => 'string',
				'sanitize' => 'enum',
				'enum'     => array( 'self', 'youtube', 'vimeo', 'aparat', 'other' ),
				'entities' => $video,
			),
			self::VIDEO_URL        => array(
				'type'     => 'string',
				'sanitize' => 'url',
				'entities' => $video,
			),
			self::VIDEO_480        => array(
				'type'     => 'string',
				'sanitize' => 'url',
				'entities' => $video,
			),
			self::VIDEO_720        => array(
				'type'     => 'string',
				'sanitize' => 'url',
				'entities' => $video,
			),
			self::VIDEO_1080       => array(
				'type'     => 'string',
				'sanitize' => 'url',
				'entities' => $video,
			),
			self::VIDEO_POSTER     => array(
				'type'     => 'integer',
				'sanitize' => 'id',
				'entities' => $video,
			),

			/* Artist ------------------------------------------------------------ */
			self::ARTIST_IMAGE     => array(
				'type'     => 'integer',
				'sanitize' => 'id',
				'entities' => $artist,
			),
			self::ARTIST_COVER     => array(
				'type'     => 'integer',
				'sanitize' => 'id',
				'entities' => $artist,
			),
			self::VERIFIED         => array(
				'type'     => 'boolean',
				'sanitize' => 'bool',
				'entities' => $artist,
			),
			self::COUNTRY          => array(
				'type'     => 'string',
				'sanitize' => 'text',
				'entities' => $artist,
			),
			self::WEBSITE          => array(
				'type'     => 'string',
				'sanitize' => 'url',
				'entities' => $artist,
			),
			self::SOCIAL_FACEBOOK  => array(
				'type'     => 'string',
				'sanitize' => 'url',
				'entities' => $artist,
			),
			self::SOCIAL_INSTAGRAM => array(
				'type'     => 'string',
				'sanitize' => 'url',
				'entities' => $artist,
			),
			self::SOCIAL_TELEGRAM  => array(
				'type'     => 'string',
				'sanitize' => 'url',
				'entities' => $artist,
			),
			self::SOCIAL_X         => array(
				'type'     => 'string',
				'sanitize' => 'url',
				'entities' => $artist,
			),
			self::SOCIAL_YOUTUBE   => array(
				'type'     => 'string',
				'sanitize' => 'url',
				'entities' => $artist,
			),
			self::SOCIAL_APARAT    => array(
				'type'     => 'string',
				'sanitize' => 'url',
				'entities' => $artist,
			),
		);
	}

	/**
	 * Schema entry for one key.
	 *
	 * @param string $key Meta key.
	 * @return array<string, mixed>|null
	 */
	public static function get( string $key ): ?array {
		$all = self::all();

		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Every social-link key, for reuse in templates, REST payloads and imports.
	 *
	 * @return string[]
	 */
	public static function social_keys(): array {
		return array(
			self::SOCIAL_FACEBOOK,
			self::SOCIAL_INSTAGRAM,
			self::SOCIAL_TELEGRAM,
			self::SOCIAL_X,
			self::SOCIAL_YOUTUBE,
			self::SOCIAL_APARAT,
		);
	}
}
