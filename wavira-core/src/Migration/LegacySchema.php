<?php
/**
 * What the legacy theme stored, and where each value goes.
 *
 * This is the *source* side of the migration: the field names, the content kinds
 * and the markers the tool writes. It is a transcription of
 * `docs/DATA-MODEL-AUDIT.md` §2 (read from the legacy templates) and
 * `docs/MIGRATION-BLUEPRINT.md` §1–§2 (the agreed target); every target key it
 * names is a constant of `MetaSchema`, which `Test_Migration` proves by
 * reflection rather than trusting this comment.
 *
 * Nothing here is a guess: a legacy field the model deliberately does not
 * implement (`views`, `adsjs_*`, `share_off`, …) is listed in `deferred()`, and
 * the tool copies it to the raw audit meta instead of reinterpreting it.
 *
 * @package Wavira\Core\Migration
 */

namespace Wavira\Core\Migration;

use Wavira\Core\Content\MetaSchema;
use Wavira\Core\Content\PostTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Class LegacySchema
 */
final class LegacySchema {

	/**
	 * Legacy meta key that names the content kind.
	 */
	public const TYPE_META = 'musics_type';

	/**
	 * Legacy taxonomy that models the artist (a black-box registration).
	 */
	public const ARTIST_TAX = 'singer';

	/**
	 * Meta the tool writes on the migrated post.
	 */
	public const BACKUP       = '_migration_backup';
	public const RAW          = '_migration_raw';
	public const MARKER       = '_migration_version';
	public const REVIEW       = '_migration_needs_review';
	public const CREATED      = '_migration_created';
	public const SOURCE_ALBUM = '_migration_source_album';
	public const SOURCE_INDEX = '_migration_source_index';

	/**
	 * Version of the mapping. Bumped when a transform changes, so a re-run can
	 * tell "already migrated" from "migrated by an older rulebook".
	 */
	public const VERSION = '1';

	/**
	 * Option that holds the last report, so `wp wavira migrate --status` works.
	 */
	public const REPORT_OPTION = 'wavira_migration_report';

	/**
	 * Legacy content kinds and the post type each becomes.
	 *
	 * @return array<string, string>
	 */
	public static function kinds(): array {
		return array(
			'mp3'   => PostTypes::TRACK,
			'mp4'   => PostTypes::VIDEO,
			'album' => PostTypes::ALBUM,
		);
	}

	/**
	 * Target post type of a legacy `musics_type` value.
	 *
	 * @param string $kind Legacy value.
	 * @return string Empty when the value is unknown.
	 */
	public static function target_of( string $kind ): string {
		$kinds = self::kinds();

		return $kinds[ $kind ] ?? '';
	}

	/**
	 * Post meta of one content kind: legacy key => [ target key, transform ].
	 *
	 * The transform is what the legacy value looks like, and it must agree with
	 * the target's own `sanitize` rule in `MetaSchema::all()`. The vocabulary of
	 * `Migrator::transform()` is `url` (a URL the legacy theme stored as text),
	 * `id` (a URL that can only become an attachment ID when the file is local),
	 * `html` (a KSES allow-listed body) and `bool`.
	 *
	 * @param string $kind Legacy `musics_type` value.
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function fields( string $kind ): array {
		$track = array(
			'music128'   => array( MetaSchema::AUDIO_128, 'url' ),
			'music320'   => array( MetaSchema::AUDIO_320, 'url' ),
			'music_text' => array( MetaSchema::LYRICS, 'html' ),
			'vip_song'   => array( MetaSchema::FEATURED, 'bool' ),
			'plym'       => array( MetaSchema::IN_INDEX_PLAYER, 'bool' ),
			'vip_img'    => array( MetaSchema::COVER, 'id' ),
		);

		$video = array(
			'video480'  => array( MetaSchema::VIDEO_480, 'url' ),
			'video720'  => array( MetaSchema::VIDEO_720, 'url' ),
			'video1080' => array( MetaSchema::VIDEO_1080, 'url' ),
			'vip_song'  => array( MetaSchema::FEATURED, 'bool' ),
			'vip_img'   => array( MetaSchema::VIDEO_POSTER, 'id' ),
		);

		$album = array(
			'album128' => array( MetaSchema::ALBUM_AUDIO_128, 'url' ),
			'album320' => array( MetaSchema::ALBUM_AUDIO_320, 'url' ),
			'vip_song' => array( MetaSchema::FEATURED, 'bool' ),
			'vip_img'  => array( MetaSchema::COVER, 'id' ),
		);

		$map = array(
			'mp3'   => $track,
			'mp4'   => $video,
			'album' => $album,
		);

		return $map[ $kind ] ?? array();
	}

	/**
	 * Meta keys that are read on every kind and mapped by their own rule.
	 *
	 * `artist` becomes a credit label plus a resolved artist entity, `song` a
	 * subtitle only when it is not already the title.
	 *
	 * @return string[]
	 */
	public static function shared_fields(): array {
		return array( 'artist', 'song' );
	}

	/**
	 * Album tracklist rows: legacy row keys => target meaning.
	 *
	 * @return array<string, string>
	 */
	public static function album_row_fields(): array {
		return array(
			'song_names'   => 'title',
			'albumlink128' => MetaSchema::AUDIO_128,
			'albumlink320' => MetaSchema::AUDIO_320,
		);
	}

	/**
	 * Artist term meta => artist post meta.
	 *
	 * @return array<string, string>
	 */
	public static function artist_term_fields(): array {
		return array(
			'aimg2'      => MetaSchema::ARTIST_IMAGE,
			'afacebook'  => MetaSchema::SOCIAL_FACEBOOK,
			'ainstagram' => MetaSchema::SOCIAL_INSTAGRAM,
			'atelegram'  => MetaSchema::SOCIAL_TELEGRAM,
			'atwitter'   => MetaSchema::SOCIAL_X,
			'ayoutube'   => MetaSchema::SOCIAL_YOUTUBE,
		);
	}

	/**
	 * Legacy fields the model deliberately does not implement.
	 *
	 * They are copied to `self::RAW` and reported; nothing is guessed. The
	 * decision behind each one lives in `docs/DECISIONS.md` and
	 * `docs/MIGRATION-BLUEPRINT.md` (`[DEFERRED]` rows).
	 *
	 * @return array<string, string> Legacy key => reason.
	 */
	public static function deferred(): array {
		return array(
			'views'     => 'the new model counts downloads, not views (no view counter by decision)',
			'thumb1'    => 'the new theme registers its own image sizes; regenerate thumbnails instead',
			'thumb2'    => 'the new theme registers its own image sizes; regenerate thumbnails instead',
			'thumb3'    => 'the new theme registers its own image sizes; regenerate thumbnails instead',
			'thumb4'    => 'the new theme registers its own image sizes; regenerate thumbnails instead',
			'adsjs_bt'  => 'no script ad slot exists in the new product',
			'adsjs_sg'  => 'no script ad slot exists in the new product',
			'share_off' => 'the new product ships no share-button feature',
			'navar_txt' => 'contained the original author\'s sales contact',
		);
	}

	/**
	 * Every legacy meta key the tool reads on a post.
	 *
	 * @return string[]
	 */
	public static function all_post_keys(): array {
		$keys = array( self::TYPE_META );

		foreach ( array_keys( self::kinds() ) as $kind ) {
			$keys = array_merge( $keys, array_keys( self::fields( $kind ) ) );
		}

		return array_values( array_unique( array_merge( $keys, self::shared_fields(), array( 'album' ), array_keys( self::deferred() ) ) ) );
	}

	/**
	 * Marker metas the tool writes, and therefore must never migrate.
	 *
	 * @return string[]
	 */
	public static function markers(): array {
		return array( self::BACKUP, self::RAW, self::MARKER, self::REVIEW, self::CREATED, self::SOURCE_ALBUM, self::SOURCE_INDEX );
	}
}
