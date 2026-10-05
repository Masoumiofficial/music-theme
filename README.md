# Wavira — WordPress music-publishing ecosystem

> **Status: 0.5.0 (player engine).** The data model, the product REST API, search, related items,
> counters, the download endpoint and the player engine are implemented. The engine is a DOM-free ES
> module with 15 unit tests; the integration suite (PHP + WordPress) runs in CI. There are **no
> templates yet** — the theme renders its own markup from 0.6.0, using the player mount point that
> now exists. Development proceeds phase by phase per `docs/REBUILD-PLAN.md`; every claim is tracked
> in `docs/VERIFICATION.md`.

**Wavira** is a premium WordPress product for publishing and discovering music: artists, albums,
tracks, music videos, genres, lyrics, multi-quality downloads and a first-class player — RTL-first with
full LTR support, dark/light, Gutenberg and Elementor ready, built for performance and WCAG 2.2 AA.

## Repository layout

| Path | What it is |
| --- | --- |
| `music-theme.zip` | **Legacy artifact, read-only.** The original ionCube-protected theme that was audited. Never modified, never shipped. |
| `docs/` | Audit reports, brand decision, architecture, coding standard, ADRs. Start at `docs/EXECUTIVE-SUMMARY.md`. |
| `wavira/` | **Wavira Music** theme (presentation layer) — skeleton in place, templates arrive in 0.6.0. |
| `wavira-core/` | **Wavira Core** plugin (all music data + business logic) — content model (0.3.0), music services (0.4.0) and the player engine (0.5.0) implemented. |
| `tests/` | PHPUnit integration suite for a real WordPress test library (`tests/test-*.php`) plus DOM-free player-engine unit tests (`tests/js/`, `node --test`). |
| `tools/` | Development tooling: `lint.sh`, asset build scripts, CI log/JUnit annotators. |
| `dist/` | Release packages (generated; never committed with binaries). |

## The two-artifact model

```
Wavira Music (theme)        →  how it looks
Wavira Core  (plugin)       →  what it stores and does  (artists, albums, tracks, videos, genres,
                              settings, player engine, downloads, REST API wavira/v1)
```

Switch themes at any time: the music catalogue, settings and player configuration live in the plugin
and survive. Deactivate the plugin and the theme still renders a clean post/page site.

## Documentation map

| Document | Purpose |
| --- | --- |
| `docs/EXECUTIVE-SUMMARY.md` | The five decisions that shape the product |
| `docs/PROJECT-AUDIT.md` | What the legacy artifact contains (97 files, hashed, classified) |
| `docs/FEATURE-MAP.md` | Every legacy feature, re-specified with priority P0–P3 |
| `docs/ARCHITECTURE.md` | **Target architecture** (binding) |
| `docs/CODING-STANDARD.md` | Enforceable rules (PHP/JS/CSS/i18n/security/perf) |
| `docs/DECISIONS.md` + `docs/adr/` | Architecture decision records |
| `docs/DATA-MODEL.md` | **Authoritative music data model** (entities, 46 meta keys, settings, REST, services) |
| `docs/VERIFICATION.md` | **Per-claim evidence log** (what is VERIFIED vs. still open) |
| `docs/ARTIST-AND-NEWS.md` | Artist profiles and the music-news section (the 0.9.0 surfaces) |
| `docs/MIGRATION-BLUEPRINT.md` | Legacy → Wavira data migration plan |
| `docs/REBUILD-PLAN.md` | Phases 0.1.0 → 1.0.0 with exit criteria |
| `docs/TECH-DEBT.md` | 35 legacy debt items and their disposition |
| `docs/BRAND-RESEARCH.md`, `docs/BRAND-DECISION.md` | Brand evidence + Brand Lock (Wavira) |

## Development quick start

```bash
# 1. Requirements: PHP 7.4+ (or none — see below), Node 18+ for asset tooling only,
#    MySQL/MariaDB for the integration suite.

# 2. Lint everything (PHP syntax, JS syntax, JSON, legacy-echo gate, asset sizes)
bash tools/lint.sh

# 3. Build front-end assets (CSS/JS) when working on the UI
node tools/build.mjs

# 4. Install for local WordPress testing
#    - copy (or symlink) wavira/      → wp-content/themes/wavira
#    - copy (or symlink) wavira-core/ → wp-content/plugins/wavira-core

# 5. Run the integration suite against a real WordPress (MySQL/MariaDB required)
composer install          # phpunit + polyfills, dev-only
composer test:install     # downloads the WordPress test library (bin/install-wp-tests.sh)
composer test             # integration suite: data model, REST, downloads, search, related, cache, player
```

```bash
# 6. Player engine unit tests (no WordPress, no browser, no dependencies)
npm run test:js           # node --test tests/js/player.test.mjs
```

`composer test:install` mirrors what CI does: it creates the `wordpress_test` database itself, so an
existing database makes the vendored installer ask for confirmation — drop it first when re-running.
Inside WordPress, two WP-CLI commands verify the install:

```bash
wp wavira verify          # post types, taxonomies, 43 meta keys, settings, REST routes, counters
wp wavira seed [--force]  # licence-clean demo content
```

The product **does not require a build to be installable**: when `assets/dist/` is missing the theme
enqueues nothing and the front end degrades gracefully (see ADR 0006).

## What the product exposes today (0.5.0)

| Surface | Detail |
| --- | --- |
| Content | CPTs `wavira_artist`, `wavira_album`, `wavira_track`, `wavira_video`; taxonomies `wavira_genre` (always) plus optional mood/language/label/year; 43 registered meta keys; one typed settings option with 17 keys. |
| REST | `wavira/v1`: typed collections for each entity with pagination headers, `/genres`, `/search`, `/search/suggest`, `/{type}/{id}/related`, `/download/{id}`. |
| Downloads | Authorization chain (site setting → per-track opt-out → optional login → filter) and atomic counters. Delivery is a redirect to the file: **authorization and accounting, never DRM** (ADR 0013). |
| Player | Framework-free engine in `wavira-core/assets/js/index.js`: one state machine per instance, its own `<audio>`, queue + shuffle + repeat (off/all/one), Media Session metadata and action handlers, documented keyboard map, ARIA state and live regions, `localStorage` preferences (`wavira.player.*`). Mounted by `wavira_player_mount()` in the theme, with a native `<audio>` fallback when JavaScript is unavailable. |
| Player data | `wavira/v1/player/tracks/{id}` and `/player/queue?context=album\|artist\|genre\|tracks\|related`; the engine only substitutes IDs into server-provided route templates. |
| WP-CLI | `wp wavira verify`, `wp wavira seed`. |
| Function API | Plugin: `wavira_core_is_active()`, `wavira_core_get_setting()`, `wavira_core_related_posts()`, `wavira_core_track_playback()`, `wavira_core_enqueue_player()` (`wavira-core/public-api.php`). Theme: `wavira_has_core()`, `wavira_get_setting()`, `wavira_icon()`, `wavira_related_posts()`. A theme never names a Core class — enforced by `tools/check-boundaries.mjs`. |
| Hooks | Filters `wavira_track_playback_payload`, `wavira_player_queue_items`, `wavira_player_settings`, `wavira_related_ids`, `wavira_related_score`, `wavira_searchable_types`, `wavira_download_quality_matrix`, `wavira_download_quality_sources`, `wavira_download_access`, `wavira_setting`, `wavira_settings_sanitized`, `wavira_rest_item`, `wavira_content_width`; actions `wavira_core_booted`, `wavira_download_counted`, `wavira_download_served`. Themes call `wavira_has_core()`, `wavira_get_setting()`, `wavira_icon()`, `wavira_related_posts()`. Player mounts emit DOM events `wavira:player:<event>` for theme JavaScript. |

## Contributing rules in one paragraph

Escape on output, sanitize on input, nonce + capability on every mutation; no front-end jQuery; no
unbounded queries; no UA sniffing; logical CSS properties only; every string translatable; WCAG 2.2 AA
and the performance budget are merge gates; new dependencies need a licence note. The full list is in
`docs/CODING-STANDARD.md`.

## Licence

GPL-2.0-or-later for the product (theme + plugin). Third-party components and their licences are listed
in `THIRD-PARTY-NOTICES.md`. The legacy artifact in this repository is **not** part of the product and
is excluded from every package for licensing reasons (`docs/LICENSE-AUDIT.md`).

## Credits

| Role | Name |
| --- | --- |
| Designer & Author (طراح و نویسنده قالب) | **Etehad WP — اتحاد وردپرس** · <https://etehadwp.com/> |
| Product & documentation | Etehad WP product team |

## Brand notice

Preliminary name screening only — professional trademark clearance is still recommended before
commercial launch. See `docs/BRAND-DECISION.md`.
