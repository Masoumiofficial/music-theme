<?php
/**
 * Performance: the budget the product promises, expressed as code.
 *
 * The budget (docs/PERFORMANCE-AUDIT.md §3, ADR 0009) is measured, not asserted:
 * `tools/check-perf.mjs` fails the build when a shipped bundle crosses its
 * gzipped limit, and CI runs it on every push. What is left for this file is the
 * part a size gate cannot see — what the page asks a visitor to download and
 * when a browser can paint:
 *
 * - **No third-party requests.** The product loads nothing from another origin,
 *   which is both a privacy promise and a speed promise; the one hint WordPress
 *   adds for its own emoji CDN is removed here.
 * - **LCP images are eligible to be prioritised.** `wavira_get_image()` used to
 *   force `loading="lazy"` on every image it rendered, including the hero
 *   portrait; that overrode core's own optimisation, which promotes the first
 *   (likely LCP) image to `fetchpriority="high"`. The images the theme renders
 *   now leave `loading` and `decoding` to core.
 * - **Nothing is enqueued twice.** The player bundle is asked for once, by the
 *   component that needs it (ADR 0005), and the theme script is deferred.
 *
 * @package Wavira\Theme
 * @since   0.10.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wavira_resource_hints' ) ) {
	/**
	 * Drop the emoji CDN hint WordPress adds to every page.
	 *
	 * `s.w.org` is a WordPress.org domain; a commercial product must not trade a
	 * visitor's DNS lookup for a feature nobody asked for. The emoji *script* is
	 * already removed in inc/hooks.php — this is the head hint that would remain.
	 *
	 * @param array<int, mixed> $urls          Hint entries.
	 * @param string            $relation_type Relation type (`dns-prefetch`, …).
	 * @return array<int, mixed>
	 */
	function wavira_resource_hints( $urls, $relation_type ) {
		if ( 'dns-prefetch' !== $relation_type || ! is_array( $urls ) ) {
			return $urls;
		}

		$kept = array();

		foreach ( $urls as $url ) {
			$href = is_array( $url ) && isset( $url['href'] ) ? (string) $url['href'] : (string) $url;

			if ( false !== strpos( $href, 's.w.org' ) ) {
				continue;
			}

			$kept[] = $url;
		}

		return $kept;
	}
}
add_filter( 'wp_resource_hints', 'wavira_resource_hints', 10, 2 );

if ( ! function_exists( 'wavira_disable_emojis_everywhere' ) ) {
	/**
	 * Keep emoji detection out of the classic editor screens too.
	 *
	 * `inc/hooks.php` removes the front-end actions; the admin ones are removed
	 * here so the two files stay about one thing each — this one about what the
	 * product costs a request.
	 *
	 * @return void
	 */
	function wavira_disable_emojis_everywhere() {
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	}
}
add_action( 'init', 'wavira_disable_emojis_everywhere', 20 );
