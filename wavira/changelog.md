# Wavira Music — changelog

All notable changes to the theme are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added — 0.9.0 (artist profiles and the news section)
- **`include inc/artists.php`** — the artist page surfaces: portrait (with a music placeholder), name,
  quote, translated social chips, counts, the works grouped by album/single/video with per-section
  counts and "view all" links, the biography and the photo gallery. Renders the payload the core
  plugin builds (`wavira_core_artist_profile()`), read once per request (`wavira_artist_data()`).
- **`inc/news.php`** — the music-news feed as cards (`wavira_get_news()`, `wavira_get_news_categories()`)
  on top of `wavira_core_news_feed()`.
- **Three blocks** — `wavira/artist-profile`, `wavira/artist-gallery` and `wavira/news` (seven in
  total) with editor strings printed from PHP, so the Persian catalogue covers the editor too.
- **Three shortcodes** — `[wavira_artist]`, `[wavira_gallery]`, `[wavira_news]`; each delegates to the
  same helper as its block.
- **Shared card component** (`wavira_get_card()`, `wavira_get_image()`, `wavira_get_section_head()`) —
  the artist sections and the news feed render one component, and every image goes through
  `wp_get_attachment_image()`, so `srcset`/`sizes`/`width`/`height` always come from core.
- **Templates** — `single-wavira_artist.html` (profile + the artist's queue), `home.html` (the blog
  index as the news section, paginated) and `archive.html` (category/tag/author/date archives with the
  term title and description).
- **Pattern** — `hidden-heading-music-news.php`, because the news heading is template text and
  template text must stay translatable (`hidden-*.php`).
- **Icons** — `assets/icons/music.svg` (placeholder portrait) and `external.svg`.
- **Styles** — cards, artist profile, gallery and news grids in `components.css`, with editor parity in
  `editor.css`; logical properties and tokens only.

### Changed — 0.9.0
- Six of the ten theme templates now carry the artist/news surfaces; `wavira-news-grid` styles core's
  Query Loop in the blog templates instead of rendering a second card component.
- Theme version 0.9.0; both catalogues grew to 215 strings (91 theme + 124 core), all translated.

### Added — 0.8.0 (Persian-first)
- **Persian ships with the theme**: `languages/fa_IR.po` (58 strings, hand-written) and the compiled
  `languages/fa_IR.mo` WordPress loads, plus a generated `languages/wavira.pot`. The front-end copy,
  the block titles/descriptions/keywords and the block editor's own strings are all translated — the
  editor gets its strings from PHP (`wavira_block_editor_strings()` + `wp_add_inline_script()`) instead
  of a hash-named JSON file, so one catalogue covers everything.
- **`[FA]` gate** (`tools/i18n.mjs check`, wired into `tools/lint.sh`): a string with no Persian
  translation, a translation that is still Latin without an explicit `#, keep-latin` flag, changed
  placeholders, a stale `.pot` or a `.mo` that no longer matches its `.po` all fail the build.
- **`npm run i18n:extract` / `i18n:build` / `i18n:check`** for the whole pipeline, and
  `tests/js/i18n.test.mjs` + `tests/test-i18n.php` to pin the file format and the runtime behaviour.

### Changed — 0.8.0
- Version 0.8.0; the editor script no longer depends on `wp-i18n` or `wp-components` and takes its
  strings from `window.waviraBlocks`.
- Block metadata strings are extracted from `block.json` with the contexts core uses
  (`block title`, `block description`, `block keyword`).

### Added — 0.7.0 (patterns polish)
- **Translatable template text** (`patterns/hidden-*.php`): a block template cannot execute PHP, so
  every sentence in `templates/*.html` and `parts/*.html` moved into a hidden pattern
  (`Inserter: no`) referenced with `wp:pattern` — the practice the core themes use. The colour-mode
  button, both footer lines and the 404 copy are included.
- **`[I18N]` gate** (`tools/check-i18n.mjs`): no hard-coded text node and no text-bearing block
  attribute in a template or part, every `wp:pattern` reference must resolve, every hidden pattern must
  be referenced. Non-translatable strings (the author attribution) carry an explicit
  `wavira:i18n-exempt` marker.
- **`[MAPPING]` gate** (`tools/check-mapping.mjs`): every `wavira_*` name promised by
  `docs/MIGRATION-BLUEPRINT.md` must exist in the schema, unless the row is marked `[DEFERRED]`.
- Insertable patterns (`album-grid`, `featured-album`, `latest-tracks`, `genre-chips`) now translate
  their own headings and labels with `esc_html_x()`.

### Fixed — 0.7.0 (patterns polish)
- **The footer year was a literal `2026`** in `parts/footer.html` and could never change; it is now
  printed by `wp_date( 'Y' )` from the footer pattern.
- **The 404 search block stored `buttonText`/`label` literals** in the template; both are omitted so
  core's translated defaults apply.
- Six sections shipped untranslatable English headings ("Latest albums", "Listen now", "Latest
  tracks", "Music videos", "Tracks", "Watch") and a "Nothing published here yet." message; all of them
  now come from patterns.

### Added — 0.7.0 (block layer)
- **Four dynamic blocks** (`blocks/{tracklist,player,video,genre-chips}/`): each ships `block.json`
  (apiVersion 3, `category: wavira-music`, `textdomain: wavira`, `render: file:./render.php`,
  `supports.html: false`) and a `render.php`. Registered on `init` priority 5 — that is before the
  patterns, so a pattern that contains a `wavira/*` block is never validated against a missing block.
- **Shared markup** (`inc/markup.php`): `wavira_get_tracklist()`, `wavira_get_video()` and
  `wavira_get_genre_chips()` return escaped markup, their print wrappers echo it, and blocks,
  shortcodes and templates all render through them — the same content cannot look different because
  of the editor that produced the page. `wavira_block_placeholder()` prints an editor-only hint when
  a dynamic block has nothing to render (REST requests only), so an empty block does not look broken.
- **Block editor script** (`blocks/editor.js`): registers the four blocks against the `wp.*` globals
  with a generic `ServerSideRender` preview and `save() → null`. No build step and no `@wordpress/*`
  import in the shipped theme (ADR 0006).
- **Templates and patterns moved to native blocks**: all 10 shortcode blocks in `templates/*.html`
  and `patterns/*.php` are now `wp:wavira/*` blocks; `wp:shortcode` appears nowhere in the product.
- **New styles**: `.wavira-genre-chips`, `.wavira-tracklist__subtitle` and the block-editor preview
  classes (`.wavira-block`, `.wavira-block-hint`, `.wavira-block-placeholder`).
- **Block gate**: `tools/check-blocks.mjs`, wired into `tools/lint.sh` as `[BLOCKS]` — metadata,
  renderer, registrar and editor registrations must agree, and the renderer must refuse direct access.

### Changed — 0.7.0
- `[wavira_tracklist]` gained `duration` and `subtitle` attributes and now delegates to the shared
  helper; `[wavira_video]` follows the same path, so shortcode and block output are identical.
- Theme version 0.7.0.

### Added — 0.6.0 (theme UI)
- **Four CSS layers** (`assets/css/tokens.css`, `base.css`, `components.css`, `utilities.css`): design
  tokens (incl. the `--wavira-on-accent` token), element defaults, components (header, footer, hero,
  sections, tracklist, chips, video frame, site player, theme toggle) and layout utilities. Logical
  properties only, no `!important`; 5.9 KB gzipped together (budget 25 KB).
- **Dark mode** (`tokens.css` + `styles/dark.json` style variation): the palette is remapped for dark,
  the mode lives on `<html>` as `light|dark|auto`, and `auto` follows the system through
  `prefers-color-scheme` (ADR 0014).
- **Editor parity** (`assets/css/editor.css`, loaded through `add_editor_style()`): palettes, cover
  radius, chips, hero grid and the shortcode placeholder match the front end.
- **Templates and parts**: `index`, `page`, `single`, `search`, `404`, `single-wavira_{album,track,
  artist,video}`, `archive-wavira_{album,track,artist,video}`, `taxonomy-wavira_genre` — 14 templates —
  plus `parts/{header,footer,player-bar}.html`.
- **Patterns** (`patterns/{featured-album,album-grid,latest-tracks,genre-chips}.php`) and the
  `wavira-music` pattern category.
- **Shortcodes** (`inc/shortcodes.php`): `[wavira_tracklist]` and `[wavira_video]`, both resolving
  `current` to the queried object, so a block-less site keeps the classic workflow.
- **Player mounting** (`inc/player.php`): `[wavira_player context=… id=… slug=… limit=… track=…
  orderby=… order=… autoplay=… sticky=… fallback=… class=…]`; the mount point and its `<audio>`
  fallback render even when the plugin is inactive (theme-switch safety, ADR 0002).
- **Assets pipeline** (`inc/assets.php`): conditional enqueue, `filemtime` cache busting, the
  `wavira_theme_settings` filter, and the pre-paint colour-mode script on `wp_head`.
- Theme version 0.6.0.

### Fixed — 0.6.0
- **`--wp--custom--player--barSpace` never resolved** (camelCase vs. WordPress' kebab-cased custom
  properties), so the fixed-bar space setting had no effect. Corrected to `--player--bar-space`; the
  new `tools/check-css.mjs` rule 6 fails the build for unresolvable token references (ADR 0014).
- **Accent-chip text failed WCAG in dark mode** (1.51:1 — a light-mode colour was hard-coded on the
  dark accent fill). Replaced by the `--wavira-on-accent` token; `tools/check-contrast.mjs` now checks
  21 mode-specific pairs, including this one.
- `Theme URI` no longer points at the unregistered `wavira.com` (see `docs/BRAND-DECISION.md`).

### Added — 0.5.0
- `inc/player.php`: `wavira_player_mount()` prints a player mount point (`data-*` contract, no element
  IDs) and a native `<audio>` fallback with the preferred source, so a track page works without
  JavaScript; `wavira_player_preferred_source()` picks 320 → 128 → external. The helper asks Core for
  the bundle through `wavira_core_enqueue_player()` and for the payload through
  `wavira_core_track_playback()` — the theme still names no plugin class (gate R3).
- Theme version aligned with the product (0.5.0). No template renders the mount point yet; that is the
  first task of 0.6.0.

### Changed — 0.4.0
- Version aligned with the product roadmap so the theme and **Wavira Core** 0.4.0 ship as one
  release. **No theme code changed in this phase:** search, related items, counters and the download
  endpoint are plugin services (ADR 0002), and the theme still renders through
  `wavira_get_setting()`/`wavira_has_core()` only. Templates and components arrive in 0.6.0.

### Changed — 0.2.1
- Author/designer credit set to **Etehad WP — اتحاد وردپرس** (`https://etehadwp.com/`) in the theme
  header, READMEs, `composer.json`, `package.json` and `LICENSE.md` (owner decision 2026-10-05).

### Added — 0.2.0 (architecture phase)
- Repository skeleton: `inc/`, `templates/`, `parts/`, `patterns/`, `blocks/`, `assets/`, `languages/`.
- Thin `functions.php` bootstrap (constants + `require_once` only — no God file).
- `inc/setup.php`: theme supports, nav menus, music artwork image sizes, text domain.
- `inc/assets.php`: build-aware, conditional asset loading (no front-end jQuery).
- `inc/helpers.php`: `wavira_has_core()`, `wavira_get_setting()`, `wavira_icon()`.
- `inc/hooks.php`: RTL/context body classes, emoji-asset cleanup, excerpt length.
- `theme.json`: design tokens (colour, typography, spacing, radius, shadow, motion, player metrics).
- Architecture documentation and ADRs (repo `docs/`).

### Removed (vs. the legacy theme)
- ionCube-encrypted `functions.php` and marketplace licence file — never carried over.
- OptionTree settings framework (2.2 MB), IE-era polyfills, icon font, `srcset`-disabling filter,
  UA sniffing, hover-only menus, global `#audio` player.
