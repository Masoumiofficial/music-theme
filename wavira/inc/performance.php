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
 * - **The primary typeface is not a round trip away.** `theme.json` declares
 *   Vazirmatn as a `fontFace`, and WordPress prints the `@font-face` rule on
 *   `wp_head` priority 50 (`wp_print_font_faces()`); the browser only discovers
 *   the file when it has parsed that CSS, which is one full round trip after
 *   the first paint. The preload here starts the download with the document.
 *   It is same-origin and the file ships in the theme, so this costs nothing
 *   when the theme is used without the font.
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

if ( ! function_exists( 'wavira_preload_primary_font' ) ) {
	/**
	 * Preload the primary typeface declared in `theme.json`.
	 *
	 * `@font-face` is printed by core on `wp_head` priority 50, so without a
	 * hint the font request cannot start until that CSS has been parsed and the
	 * browser has matched a rule — the text is already on screen by then, in a
	 * fallback face, and re-lays out when the font arrives. A preload starts the
	 * same (same-origin) request with the document instead.
	 *
	 * The URL comes from the theme, never from input; the hint is skipped when
	 * the file is absent, so a checkout that never had the font still renders.
	 *
	 * @return void
	 */
	function wavira_preload_primary_font() {
		$relative = 'assets/fonts/vazirmatn/vazirmatn-variable.woff2';

		if ( ! file_exists( WAVIRA_THEME_DIR . $relative ) ) {
			return;
		}

		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( WAVIRA_THEME_URI . $relative )
		);
	}
}
add_action( 'wp_head', 'wavira_preload_primary_font', 2 );

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
