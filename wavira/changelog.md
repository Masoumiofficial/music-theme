# Wavira Music — changelog

All notable changes to the theme are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

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
