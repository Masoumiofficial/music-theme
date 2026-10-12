<?php
/**
 * The Jalali (Solar Hijri / Shamsi) calendar, as arithmetic.
 *
 * Persian sites publish dates in the Jalali calendar — a news card that says
 * "January 5, 2026" is a foreign artifact in a Persian music site, and the
 * Iranian market expects Shamsi dates everywhere. WordPress ships Gregorian
 * locale data only, so the conversion has to live here.
 *
 * This class is **pure arithmetic**: a PHP port of the Borkowski algorithm as
 * published in [jalaali-js](https://github.com/jalaali/jalaali-js) (MIT, © Behrang
 * Norouzinia and contributors), which maps Gregorian ↔ Jalali through a Julian day
 * number over the table of 33-year cycle breakpoints — the same arithmetic the
 * Iranian official calendar uses, not an astronomical estimate. Attribution and
 * licence: `THIRD-PARTY-NOTICES.md`.
 *
 * It contains no astronomy, no external call, no locale data file and no
 * dependency, so it can be unit tested with fixed anchor dates — which is the
 * condition ADR 0015 §8 set for shipping a calendar at all ("a Jalali layer is a
 * separate, tested change").
 *
 * Scope: years 1178–1633 Jalali (1799–2256 Gregorian), the range the algorithm is
 * accurate for; outside it the class clamps rather than inventing a date.
 *
 * @package Wavira\Core\Content
 */

namespace Wavira\Core\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Class Jalali
 */
final class Jalali {

	/**
	 * Jalali month names, Farvardin first.
	 *
	 * These are calendar data, not interface text: they are the same in every
	 * language (a Persian date is written with Persian month names), so they are
	 * constants rather than `__()` calls, exactly like a date format string.
	 *
	 * @var string[]
	 */
	public const MONTHS = array(
		1  => 'فروردین',
		2  => 'اردیبهشت',
		3  => 'خرداد',
		4  => 'تیر',
		5  => 'مرداد',
		6  => 'شهریور',
		7  => 'مهر',
		8  => 'آبان',
		9  => 'آذر',
		10 => 'دی',
		11 => 'بهمن',
		12 => 'اسفند',
	);

	/**
	 * Weekday names, Saturday first (the Iranian week starts on Saturday).
	 *
	 * @var string[]
	 */
	public const WEEKDAYS = array( 'شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه' );

	/**
	 * Year-cycle breakpoints of the 33-year leap rule.
	 *
	 * @var int[]
	 */
	private const BREAKS = array(
		-61,
		9,
		38,
		199,
		426,
		686,
		756,
		818,
		1111,
		1181,
		1210,
		1635,
		2060,
		2097,
		2192,
		2262,
		2324,
		2394,
		2456,
		3178,
	);

	/**
	 * Lowest Jalali year the algorithm is accurate for.
	 */
	public const MIN_YEAR = 1178;

	/**
	 * Highest Jalali year the algorithm is accurate for.
	 */
	public const MAX_YEAR = 1633;

	/**
	 * Convert a Gregorian date to Jalali.
	 *
	 * @param int $gy Gregorian year.
	 * @param int $gm Gregorian month (1–12).
	 * @param int $gd Gregorian day (1–31).
	 * @return array{year: int, month: int, day: int}
	 */
	public static function from_gregorian( int $gy, int $gm, int $gd ): array {
		return self::from_julian_day( self::gregorian_to_julian_day( $gy, $gm, $gd ) );
	}

	/**
	 * Convert a Jalali date to Gregorian.
	 *
	 * @param int $jy Jalali year.
	 * @param int $jm Jalali month (1–12).
	 * @param int $jd Jalali day (1–31).
	 * @return array{year: int, month: int, day: int}
	 */
	public static function to_gregorian( int $jy, int $jm, int $jd ): array {
		return self::julian_day_to_gregorian( self::jalali_to_julian_day( $jy, $jm, $jd ) );
	}

	/**
	 * Whether a Jalali year is a leap year (Esfand has 30 days).
	 *
	 * @param int $jy Jalali year.
	 * @return bool
	 */
	public static function is_leap_year( int $jy ): bool {
		if ( $jy < self::MIN_YEAR || $jy > self::MAX_YEAR ) {
			return false;
		}

		return 0 === self::calendar( $jy )['leap'];
	}

	/**
	 * Number of days in a Jalali month.
	 *
	 * @param int $jy Jalali year.
	 * @param int $jm Jalali month (1–12).
	 * @return int
	 */
	public static function days_in_month( int $jy, int $jm ): int {
		if ( $jm < 1 || $jm > 12 ) {
			return 0;
		}

		if ( $jm <= 6 ) {
			return 31;
		}

		if ( $jm <= 11 ) {
			return 30;
		}

		return self::is_leap_year( $jy ) ? 30 : 29;
	}

	/**
	 * Name of a Jalali month.
	 *
	 * @param int $jm Jalali month (1–12).
	 * @return string Empty string for an impossible month.
	 */
	public static function month_name( int $jm ): string {
		return self::MONTHS[ $jm ] ?? '';
	}

	/**
	 * Name of the weekday of a Gregorian date, Saturday first.
	 *
	 * @param int $gy Gregorian year.
	 * @param int $gm Gregorian month.
	 * @param int $gd Gregorian day.
	 * @return string
	 */
	public static function weekday_name( int $gy, int $gm, int $gd ): string {
		// The Julian day number of 1 January 1 is 1721426, and that day was a
		// Monday; `+ 2` moves the index so 0 is Saturday.
		$index = ( self::gregorian_to_julian_day( $gy, $gm, $gd ) + 2 ) % 7;

		return self::WEEKDAYS[ (int) $index ];
	}

	/**
	 * A Jalali date formatted with a small, documented token set.
	 *
	 * Tokens: `Y` (year, four digits), `y` (year, two digits), `n` (month number),
	 * `m` (month, zero-padded), `F` (month name), `j` (day), `d` (day,
	 * zero-padded), `l` (weekday name), and `\` to escape a literal character.
	 * The format is calendar data, so it is passed through as written.
	 *
	 * @param int    $gy     Gregorian year.
	 * @param int    $gm     Gregorian month.
	 * @param int    $gd     Gregorian day.
	 * @param string $format Format string (default `j F Y`).
	 * @return string
	 */
	public static function format( int $gy, int $gm, int $gd, string $format = 'j F Y' ): string {
		$date = self::from_gregorian( $gy, $gm, $gd );

		$tokens = array(
			'Y' => (string) $date['year'],
			'y' => substr( (string) $date['year'], -2 ),
			'n' => (string) $date['month'],
			'm' => str_pad( (string) $date['month'], 2, '0', STR_PAD_LEFT ),
			'F' => self::month_name( $date['month'] ),
			'j' => (string) $date['day'],
			'd' => str_pad( (string) $date['day'], 2, '0', STR_PAD_LEFT ),
			'l' => self::weekday_name( $gy, $gm, $gd ),
		);

		$out     = '';
		$escaped = false;
		$length  = strlen( $format );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $format[ $i ];

			if ( $escaped ) {
				$out    .= $char;
				$escaped = false;
				continue;
			}

			if ( '\\' === $char ) {
				$escaped = true;
				continue;
			}

			$out .= $tokens[ $char ] ?? $char;
		}

		return $out . ( $escaped ? '\\' : '' );
	}

	/**
	 * Gregorian date → Julian day number.
	 *
	 * @param int $gy Year.
	 * @param int $gm Month.
	 * @param int $gd Day.
	 * @return int
	 */
	private static function gregorian_to_julian_day( int $gy, int $gm, int $gd ): int {
		$day = self::div( ( $gy + self::div( $gm - 8, 6 ) + 100100 ) * 1461, 4 )
			+ self::div( 153 * self::mod( $gm + 9, 12 ) + 2, 5 )
			+ $gd - 34840408;

		return $day - self::div( self::div( $gy + 100100 + self::div( $gm - 8, 6 ), 100 ) * 3, 4 ) + 752;
	}

	/**
	 * Julian day number → Gregorian date.
	 *
	 * @param int $jdn Julian day number.
	 * @return array{year: int, month: int, day: int}
	 */
	private static function julian_day_to_gregorian( int $jdn ): array {
		$j = 4 * $jdn + 139361631;
		$j = $j + self::div( self::div( 4 * $jdn + 183187720, 146097 ) * 3, 4 ) * 4 - 3908;

		$i = self::div( self::mod( $j, 1461 ), 4 ) * 5 + 308;

		$gd = self::div( self::mod( $i, 153 ), 5 ) + 1;
		$gm = self::mod( self::div( $i, 153 ), 12 ) + 1;
		$gy = self::div( $j, 1461 ) - 100100 + self::div( 8 - $gm, 6 );

		return array(
			'year'  => (int) $gy,
			'month' => (int) $gm,
			'day'   => (int) $gd,
		);
	}

	/**
	 * Jalali date → Julian day number.
	 *
	 * @param int $jy Year.
	 * @param int $jm Month.
	 * @param int $jd Day.
	 * @return int
	 */
	private static function jalali_to_julian_day( int $jy, int $jm, int $jd ): int {
		$calendar = self::calendar( $jy );

		return self::gregorian_to_julian_day( $calendar['gy'], 3, $calendar['march'] )
			+ ( $jm - 1 ) * 31 - self::div( $jm, 7 ) * ( $jm - 7 ) + $jd - 1;
	}

	/**
	 * Julian day number → Jalali date, clamped to the accurate range.
	 *
	 * The clamp is decided on the day number, not on a year read out of the
	 * algorithm: the algorithm walks back to the previous Jalali year when a
	 * date precedes that year's Nowruz, so an input before the floor used to
	 * come back as `MIN_YEAR - 1` — a year outside the table, presented as if it
	 * were a real conversion (CI found it: 1500-01-01 → 1177/10/11). Input
	 * outside the range now returns the exact first or last day the table
	 * describes.
	 *
	 * @param int $jdn Julian day number.
	 * @return array{year: int, month: int, day: int}
	 */
	private static function from_julian_day( int $jdn ): array {
		$first = self::jalali_to_julian_day( self::MIN_YEAR, 1, 1 );

		if ( $jdn < $first ) {
			return array(
				'year'  => self::MIN_YEAR,
				'month' => 1,
				'day'   => 1,
			);
		}

		$last_month = self::days_in_month( self::MAX_YEAR, 12 );
		$last       = self::jalali_to_julian_day( self::MAX_YEAR, 12, $last_month );

		if ( $jdn > $last ) {
			return array(
				'year'  => self::MAX_YEAR,
				'month' => 12,
				'day'   => $last_month,
			);
		}

		$gregorian = self::julian_day_to_gregorian( $jdn );
		$jy        = $gregorian['year'] - 621;

		$calendar = self::calendar( $jy );
		$new_year = self::gregorian_to_julian_day( $gregorian['year'], 3, $calendar['march'] );
		$offset   = $jdn - $new_year;

		if ( $offset >= 0 ) {
			if ( $offset <= 185 ) {
				return array(
					'year'  => $jy,
					'month' => (int) ( 1 + self::div( $offset, 31 ) ),
					'day'   => (int) ( self::mod( $offset, 31 ) + 1 ),
				);
			}

			$offset -= 186;
		} else {
			// Inside the range, so the previous year is in it too.
			--$jy;
			$offset += 179;

			if ( 1 === $calendar['leap'] ) {
				++$offset;
			}
		}

		return array(
			'year'  => $jy,
			'month' => (int) ( 7 + self::div( $offset, 30 ) ),
			'day'   => (int) ( self::mod( $offset, 30 ) + 1 ),
		);
	}

	/**
	 * The 33-year cycle data of a Jalali year: leap state, Gregorian year and the
	 * March day on which the Jalali year starts.
	 *
	 * @param int $jy Jalali year.
	 * @return array{leap: int, gy: int, march: int}
	 */
	private static function calendar( int $jy ): array {
		$gy     = $jy + 621;
		$leap_j = -14;
		$jp     = self::BREAKS[0];
		$jump   = 0;

		foreach ( self::BREAKS as $index => $jm ) {
			if ( 0 === $index ) {
				continue;
			}

			$jump = $jm - $jp;

			if ( $jy < $jm ) {
				break;
			}

			$leap_j += self::div( $jump, 33 ) * 8 + self::div( self::mod( $jump, 33 ), 4 );
			$jp      = $jm;
		}

		$n       = $jy - $jp;
		$leap_j += self::div( $n, 33 ) * 8 + self::div( self::mod( $n, 33 ) + 3, 4 );

		if ( 4 === self::mod( $jump, 33 ) && 4 === $jump - $n ) {
			++$leap_j;
		}

		$leap_g = self::div( $gy, 4 ) - self::div( ( self::div( $gy, 100 ) + 1 ) * 3, 4 ) - 150;
		$march  = 20 + $leap_j - $leap_g;

		if ( $jump - $n < 6 ) {
			$n = $n - $jump + self::div( $jump + 4, 33 ) * 33;
		}

		$leap = self::mod( self::mod( $n + 1, 33 ) - 1, 4 );

		if ( -1 === $leap ) {
			$leap = 4;
		}

		return array(
			'leap'  => (int) $leap,
			'gy'    => (int) $gy,
			'march' => (int) $march,
		);
	}

	/**
	 * Integer division truncated toward zero (the arithmetic the algorithm is
	 * written in; it is not floor division).
	 *
	 * @param int $a Dividend.
	 * @param int $b Divisor.
	 * @return int
	 */
	private static function div( int $a, int $b ): int {
		return (int) intdiv( $a, $b );
	}

	/**
	 * Remainder matching `div()` above.
	 *
	 * @param int $a Dividend.
	 * @param int $b Divisor.
	 * @return int
	 */
	private static function mod( int $a, int $b ): int {
		return $a - (int) intdiv( $a, $b ) * $b;
	}
}
