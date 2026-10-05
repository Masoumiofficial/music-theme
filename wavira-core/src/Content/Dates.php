<?php
/**
 * Date presentation policy: which calendar the front end speaks.
 *
 * WordPress ships Gregorian locale data only. A Persian music site that prints
 * "January 5, 2026" is a foreign artifact — the Iranian market expects Shamsi
 * dates on the news cards, the artist pages and the archives. This class is the
 * one place that decides, and it decides with a switch:
 *
 * | Locale | Default style | Output |
 * | --- | --- | --- |
 * | `fa*` | `jalali` | «۱۳ مهر ۱۴۰۵», Persian numerals |
 * | anything else | `gregorian` | the site's own `date_format` |
 *
 * Detection is the site's locale, not a hard-coded assumption, and
 * `wavira_core_date_style` overrides it either way — a Persian site that already
 * runs another Jalali plugin sets it to `gregorian` and the product steps aside
 * (ADR 0017).
 *
 * Four rules keep the conversion from corrupting data:
 *
 * 1. **Machine formats are never converted** (`c`, `U`, `r`, `Y-m-d`, `Ymd`,
 *    `Y-m-d H:i:s`, `Y-m-d\TH:i:sP`). Structured data, `<time datetime>`, REST
 *    payloads and feeds must stay unambiguous.
 * 2. **Formats that carry a time are never converted**: a Jalali day replaces a
 *    whole string, so a mixed date+time string would lose its time. Those keep
 *    the locale data.
 * 3. **Admin, REST, AJAX, cron and feeds are never converted.** The editor, the
 *    REST API and a site's exports keep Gregorian, which every integration
 *    expects.
 * 4. **The conversion is idempotent**: it recomputes from the post's timestamp
 *    rather than parsing the incoming string, so a value that passes through two
 *    core filters still lands on the same label.
 *
 * @package Wavira\Core\Content
 */

namespace Wavira\Core\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Class Dates
 */
final class Dates {

	/**
	 * Jalali calendar style.
	 */
	public const STYLE_JALALI = 'jalali';

	/**
	 * Gregorian calendar style (WordPress's own locale data).
	 */
	public const STYLE_GREGORIAN = 'gregorian';

	/**
	 * Formats that carry data, never presentation.
	 *
	 * @var string[]
	 */
	private const MACHINE_FORMATS = array( 'c', 'U', 'r', 'Y-m-d', 'Ymd', 'Y-m-d H:i:s', 'Y-m-d\\TH:i:sP', 'd/m/Y' );

	/**
	 * Characters that only appear in a time part.
	 *
	 * @var string
	 */
	private const TIME_TOKENS = 'aABgGhHisuv';

	/**
	 * Characters that only appear in a date part.
	 *
	 * @var string
	 */
	private const DATE_TOKENS = 'dDjlNSwzWFmMntLoXxYy';

	/**
	 * Effective style of the front end.
	 *
	 * @return string `jalali` or `gregorian`.
	 */
	public static function style(): string {
		$style = self::locale_is_persian() ? self::STYLE_JALALI : self::STYLE_GREGORIAN;

		/**
		 * Filters which calendar the front end prints.
		 *
		 * @since 0.10.1
		 * @param string $style `jalali` or `gregorian`.
		 */
		$style = (string) apply_filters( 'wavira_core_date_style', $style );

		return self::STYLE_JALALI === $style ? self::STYLE_JALALI : self::STYLE_GREGORIAN;
	}

	/**
	 * Whether the front end prints Jalali dates.
	 *
	 * @return bool
	 */
	public static function uses_jalali(): bool {
		return self::STYLE_JALALI === self::style();
	}

	/**
	 * Whether the site language is Persian (`fa_IR`, `fa_AF`, …).
	 *
	 * @return bool
	 */
	public static function locale_is_persian(): bool {
		return 0 === strpos( strtolower( (string) get_locale() ), 'fa' );
	}

	/**
	 * The Jalali format string, filterable.
	 *
	 * @return string
	 */
	public static function format(): string {
		/**
		 * Filters the Jalali date format (tokens: `j n m F d Y y l`).
		 *
		 * @since 0.10.1
		 * @param string $format Default `j F Y`.
		 */
		return (string) apply_filters( 'wavira_core_date_format', 'j F Y' );
	}

	/**
	 * A localised date label for a timestamp.
	 *
	 * @param int    $timestamp Unix timestamp.
	 * @param string $format    Optional. Display format for the Gregorian style.
	 * @return string
	 */
	public static function label( int $timestamp, string $format = '' ): string {
		if ( $timestamp < 1 ) {
			return '';
		}

		if ( ! self::uses_jalali() ) {
			$format = '' !== $format ? $format : (string) get_option( 'date_format' );

			return (string) wp_date( $format, $timestamp );
		}

		$local = (string) wp_date( 'Y-n-j', $timestamp );

		return self::jalali_label( $local );
	}

	/**
	 * A localised date label for a post, using the post's local date.
	 *
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	public static function label_for_post( \WP_Post $post ): string {
		if ( ! self::uses_jalali() ) {
			return (string) get_the_date( '', $post );
		}

		// The post's own local date fields, so no timezone arithmetic is needed
		// here and the label matches what the editor sees.
		$local = (string) get_post_time( 'Y-n-j', false, $post );

		return self::jalali_label( $local );
	}

	/**
	 * Persian numerals, when the site language is Persian.
	 *
	 * @param string $value Any string containing Latin digits.
	 * @return string
	 */
	public static function digits( string $value ): string {
		if ( ! self::locale_is_persian() ) {
			return $value;
		}

		return strtr( $value, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) );
	}

	/**
	 * Register the front-end date filters.
	 *
	 * `wp_date()` is the seam that matters: core's own `core/post-date` block
	 * renders through it (`render_block_core_post_date()`), so the news grid
	 * built from the Query Loop is localised by the same rule as the product's
	 * own labels — and the two cannot drift.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'wp_date', array( __CLASS__, 'filter_wp_date' ), 10, 3 );
		add_filter( 'get_the_time', array( __CLASS__, 'filter_get_the_time' ), 10, 3 );
		add_filter( 'get_the_modified_time', array( __CLASS__, 'filter_get_the_time' ), 10, 3 );
		add_filter( 'get_the_date', array( __CLASS__, 'filter_get_the_time' ), 10, 3 );
		add_filter( 'get_the_modified_date', array( __CLASS__, 'filter_get_the_time' ), 10, 3 );
	}

	/**
	 * Filter `wp_date()` output on the front end.
	 *
	 * @param string    $date      Formatted date.
	 * @param string    $format    Format string.
	 * @param int|float $timestamp Timestamp.
	 * @return string
	 */
	public static function filter_wp_date( $date, $format = '', $timestamp = 0 ) {
		if ( ! self::should_convert( (string) $format ) ) {
			return $date;
		}

		return self::label( (int) $timestamp );
	}

	/**
	 * Filter the `get_the_*` date/time functions.
	 *
	 * @param string        $value  Formatted value.
	 * @param string        $format Format string.
	 * @param \WP_Post|null $post   Post.
	 * @return string
	 */
	public static function filter_get_the_time( $value, $format = '', $post = null ) {
		if ( ! self::should_convert( (string) $format, $post ) ) {
			return $value;
		}

		$post = $post instanceof \WP_Post ? $post : get_post( $post );

		if ( ! $post instanceof \WP_Post ) {
			return $value;
		}

		return self::label_for_post( $post );
	}

	/**
	 * Whether a value may be converted at all.
	 *
	 * @param string        $format Format string.
	 * @param \WP_Post|null $post   Post, when the caller has one.
	 * @return bool
	 */
	private static function should_convert( string $format, $post = null ): bool {
		unset( $post );

		if ( ! self::uses_jalali() ) {
			return false;
		}

		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed() || is_robots() ) {
			return false;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		// Machine formats are data.
		if ( in_array( $format, self::MACHINE_FORMATS, true ) ) {
			return false;
		}

		if ( '' === $format ) {
			// The `get_the_*` functions resolve an empty format to the site's
			// option; that is a display format by definition.
			return true;
		}

		// A format with a time part would lose that part inside a Jalali day, so
		// it is left exactly as WordPress formatted it.
		if ( false !== strpbrk( $format, self::TIME_TOKENS ) ) {
			return false;
		}

		// Only a format that names at least one date part is a date at all.
		return false !== strpbrk( $format, self::DATE_TOKENS );
	}

	/**
	 * A Jalali label from a `Y-n-j` local date string.
	 *
	 * @param string $local Local Gregorian date, `Y-n-j`.
	 * @return string Empty string when the string is not a date.
	 */
	private static function jalali_label( string $local ): string {
		$parts = array_map( 'intval', explode( '-', $local ) );

		if ( 3 !== count( $parts ) || $parts[0] < 1 || $parts[1] < 1 || $parts[2] < 1 ) {
			return '';
		}

		$label = Jalali::format( $parts[0], $parts[1], $parts[2], self::format() );

		/**
		 * Filters the final Jalali label.
		 *
		 * @since 0.10.1
		 * @param string $label The label.
		 * @param int    $year  Gregorian year of the label.
		 * @param int    $month Gregorian month.
		 * @param int    $day   Gregorian day.
		 */
		$label = (string) apply_filters( 'wavira_core_date_label', $label, $parts[0], $parts[1], $parts[2] );

		return self::digits( $label );
	}
}
