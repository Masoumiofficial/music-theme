<?php
/**
 * SEO integration seams: who owns which part of the page.
 *
 * The product's rule (FEATURE-MAP F-73, ADR 0016) is cooperation, not
 * competition. A site almost always runs Rank Math, Yoast, SEOPress or
 * All-in-One SEO, and those plugins own the generic surface: titles, canonical
 * URLs, `meta description`, Open Graph, Twitter cards, sitemaps. What none of
 * them knows about is *music*: album/artist relationships, track durations,
 * release dates, hosting credits. That difference is what this namespace
 * provides, and this class is the one place that decides who is present.
 *
 * The detection is deliberately conservative: a plugin is "active" when its own
 * public constant or main class exists, because those have been stable for
 * years. Sites can override either answer with a filter, which is also how the
 * integration is tested.
 *
 * @package Wavira\Core\Seo
 */

namespace Wavira\Core\Seo;

defined( 'ABSPATH' ) || exit;

/**
 * Class SeoSupport
 */
final class SeoSupport {

	/**
	 * Public markers of the SEO plugins the product cooperates with.
	 *
	 * Yoast SEO, SEOPress and All in One SEO expose a stable version constant;
	 * Rank Math exposes its main class. Both markers have survived the plugins'
	 * own major releases, unlike option or function probing.
	 *
	 * @var array<int, array{0: string, 1: string}> Pairs of `[ kind, name ]`,
	 *                                             where kind is `constant` or `class`.
	 */
	private const MARKERS = array(
		array( 'constant', 'WPSEO_VERSION' ),
		array( 'constant', 'SEOPRESS_VERSION' ),
		array( 'constant', 'AIOSEO_VERSION' ),
		array( 'class', 'RankMath' ),
	);

	/**
	 * Whether another plugin already owns the generic SEO surface.
	 *
	 * @return bool
	 */
	public static function plugin_active(): bool {
		$active = false;

		foreach ( self::MARKERS as $marker ) {
			$found = 'constant' === $marker[0]
				? defined( $marker[1] )
				: class_exists( $marker[1] );

			if ( $found ) {
				$active = true;
				break;
			}
		}

		/**
		 * Filters whether a third-party SEO plugin is considered active.
		 *
		 * @since 0.10.0
		 * @param bool $active Detected state.
		 */
		return (bool) apply_filters( 'wavira_core_seo_plugin_active', $active );
	}

	/**
	 * Whether the product should emit its own music structured data.
	 *
	 * Music schema is a gap in every generic SEO plugin, so it is on by default
	 * even when one is active — and a site whose SEO plugin builds its own
	 * `MusicGroup`/`MusicAlbum` graph turns it off here.
	 *
	 * @return bool
	 */
	public static function structured_data_enabled(): bool {
		/**
		 * Filters whether Wavira structured data is emitted.
		 *
		 * @since 0.10.0
		 * @param bool $enabled Default true.
		 */
		return (bool) apply_filters( 'wavira_core_structured_data_enabled', true );
	}
}
