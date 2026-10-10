<?php
/**
 * Runtime verification of the Persian catalogues.
 *
 * The gate in `tools/i18n.mjs` proves that every source string has a Persian
 * translation and that the `.mo` matches the `.po`. This suite proves the other
 * half: that WordPress, given the shipped files, actually returns Persian for
 * the strings the product prints — front end, admin, block metadata and the
 * player payload — and that a string outside the catalogue still falls through
 * to its source instead of disappearing.
 *
 * @package Wavira\Tests
 */

/**
 * Class Test_I18n
 */
class Test_I18n extends Wavira_Test_Case {

	/**
	 * Unload the catalogues so no other test inherits Persian.
	 *
	 * @return void
	 */
	public function tear_down() {
		unload_textdomain( 'wavira' );
		unload_textdomain( 'wavira-core' );

		parent::tear_down();
	}

	/**
	 * Load one shipped catalogue.
	 *
	 * @param string $domain Text domain.
	 * @param string $file   Path relative to the repository root.
	 * @return void
	 */
	private function load_catalogue( string $domain, string $file ) {
		unload_textdomain( $domain );

		$this->assertTrue(
			load_textdomain( $domain, dirname( __DIR__ ) . '/' . $file, 'fa_IR' ),
			"{$file} must load as the fa_IR catalogue for {$domain}"
		);
	}

	/**
	 * Both artifacts ship a Persian catalogue, not just English sources.
	 *
	 * @return void
	 */
	public function test_persian_catalogues_ship_with_both_artifacts() {
		foreach ( array( 'wavira/languages/fa_IR.mo', 'wavira-core/languages/fa_IR.mo' ) as $file ) {
			$path = dirname( __DIR__ ) . '/' . $file;

			$this->assertFileExists( $path );
			$this->assertGreaterThan( 1024, (int) filesize( $path ), "{$file} must carry real translations" );
		}
	}

	/**
	 * The strings a visitor sees on the front end are Persian.
	 *
	 * @return void
	 */
	public function test_front_end_strings_come_back_in_persian() {
		$this->load_catalogue( 'wavira', 'wavira/languages/fa_IR.mo' );

		$this->assertSame( 'جدیدترین آلبوم‌ها', esc_html_x( 'Latest albums', 'heading of the album grid pattern', 'wavira' ) );
		$this->assertSame( 'قطعه‌ها', esc_html_x( 'Tracks', 'section heading above an album tracklist', 'wavira' ) );
		$this->assertSame( 'تماشای ویدیو', esc_html__( 'Watch the video', 'wavira' ) );
		$this->assertSame( 'همین حالا بشنوید', esc_html_x( 'Listen now', 'heading of the catalogue player pattern', 'wavira' ) );
		$this->assertSame( 'صفحه یافت نشد', esc_html_x( 'Page not found', 'heading of the 404 template', 'wavira' ) );

		// The 0.9.0 surfaces: the artist profile and the news section ship
		// Persian on install too, not only the strings that predate them.
		$this->assertSame( 'اخبار موسیقی', esc_html_x( 'Music news', 'heading above the news feed', 'wavira' ) );
		$this->assertSame( 'تصاویر', esc_html__( 'Photos', 'wavira' ) );
		$this->assertSame( 'مشاهدهٔ همه', esc_html__( 'View all', 'wavira' ) );
		$this->assertSame( 'تازه‌ترین خبرها به‌صورت کارت.', esc_html__( 'The newest news posts as cards.', 'wavira' ) );

		// A string that is not in the catalogue must keep its source text: a
		// missing translation degrades to English, it never prints nothing.
		$this->assertSame( 'Not in the catalogue', __( 'Not in the catalogue', 'wavira' ) );
	}

	/**
	 * Block metadata is translated in the context core looks it up with.
	 *
	 * `translate_settings_using_i18n_schema()` uses the contexts of
	 * `wp-includes/block-i18n.json`; a catalogue built for a different context
	 * would leave the inserter English.
	 *
	 * @return void
	 */
	public function test_block_metadata_is_persian() {
		$this->load_catalogue( 'wavira', 'wavira/languages/fa_IR.mo' );

		$this->assertSame( 'پخشکنندهٔ موسیقی', _x( 'Music player', 'block title', 'wavira' ) );
		$this->assertSame( 'برچسب‌های سبک', _x( 'Genre chips', 'block title', 'wavira' ) );
		$this->assertSame( 'موسیقی', _x( 'music', 'block keyword', 'wavira' ) );
		$this->assertSame( 'نمای هنرمند', _x( 'Artist profile', 'block title', 'wavira' ) );
		$this->assertSame( 'گالری تصاویر', _x( 'Photo gallery', 'block title', 'wavira' ) );
		$this->assertSame( 'هنرمند', _x( 'artist', 'block keyword', 'wavira' ) );
	}

	/**
	 * The artist and news strings the core plugin builds are Persian too.
	 *
	 * @return void
	 */
	public function test_artist_and_news_strings_come_back_in_persian() {
		$this->load_catalogue( 'wavira-core', 'wavira-core/languages/fa_IR.mo' );

		$this->assertSame( 'اینستاگرام', __( 'Instagram', 'wavira-core' ) );
		$this->assertSame( 'آپارات', __( 'Aparat', 'wavira-core' ) );
		$this->assertSame( 'تک‌آهنگ‌ها', __( 'Singles', 'wavira-core' ) );
		$this->assertSame( 'آثار', __( 'Works', 'wavira-core' ) );
	}

	/**
	 * The player payload (theme and engine strings) is Persian.
	 *
	 * @return void
	 */
	public function test_player_strings_come_back_in_persian() {
		$this->load_catalogue( 'wavira-core', 'wavira-core/languages/fa_IR.mo' );

		$this->assertSame( 'پخش', __( 'Play', 'wavira-core' ) );
		$this->assertSame( 'پخش تصادفی', __( 'Shuffle', 'wavira-core' ) );
		$this->assertSame( 'حالت تکرار', __( 'Repeat mode', 'wavira-core' ) );
		$this->assertSame( 'این قطعه پخش نشد.', __( 'This track could not be played.', 'wavira-core' ) );
	}

	/**
	 * Admin-facing strings — settings screens and content labels — are Persian.
	 *
	 * @return void
	 */
	public function test_admin_strings_come_back_in_persian() {
		$this->load_catalogue( 'wavira-core', 'wavira-core/languages/fa_IR.mo' );

		$this->assertSame( 'تنظیمات واویرا', __( 'Wavira settings', 'wavira-core' ) );
		$this->assertSame( 'تعداد قطعه در هر صفحهٔ آرشیو', __( 'Tracks per archive page', 'wavira-core' ) );
		$this->assertSame( 'قطعه‌ها', __( 'Tracks', 'wavira-core' ) );
		$this->assertSame( 'موزیکویدیوها', __( 'Music videos', 'wavira-core' ) );
		$this->assertSame( 'زندگینامهٔ نمونه. آن را با محتوای واقعی جایگزین کنید.', __( 'Generated demo biography. Replace with real content.', 'wavira-core' ) );
	}

	/**
	 * Placeholders survive translation, so `sprintf()` keeps working.
	 *
	 * @return void
	 */
	public function test_placeholders_survive_translation() {
		$this->load_catalogue( 'wavira-core', 'wavira-core/languages/fa_IR.mo' );

		$this->assertSame( '320 کیلوبیت‌برثانیه', sprintf( __( '%d kbps', 'wavira-core' ), 320 ) );
		$this->assertSame( 'قطعهٔ 2 از 9', sprintf( __( 'Track %1$d of %2$d', 'wavira-core' ), 2, 9 ) );
		$this->assertSame( 'آرشیو نمونهٔ قطعه', sprintf( __( '%s archive', 'wavira-core' ), 'نمونهٔ قطعه' ) );

		$this->load_catalogue( 'wavira', 'wavira/languages/fa_IR.mo' );

		$this->assertSame( 'باز کردن قطعهٔ نمونه', sprintf( __( 'Open %s', 'wavira' ), 'قطعهٔ نمونه' ) );
	}

	/**
	 * The strings 0.15.0 adds — downloads and the photo gallery — are Persian.
	 *
	 * @return void
	 */
	public function test_download_and_gallery_strings_come_back_in_persian() {
		$this->load_catalogue( 'wavira', 'wavira/languages/fa_IR.mo' );

		$this->assertSame( 'دانلود قطعه', __( 'Download the track', 'wavira' ) );
		$this->assertSame( 'گالری تصاویر', _x( 'Photo gallery', 'block title', 'wavira' ) );
		$this->assertSame( 'تصاویر', __( 'Photos', 'wavira' ) );
		$this->assertSame( '3.2 مگابایت', sprintf( __( '%s MB', 'wavira' ), '3.2' ) );

		$this->load_catalogue( 'wavira-core', 'wavira-core/languages/fa_IR.mo' );

		$this->assertSame( 'فایل قابل دانلودی برای این نوشته موجود نیست.', __( 'There is no downloadable file for this post.', 'wavira-core' ) );
		$this->assertSame( '720 پیکسل', sprintf( __( '%d pixels', 'wavira-core' ), 720 ) );
	}

	/**
	 * A Persian plural keeps its نیم‌فاصله.
	 *
	 * Persian separates the suffix «ها» from a stem that joins forward with a
	 * U+200C, and leaves it attached after the seven letters that never join
	 * forward at all (ا، د، ذ، ر، ز، ژ، و): «پیوندها» and «تصویرها» are right,
	 * «قطعهها» is not. Twenty-five translations joined the two in 0.15.0 — three
	 * of them were literals in this very file, which is how a test ends up
	 * defending a defect. The literals are corrected above; this reads every
	 * translation in both catalogues so the next one is caught here too, before
	 * it reaches a page.
	 *
	 * @return void
	 */
	public function test_persian_plurals_keep_their_nim_fasele() {
		// The letters that join the letter after them, and the suffix that has to
		// be separated from them: written as characters, because a single-quoted
		// PHP string does not read \u escapes.
		$joins_forward = 'بپتثجچحخسشصضطظعغفقکگلمنهی';
		$stuck         = '/[' . $joins_forward . ']ها(?:ی(?:مان|تان|شان|م|ت|ش)?)?(?![\x{0621}-\x{06cc}])/u';


		foreach ( array( 'wavira/languages/fa_IR.po', 'wavira-core/languages/fa_IR.po' ) as $file ) {
			$lines = file( dirname( __DIR__ ) . '/' . $file, FILE_IGNORE_NEW_LINES );

			$this->assertNotFalse( $lines, "{$file} must be readable" );

			foreach ( $lines as $number => $line ) {
				if ( 0 !== strpos( $line, 'msgstr' ) ) {
					continue;
				}

				$this->assertSame(
					0,
					preg_match( $stuck, $line ),
					sprintf( '%s:%d writes a plural without a نیم‌فاصله: %s', $file, $number + 1, $line )
				);
			}
		}
	}
}
