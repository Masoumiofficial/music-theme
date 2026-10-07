<?php
/**
 * What a source site stored, and where each value goes.
 *
 * This is the *source* side of the import: field names, content kinds, the
 * album repeater shape and the markers the tool writes. Two sources are
 * described here, and neither is a guess:
 *
 * - `legacy` — the audited theme. A transcription of `docs/DATA-MODEL-AUDIT.md`
 *   §2 (read from the legacy templates) and `docs/MIGRATION-BLUEPRINT.md` §1–§2
 *   (the agreed target).
 * - `music-publisher` — the "Sajad Music Publisher" plugin the same audience
 *   already publishes with (v1.0.0, GPL-2.0-or-later). Its readers were read the
 *   same way: `includes/class-smp-post-handler.php`, `class-smp-settings.php`
 *   and `class-smp-admin-pages.php`. It writes the *same* `musics_type`
 *   discriminator, the *same* `music128`/`music320`/`video*`/`album*` keys and
 *   the *same* `singer` taxonomy, with different names for the artist, the work
 *   and the lyrics and a different album repeater (`album_dl`). That overlap is
 *   why the map lives in one class instead of a second importer.
 *
 * Every target key named here is a constant of `MetaSchema`, which
 * `Test_Migration` proves by reflection rather than trusting this comment, and a
 * source field the model deliberately does not implement is listed in
 * `deferred()` with a reason: it is copied to the raw audit meta and reported,
 * never reinterpreted.
 *
 * @package Wavira\Core\Migration
 */

namespace Wavira\Core\Migration;

use Wavira\Core\Content\MetaSchema;
use Wavira\Core\Content\PostTypes;
use Wavira\Core\Content\Taxonomies;

defined( 'ABSPATH' ) || exit;

/**
 * Class LegacySchema
 */
final class LegacySchema {

	/**
	 * The audited theme (the default source).
	 */
	public const SOURCE_LEGACY = 'legacy';

	/**
	 * The "Sajad Music Publisher" plugin.
	 */
	public const SOURCE_MUSIC_PUBLISHER = 'music-publisher';

	/**
	 * Meta key that names the content kind — shared by both sources.
	 */
	public const TYPE_META = 'musics_type';

	/**
	 * Taxonomy that models the artist in both sources.
	 */
	public const ARTIST_TAX = 'singer';

	/**
	 * The legacy repeater row key that holds a track name — a repeater
	 * sub-field name from the old theme, so it is not a `wavira_*` key.
	 */
	public const ALBUM_TITLE_ROW = 'song_names';

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
	public const SOURCE_META  = '_migration_source';

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
	 * The sources the tool knows, and what each one is.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function sources(): array {
		return array(
			self::SOURCE_LEGACY          => array(
				'label' => 'the audited legacy theme',
				'note'  => 'post + musics_type, artist from the singer taxonomy and tags, ACF album repeater (album)',
			),
			self::SOURCE_MUSIC_PUBLISHER => array(
				'label' => '"Sajad Music Publisher" 1.0.0',
				'note'  => 'post + musics_type (musicss*), artist from the singer taxonomy, album repeater (album_dl)',
			),
		);
	}

	/**
	 * Whether a source slug is known.
	 *
	 * @param string $source Source slug.
	 * @return bool
	 */
	public static function has_source( string $source ): bool {
		return isset( self::sources()[ $source ] );
	}

	/**
	 * Content kinds of a source and the post type each becomes.
	 *
	 * @param string $source Source slug.
	 * @return array<string, string> `musics_type` value => target post type.
	 */
	public static function kinds( string $source = self::SOURCE_LEGACY ): array {
		if ( self::SOURCE_MUSIC_PUBLISHER === $source ) {
			return array(
				'musicss'         => PostTypes::TRACK,
				'musicss_remix'   => PostTypes::TRACK,
				'musicss_nohe'    => PostTypes::TRACK,
				'musicss_podcast' => PostTypes::TRACK,
				'musicss_video'   => PostTypes::VIDEO,
				'musicss_album'   => PostTypes::ALBUM,
			);
		}

		return array(
			'mp3'   => PostTypes::TRACK,
			'mp4'   => PostTypes::VIDEO,
			'album' => PostTypes::ALBUM,
		);
	}

	/**
	 * The kind term a source kind lands in (`wavira_kind`).
	 *
	 * A video or an album is already its own post type, so only audio items
	 * carry a kind term; everything else returns an empty string.
	 *
	 * @param string $kind   `musics_type` value.
	 * @param string $source Source slug.
	 * @return string Term slug, or an empty string.
	 */
	public static function kind_term( string $kind, string $source = self::SOURCE_LEGACY ): string {
		if ( PostTypes::TRACK !== self::target_of( $kind, $source ) ) {
			return '';
		}

		if ( self::SOURCE_MUSIC_PUBLISHER === $source ) {
			return Taxonomies::normalize_kind( $kind );
		}

		// Every legacy audio kind is a song; the theme had no remix or noha
		// type of its own, only categories, and a category is not a kind.
		return 'music';
	}

	/**
	 * Target post type of a source `musics_type` value.
	 *
	 * @param string $kind   Source value.
	 * @param string $source Source slug.
	 * @return string Empty when the value is unknown.
	 */
	public static function target_of( string $kind, string $source = self::SOURCE_LEGACY ): string {
		$kinds = self::kinds( $source );

		return $kinds[ $kind ] ?? '';
	}

	/**
	 * Post meta of one content kind: source key => [ target key, transform ].
	 *
	 * The transform is what the source value looks like, and it must agree with
	 * the target's own `sanitize` rule in `MetaSchema::all()`. The vocabulary of
	 * `Migrator::transform()` is `url` (a URL the source stored as text), `id`
	 * (a URL that can only become an attachment ID when the file is local),
	 * `html` (a KSES allow-listed body), `bool`, `text` and `int`.
	 *
	 * @param string $kind   Source `musics_type` value.
	 * @param string $source Source slug.
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function fields( string $kind, string $source = self::SOURCE_LEGACY ): array {
		if ( self::SOURCE_MUSIC_PUBLISHER === $source ) {
			return self::publisher_fields( $kind );
		}

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
	 * The field map for the publishing plugin.
	 *
	 * @param string $kind `musics_type` value.
	 * @return array<string, array{0: string, 1: string}>
	 */
	private static function publisher_fields( string $kind ): array {
		// The three audio kinds share the panel's fields: its forms are the same
		// markup with a different category and `musics_type`.
		$audio = array(
			'music128'       => array( MetaSchema::AUDIO_128, 'url' ),
			'music320'       => array( MetaSchema::AUDIO_320, 'url' ),
			'music_txt'      => array( MetaSchema::LYRICS, 'html' ),
			'online_ply'     => array( MetaSchema::IN_INDEX_PLAYER, 'bool' ),
			'slider_song'    => array( MetaSchema::FEATURED, 'bool' ),
			'fifu_image_url' => array( MetaSchema::COVER, 'id' ),
		);

		$video = array(
			'video480'       => array( MetaSchema::VIDEO_480, 'url' ),
			'video720'       => array( MetaSchema::VIDEO_720, 'url' ),
			'video1080'      => array( MetaSchema::VIDEO_1080, 'url' ),
			'slider_song'    => array( MetaSchema::FEATURED, 'bool' ),
			'fifu_image_url' => array( MetaSchema::VIDEO_POSTER, 'id' ),
		);

		$album = array(
			'album128'       => array( MetaSchema::ALBUM_AUDIO_128, 'url' ),
			'album320'       => array( MetaSchema::ALBUM_AUDIO_320, 'url' ),
			'slider_song'    => array( MetaSchema::FEATURED, 'bool' ),
			'fifu_image_url' => array( MetaSchema::COVER, 'id' ),
		);

		$map = array(
			'musicss'         => $audio,
			'musicss_remix'   => $audio,
			'musicss_nohe'    => $audio,
			'musicss_podcast' => $audio,
			'musicss_video'   => $video,
			'musicss_album'   => $album,
		);

		return $map[ $kind ] ?? array();
	}

	/**
	 * The meta key that holds the artist name, per source.
	 *
	 * `artist` in the legacy theme, `art_name` in the publishing plugin — the
	 * panel writes the display name there and the English name in `artist_en`.
	 *
	 * @param string $source Source slug.
	 * @return string
	 */
	public static function credit_meta( string $source = self::SOURCE_LEGACY ): string {
		return self::SOURCE_MUSIC_PUBLISHER === $source ? 'art_name' : 'artist';
	}

	/**
	 * The meta key that holds the work's title, per source.
	 *
	 * @param string $source Source slug.
	 * @return string
	 */
	public static function title_meta( string $source = self::SOURCE_LEGACY ): string {
		return self::SOURCE_MUSIC_PUBLISHER === $source ? 'track_name' : 'song';
	}

	/**
	 * Meta keys that are read on every kind and mapped by their own rule.
	 *
	 * The artist name becomes a credit label plus a resolved artist entity; the
	 * work's title a subtitle only when it is not already the post title.
	 *
	 * @param string $source Source slug.
	 * @return string[]
	 */
	public static function shared_fields( string $source = self::SOURCE_LEGACY ): array {
		$keys = array( self::credit_meta( $source ), self::title_meta( $source ) );

		if ( self::SOURCE_MUSIC_PUBLISHER === $source ) {
			// The English pair is the site's own SEO/second-language field; it is
			// copied to the raw audit meta and reported (see `deferred()`).
			$keys[] = 'artist_en';
			$keys[] = 'song_en';
		}

		return $keys;
	}

	/**
	 * Taxonomies that model the artist, in priority order.
	 *
	 * @param string $source Source slug.
	 * @return string[] Taxonomy names that exist on the site (never assumed).
	 */
	public static function artist_taxonomies( string $source = self::SOURCE_LEGACY ): array {
		unset( $source );

		// Both sources tag the performer in `singer`; the legacy theme also let
		// authors use ordinary tags, which is why tags are the second source.
		return array( self::ARTIST_TAX, 'post_tag' );
	}

	/**
	 * Taxonomies that hold credits the v1 model has no field for.
	 *
	 * The publishing plugin stores the songwriter, the composer, the arranger
	 * and the mix/master engineer as terms of their own taxonomies. v1 has no
	 * role-credit field (a documented limitation), so the tool records them in
	 * the raw audit meta and reports them once per post instead of inventing
	 * artist entities for people who may only be credited on one line.
	 *
	 * @param string $source Source slug.
	 * @return string[]
	 */
	public static function contributor_taxonomies( string $source = self::SOURCE_LEGACY ): array {
		if ( self::SOURCE_MUSIC_PUBLISHER !== $source ) {
			return array();
		}

		return array( 'songwriter', 'composer', 'regulator', 'mixmaster' );
	}

	/**
	 * The album repeater of a source: the meta key and its row shape.
	 *
	 * The legacy theme used an ACF repeater named `album` with `song_names`,
	 * `albumlink128`, `albumlink320`; the publishing plugin builds an array named
	 * `album_dl` from its fifteen track rows with `title`, `al_url128`,
	 * `al_url320`. Callers must index a row by the *row* keys, never by the
	 * meaning (that defect shipped once and is asserted against).
	 *
	 * @param string $source Source slug.
	 * @return array{meta: string, title: string, map: array<string, string>}
	 */
	public static function album_rows( string $source = self::SOURCE_LEGACY ): array {
		if ( self::SOURCE_MUSIC_PUBLISHER === $source ) {
			return array(
				'meta'  => 'album_dl',
				'title' => 'title',
				'map'   => array(
					'al_url128' => MetaSchema::AUDIO_128,
					'al_url320' => MetaSchema::AUDIO_320,
				),
			);
		}

		return array(
			'meta'  => 'album',
			'title' => self::ALBUM_TITLE_ROW,
			'map'   => array(
				'albumlink128' => MetaSchema::AUDIO_128,
				'albumlink320' => MetaSchema::AUDIO_320,
			),
		);
	}

	/**
	 * Album tracklist rows of the legacy source: row key => meaning.
	 *
	 * Kept for the callers that predate `album_rows()`; the meaning of the title
	 * row is the literal `title` because a post title is not meta.
	 *
	 * @return array<string, string>
	 */
	public static function album_row_fields(): array {
		$rows = self::album_rows();

		return array( $rows['title'] => 'title' ) + $rows['map'];
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
	 * Source fields the model deliberately does not implement.
	 *
	 * They are copied to `self::RAW` and reported; nothing is guessed. The
	 * decision behind each one lives in `docs/DECISIONS.md`,
	 * `docs/MIGRATION-BLUEPRINT.md` (`[DEFERRED]` rows) and — for the publishing
	 * plugin — `docs/INTEGRATIONS.md`.
	 *
	 * @param string $source Source slug.
	 * @return array<string, string> Source key => reason.
	 */
	public static function deferred( string $source = self::SOURCE_LEGACY ): array {
		if ( self::SOURCE_MUSIC_PUBLISHER === $source ) {
			return array(
				'artist_en'         => 'the English artist name is the site\'s second-language field, not a v1 model field',
				'song_en'           => 'the English work title is the site\'s second-language field, not a v1 model field',
				'talbume128'        => 'a folder URL for the download host; the model stores file URLs, not folders',
				'talbume320'        => 'a folder URL for the download host; the model stores file URLs, not folders',
				'select_effect'     => 'a hover-effect class of the old site templates, not content',
				'pplayer_in'        => 'the publishing panel\'s own player switch; the player is the product\'s (ADR 0005)',
				'fifu_image_alt'    => 'stored with the attachment on import; the cover resolves to the media library entry',
				'music320_video'    => 'the MP3 of a music video: the video post type stores video sources, so a second audio track would have no home',
			);
		}

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
	 * Every source meta key the tool reads on a post.
	 *
	 * @param string $source Source slug.
	 * @return string[]
	 */
	public static function all_post_keys( string $source = self::SOURCE_LEGACY ): array {
		$keys = array( self::TYPE_META );

		foreach ( array_keys( self::kinds( $source ) ) as $kind ) {
			$keys = array_merge( $keys, array_keys( self::fields( $kind, $source ) ) );
		}

		// The deferred keys are read too: their values are what the audit meta
		// keeps and what `detect()` counts, so a backup that dropped them would
		// be a backup that cannot be rolled back completely.
		return array_values(
			array_unique(
				array_merge( $keys, self::shared_fields( $source ), array( self::album_rows( $source )['meta'] ), array_keys( self::deferred( $source ) ) )
			)
		);
	}

	/**
	 * Marker metas the tool writes, and therefore must never migrate.
	 *
	 * @return string[]
	 */
	public static function markers(): array {
		return array( self::BACKUP, self::RAW, self::MARKER, self::REVIEW, self::CREATED, self::SOURCE_ALBUM, self::SOURCE_INDEX, self::SOURCE_META );
	}
}
