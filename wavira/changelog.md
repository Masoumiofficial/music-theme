# Wavira Music — changelog

All notable changes to the theme are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

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
