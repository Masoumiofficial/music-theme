<?php
/**
 * The demo catalogue: the fixtures the product ships as its example site.
 *
 * The Persian catalogue is the default one (ADR 0017): the product is sold to
 * Persian-language sites, so the demo a buyer installs is a Persian music site —
 * Persian content, `fa_IR`, Asia/Tehran, a week that starts on Saturday, Jalali
 * dates on the front end. `Fixtures::english()` is the neutral fixture the test
 * suite and the `--english` switch use.
 *
 * Every lyric and biography here is written for the demo (ADR 0010): no real
 * song, no third-party work, no real person. The text is content, not interface
 * copy — it is deliberately not passed through the translation functions, so it
 * never lands in the catalogue of interface strings. That also means a site
 * owner replaces it with real content instead of translating it.
 *
 * The same fixture feeds three surfaces: `wp wavira seed` (WP-CLI), the admin
 * screen `Tools → Wavira demo content` (no WP-CLI needed), and the WXR export
 * `wp wavira export-demo` (the standard WordPress import path). One catalogue,
 * three doors — a demo that only one of them can reproduce is a demo that drifts.
 *
 * @package Wavira\Core
 */

namespace Wavira\Core\Demo;

use Wavira\Core\Content\PostTypes;
use Wavira\Core\Content\Taxonomies;

defined( 'ABSPATH' ) || exit;

/**
 * Class Fixtures
 */
final class Fixtures {

	/**
	 * The Persian catalogue: the demo the product ships as its example site.
	 *
	 * Fictional content, written for the demo (ADR 0010): one artist, an album
	 * with four tracks (one of them a remix), a single, a music video, Persian
	 * genres and Jalali-era release dates. No audio or video file is referenced
	 * — a demo URL that 404s would break the player on the buyer's first click.
	 *
	 * @return array<string, mixed>
	 */
	public static function persian(): array {
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
							'kind'     => 'music',
							'duration' => 214,
							'featured' => true,
							'lyrics'   => "چترم از باران پر است،\nکوچه را تا خانه می‌دوم.",
						),
						array(
							'title'    => 'سکوت',
							'date'     => '2025-11-19',
							'genre'    => 'پاپ رؤیایی',
							'kind'     => 'music',
							'duration' => 187,
							'lyrics'   => "سکوت من ترانه نیست،\nنفسِ میان دو واژه است.",
						),
						array(
							'title'    => 'پرواز',
							'date'     => '2025-12-03',
							'genre'    => 'راک تجربی',
							'kind'     => 'music',
							'duration' => 246,
							'lyrics'   => "اگر بمانم، پَر می‌ریزم،\nپس می‌پرم از لبهٔ شهر.",
						),
						// A remix of another demo track, so the demo shows what
						// the import path of a real publishing site has to land
						// in: a remix is a kind of its own, not another song.
						array(
							'title'    => 'راه بارانی (نسخهٔ ریمیکس)',
							'date'     => '2025-12-10',
							'genre'    => 'پاپ رؤیایی',
							'kind'     => 'remix',
							'duration' => 238,
							'lyrics'   => "چترم از باران پر است،\nاین بار شهر را قدم می‌زنم.",
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
							'kind'     => 'music',
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
	 * Used by the test suite and by `--english`: content only, and the installer
	 * never touches the site's locale, timezone or menu for it (ADR 0017). The
	 * strings here *are* translatable, unlike the Persian catalogue, because
	 * this fixture is a developer fixture rather than a market demo.
	 *
	 * @return array<string, mixed>
	 */
	public static function english(): array {
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
				'kind'     => 'music',
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
