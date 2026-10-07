=== Wavira Music ===
Requires at least: 6.6
Requires PHP: 7.4
Tested up to: 7.1
Version: 0.11.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Tags: music, rtl-language-support, translation-ready, block-styles, wide-blocks, custom-colors, accessibility-ready

A Persian-first, RTL-first music-publishing theme. Artists, albums, tracks, videos and genres.

== Description ==

Wavira Music is the presentation layer of the Wavira music ecosystem: a premium,
RTL-first WordPress theme for musicians, labels, studios and music magazines that
publish on their own site.

The theme renders music content; the **Wavira Core** plugin owns it. Artists,
albums, tracks, videos, genres, audio sources (128/320), lyrics, artist social
links, the player engine and the REST API all live in the plugin, so switching
theme never takes your catalogue with it.

**What you get**

* Four content types — artists, albums, tracks, music videos — plus a genre
  taxonomy, with templates, archives and curated front-page sections.
* A component-based player engine: several players on one page, Media Session
  support, full keyboard operation, no global element IDs, no jQuery.
* Persian by default: the interface is translated (the catalogue ships in the
  package), dates are Jalali (Shamsi) with Persian numerals, the text direction
  is right-to-left with complete left-to-right parity, and the bundled Vazirmatn
  typeface is served from your own server — no third-party requests.
* Dark and light colour modes driven by CSS variables, honouring the visitor's
  system preference and remembering their choice.
* Structured data (MusicGroup, MusicAlbum, MusicRecording, MusicVideoObject)
  printed on music pages, and meta tags emitted only when no SEO plugin is
  active — so Rank Math or Yoast stay in charge.
* Accessibility treated as a requirement, not a feature: WCAG 2.2 AA contrast
  is checked on every build, focus is visible, the player is operable by
  keyboard and screen reader.
* A legacy migration tool in the plugin (`wp wavira migrate`) that moves an
  older music site into this model idempotently, with a dry run, a review queue
  and a rollback.

== Installation ==

1. Install and activate the **Wavira Core** plugin first (it provides the content
   types this theme renders).
2. Upload this theme, activate it, then set your permalinks to a pretty
   structure (Settings → Permalinks).
3. Optional: create the Persian demo content with `wp wavira seed --force`.
4. Optional: if you have a legacy music site, review `wp wavira migrate --dry-run`
   before running `wp wavira migrate`.

== Frequently Asked Questions ==

= Does the theme work without the plugin? =

It does not crash, but it has nothing to render: artists, albums, tracks and
videos come from Wavira Core by design. Install the plugin.

= Can I use it with an SEO plugin? =

Yes, and you should. The theme stops emitting its own description, Open Graph
and Twitter card tags as soon as a supported SEO plugin is active; structured
data is always emitted by the plugin, never duplicated.

= Is the interface only in Persian? =

Persian ships for the front end, the admin and the editor. Every string is in
`.po`/`.mo` catalogues, so any language can be added with a translation file and
nothing else.

= Which fonts are bundled? =

Vazirmatn, under the SIL Open Font License 1.1. The licence text ships in
`assets/fonts/vazirmatn/OFL.txt`.

== Screenshots ==

1. The front page: featured tracks, the latest albums and the artist grid.
2. An album with its tracklist and the player bar.
3. An artist profile with social links and the discography.

== Changelog ==

See `changelog.md` in the theme folder.

== Copyright ==

Wavira Music is free software, released under the GNU General Public License v2
or later.

Design and development: Etehad WP (اتحاد وردپرس) — https://etehadwp.com/

The bundled Vazirmatn typeface is Copyright 2015 The Vazirmatn Project Authors
(https://github.com/rastikerdar/vazirmatn) and is licensed under the SIL Open
Font License 1.1; the licence text ships with the font. The Jalali date
conversion is a PHP port of the Borkowski algorithm as published in
`jalaali/jalaali-js` (MIT, Copyright (c) 2020 Behrang Norouzinia); the notice
travels in `THIRD-PARTY-NOTICES.md`.
