# Wavira Music — changelog

All notable changes to the theme are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

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
