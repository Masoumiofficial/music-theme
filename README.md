# Wavira — WordPress music-publishing ecosystem

> **Status: 0.12.0 (release candidate).** Every v1 feature has landed and is verified by CI: the data
> model, REST API, player engine, templates and blocks, artist profiles, the news section, SEO
> cooperation, the performance budget, Persian/Jalali localisation, the legacy migration tool and the
> release packages. The product is **Persian by default**: Persian catalogues for both artifacts, Jalali
> (Shamsi) dates with Persian numerals on `fa_IR` sites, an Iranian demo seeder, a bundled Vazirmatn
> typeface and a Persian developer harness. What is left is release mechanics, listed item by item in
> [`docs/RELEASE-CANDIDATE.md`](docs/RELEASE-CANDIDATE.md) §6; every claim in this repository is tracked
> in [`docs/VERIFICATION.md`](docs/VERIFICATION.md).

## Download

| File | Install it as | Direct link |
| --- | --- | --- |
| `wavira-core-0.12.0.zip` | the core plugin — **first**, via *Plugins → Add New → Upload Plugin* | [download](https://github.com/Masoumiofficial/music-theme/releases/download/v0.12.0-rc/wavira-core-0.12.0.zip) |
| `wavira-theme-0.12.0.zip` | the theme, via *Appearance → Themes → Add New → Upload Theme* | [download](https://github.com/Masoumiofficial/music-theme/releases/download/v0.12.0-rc/wavira-theme-0.12.0.zip) |
| `wavira-0.12.0-bundle.zip` | both, plus a `README-FIRST/` folder | [download](https://github.com/Masoumiofficial/music-theme/releases/download/v0.12.0-rc/wavira-0.12.0-bundle.zip) |

All releases and the notes for each: <https://github.com/Masoumiofficial/music-theme/releases>. The
archives attached to a release are built by CI from the tagged commit with a fixed `SOURCE_DATE_EPOCH`,
so their SHA-256 is the one the verification jobs saw (0.12.0: digests in the release's `SHA256SUMS`,
and repeated in `docs/RELEASE-CANDIDATE.md`). Persian install guide:
[`docs/fa/USER-GUIDE.md`](docs/fa/USER-GUIDE.md). Settings: **Appearance → Customize → Wavira
settings** — identity and logo, header, appearance, fonts, social links, footer text, additional CSS,
and the one-click Persian setup.

**Wavira** is a premium WordPress product for publishing and discovering music: artists, albums,
tracks, music videos, genres, lyrics, multi-quality downloads and a first-class player — RTL-first with
full LTR support, dark/light, Gutenberg and Elementor ready, built for performance and WCAG 2.2 AA. The
theme ships a settings panel (ADR 0020) and a one-click Persian setup; the music model, the player and
the migration tool live in the core plugin so they survive a theme switch.
It ships Persian: the interface, the admin and the editor are translated, dates follow the Jalali
(Shamsi) calendar on Persian sites, and the demo it seeds is a Persian music site.

## Repository layout

| Path | What it is |
| --- | --- |
| `music-theme.zip` | **Legacy artifact, read-only.** The original ionCube-protected theme that was audited. Never modified, never shipped. |
| `docs/` | Audit reports, brand decision, architecture, coding standard, ADRs. Start at `docs/EXECUTIVE-SUMMARY.md`. |
| `wavira/` | **Wavira Music** theme (presentation layer) — templates, patterns and blocks (0.6.0–0.9.0). |
| `wavira-core/` | **Wavira Core** plugin (all music data + business logic) — content model, services, player engine, REST API, SEO layer and the Jalali/date policy. |
| `tests/` | PHPUnit integration suite for a real WordPress test library (`tests/test-*.php`) plus DOM-free player-engine unit tests (`tests/js/`, `node --test`). |
| `tools/` | Development tooling: `lint.sh` (all gates), `build.mjs` (assets), `package.mjs` (release archives + `[PACKAGE]` gate), checkers (`check-*.mjs`, `check-class-refs.py`), CI log/JUnit annotators, `preview/`. |
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
| `docs/SEO-AND-PERF.md` | Music structured data, SEO cooperation and the performance budget (0.10.0) |
| `docs/PERSIAN-LOCALIZATION.md` | What a Persian site gets: catalogues, the Jalali calendar, Iranian defaults, Persian numerals, the bundled typeface (0.10.1, 0.11.0) |
| `docs/RELEASE-CANDIDATE.md` | **Release view**: what ships, what is verified, the pre-upload checklist, the known limitations (0.11.0) |
| `docs/MIGRATION-BLUEPRINT.md` | Legacy → Wavira data migration plan (both sources) |
| `docs/INTEGRATIONS.md` | **Interop contract**: what the publishing plugin writes, the field map, the kind taxonomy, the limits |
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

# 7. Release packages (deterministic; writes dist/, which is never committed)
node tools/build.mjs && node tools/package.mjs
node tools/package.mjs --check        # the [PACKAGE] gate lint.sh runs
node tools/package.mjs --check --strict   # the extra files a marketplace needs
```

`composer test:install` mirrors what CI does: it creates the `wordpress_test` database itself, so an
existing database makes the vendored installer ask for confirmation — drop it first when re-running.
Inside WordPress, two WP-CLI commands verify the install:

```bash
wp wavira verify           # post types, taxonomies, 46 meta keys, settings, REST routes, counters
wp wavira seed [--force]   # Persian demo + Iranian defaults; --english for the neutral fixture
wp wavira migrate --detect # what a legacy site holds (read-only)
wp wavira migrate --dry-run # the full migration plan, writing nothing
wp wavira migrate          # run it (idempotent, resumable, rollback available)
```

The product **does not require a build to be installable**: when `assets/dist/` is missing the theme
enqueues nothing and the front end degrades gracefully (see ADR 0006).

## Release packages

`node tools/package.mjs` writes three archives into `dist/` — `wavira-theme-<version>.zip`,
`wavira-core-<version>.zip` and `wavira-<version>-bundle.zip` (both plus the buyer-facing
`README-FIRST/`) — with a `manifest.json` (every file, size and SHA-256) and `SHA256SUMS`. The archives
are deterministic: two builds of one commit are byte-identical, and the tool proves it before reporting
success. What may ship is decided by explicit include lists plus a scan of the *result*, so a dev-only
directory cannot leak; third-party files must be named in `THIRD-PARTY-NOTICES.md`, and the font's
licence text must be inside the package (ADR 0019). CI builds and uploads the packages on every push.

## What the product exposes today (0.5.0)

| Surface | Detail |
| --- | --- |
| Content | CPTs `wavira_artist`, `wavira_album`, `wavira_track`, `wavira_video`; taxonomies `wavira_genre` and `wavira_kind` (single · remix · noha · podcast, always on) plus optional mood/language/label/year; 46 registered meta keys; one typed settings option with 17 keys. |
| REST | `wavira/v1`: typed collections for each entity with pagination headers, `/genres`, `/search`, `/search/suggest`, `/{type}/{id}/related`, `/download/{id}`. |
| Downloads | Authorization chain (site setting → per-track opt-out → optional login → filter) and atomic counters. Delivery is a redirect to the file: **authorization and accounting, never DRM** (ADR 0013). |
| Player | Framework-free engine in `wavira-core/assets/js/index.js`: one state machine per instance, its own `<audio>`, queue + shuffle + repeat (off/all/one), Media Session metadata and action handlers, documented keyboard map, ARIA state and live regions, `localStorage` preferences (`wavira.player.*`). Mounted by `wavira_player_mount()` in the theme, with a native `<audio>` fallback when JavaScript is unavailable. |
| Player data | `wavira/v1/player/tracks/{id}` and `/player/queue?context=album\|artist\|genre\|tracks\|related`; the engine only substitutes IDs into server-provided route templates. |
| WP-CLI | `wp wavira verify`, `wp wavira seed [--force] [--english] [--no-site]` (the same installer as the admin screen), `wp wavira export-demo [--file=<path>] [--type=<post-type>]`, `wp wavira migrate [--detect\|--dry-run\|--status\|--rollback] [--batch=<n>] [--offset=<n>] [--kind=<kind>] [--source=legacy\|music-publisher] [--report=<file>]`. |
| Admin | `Tools → Wavira demo content`: import the Persian demo (or the English fixture) and download the content as WXR — no terminal required. |
| Interop | A site built with the “Sajad Music Publisher” plugin converts through the same migration tool (`--source=music-publisher`), keeping remix/noha/podcast kinds, album track rows and role credits as data (`docs/INTEGRATIONS.md`). |
| Packaging | `node tools/package.mjs [--check] [--strict] [--out=DIR]` — the archives, the manifest and the `[PACKAGE]` gate (ADR 0019). |
| Function API | Plugin: `wavira_core_is_active()`, `wavira_core_get_setting()`, `wavira_core_related_posts()`, `wavira_core_track_playback()`, `wavira_core_enqueue_player()`, `wavira_core_date_style()`, `wavira_core_date_label()`, `wavira_core_digits()` (`wavira-core/public-api.php`). Theme: `wavira_has_core()`, `wavira_get_setting()`, `wavira_icon()`, `wavira_related_posts()`. A theme never names a Core class — enforced by `tools/check-boundaries.mjs`. |
| Hooks | Filters `wavira_track_playback_payload`, `wavira_core_date_style`, `wavira_core_date_format`, `wavira_core_date_label`, `wavira_player_queue_items`, `wavira_player_settings`, `wavira_related_ids`, `wavira_related_score`, `wavira_searchable_types`, `wavira_download_quality_matrix`, `wavira_download_quality_sources`, `wavira_download_access`, `wavira_setting`, `wavira_settings_sanitized`, `wavira_rest_item`, `wavira_content_width`; actions `wavira_core_booted`, `wavira_download_counted`, `wavira_download_served`. Themes call `wavira_has_core()`, `wavira_get_setting()`, `wavira_icon()`, `wavira_related_posts()`. Player mounts emit DOM events `wavira:player:<event>` for theme JavaScript. |

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
