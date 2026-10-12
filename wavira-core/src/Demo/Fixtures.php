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
				// A podcast series is a release of its own (type `podcast`) whose
				// episodes are long-form tracks: an episode number, a timestamped
				// chapter list, and a longer generated tone for the demo player to
				// actually seek in. Without one, the chapters panel, the playback
				// speed control and the resume button ship invisible on a fresh
				// install — the same defect as an empty section (ADR 0024).
				array(
					'title'        => 'پادکست واویرا',
					'type'         => 'podcast',
					'date'         => '2026-02-10',
					'release_date' => '2026-02-10',
					'genre'        => 'گفت‌وگو',
					'tracks'       => array(
						array(
							'title'    => 'قسمت یکم: پشت صحنهٔ ضبط',
							'date'     => '2026-02-10',
							'genre'    => 'گفت‌وگو',
							'kind'     => 'podcast',
							'duration' => 1320,
							'episode'  => 1,
							'longform' => true,
							'chapters' => "00:00 خوش‌آمدگویی\n00:06 اتاق ضبط و سازها\n00:13 پرسش شنوندگان\n00:18 جمع‌بندی",
							'lyrics'   => 'یادداشت این قسمت: گفت‌وگویی نمایشی دربارهٔ روند ضبط یک قطعه، از انتخاب ریتم تا میکس نهایی.',
						),
						array(
							'title'    => 'قسمت دوم: گفت‌وگو با تهیه‌کننده',
							'date'     => '2026-02-24',
							'genre'    => 'گفت‌وگو',
							'kind'     => 'podcast',
							'duration' => 1495,
							'episode'  => 2,
							'longform' => true,
							'chapters' => "00:00 مقدمه\n00:07 میز تدوین\n00:14 پیشنهاد شنیدنی\n00:19 پایان",
							'lyrics'   => 'یادداشت این قسمت: تهیه‌کننده از تصمیم‌های هنری و ترتیب قطعات آلبوم می‌گوید.',
						),
					),
				),
			),
			'video'    => array(
				'title' => 'اجرای زندهٔ نمایشی',
				'date'  => '2026-02-01',
			),
			// The news is ordinary posts, because that is what news is: a demo
			// whose «اخبار» page lists nothing but the sample post WordPress
			// installs has an empty section where the product promises a working
			// one. Fictional, like the rest of the catalogue (ADR 0010).
			'news'     => array(
				array(
					'title'    => 'آلبوم «شب‌های تهران» منتشر شد',
					'excerpt'  => 'چهار قطعهٔ تازه با صدای آرمان راد، از امروز برای شنیدن و دانلود در دسترس است.',
					'content'  => "آلبوم «شب‌های تهران» با چهار قطعه منتشر شد: «راه بارانی»، «سکوت»، «پرواز» و یک نسخهٔ ریمیکس از قطعهٔ نخست.\n\nضبط این آلبوم از پاییز در یک استودیوی کوچک در تهران آغاز شد و هر قطعه در چند برداشت پی‌درپی کامل شده است.\n\nاین متن نمایشی است؛ آن را با خبر واقعی خودتان جایگزین کنید.",
					'date'     => '2025-12-20',
					'category' => 'تازه‌ها',
				),
				array(
					'title'    => 'پشت صحنهٔ ضبط «پرواز»',
					'excerpt'  => 'گفت‌وگویی کوتاه دربارهٔ ساخت قطعه‌ای که بیش از دو ماه زمان برد.',
					'content'  => "«پرواز» از یک زمزمهٔ ساده شروع شد: دو واژه روی یک نت، که بعدتر به بخش اصلی ترانه تبدیل شد.\n\nدر این گزارش کوتاه از انتخاب سازها، از برداشت‌هایی که کنار گذاشته شد و از روزی می‌خوانید که گروه تصمیم گرفت قطعه را دوباره از اول ضبط کند.\n\nاین متن نمایشی است؛ آن را با گزارش واقعی خودتان جایگزین کنید.",
					'date'     => '2026-01-05',
					'category' => 'گزارش',
				),
				array(
					'title'    => 'برنامهٔ اجراهای زمستان',
					'excerpt'  => 'سه اجرای زنده در تهران و یک اجرا در اصفهان؛ برنامهٔ کامل در این خبر.',
					'content'  => "اجراهای زمستان اعلام شد: سه شب در تهران و یک شب در اصفهان، با همان گروهی که آلبوم را ضبط کرده است.\n\nترتیب قطعه‌ها در هر شب متفاوت خواهد بود و برنامهٔ هر شب یک هفته پیش از اجرا منتشر می‌شود.\n\nاین متن نمایشی است؛ آن را با خبر واقعی خودتان جایگزین کنید.",
					'date'     => '2026-02-10',
					'category' => 'تازه‌ها',
				),
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
						// A path, not an absolute URL: this is the one item whose
						// link the importer has to write into the database as a
						// string (the rest are archives and terms, resolved when
						// the page renders). Stored absolutely, it is the first
						// thing that breaks when the site moves to another
						// domain — and it is a link to the site's own front
						// page, which needs no host at all. `wp_make_link_relative()`
						// keeps a site installed in a subdirectory right, which
						// a plain `/` would not.
						'url'   => wp_make_link_relative( home_url( '/' ) ),
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
					// The news, last: «posts» is not a post type archive but the
					// page the site lists its posts on, which the installer
					// creates in its site step. When there is none, the item is
					// left out rather than pointing at nothing.
					array(
						'type'  => 'posts',
						'label' => 'اخبار',
					),
					// No «سبک‌ها» (styles): a taxonomy has no archive of its own and
				// only a term has a page, so a menu item for it is a link with no
				// destination. The genres are on the releases, where a visitor can
				// follow them.
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

		$news = array();

		for ( $index = 1; $index <= 3; $index++ ) {
			$news[] = array(
				'title'    => sprintf(
					/* translators: %d: demo news post number. */
					__( 'Demo News %d', 'wavira-core' ),
					$index
				),
				'excerpt'  => __( 'Generated demo excerpt — replace this text.', 'wavira-core' ),
				'content'  => __( 'Generated demo news post. Replace with real content.', 'wavira-core' ),
				'date'     => '2026-01-1' . $index,
				'category' => __( 'Demo News', 'wavira-core' ),
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
			'news'     => $news,
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
