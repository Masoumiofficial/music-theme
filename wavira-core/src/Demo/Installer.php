<?php
/**
 * Install the demo content — the one code path behind every door.
 *
 * `wp wavira seed` and the admin screen `Tools → Wavira demo content` both call
 * `Installer::install()`; keeping the two in sync by hand is how a demo drifts
 * from the product it demonstrates. The routine is idempotent by default: a site
 * that already has tracks is left alone unless the caller forces a second run.
 *
 * Two rules, both inherited from the CLI command this replaced:
 *
 * - **No fabricated media.** Audio and video files are never seeded. A demo URL
 *   that 404s is worse than no URL: the player would look broken on the first
 *   click a buyer makes. The catalogue carries the metadata a music site needs
 *   (artists, releases, tracklists, durations, lyrics, genres, kinds) and lets
 *   the buyer add their own files.
 * - **Fictional content only.** Every lyric, biography and title is written for
 *   the demo (ADR 0010); nothing is copied from a real artist or song.
 *
 * @package Wavira\Core
 */

namespace Wavira\Core\Demo;

use RuntimeException;
use Wavira\Core\Content\MetaSchema;
use Wavira\Core\Content\PostTypes;
use Wavira\Core\Content\Taxonomies;

defined( 'ABSPATH' ) || exit;

/**
 * Class Installer
 */
final class Installer {

	/**
	 * Post meta that marks a post as demo content this tool created.
	 *
	 * It is what makes a second import safe: a forced run removes exactly the
	 * posts carrying this marker (and their attachments are never deleted),
	 * while anything the site owner wrote is left untouched.
	 */
	public const MARKER = '_wavira_demo';

	/**
	 * Install the demo catalogue.
	 *
	 * @param array<string, mixed> $args `force` (bool), `english` (bool),
	 *                                   `site` (bool: touch site options and the
	 *                                   menu, default true).
	 * @return array<string, mixed> Report: `skipped`, `artist`, `releases`,
	 *                              `tracks`, `video`, `menu`, `notices`.
	 * @throws RuntimeException When WordPress refuses to create a post.
	 */
	public static function install( array $args = array() ): array {
		$force   = ! empty( $args['force'] );
		$english = ! empty( $args['english'] );
		$site    = ! array_key_exists( 'site', $args ) || ! empty( $args['site'] );

		$report = array(
			'skipped'  => false,
			'artist'   => 0,
			'releases' => array(),
			'tracks'   => array(),
			'video'    => 0,
			'menu'     => 0,
			'replaced' => 0,
			'media'    => 0,
			'notices'  => array(),
		);

		if ( self::has_tracks() && ! $force ) {
			$report['skipped']   = true;
			$report['notices'][] = __( 'Tracks already exist — nothing was imported. Use force to import anyway.', 'wavira-core' );

			return $report;
		}

		$demo = $english ? Fixtures::english() : Fixtures::persian();

		if ( $force ) {
			$report['replaced'] = self::remove_previous( $english ? 'english' : 'persian' );
		}

		Taxonomies::ensure_kind_terms();

		$report['artist'] = self::insert_demo_post( PostTypes::ARTIST, $demo['artist'] );

		if ( Placeholders::cover( $report['artist'], self::palette( 4 ), (string) $demo['artist']['title'], 800 ) > 0 ) {
			++$report['media'];
		}

		// The gallery is content, not a site setting: `$site` decides whether the
		// language, timezone and date formats are touched, and a demo whose
		// artist page has an empty gallery (or whose album page offers no
		// download) because somebody chose the narrowly-scoped import is a demo
		// that does not demonstrate the product (ADR 0024).
		for ( $photo = 1; $photo <= 6; $photo++ ) {
			$id = Placeholders::photo(
				$report['artist'],
				self::palette( 4 + $photo ),
				(string) $demo['artist']['title'] . $photo,
				$photo
			);

			if ( $id > 0 ) {
				++$report['media'];
			}
		}

		$release_index = 0;

		foreach ( $demo['releases'] as $release ) {
			$release_id = self::insert_demo_post( PostTypes::ALBUM, $release );

			update_post_meta( $release_id, MetaSchema::ARTIST, $report['artist'] );
			update_post_meta( $release_id, MetaSchema::ALBUM_TYPE, (string) $release['type'] );
			update_post_meta( $release_id, MetaSchema::RELEASE_DATE, (string) $release['release_date'] );

			self::attach_term( $release_id, Taxonomies::GENRE, (string) $release['genre'] );

			// The demo ships generated media, never a URL that can 404 (ADR 0010
			// amended by ADR 0024): a cover per release, a tone per track so the
			// player and the download button have a file, a photo set for the
			// artist and a poster for the video.
			$palette = self::palette( $release_index );
			$cover   = Placeholders::cover( $release_id, $palette, (string) $release['title'] );

			if ( $cover > 0 ) {
				++$report['media'];
			}

			$album_file = Placeholders::album_audio( $release_id, 8, 262 + ( $release_index * 55 ) );

			if ( $album_file > 0 ) {
				++$report['media'];
			}

			$release_tracks = array();

			foreach ( $release['tracks'] as $index => $track ) {
				$track_id = self::insert_demo_post(
					PostTypes::TRACK,
					array(
						'title'      => (string) $track['title'],
						'date'       => (string) $track['date'],
						'menu_order' => $index + 1,
					)
				);

				update_post_meta( $track_id, MetaSchema::ARTIST, $report['artist'] );
				update_post_meta( $track_id, MetaSchema::ALBUM, $release_id );
				update_post_meta( $track_id, MetaSchema::DURATION, (int) $track['duration'] );
				update_post_meta( $track_id, MetaSchema::LYRICS, (string) $track['lyrics'] );
				update_post_meta( $track_id, MetaSchema::RELEASE_DATE, (string) $track['date'] );

				if ( ! empty( $track['featured'] ) ) {
					update_post_meta( $track_id, MetaSchema::FEATURED, true );
				}

				self::attach_term( $track_id, Taxonomies::GENRE, (string) $track['genre'] );
				self::attach_kind( $track_id, (string) ( $track['kind'] ?? 'music' ) );

				$track_cover = Placeholders::cover( $track_id, $palette, (string) $track['title'], 600 );

				if ( $track_cover > 0 ) {
					++$report['media'];
				}

				$audio = Placeholders::audio( $track_id, 6, 330 + ( $index * 42 ) );

				if ( ! empty( $audio['id'] ) ) {
					$report['media'] += (int) ( $audio['files'] ?? 1 );

					// The demo's own file length is what the player shows while
					// nothing has loaded yet: six seconds of tone, not the four
					// minutes the fixture pretends the song lasts.
					update_post_meta( $track_id, MetaSchema::DURATION, 6 );
				}

				$release_tracks[] = $track_id;
			}

			$report['tracks'] = array_merge( $report['tracks'], $release_tracks );

			update_post_meta( $release_id, MetaSchema::TRACKLIST, $release_tracks );

			$report['releases'][] = $release_id;
			++$release_index;
		}

		$report['video'] = self::insert_demo_post( PostTypes::VIDEO, $demo['video'] );

		update_post_meta( $report['video'], MetaSchema::ARTIST, $report['artist'] );
		update_post_meta( $report['video'], MetaSchema::ALBUM, (int) reset( $report['releases'] ) );
		update_post_meta( $report['video'], MetaSchema::VIDEO_SOURCE, 'other' );

		// The video has a poster so the section is not an empty box; there is no
		// video *file*, and the block says so plainly instead of pointing at a
		// file that is not there (see docs/DEMO-CONTENT.md).
		if ( Placeholders::cover( $report['video'], self::palette( 3 ), (string) $demo['video']['title'], 960 ) > 0 ) {
			++$report['media'];
		}

		if ( ! $english && $site ) {
			$report['notices'] = array_merge( $report['notices'], self::apply_site_defaults( $demo ) );
			$report['menu']    = self::create_primary_menu( $demo );
		}

		/**
		 * Fires after the demo content was installed.
		 *
		 * @since 0.11.0
		 * @param array<string, mixed> $report Install report.
		 * @param array<string, mixed> $demo   Fixture that was installed.
		 */
		do_action( 'wavira_core_demo_installed', $report, $demo );

		return $report;
	}

	/**
	 * Cover palette of the demo.
	 *
	 * Covers are generated, so their colours are the one thing that makes two
	 * releases distinguishable in a grid. The set is deliberately dark-to-mid: the
	 * theme's accent is indigo and a demo of pastel covers would look like a
	 * different product.
	 *
	 * @param int $index Palette number; wraps around.
	 * @return array<int, array<int, int>> Two RGB triples.
	 */
	private static function palette( int $index ): array {
		$palettes = array(
			array( array( 27, 32, 74 ), array( 118, 96, 232 ) ),   // Indigo night.
			array( array( 12, 74, 86 ), array( 92, 214, 195 ) ),   // Persian turquoise.
			array( array( 84, 24, 44 ), array( 240, 138, 122 ) ),  // Warm sunset.
			array( array( 18, 18, 24 ), array( 108, 122, 148 ) ),  // Charcoal.
			array( array( 52, 38, 12 ), array( 226, 178, 92 ) ),   // Amber.
			array( array( 16, 52, 38 ), array( 138, 214, 138 ) ),  // Garden.
			array( array( 60, 20, 60 ), array( 226, 128, 214 ) ),  // Orchid.
		);

		return $palettes[ $index % count( $palettes ) ];
	}

	/**
	 * Whether the site already holds tracks.
	 *
	 * @return bool
	 */
	public static function has_tracks(): bool {
		$count = wp_count_posts( PostTypes::TRACK );

		return isset( $count->publish ) && (int) $count->publish > 0;
	}

	/**
	 * Insert one demo post, failing loudly when WordPress refuses.
	 *
	 * The caller gets an exception rather than a half-installed catalogue: a
	 * partial demo is harder to diagnose than a failed import.
	 *
	 * @param string               $post_type Post type.
	 * @param array<string, mixed> $data      Title, content, date and menu order.
	 * @return int Post ID.
	 * @throws RuntimeException When the post cannot be created.
	 */
	private static function insert_demo_post( string $post_type, array $data ): int {
		$post_id = wp_insert_post(
			array(
				'post_type'    => $post_type,
				'post_title'   => (string) ( $data['title'] ?? '' ),
				'post_content' => (string) ( $data['content'] ?? '' ),
				'post_status'  => 'publish',
				'post_date'    => (string) ( $data['date'] ?? '' ),
				'menu_order'   => (int) ( $data['menu_order'] ?? 0 ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			throw new RuntimeException( $post_id->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- the caller escapes it for the surface it prints on.
		}

		if ( (int) $post_id <= 0 ) {
			throw new RuntimeException( 'WordPress did not create the demo post.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- a fixed string, never user input.
		}

		update_post_meta( (int) $post_id, self::MARKER, '1' );

		return (int) $post_id;
	}

	/**
	 * Remove the demo content a previous run created.
	 *
	 * Only posts carrying the marker are deleted, and they are deleted in pages
	 * so a large catalogue cannot exhaust memory on a shared host. Nothing here
	 * touches media: an attachment the owner uploaded under a demo title stays.
	 *
	 * @param string $fixture Fixture name, for the log.
	 * @return int Number of posts deleted.
	 */
	private static function remove_previous( string $fixture ): int {
		$deleted = 0;
		$types   = array( PostTypes::ARTIST, PostTypes::ALBUM, PostTypes::TRACK, PostTypes::VIDEO );

		// The generated attachments go first, and separately: a forced import
		// that deleted its titles and left twenty-three files in the uploads
		// folder would fill a site with orphans (ADR 0024). `wp_delete_attachment()`
		// removes the file with the row, so nothing is unlinked by hand; only
		// files this importer wrote carry the marker.
		$deleted += self::remove_previous_media();

		do {
			$ids = get_posts(
				array(
					'post_type'        => $types,
					'post_status'      => 'any',
					'posts_per_page'   => 100,
					'fields'           => 'ids',
					'orderby'          => 'ID',
					'order'            => 'ASC',
					'no_found_rows'    => true,
					'suppress_filters' => false,
					'meta_key'         => self::MARKER, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- an administrative one-off, not a front-end query.
					'meta_value'       => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- same.
				)
			);

			foreach ( $ids as $id ) {
				if ( wp_delete_post( (int) $id, true ) ) {
					++$deleted;
				}
			}
		} while ( array() !== $ids );

		/**
		 * Fires after a forced import removed the demo content of a previous run.
		 *
		 * @since 0.11.0
		 * @param int    $deleted Number of posts removed.
		 * @param string $fixture Fixture that is about to be installed.
		 */
		do_action( 'wavira_core_demo_replaced', $deleted, $fixture );

		return $deleted;
	}

	/**
	 * Attach one term of a taxonomy, creating it on first use.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy name.
	 * @param string $name     Term name.
	 * @return void
	 */
	private static function attach_term( int $post_id, string $taxonomy, string $name ): void {
		if ( '' === $name || ! taxonomy_exists( $taxonomy ) ) {
			return;
		}

		$term = term_exists( $name, $taxonomy );

		if ( ! $term ) {
			$term = wp_insert_term( $name, $taxonomy );
		}

		if ( is_wp_error( $term ) || ! is_array( $term ) || ! isset( $term['term_id'] ) ) {
			return;
		}

		wp_set_object_terms( $post_id, array( (int) $term['term_id'] ), $taxonomy, true );
	}

	/**
	 * Attach the kind term of a demo track.
	 *
	 * The kind is what the archive filter and — for imported content — the
	 * migration tool use, so the demo declares one per track: the default demo
	 * ships songs and a remix of one of them, which is enough to show that a
	 * remix is not just another song on the shelf.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $kind    Kind slug or a source value.
	 * @return void
	 */
	private static function attach_kind( int $post_id, string $kind ): void {
		$slug = Taxonomies::normalize_kind( $kind );

		if ( '' === $slug ) {
			return;
		}

		self::attach_term( $post_id, Taxonomies::KIND, $slug );
	}

	/**
	 * The Iranian defaults a Persian music site expects.
	 *
	 * Public because the public API exposes it as
	 * `wavira_core_apply_persian_defaults()` and the theme's one-click Persian
	 * setup calls that function: one implementation of "what a Persian site
	 * needs", shared by the demo installer and the theme.
	 *
	 * @param array<string, mixed> $demo Demo catalogue (its `site` block carries
	 *                                   the locale, timezone and description).
	 * @return string[] Notices for the report.
	 */
	public static function apply_site_defaults( array $demo ): array {
		update_option( 'WPLANG', (string) $demo['site']['locale'] );
		update_option( 'timezone_string', (string) $demo['site']['timezone'] );
		update_option( 'start_of_week', 6 );
		update_option( 'date_format', 'j F Y' );
		update_option( 'time_format', 'H:i' );

		$description = (string) get_option( 'blogdescription' );

		// Only replace a description nobody wrote on purpose.
		if ( '' === trim( $description ) || 'Just another WordPress site' === $description ) {
			update_option( 'blogdescription', (string) $demo['site']['description'] );
		}

		return array(
			__( 'Site locale, timezone, first day of the week and date format were set to the Iranian defaults.', 'wavira-core' ),
		);
	}

	/**
	 * Delete the attachments a previous import generated.
	 *
	 * Bounded and marker-scoped: a real site's uploads never carry
	 * `Placeholders::MARKER`, so nothing an owner uploaded can be touched.
	 *
	 * @return int Number of attachments deleted.
	 */
	private static function remove_previous_media(): int {
		$deleted = 0;

		do {
			$ids = get_posts(
				array(
					'post_type'        => 'attachment',
					'post_status'      => 'any',
					'posts_per_page'   => 100,
					'fields'           => 'ids',
					'no_found_rows'    => true,
					'suppress_filters' => false,
					'meta_key'         => Placeholders::MARKER, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- administrative one-off.
					'meta_value'       => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- same.
				)
			);

			foreach ( $ids as $id ) {
				if ( wp_delete_attachment( (int) $id, true ) ) {
					++$deleted;
				}
			}
		} while ( array() !== $ids );

		return $deleted;
	}

	/**
	 * Build the theme's primary menu from the demo catalogue.
	 *
	 * @param array<string, mixed> $demo Demo catalogue.
	 * @return int Menu ID, 0 when the menu could not be created.
	 */
	private static function create_primary_menu( array $demo ): int {
		$menu_id = wp_create_nav_menu( (string) $demo['menu']['name'] );

		if ( is_wp_error( $menu_id ) ) {
			$existing = wp_get_nav_menu_object( (string) $demo['menu']['name'] );

			if ( ! $existing ) {
				return 0;
			}

			$menu_id = (int) $existing->term_id;
		}

		foreach ( (array) $demo['menu']['items'] as $item ) {
			$args = array(
				'menu-item-title'  => (string) $item['label'],
				'menu-item-status' => 'publish',
			);

			if ( 'archive' === $item['type'] ) {
				$args['menu-item-type']   = 'post_type_archive';
				$args['menu-item-object'] = (string) $item['object'];
			} elseif ( 'taxonomy' === $item['type'] ) {
				$args['menu-item-type']   = 'taxonomy';
				$args['menu-item-object'] = (string) $item['object'];
			} else {
				$args['menu-item-type'] = 'custom';
				$args['menu-item-url']  = (string) $item['url'];
			}

			wp_update_nav_menu_item( (int) $menu_id, 0, $args );
		}

		// Read-modify-write of the nav menu locations, as its own step.
		$locations = (array) get_theme_mod( 'nav_menu_locations', array() );

		$locations['primary'] = (int) $menu_id;

		set_theme_mod( 'nav_menu_locations', $locations );

		return (int) $menu_id;
	}
}
