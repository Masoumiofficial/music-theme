<?php
/**
 * WP-CLI commands: `wp wavira verify` and `wp wavira seed`.
 *
 * The commands exist so that the data model can be validated and exercised on a
 * real installation without shipping demo content by accident. Seed data is
 * generated text only — no third-party media, no real lyrics (ADR 0010).
 *
 * @package Wavira\Core\Admin
 */

namespace Wavira\Core\Admin;

use Wavira\Core\Content\MetaSchema;
use Wavira\Core\Content\MetaValues;
use Wavira\Core\Content\PostTypes;
use Wavira\Core\Content\Taxonomies;
use Wavira\Core\Contracts\Registrable;
use Wavira\Core\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class Cli
 */
final class Cli implements Registrable {

	/**
	 * Register hooks (only inside a WP-CLI request).
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		\WP_CLI::add_command( 'wavira verify', array( $this, 'verify' ) );
		\WP_CLI::add_command( 'wavira seed', array( $this, 'seed' ) );
	}

	/**
	 * Verify the data model: post types, taxonomies and meta registration.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wavira verify
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Associative arguments (unused).
	 * @return void
	 */
	public function verify( $args = array(), $assoc_args = array() ): void {
		unset( $args, $assoc_args );

		$errors = array();

		foreach ( PostTypes::all() as $post_type ) {
			if ( ! post_type_exists( $post_type ) ) {
				$errors[] = sprintf( 'Post type missing: %s', $post_type );
			}
		}

		foreach ( array( Taxonomies::GENRE, Taxonomies::MOOD, Taxonomies::LANGUAGE, Taxonomies::LABEL, Taxonomies::YEAR ) as $taxonomy ) {
			$enabled = Taxonomies::GENRE === $taxonomy || Settings::get( 'enable_' . str_replace( 'wavira_', '', $taxonomy ), false );

			if ( $enabled && ! taxonomy_exists( $taxonomy ) ) {
				$errors[] = sprintf( 'Taxonomy missing: %s', $taxonomy );
			}
		}

		foreach ( MetaSchema::all() as $key => $field ) {
			foreach ( $field['entities'] as $post_type ) {
				if ( ! registered_meta_key_exists( 'post', $key, $post_type ) ) {
					$errors[] = sprintf( 'Meta not registered: %s on %s', $key, $post_type );
				}
			}
		}

		$counts = array();

		foreach ( PostTypes::all() as $post_type ) {
			$count                = wp_count_posts( $post_type );
			$counts[ $post_type ] = isset( $count->publish ) ? (int) $count->publish : 0;
		}

		\WP_CLI::log( 'Published content:' );

		foreach ( $counts as $post_type => $count ) {
			\WP_CLI::log( sprintf( '  %-18s %d', $post_type, $count ) );
		}

		$genres = get_terms(
			array(
				'taxonomy'   => Taxonomies::GENRE,
				'hide_empty' => false,
				'fields'     => 'count',
			)
		);

		\WP_CLI::log( sprintf( '  %-18s %d', 'genres', is_wp_error( $genres ) ? 0 : (int) $genres ) );

		if ( empty( $errors ) ) {
			\WP_CLI::success( 'Data model verified: post types, taxonomies and registered meta are all present.' );
			return;
		}

		foreach ( $errors as $error ) {
			\WP_CLI::warning( $error );
		}

		\WP_CLI::error( sprintf( '%d problem(s) found.', count( $errors ) ) );
	}

	/**
	 * Create a licence-clean demo set (artist, releases, tracks, video).
	 *
	 * Persian by default: the product is sold to Persian-language sites, so the
	 * demo it ships with is a Persian music site — Persian content, `fa_IR`,
	 * Asia/Tehran, a week that starts on Saturday, and Jalali dates on the front
	 * end (ADR 0017). `--english` seeds the neutral English fixture instead and
	 * leaves the site's locale, timezone and menus alone.
	 *
	 * Idempotent: does nothing when the site already has tracks, unless --force.
	 * Audio files are never seeded: a fabricated media URL that 404s is worse
	 * than no URL at all, and the player is covered by the harness in
	 * `tools/preview/` and by the PHP suite.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Seed even when tracks already exist.
	 *
	 * [--english]
	 * : Seed the English fixture, and leave the site's locale, timezone and
	 *   menus untouched.
	 *
	 * [--no-site]
	 * : Seed the content only; do not touch site options or menus.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wavira seed
	 *     wp wavira seed --force
	 *     wp wavira seed --english --no-site
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function seed( $args = array(), $assoc_args = array() ): void {
		unset( $args );

		$force      = isset( $assoc_args['force'] );
		$english    = isset( $assoc_args['english'] );
		$with_site  = ! isset( $assoc_args['no-site'] );
		$count      = wp_count_posts( PostTypes::TRACK );
		$has_tracks = isset( $count->publish ) && (int) $count->publish > 0;

		if ( $has_tracks && ! $force ) {
			\WP_CLI::warning( 'Tracks already exist — nothing seeded. Use --force to seed anyway.' );
			return;
		}

		$demo = $english ? self::english_demo() : self::persian_demo();

		$artist_id   = self::insert_demo_post( PostTypes::ARTIST, $demo['artist'] );
		$release_ids = array();
		$track_ids   = array();

		foreach ( $demo['releases'] as $release ) {
			$release_id = self::insert_demo_post( PostTypes::ALBUM, $release );

			update_post_meta( $release_id, MetaSchema::ARTIST, $artist_id );
			update_post_meta( $release_id, MetaSchema::ALBUM_TYPE, (string) $release['type'] );
			update_post_meta( $release_id, MetaSchema::RELEASE_DATE, (string) $release['release_date'] );

			self::attach_genre( $release_id, (string) $release['genre'] );

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

				update_post_meta( $track_id, MetaSchema::ARTIST, $artist_id );
				update_post_meta( $track_id, MetaSchema::ALBUM, $release_id );
				update_post_meta( $track_id, MetaSchema::DURATION, (int) $track['duration'] );
				update_post_meta( $track_id, MetaSchema::LYRICS, (string) $track['lyrics'] );
				update_post_meta( $track_id, MetaSchema::RELEASE_DATE, (string) $track['date'] );

				if ( ! empty( $track['featured'] ) ) {
					update_post_meta( $track_id, MetaSchema::FEATURED, true );
				}

				self::attach_genre( $track_id, (string) $track['genre'] );

				$release_tracks[] = (int) $track_id;
				$track_ids[]      = (int) $track_id;
			}

			update_post_meta( $release_id, MetaSchema::TRACKLIST, $release_tracks );

			$release_ids[] = (int) $release_id;
		}

		$video_id = self::insert_demo_post( PostTypes::VIDEO, $demo['video'] );

		update_post_meta( $video_id, MetaSchema::ARTIST, $artist_id );
		update_post_meta( $video_id, MetaSchema::ALBUM, (int) reset( $release_ids ) );
		update_post_meta( $video_id, MetaSchema::VIDEO_SOURCE, 'other' );

		if ( ! $english && $with_site ) {
			self::apply_site_defaults( $demo );
			self::create_primary_menu( $demo );
		}

		\WP_CLI::success(
			sprintf(
				'Seeded artist #%1$d, %2$d releases, %3$d tracks and a video.',
				(int) $artist_id,
				count( $release_ids ),
				count( $track_ids )
			)
		);
	}

	/**
	 * Insert one demo post, failing loudly when WordPress refuses.
	 *
	 * @param string               $post_type Post type.
	 * @param array<string, mixed> $data      Title, content, date and menu order.
	 * @return int Post ID.
	 */
	private static function insert_demo_post( string $post_type, array $data ): int {
		$post_id = wp_insert_post(
			array(
				'post_type'    => $post_type,
				'post_title'   => (string) $data['title'],
				'post_content' => (string) ( $data['content'] ?? '' ),
				'post_status'  => 'publish',
				'post_date'    => (string) ( $data['date'] ?? '' ),
				'menu_order'   => (int) ( $data['menu_order'] ?? 0 ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			\WP_CLI::error( $post_id->get_error_message() );
		}

		return (int) $post_id;
	}

	/**
	 * Attach one genre term, creating it on first use.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $name    Genre name.
	 * @return void
	 */
	private static function attach_genre( int $post_id, string $name ): void {
		$term = term_exists( $name, Taxonomies::GENRE );

		if ( ! $term ) {
			$term = wp_insert_term( $name, Taxonomies::GENRE );
		}

		if ( is_wp_error( $term ) || ! is_array( $term ) || ! isset( $term['term_id'] ) ) {
			return;
		}

		wp_set_object_terms( $post_id, array( (int) $term['term_id'] ), Taxonomies::GENRE, true );
	}

	/**
	 * The Iranian defaults a Persian music site expects.
	 *
	 * @param array<string, mixed> $demo Demo catalogue.
	 * @return void
	 */
	private static function apply_site_defaults( array $demo ): void {
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

		\WP_CLI::log( '  site   fa_IR · Asia/Tehran · week starts on Saturday · Jalali dates' );
	}

	/**
	 * Build the theme's primary menu from the demo catalogue.
	 *
	 * @param array<string, mixed> $demo Demo catalogue.
	 * @return void
	 */
	private static function create_primary_menu( array $demo ): void {
		$menu_id = wp_create_nav_menu( (string) $demo['menu']['name'] );

		if ( is_wp_error( $menu_id ) ) {
			$existing = wp_get_nav_menu_object( (string) $demo['menu']['name'] );

			if ( ! $existing ) {
				\WP_CLI::warning( 'Could not create or find the demo menu.' );
				return;
			}

			$menu_id = (int) $existing->term_id;
		}

		foreach ( $demo['menu']['items'] as $item ) {
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

		\WP_CLI::log( sprintf( '  menu   %s → primary', (string) $demo['menu']['name'] ) );
	}

	/**
	 * The Persian demo catalogue — the product's default demo.
	 *
	 * The text is content, not interface copy: it is written in Persian once and
	 * is not passed through the translation functions, so it never lands in the
	 * catalogue of interface strings. Every lyric here is generated for the demo
	 * (ADR 0010): no real song, no third-party work, no real biography.
	 *
	 * @return array<string, mixed>
	 */
	private static function persian_demo(): array {
		return array(
			'artist'   => array(
				'title'   => 'آرمان راد',
				'content' => "آرمان راد، ترانه‌ساز و خوانندهٔ اهل تهران است. کار را با اجراهای کوچک در سالن‌های محلی آغاز کرد و سپس با انتشار تک‌آهنگ‌ها و همکاری با نوازندگان دیگر به صحنه‌های بزرگ‌تر رسید.\n\nاین متن نمایشی است؛ آن را با زندگی‌نامهٔ واقعی هنرمند جایگزین کنید.",
				'date'    => '2025-11-01',
			),
			'releases' => array(
				array(
					'title'        => 'شب‌های تهران',
					'type'         => 'album',
					'date'         => '2025-12-20',
					'release_date' => '2025-12-20',
					'genre'        => 'پاپ رؤیایی',
					'tracks'       => array(
						array(
							'title'    => 'راه بارانی',
							'date'     => '2025-11-05',
							'genre'    => 'پاپ رؤیایی',
							'duration' => 214,
							'featured' => true,
							'lyrics'   => "چترم از باران پر است،\nکوچه را تا خانه می‌دوم.",
						),
						array(
							'title'    => 'سکوت',
							'date'     => '2025-11-19',
							'genre'    => 'پاپ رؤیایی',
							'duration' => 187,
							'lyrics'   => "سکوت من ترانه نیست،\nنفسِ میان دو واژه است.",
						),
						array(
							'title'    => 'پرواز',
							'date'     => '2025-12-03',
							'genre'    => 'راک تجربی',
							'duration' => 246,
							'lyrics'   => "اگر بمانم، پَر می‌ریزم،\nپس می‌پرم از لبهٔ شهر.",
						),
					),
				),
				array(
					'title'        => 'باران بهاری',
					'type'         => 'single',
					'date'         => '2026-01-15',
					'release_date' => '2026-01-15',
					'genre'        => 'امبینت',
					'tracks'       => array(
						array(
							'title'    => 'باران بهاری',
							'date'     => '2026-01-15',
							'genre'    => 'امبینت',
							'duration' => 231,
							'lyrics'   => "باران که آمد، شهر نفس کشید،\nو بوی بهار از پنجره گذشت.",
						),
					),
				),
			),
			'video'    => array(
				'title' => 'اجرای زندهٔ نمایشی',
				'date'  => '2026-02-01',
			),
			'site'     => array(
				'locale'      => 'fa_IR',
				'timezone'    => 'Asia/Tehran',
				'description' => 'انتشار موسیقی روی سایت خودتان',
			),
			'menu'     => array(
				'name'  => 'منوی اصلی',
				'items' => array(
					array(
						'type'  => 'custom',
						'label' => 'خانه',
						'url'   => home_url( '/' ),
					),
					array(
						'type'   => 'archive',
						'label'  => 'آهنگ‌ها',
						'object' => PostTypes::TRACK,
					),
					array(
						'type'   => 'archive',
						'label'  => 'آلبوم‌ها',
						'object' => PostTypes::ALBUM,
					),
					array(
						'type'   => 'archive',
						'label'  => 'هنرمندان',
						'object' => PostTypes::ARTIST,
					),
					array(
						'type'   => 'archive',
						'label'  => 'ویدیوها',
						'object' => PostTypes::VIDEO,
					),
					array(
						'type'   => 'taxonomy',
						'label'  => 'سبک‌ها',
						'object' => Taxonomies::GENRE,
					),
				),
			),
		);
	}

	/**
	 * The neutral English fixture.
	 *
	 * @return array<string, mixed>
	 */
	private static function english_demo(): array {
		$lyrics = __( 'Generated demo lyrics — replace this text.', 'wavira-core' );
		$genre  = __( 'Demo Genre', 'wavira-core' );
		$tracks = array();

		for ( $index = 1; $index <= 3; $index++ ) {
			$tracks[] = array(
				'title'    => sprintf(
					/* translators: %d: demo track number. */
					__( 'Demo Track %d', 'wavira-core' ),
					$index
				),
				'date'     => '2026-01-0' . $index,
				'genre'    => $genre,
				'duration' => 180 + ( $index * 7 ),
				'featured' => 1 === $index,
				'lyrics'   => $lyrics,
			);
		}

		return array(
			'artist'   => array(
				'title'   => __( 'Demo Artist', 'wavira-core' ),
				'content' => __( 'Generated demo biography. Replace with real content.', 'wavira-core' ),
				'date'    => '2026-01-01',
			),
			'releases' => array(
				array(
					'title'        => __( 'Demo Album', 'wavira-core' ),
					'type'         => 'album',
					'date'         => '2026-01-08',
					'release_date' => '2026-01-08',
					'genre'        => $genre,
					'tracks'       => $tracks,
				),
			),
			'video'    => array(
				'title' => __( 'Demo Music Video', 'wavira-core' ),
				'date'  => '2026-01-15',
			),
			'site'     => array(
				'locale'      => 'en_US',
				'timezone'    => 'UTC',
				'description' => '',
			),
			'menu'     => array(
				'name'  => 'Demo menu',
				'items' => array(),
			),
		);
	}
}
