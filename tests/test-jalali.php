<?php
/**
 * Runtime verification of the Persian calendar and the date policy (phase 0.10.1).
 *
 * ADR 0015 §8 made this change conditional on verifiable anchors: a calendar
 * conversion that is off by one day puts a wrong date on every card of a Persian
 * site. The tests below pin the conversions against dates that are matters of
 * public record (Nowruz, the Islamic Revolution), pin the leap-year rule, and
 * then assert the policy: which locales get Jalali, which formats are never
 * touched, and that a site can switch the whole thing off.
 *
 * @package Wavira\Tests
 */

use Wavira\Core\Content\Dates;
use Wavira\Core\Content\Jalali;

/**
 * Class Test_Jalali
 */
class Test_Jalali extends Wavira_Test_Case {

	/**
	 * Define the theme constants when another suite has not.
	 *
	 * @return void
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		if ( ! defined( 'WAVIRA_THEME_DIR' ) ) {
			define( 'WAVIRA_THEME_DIR', trailingslashit( dirname( __DIR__ ) . '/wavira' ) );
			define( 'WAVIRA_THEME_URI', 'https://example.test/wp-content/themes/wavira/' );
			define( 'WAVIRA_THEME_VERSION', '0.10.1-test' );
		}
	}

	/**
	 * Restore the locale, the filters and the query flags after every test.
	 *
	 * The locale is faked with the `locale` filter rather than
	 * `switch_to_locale()`: the filter is what `get_locale()` reads, it needs no
	 * translation file on disk, and it cannot leak a locale into the next test.
	 *
	 * @return void
	 */
	public function tear_down() {
		remove_all_filters( 'locale' );
		remove_all_filters( 'wp_date' );
		remove_all_filters( 'get_the_time' );
		remove_all_filters( 'get_the_modified_time' );
		remove_all_filters( 'get_the_date' );
		remove_all_filters( 'get_the_modified_date' );
		remove_all_filters( 'wavira_core_date_style' );
		remove_all_filters( 'wavira_core_date_format' );

		unset( $GLOBALS['current_screen'] );

		$GLOBALS['wp_query']->is_feed   = false;
		$GLOBALS['wp_query']->is_robots = false;

		parent::tear_down();
	}

	/**
	 * Pretend the site language is Persian.
	 *
	 * @return void
	 */
	private function pretend_persian(): void {
		add_filter( 'locale', static function () {
			return 'fa_IR';
		} );
	}

	/**
	 * Pretend the site language is English (the suite default).
	 *
	 * @return void
	 */
	private function pretend_english(): void {
		remove_all_filters( 'locale' );
	}

	/**
	 * Convert a Gregorian date to a `jy/jm/jd` string.
	 *
	 * @param string $gregorian `YYYY-MM-DD`.
	 * @return string
	 */
	private function jalali_of( string $gregorian ): string {
		list( $gy, $gm, $gd ) = array_map( 'intval', explode( '-', $gregorian ) );
		$date                 = Jalali::from_gregorian( $gy, $gm, $gd );

		return $date['year'] . '/' . $date['month'] . '/' . $date['day'];
	}

	/**
	 * The conversions a Persian site depends on.
	 *
	 * @return void
	 */
	public function test_anchor_dates_convert_correctly() {
		// Nowruz, the Iranian new year, for six consecutive years.
		$this->assertSame( '1400/1/1', $this->jalali_of( '2021-03-21' ) );
		$this->assertSame( '1401/1/1', $this->jalali_of( '2022-03-21' ) );
		$this->assertSame( '1402/1/1', $this->jalali_of( '2023-03-21' ) );
		$this->assertSame( '1403/1/1', $this->jalali_of( '2024-03-20' ) );

		// 1403 is leap: it ends on 30 Esfand, and 1404 starts one day later.
		$this->assertSame( '1403/12/30', $this->jalali_of( '2025-03-20' ) );
		$this->assertSame( '1404/1/1', $this->jalali_of( '2025-03-21' ) );
		$this->assertSame( '1405/1/1', $this->jalali_of( '2026-03-21' ) );

		// Public-record dates and the arithmetic of a mid-year date.
		$this->assertSame( '1357/11/22', $this->jalali_of( '1979-02-11' ), 'the Islamic Revolution' );
		$this->assertSame( '1378/10/11', $this->jalali_of( '2000-01-01' ) );
		$this->assertSame( '1404/10/15', $this->jalali_of( '2026-01-05' ) );
		$this->assertSame( '1405/7/13', $this->jalali_of( '2026-10-05' ) );
	}

	/**
	 * Every day of forty years survives a round trip.
	 *
	 * A single off-by-one in the month arithmetic only shows up in the second
	 * half of the year, which is why this walks the whole span instead of
	 * sampling: the first version of this class had exactly that bug.
	 *
	 * @return void
	 */
	public function test_round_trip_holds_across_forty_years() {
		$checked = 0;
		$time    = (int) strtotime( '1979-01-01 00:00:00 UTC' );

		for ( $day = 0; $day < 14610; $day++ ) {
			$stamp     = $time + ( $day * DAY_IN_SECONDS );
			$gregorian = array(
				(int) gmdate( 'Y', $stamp ),
				(int) gmdate( 'n', $stamp ),
				(int) gmdate( 'j', $stamp ),
			);

			$jalali = Jalali::from_gregorian( $gregorian[0], $gregorian[1], $gregorian[2] );
			$back   = Jalali::to_gregorian( $jalali['year'], $jalali['month'], $jalali['day'] );

			if ( $back['year'] !== $gregorian[0] || $back['month'] !== $gregorian[1] || $back['day'] !== $gregorian[2] ) {
				$this->fail( sprintf( 'round trip broke on %s', gmdate( 'Y-m-d', $stamp ) ) );
			}

			$checked++;
		}

		$this->assertSame( 14610, $checked );
	}

	/**
	 * The leap-year rule and the month lengths follow the official calendar.
	 *
	 * @return void
	 */
	public function test_leap_years_and_month_lengths() {
		foreach ( array( 1399, 1403, 1408 ) as $leap ) {
			$this->assertTrue( Jalali::is_leap_year( $leap ), $leap . ' is a leap year' );
			$this->assertSame( 30, Jalali::days_in_month( $leap, 12 ) );
		}

		foreach ( array( 1400, 1401, 1402, 1404, 1405 ) as $common ) {
			$this->assertFalse( Jalali::is_leap_year( $common ), $common . ' is not a leap year' );
			$this->assertSame( 29, Jalali::days_in_month( $common, 12 ) );
		}

		$this->assertSame( 31, Jalali::days_in_month( 1404, 1 ) );
		$this->assertSame( 31, Jalali::days_in_month( 1404, 6 ) );
		$this->assertSame( 30, Jalali::days_in_month( 1404, 7 ) );
		$this->assertSame( 30, Jalali::days_in_month( 1404, 11 ) );
		$this->assertSame( 0, Jalali::days_in_month( 1404, 13 ) );
	}

	/**
	 * Month and weekday names, in the Iranian week order.
	 *
	 * @return void
	 */
	public function test_names_and_weekday_order() {
		$this->assertSame( 'فروردین', Jalali::month_name( 1 ) );
		$this->assertSame( 'اسفند', Jalali::month_name( 12 ) );
		$this->assertSame( '', Jalali::month_name( 13 ) );

		$this->assertSame( 'شنبه', Jalali::weekday_name( 2026, 3, 21 ), 'Nowruz 1405 is a Saturday' );
		$this->assertSame( 'جمعه', Jalali::weekday_name( 2025, 3, 21 ) );
		$this->assertSame( 'دوشنبه', Jalali::weekday_name( 2026, 10, 5 ) );
		$this->assertSame( 'چهارشنبه', Jalali::weekday_name( 2024, 3, 20 ) );
	}

	/**
	 * The format token set, and the escaping rule.
	 *
	 * @return void
	 */
	public function test_format_tokens() {
		$this->assertSame( '13 مهر 1405', Jalali::format( 2026, 10, 5 ) );
		$this->assertSame( '13 مهر 1405', Jalali::format( 2026, 10, 5, 'j F Y' ) );
		$this->assertSame( '1405/07/13', Jalali::format( 2026, 10, 5, 'Y/m/d' ) );
		$this->assertSame( 'دوشنبه 13 مهر', Jalali::format( 2026, 10, 5, 'l j F' ) );
		$this->assertSame( '13Y', Jalali::format( 2026, 10, 5, 'd\Y' ), 'a backslash escapes the next character' );
		$this->assertSame( '1405', Jalali::format( 2026, 10, 5, 'Y' ) );
		$this->assertSame( '13\\', Jalali::format( 2026, 10, 5, 'd\\' ), 'a trailing escape is kept as written' );
		$this->assertSame( 'd', Jalali::format( 2026, 10, 5, '\\d' ), 'an escaped token prints literally' );
	}

	/**
	 * Out-of-range input degrades instead of inventing a date.
	 *
	 * @return void
	 */
	public function test_out_of_range_input_is_clamped() {
		$before = Jalali::from_gregorian( 1500, 1, 1 );
		$after  = Jalali::from_gregorian( 2500, 1, 1 );

		$this->assertSame( Jalali::MIN_YEAR, $before['year'], 'the year floor is honoured' );
		$this->assertSame( Jalali::MAX_YEAR, $after['year'], 'the year ceiling is honoured' );

		// The clamp lands on the boundary itself, not on the year before it: the
		// algorithm walks back one year when a date precedes that year's Nowruz,
		// so clamping the year alone answered MIN_YEAR - 1 (CI: 1500-01-01 →
		// 1177/10/11).
		$this->assertSame(
			array( 'year' => Jalali::MIN_YEAR, 'month' => 1, 'day' => 1 ),
			$before,
			'the floor is the first day of the first accurate year'
		);
		$this->assertSame( 12, $after['month'], 'the ceiling is in Esfand' );
		$this->assertSame(
			Jalali::days_in_month( Jalali::MAX_YEAR, 12 ),
			$after['day'],
			'the ceiling is the last day of the last accurate year'
		);

		$this->assertFalse( Jalali::is_leap_year( 900 ), 'a year outside the table is not a leap year' );
		$this->assertSame( 29, Jalali::days_in_month( 900, 12 ) );
	}

	/**
	 * The policy: Persian locale → Jalali, everything else → Gregorian.
	 *
	 * @return void
	 */
	public function test_style_follows_the_locale_and_the_filter() {
		$this->assertFalse( Dates::locale_is_persian(), 'the test site runs in English' );
		$this->assertSame( 'gregorian', Dates::style() );
		$this->assertFalse( Dates::uses_jalali() );

		$this->pretend_persian();

		$this->assertTrue( Dates::locale_is_persian() );
		$this->assertSame( 'jalali', Dates::style(), 'a Persian site gets the Persian calendar' );
		$this->assertTrue( Dates::uses_jalali() );

		add_filter( 'wavira_core_date_style', static function () {
			return 'gregorian';
		} );

		$this->assertSame( 'gregorian', Dates::style(), 'a site running another Jalali plugin steps aside' );

		remove_all_filters( 'wavira_core_date_style' );
		$this->pretend_english();

		$this->assertSame( 'gregorian', Dates::style() );
	}

	/**
	 * Labels: Persian numerals in the Jalali style, the site format otherwise.
	 *
	 * @return void
	 */
	public function test_labels_in_both_styles() {
		$stamp = (int) strtotime( '2026-10-05 10:00:00' );

		$this->assertSame( (string) wp_date( (string) get_option( 'date_format' ), $stamp ), Dates::label( $stamp ) );

		$this->pretend_persian();

		$this->assertSame( '۱۳ مهر ۱۴۰۵', Dates::label( $stamp ), 'Persian numerals and month name' );

		add_filter( 'wavira_core_date_format', static function () {
			return 'l j F Y';
		} );

		$this->assertSame( 'دوشنبه ۱۳ مهر ۱۴۰۵', Dates::label( $stamp ) );

		remove_all_filters( 'wavira_core_date_format' );

		$this->assertSame( '', Dates::label( 0 ), 'no timestamp, no label' );
		$this->assertSame( '', Dates::label( -5 ), 'a negative timestamp is not a date' );

		$this->pretend_english();
	}

	/**
	 * Persian numerals reach the interface, and only a Persian site gets them.
	 *
	 * @return void
	 */
	public function test_digits_are_persian_on_a_persian_site() {
		$this->assertSame( '2026', Dates::digits( '2026' ), 'an English site keeps Latin digits' );

		$this->pretend_persian();

		$this->assertSame( '۱۴۰۵', Dates::digits( '1405' ) );
		$this->assertSame( '۲۴۰ / ۳۲۰', Dates::digits( '240 / 320' ) );

		$this->pretend_english();
	}

	/**
	 * Numbers a Persian page shows are Persian; machine output is not.
	 *
	 * The duration, the bitrate matrix and the artist counts all read from the
	 * same helper, so a Persian site never mixes numeral systems on one page.
	 *
	 * @return void
	 */
	public function test_numeric_output_follows_the_locale() {
		$post = self::factory()->post->create(
			array(
				'post_type'   => \Wavira\Core\Content\PostTypes::TRACK,
				'post_status' => 'publish',
			)
		);

		update_post_meta( $post, \Wavira\Core\Content\MetaSchema::DURATION, 214 );

		$this->assertSame( '3:34', \Wavira\Core\Content\MetaValues::duration_label( $post ), 'an English site keeps Latin digits' );
		$this->assertSame( '12', wavira_core_digits( 12 ) );

		$this->pretend_persian();

		$this->assertSame( '۳:۳۴', \Wavira\Core\Content\MetaValues::duration_label( $post ) );
		$this->assertSame( '۱۲', wavira_core_digits( 12 ) );
		$this->assertSame( '۲۴۰', wavira_core_digits( '240' ) );

		$this->pretend_english();
	}

	/**
	 * Display formats convert; machine formats and time formats never do.
	 *
	 * @return void
	 */
	public function test_display_formats_convert_but_data_formats_do_not() {
		$this->pretend_persian();

		$stamp = (int) strtotime( '2026-10-05 10:00:00' );

		// Display formats: converted.
		$this->assertSame( '۱۳ مهر ۱۴۰۵', Dates::filter_wp_date( 'October 5, 2026', 'F j, Y', $stamp ) );
		$this->assertSame( '۱۳ مهر ۱۴۰۵', Dates::filter_wp_date( '۱۳ مهر ۱۴۰۵', 'j F Y', $stamp ), 'idempotent' );

		// `wp_date( '' )` prints nothing in core, so there is no date to convert;
		// a filter must never turn an empty string into one. The `get_the_date()`
		// family resolves an empty format to its own option and is covered by the
		// wiring test below.
		$this->assertSame( '', Dates::filter_wp_date( '', '', $stamp ) );

		// Data formats: untouched, whatever the locale says. The expected value
		// is read from the same function a template would call, so the assertion
		// holds whether or not the class is wired into the running request.
		foreach ( array( 'c', 'U', 'r', 'Y-m-d', 'Ymd', 'Y-m-d H:i:s', 'Y-m-d\TH:i:sP', 'd/m/Y' ) as $format ) {
			$original = (string) wp_date( $format, $stamp );

			$this->assertSame( $original, Dates::filter_wp_date( $original, $format, $stamp ), $format . ' stays machine-readable' );
		}

		// A format with a time part would lose the time: left alone.
		$this->assertSame( '10:00', Dates::filter_wp_date( '10:00', 'H:i', $stamp ) );
		$this->assertSame( 'October 5, 2026 10:00 am', Dates::filter_wp_date( 'October 5, 2026 10:00 am', 'F j, Y g:i a', $stamp ) );

		// A format that names no date part at all is not a date. It has to be a
		// format without a single token letter — `l`, `n`, `d`, `F` and friends
		// are tokens, so a word like "hello" would be treated as a format.
		$this->assertSame( ' / ', Dates::filter_wp_date( ' / ', ' / ', $stamp ) );

		$this->pretend_english();
	}

	/**
	 * The admin, feeds and robots.txt keep Gregorian, whatever the locale.
	 *
	 * `is_admin()` reads the screen object core sets for the request, so the
	 * admin surface is exercised with a stand-in screen instead of loading the
	 * whole admin bootstrap. `REST_REQUEST` cannot be defined this late in a
	 * process, so the REST guard is locked in the source instead (below).
	 *
	 * @return void
	 */
	public function test_admin_feeds_and_robots_keep_gregorian() {
		$this->pretend_persian();

		$stamp = (int) strtotime( '2026-10-05 10:00:00' );

		$GLOBALS['current_screen'] = new class() {
			/**
			 * Report an admin screen, the way `WP_Screen` does.
			 *
			 * @return bool
			 */
			public function in_admin() {
				return true;
			}
		};

		$this->assertTrue( is_admin() );
		$this->assertSame( 'October 5, 2026', Dates::filter_wp_date( 'October 5, 2026', 'F j, Y', $stamp ) );

		unset( $GLOBALS['current_screen'] );

		$this->assertFalse( is_admin() );

		$GLOBALS['wp_query']->is_feed = true;

		$this->assertTrue( is_feed() );
		$this->assertSame( 'October 5, 2026', Dates::filter_wp_date( 'October 5, 2026', 'F j, Y', $stamp ) );

		$GLOBALS['wp_query']->is_feed   = false;
		$GLOBALS['wp_query']->is_robots = true;

		$this->assertTrue( is_robots() );
		$this->assertSame( 'October 5, 2026', Dates::filter_wp_date( 'October 5, 2026', 'F j, Y', $stamp ) );

		$GLOBALS['wp_query']->is_robots = false;

		// Back on the front end, the same call converts.
		$this->assertSame( '۱۳ مهر ۱۴۰۵', Dates::filter_wp_date( 'October 5, 2026', 'F j, Y', $stamp ) );

		$this->pretend_english();
	}

	/**
	 * The registered filters convert the front end and leave the machine surfaces.
	 *
	 * The class is registered inside the test rather than relied upon from the
	 * boot sequence: the core test library restores `$wp_filter` after every test
	 * (`_backup_hooks()` behind a static flag), so a hook added by a file loaded
	 * mid-suite cannot be observed from here.
	 *
	 * @return void
	 */
	public function test_registered_filters_localise_the_front_end() {
		$this->pretend_persian();

		Dates::register();

		$stamp = (int) strtotime( '2026-10-05 10:00:00' );
		$post  = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_date'   => '2026-10-05 10:00:00',
			)
		);

		$this->assertSame( '۱۳ مهر ۱۴۰۵', wp_date( 'F j, Y', $stamp ), 'core blocks that render through wp_date() are localised' );
		$this->assertSame( '2026-10-05', wp_date( 'Y-m-d', $stamp ), 'a machine format survives filtering' );
		$this->assertSame( '۱۳ مهر ۱۴۰۵', get_the_date( '', $post ) );
		$this->assertSame( '۱۳ مهر ۱۴۰۵', get_the_date( 'F j, Y', $post ) );
		$this->assertSame( '۱۳ مهر ۱۴۰۵', get_the_modified_date( '', $post ) );
		$this->assertSame( '۱۳ مهر ۱۴۰۵', get_the_time( 'F j, Y', $post ), 'a date format converts on any of the four filters' );

		// A time format has no Jalali equivalent, so it keeps the site's own output.
		$this->assertSame(
			(string) wp_date( (string) get_option( 'time_format' ), $stamp ),
			get_the_time( '', $post )
		);
		$this->assertStringNotContainsString( '۰', get_the_time( '', $post ), 'a time never carries Persian numerals' );

		$this->pretend_english();
	}

	/**
	 * The conversion is re-entrancy safe.
	 *
	 * `get_the_date()` reaches this class twice — through `wp_date()` inside
	 * `get_post_time()`, then through the `get_the_date` filter itself — so an
	 * unguarded conversion recurses until the process dies. CI caught exactly
	 * that: both integration jobs hung on the first Persian request. A nested call
	 * must keep WordPress's own output instead.
	 *
	 * @return void
	 */
	public function test_conversion_does_not_recurse() {
		$this->pretend_persian();

		Dates::register();

		$stamp = (int) strtotime( '2026-10-05 10:00:00' );

		// `wavira_core_date_label` is the documented extension point, so a filter
		// there observes the outermost nested call of a conversion.
		$nested = 'not called';
		$probe  = static function ( $label ) use ( &$nested ) {
			$nested = (string) wp_date( 'F j, Y', (int) strtotime( '2026-10-05 10:00:00' ) );

			return $label;
		};

		add_filter( 'wavira_core_date_label', $probe, 5 );

		$this->assertSame( '۱۳ مهر ۱۴۰۵', Dates::label( $stamp ), 'a direct call converts exactly once' );
		$this->assertStringContainsString( '2026', $nested, 'a nested call returns WordPress output' );
		$this->assertStringNotContainsString( '۱۴۰۵', $nested, 'a nested call is not converted again' );

		remove_filter( 'wavira_core_date_label', $probe, 5 );

		// The end-to-end path that hung the integration job: it returns at all.
		$post = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_date'   => '2026-10-05 10:00:00',
			)
		);

		$this->assertSame( '۱۳ مهر ۱۴۰۵', get_the_date( 'F j, Y', $post ) );
		$this->assertSame( '۱۳ مهر ۱۴۰۵', get_the_modified_date( 'F j, Y', $post ) );
		$this->assertStringNotContainsString( '۰', (string) get_the_time( '', $post ), 'an empty time format stays a time' );

		$this->pretend_english();
	}

	/**
	 * The payloads carry the localised label, and the public functions agree.
	 *
	 * @return void
	 */
	public function test_payloads_carry_the_localised_label() {
		$post = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Concert announced',
				'post_date'   => '2026-10-05 10:00:00',
			)
		);

		$this->pretend_persian();

		$items = wavira_core_news_feed( array( 'limit' => 1 ) );
		$item  = array_values(
			array_filter(
				$items,
				static function ( $row ) use ( $post ) {
					return (int) $row['id'] === (int) $post;
				}
			)
		);

		$this->assertNotSame( array(), $item );
		$this->assertSame( '۱۳ مهر ۱۴۰۵', $item[0]['date_label'], 'the news card speaks Persian' );

		// The public functions answer the same way for any template.
		$this->assertSame( '۱۵', wavira_core_digits( 15 ) );
		$this->assertSame( '۱۳ مهر ۱۴۰۵', wavira_core_date_label( (int) strtotime( '2026-10-05 10:00:00' ) ) );
		$this->assertSame( 'jalali', wavira_core_date_style() );

		$this->pretend_english();

		$again   = wavira_core_news_feed( array( 'limit' => 1 ) );
		$english = array_values(
			array_filter(
				$again,
				static function ( $row ) use ( $post ) {
					return (int) $row['id'] === (int) $post;
				}
			)
		);

		$this->assertNotSame( array(), $english );
		$this->assertStringContainsString( '2026', $english[0]['date_label'], 'an English site keeps Gregorian dates' );
		$this->assertSame( 'gregorian', wavira_core_date_style() );
	}

	/**
	 * The wiring a live request reads, locked in the source.
	 *
	 * Same rationale as the hook note above: `Dates::register()` is called from
	 * `ContentModule::register()` during `plugins_loaded`, which no test can
	 * observe through `$wp_filter`. The guards, the filters and the two call sites
	 * that fill the payloads are asserted where they live.
	 *
	 * @return void
	 */
	public function test_wiring_is_present_in_the_source() {
		$dates  = (string) file_get_contents( dirname( __DIR__ ) . '/wavira-core/src/Content/Dates.php' );
		$module = (string) file_get_contents( dirname( __DIR__ ) . '/wavira-core/src/Content/ContentModule.php' );
		$news   = (string) file_get_contents( dirname( __DIR__ ) . '/wavira-core/src/News/NewsFeed.php' );
		$artist = (string) file_get_contents( dirname( __DIR__ ) . '/wavira-core/src/Content/ArtistProfile.php' );

		// The one seam that also localises core's own post-date block.
		$this->assertStringContainsString( "add_filter( 'wp_date', array( __CLASS__, 'filter_wp_date' ), 10, 3 )", $dates );

		// The four post-date functions, so a theme template cannot bypass the policy.
		foreach ( array( 'get_the_time', 'get_the_modified_time', 'get_the_date', 'get_the_modified_date' ) as $filter ) {
			$this->assertStringContainsString( "add_filter( '" . $filter . "'", $dates, $filter . ' is wired' );
		}

		// Guards: never machine surfaces.
		foreach ( array( 'is_admin()', 'wp_doing_ajax()', 'wp_doing_cron()', 'is_feed()', 'is_robots()', "defined( 'REST_REQUEST' ) && REST_REQUEST" ) as $guard ) {
			$this->assertStringContainsString( $guard, $dates, $guard . ' is guarded' );
		}

		// The extension points, and the module wiring.
		$this->assertStringContainsString( "'wavira_core_date_style'", $dates );
		$this->assertStringContainsString( "'wavira_core_date_format'", $dates );
		$this->assertStringContainsString( "'wavira_core_date_label'", $dates );
		$this->assertStringContainsString( 'Dates::register();', $module );

		// Numbers a Persian page shows go through the same helper.
		$this->assertStringContainsString( 'Dates::digits(', (string) file_get_contents( dirname( __DIR__ ) . '/wavira-core/src/Content/MetaValues.php' ) );
		$this->assertStringContainsString( 'Dates::digits(', (string) file_get_contents( dirname( __DIR__ ) . '/wavira-core/src/Downloads/Access.php' ) );
		$this->assertStringContainsString( 'wavira_core_digits(', (string) file_get_contents( WAVIRA_THEME_DIR . 'inc/artists.php' ) );
		$this->assertStringContainsString( 'wavira_core_digits(', (string) file_get_contents( WAVIRA_THEME_DIR . 'inc/markup.php' ) );

		// Payloads read the label from the policy, not from raw core output.
		$this->assertStringContainsString( 'Dates::label_for_post( $post )', $news );
		$this->assertStringContainsString( 'Dates::label_for_post( $post )', $artist );
		$this->assertStringNotContainsString( 'get_the_date( \'\', $post )', $news, 'the news feed no longer prints raw core dates' );
	}
}
