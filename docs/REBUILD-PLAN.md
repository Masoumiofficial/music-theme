# REBUILD-PLAN.md — Target Product, Architecture & Phased Plan

**Product:** *Wavira Music* — a premium WordPress music-publishing ecosystem (theme + core plugin + player engine + builder integrations + documentation), independent of the legacy codebase.
**Brand Lock:** applied (see `BRAND-DECISION.md`). **Implementation status:** NOT_STARTED (audit phase only).

---

## 1. Target architecture

```
PRODUCT: Wavira
│
├── Theme  "Wavira Music"        → presentation only (templates, blocks, styles, theme.json)
├── Core plugin "Wavira Core"    → ALL persistent data + business logic (CPTs, taxonomies, meta,
│                                  player engine, REST API, download service, settings, migration tool)
├── Gutenberg integration        → blocks + patterns (theme side, data via Core)
├── Elementor integration        → widgets mirroring the blocks (optional, graceful degradation)
├── Player Engine                → framework-agnostic JS module (in Core) + theme-styled components
├── REST API `wavira/v1`         → versioned, permission-aware, cacheable, documented
├── SEO layer                    → Rank Math / Yoast cooperative (no duplication)
├── Demo importer                → licence-clean demo content
├── Migration tool               → legacy → Wavira (Phase 10, separate, feature-flagged)
└── Marketplace package          → /dist/*.zip + documentation
```

**Golden rule applied:** *If the user switches the theme, this data must survive* → artists, albums,
tracks, videos, genres, downloads, player settings live in **Core plugin**, never in the theme.

## 2. Theme structure (target)

```
wavira/                       (theme, text domain "wavira")
├── style.css  functions.php  theme.json  screenshot.png  readme.txt
├── templates/    index.html  front-page.html  home.html  single.html  page.html  archive.html
│                 search.html  404.html  single-wavira_artist.html  archive-wavira_artist.html
│                 single-wavira_album.html  archive-wavira_album.html
│                 single-wavira_track.html  archive-wavira_track.html
│                 single-wavira_video.html  archive-wavira_video.html
│                 taxonomy-wavira_genre.html
├── parts/        header.html  footer.html  sidebar.html  hero.html  player-bar.html  empty-state.html
├── patterns/     home-hero.php  latest-tracks.php  featured-artists.php  genre-grid.php  cta.php …
├── inc/          setup.php  assets.php  enqueue.php  hooks.php  helpers.php  blocks.php
│                 block-styles.php  seo.php  accessibility.php  performance.php  template-tags.php
│                 integrations/{elementor,rank-math,yoast,wpml}.php
├── assets/       css/ (tokens, base, components, utilities)  js/ (modules)  icons/ (SVG sprite)
│                 fonts/ (OFL only)  images/ (owned/CC0 demo art)
├── languages/    wavira.pot (+ fa_IR, ar, de, fr, es translations)
└── docs/         (rendered product documentation sources)
```

Rules: `functions.php` is a 20-line bootstrap only (no God file); every concern in its own file;
`ABSPATH` guard everywhere; no global variables; namespaced classes (`Wavira\Theme\…`).

## 3. Core plugin structure (target)

```
wavira-core/                  (plugin, text domain "wavira-core", PHP namespace Wavira\Core)
├── wavira-core.php           bootstrap + requirements check + activation/deactivation
├── src/
│   ├── Plugin.php            container/bootstrap (PSR-4-ish autoloader, no external deps)
│   ├── Content/              CPTs: Artist, Album, Track, Video + taxonomies (genre, mood, language,
│   │                         label, year) + registered meta schemas
│   ├── Player/               player config, queue building, REST endpoints, Media Session payload
│   ├── Downloads/            quality matrix, capability gate, optional signed URLs, counters
│   ├── Rest/                 wavira/v1 controllers + schemas + permissions + pagination
│   ├── Settings/             Settings API schema, sanitization, capability checks, REST exposure
│   ├── Search/               cross-type search service (debounced, cached)
│   ├── Related/              related-content service with cache
│   ├── Admin/                editor panels, admin columns, validation, bulk actions, notices
│   ├── Import/               CSV/JSON import-export engine (dry-run, dedupe, rollback)
│   ├── Demo/                 demo importer (licence-clean content)
│   ├── Migration/            legacy migration tool (Phase 10)
│   └── Support/              cache helper, logger, upgrade routines, capability map
├── assets/  (player JS module, admin JS/CSS, icons)
├── languages/ wavira-core.pot
└── uninstall.php   (opt-in data removal)
```

**Data rules:** every meta registered with `register_post_meta()`/`register_term_meta()`
(type, single, sanitize, auth, `show_in_rest` + schema). No ACF dependency. No serialized blobs for
tracks. No `posts_per_page => -1` anywhere. Every entity `show_in_rest` by default.

## 4. Data model (target, authoritative for development)

| Entity | CPT | Key fields |
| --- | --- | --- |
| Artist | `wavira_artist` | name, bio, profile image, cover image, country, verified (bool), related artists (IDs), socials (map), website |
| Album | `wavira_album` | title, cover, primary artist, featured artists, release date, type (album/single/EP/compilation), label, genre terms, tracklist (ordered IDs), description |
| Track | `wavira_track` | title, primary artist, featured artists, album, genre terms, release date, cover, audio_128, audio_320, external audio URL, lyrics, duration, file size, download enabled (bool), explicit (bool), ISRC (optional), version/remix note |
| Video | `wavira_video` | title, artist, album, source type (self-hosted/YouTube/Vimeo/Aparat/other), sources per quality, poster, duration, description, release date |
| Genre / Mood / Language / Label / Year | taxonomies | dynamic; **never hardcoded** |

Player state (client): `currentTrack, queue, currentIndex, isPlaying, isLoading, duration, currentTime,
volume, muted, repeatMode, shuffleMode` — DOM-independent, one instance per mount point, persisted
preferences (volume, theme, last queue position) in documented localStorage keys.

## 5. Design system (target)

- **Tokens** (CSS custom properties, `--wavira-*`): colour (surface/text/muted/border/brand/accent),
  radius (sm/md/lg/xl), shadow (sm/md/lg), spacing scale, typography scale, motion durations/easings.
- **Themes:** light + dark via `[data-theme]` + `prefers-color-scheme`, zero flash (pre-paint script),
  WCAG 2.2 AA verified contrast in both.
- **Typography:** Persian + Latin pairing (OFL fonts only), fluid `clamp()` scale, RTL-aware.
- **Direction:** logical properties everywhere; RTL-first, LTR-parity verified.
- **Components:** card, hero, track row, album card, artist card, player bar, mini-player, playlist,
  badges (quality/VIP/explicit), download panel, carousel (accessible), empty state, skeleton loader,
  pagination, breadcrumbs, share, search panel.
- **Motion:** restrained; `prefers-reduced-motion` disables all non-essential motion.
- **Gutenberg:** `theme.json` (settings/styles), patterns for homepage sections, dynamic blocks
  (server-rendered, no build step required for the runtime; optional build for editor-only code).

## 6. Delivery phases & versioning

| Version | Phase | Deliverable | Exit criteria |
| --- | --- | --- | --- |
| 0.1.0 | AUDIT | this documentation set | ✅ complete |
| 0.2.0 | ARCHITECTURE | ADRs, folder skeleton, coding standard, CI lint config | repo builds, `php -l` clean, empty namespaces |
| 0.3.0 | DATA MODEL | Core plugin: CPTs, taxonomies, meta schemas, REST skeleton, settings schema | unit-ish verification via WP-CLI seed script |
| 0.4.0 | MUSIC ENGINE | related service, search service, download service, counters | functional tests on seeded data |
| 0.5.0 | PLAYER | Player Engine + player REST data + theme mount point + Media Session + a11y | ✅ engine + data shipped, 15 DOM-free unit tests + PHP suite; the visual/keyboard playthrough moves with the 0.6.0 templates (see `docs/VERIFICATION.md`) |
| 0.6.0 | UI | design tokens, templates, patterns, dark/light, RTL+LTR | ✅ breakpoint matrix clean at 360→1920; CI run `37309252018` 8/8 |
| 0.7.0 | BUILDERS | blocks + patterns polish; Elementor widgets | ✅ four dynamic blocks, patterns migrated to native blocks, template text moved into translatable hidden patterns, `[BLOCKS]`/`[I18N]`/`[MAPPING]` gates; CI runs `37314137090`/`37314145602` 8/8. Elementor deferred by decision (docs/DECISIONS.md) |
| 0.8.0 | PERSIAN-FIRST | fa_IR catalogues for both artifacts, translation pipeline + `[FA]` gate, Persian admin surfaces | ✅ **VERIFIED** (CI runs `37317660301`/`37317669115` 8/8, 83 tests / 716 assertions on PHP 7.4 + 8.2) |
| 0.9.0 | ARTIST + NEWS | artist profile (works, biography, socials, gallery) and the music-news section (blog templates + feed block) | ✅ verified: CI `37354185539`/`37354194397` 8/8, 100 tests / 871 assertions on PHP 7.4 + 8.2; `docs/ARTIST-AND-NEWS.md`; per-claim evidence in `docs/VERIFICATION.md` |
| 0.10.0 | SEO + PERF | SEO cooperation (music schema, plugin-aware fallbacks), a measured performance budget | ✅ **VERIFIED**: CI `37358851972`/`37358981113` 8/8, 118 tests / 948 assertions on PHP 7.4 + 8.2; music JSON-LD + `[PERF]` gate (7.2 / 2.5 / 13.9 KB gzipped against 25 / 30 / 15 KB), zero third-party URLs, no unbounded query; `docs/SEO-AND-PERF.md`; evidence in `docs/VERIFICATION.md`. Lighthouse/field CWV stay `WP-RUNTIME` |
| 0.11.0 | RELEASE CANDIDATE | migration tool, demo import, docs, packaging | migration acceptance tests pass; docs complete |
| 1.0.0 | PRODUCTION | marketplace packages | final quality gate (§88 of the brief) fully green |

Every phase ends with: implemented → tested → documented → translated → accessible → responsive →
secure → performant → compatible → reviewed (Definition of Done from the brief).

## 7. Workstreams (parallelisable after 0.3.0)

1. Core data (`Content/`) 2. Player 3. Theme & design system 4. Blocks/Elementor
5. REST/headless readiness 6. SEO/perf 7. Admin UX (bulk/import/export) 8. Migration + demo
9. Docs/marketing 10. QA automation (PHP lint, PHPCS WPCS, ESLint, stylelint, a11y audits, Lighthouse CI)

## 8. Technical constraints carried into development

- PHP: follow the WordPress-compatible floor decided in 0.2.0 (target PHP 7.4+ syntax, tested on 8.2/8.3;
  no deprecated APIs; no `create_function`, no `each()`).
- WordPress: current supported releases at build time (do not target ancient versions).
- JS: vanilla ES modules, no front-end jQuery, no global pollution, no duplicate event handlers.
- CSS: no `!important` except documented resets; no deep nesting; no duplicate declarations.
- Security: escape on output, sanitize on input, nonces + capability checks on every mutation.
- Performance: budgets in `PERFORMANCE-AUDIT.md` §3 are merge gates.
- Accessibility: WCAG 2.2 AA is a merge gate, not a follow-up task.
- Licensing: only MIT/GPL-compatible/OFL/own assets; provenance log for demo content.
- Privacy: no hidden telemetry, no third-party requests by default, documented storage keys.
- No DRM claims; audio protection limited to access control, documented honestly.

## 9. Risks & mitigations (rebuild-specific)

| Risk | Mitigation |
| --- | --- |
| Scope creep into SaaS/streaming/AI | §54 YAGNI rule: seams only, no implementations in v1 |
| Feature parity pressure from legacy quirks | Feature decisions recorded in FEATURE-MAP (P0/P1/P2/P3 with rationale) |
| Player complexity | Player Engine specified as a standalone module with its own test plan before UI polish |
| Data loss on migration | dry-run + rollback + reconciliation tests (M1–M10) |
| Marketplace rejection on licence grounds | LICENSE-AUDIT actions A1–A7 closed before packaging |
| Legacy authors' brand leaking into product | BRAND-DECISION vocabulary + pre-packaging grep gate |
