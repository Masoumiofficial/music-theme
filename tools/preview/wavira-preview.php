<?php
/**
 * The live preview's own mu-plugin: what Playground needs that a real host gives
 * for free.
 *
 * A WordPress Playground install is a *fresh* install: no language pack is
 * fetched (the sandbox that serves the preview cannot reach
 * `api.wordpress.org`), so `fa_IR` exists as a locale name and nothing else —
 * and no plugins are active. This file supplies exactly the three things that
 * gap leaves, and nothing a real site would need:
 *
 * 1. **The locale.** `locale` is pinned to `fa_IR` so the views are rendered as
 *    a Persian site.
 * 2. **The text domains.** Core would look for `fa_IR.mo` under
 *    `wp-content/languages/`; the language pack is what is missing here, so the
 *    product's own catalogues are pointed at the copies the theme and the plugin
 *    ship (which is what a real host does too).
 * 3. **The core strings a Persian WordPress would translate.** «باز کردن فهرست»
 *    for core's `Open menu` is in the language pack, not in the product — the
 *    preview is not allowed to look English because of a missing download, so
 *    the handful of strings the theme's own templates render is filled in here.
 *
 * It also carries the preview's site name. Nothing in this file is copied into a
 * release package: it lives in `tools/`, which `tools/package.mjs` excludes.
 *
 * @package Wavira\Tools
 */

add_filter(
	'locale',
	static function () {
		return 'fa_IR';
	}
);

add_filter(
	'pre_option_blogname',
	static function () {
		return 'واویرا';
	}
);

add_filter(
	'wp_Textdomain_Registry_get',
	static function ( $path, $domain ) {
		if ( 'wavira' === $domain ) {
			return get_template_directory() . '/languages/';
		}

		if ( 'wavira-core' === $domain && defined( 'WAVIRA_CORE_PATH' ) ) {
			return WAVIRA_CORE_PATH . 'languages/';
		}

		return $path;
	},
	10,
	3
);

add_filter(
	'gettext',
	static function ( $translation, $text ) {
		$core_fa = array(
			'Open menu'     => 'باز کردن فهرست',
			'Close menu'    => 'بستن فهرست',
			'Pagination'    => 'صفحه‌بندی',
			'Next Page'     => 'صفحهٔ بعدی',
			'Previous Page' => 'صفحهٔ قبلی',
			'Menu'          => 'فهرست',
			'Navigation'    => 'ناوبری',
		);

		return $core_fa[ $text ] ?? $translation;
	},
	10,
	2
);
