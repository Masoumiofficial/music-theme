# ARCHITECTURE.md — Wavira (Theme + Core Plugin)

**Version:** 0.2.0 (architecture phase) · **Status:** binding for all later phases
**Supersedes (as a plan):** nothing — this is the first target-architecture document.
**Inputs:** `PROJECT-AUDIT.md`, `ARCHITECTURE-LEGACY.md`, `DATA-MODEL-AUDIT.md`, `REBUILD-PLAN.md`.

---

## 1. System overview

```
┌──────────────────────────────────────────────────────────────────────────┐
│                              WordPress                                    │
│                                                                          │
│  ┌───────────────────────────┐        ┌───────────────────────────────┐  │
│  │  THEME  "Wavira Music"    │        │  PLUGIN  "Wavira Core"        │  │
│  │  (presentation only)      │        │  (all persistent music data)  │  │
│  │                           │        │                               │  │
│  │  templates/ patterns/     │  reads │  Content (CPTs + taxonomies)  │  │
│  │  parts/ assets/ theme.json│ ─────► │  Meta schemas                 │  │
│  │                           │        │  Settings (wavira_settings)   │  │
│  │  • no CPTs                │        │  Player data + REST wavira/v1 │  │
│  │  • no music logic         │        │  Downloads / Search / Related │  │
│  │  • renders + styles       │        │  Admin UX / Import / Demo     │  │
│  └───────────────────────────┘        │  Migration tool (legacy→new)  │  │
│                                       └───────────────────────────────┘  │
│                                                     │                    │
│                                        data survives theme switches       │
└──────────────────────────────────────────────────────────────────────────┘
```

**The contract (from `REBUILD-PLAN.md`):** *"If the user switches the theme, must this survive?"* →
if yes, it lives in **Core**, never in the theme.

## 2. Dependency rules (enforced)

| Rule | Allowed | Forbidden |
| --- | --- | --- |
| Layer direction | Theme → Core (read-only, via helpers) · Core → WordPress | Core → Theme, Core → active theme's functions |
| Optional integrations | Theme ↔ Core via public functions/actions only | Theme requiring Core classes to render a page |
| Missing plugin | Theme renders a usable site and shows a documented admin notice | Fatal error, white screen, broken layout |
| Missing theme | Core keeps all data and REST endpoints working (headless-ready) | Data loss, orphaned CPTs |
| Third-party runtime deps | None | ACF, OptionTree, jQuery, page builders (all optional, integrated only) |
| Public surface | Theme (and third parties) call `wavira_core_*()` functions from `wavira-core/public-api.php`, documented actions/filters, or the REST API | Theme naming a Core class, reading Core internals, or requiring the plugin to render a page |
| Layer direction | foundation (Contracts, Autoloader/Requirements/Cache) → data (Content, Settings) → services (Downloads, Search, Related) → entry points (Rest, Admin, Plugin) | An upward dependency (service using a controller, content using a service) |

**Enforcement:** `tools/check-boundaries.mjs` (gate 7 of `tools/lint.sh`, `docs/CODING-STANDARD.md` A1–A5) checks
every rule above on every commit: no Core → Theme references, downward-only dependencies, no Core class
names in the theme, a direct-access guard in every product file, and the documented function prefixes.

**Degradation contract:** the theme must never assume Core is active. `wavira_has_core()`,
`wavira_get_setting()` and post-type checks guard every data-dependent render, and templates fall back
to WordPress-native loops (posts/pages) when music types are missing.

## 3. Directory contracts

### 3.1 Theme `wavira/`
| Path | Contains | Must not contain |
| --- | --- | --- |
| `functions.php` | constants + `require_once` | any logic (God-file rule) |
| `inc/` | one concern per file, prefixed functions or namespaced classes | business logic for music data |
| `inc/integrations/` | third-party adapters (Rank Math, Yoast, Elementor, WPML) | anything loaded when the integration is absent |
| `inc/markup.php` | the **one** implementation of tracklist, video and genre-chip markup, shared by blocks, shortcodes and templates | a second copy of any content markup, or business logic |
| `inc/blocks.php` | registration of every `blocks/<name>/block.json` (one list, `wavira_block_names()`) | markup or queries of its own |
| `blocks/<name>/` | a dynamic block: `block.json` + `render.php`, plus the shared `editor.js` | stored block HTML, a renderer that bypasses `inc/markup.php`, or a build step the runtime depends on |
| `templates/` | block templates | PHP template hierarchy duplicates |
| `parts/` | template parts | logic |
| `patterns/` | editor patterns (`register_block_pattern`): insertable sections, plus `hidden-*` patterns that only templates reference | data queries beyond what blocks expose |
| `languages/` | the shipped catalogues: `<domain>.pot` (generated, committed), `fa_IR.po` (the translation, hand-edited) and `fa_IR.mo` (compiled by `tools/i18n.mjs build`, loaded by WordPress) | a `.po` without its `.mo`, or a catalogue that drifts from the sources — the `[FA]` gate fails on both |
| `patterns/hidden-*.php` | every user-visible string of a template or part (`Inserter: no`, translated with `esc_html_x()`) — a `.html` template cannot run PHP, so template text would be untranslatable | layout or queries; a hidden pattern that no template references |
| `assets/css/` | token → base → components → utilities sources | framework dumps |
| `assets/css/editor.css` | editor-only parity styles, loaded through `add_editor_style()` | front-end rules (those belong in the layered build) |
| `assets/js/` | ES modules, no jQuery, scoped to `window.Wavira` when global is unavoidable | globals, duplicated handlers |
| `assets/dist/` | build output (git-ignored) | source files |
| `languages/` | `wavira.pot` + `.po/.mo` | hardcoded UI strings anywhere else |

### 3.2 Plugin `wavira-core/`
| Path | Responsibility |
| --- | --- |
| `src/Plugin.php` | boot, i18n, module list, `wavira_core_booted` action |
| `src/Contracts/` | `Registrable`, `Cacheable`, and later `Repository`, `Provider` |
| `src/Content/` | CPTs, taxonomies, meta registration, term meta, upgrade routines |
| `src/Settings/` | one typed settings schema, Settings API + REST exposure |
| `src/Player/` | queue building, playback payloads, Media Session data, bundle registration (`Payload`, `Queue`, `Assets`) — 0.5.0 ✅ |
| `src/Downloads/` | quality matrix, access gate, **atomic counters** (0.4.0 ✅) |
| `src/Search/` | cross-type search + suggestions, `SearchService` (0.4.0 ✅) |
| `src/Related/` | scored related resolution, `RelatedService` (0.4.0 ✅) |
| `src/Rest/` | `wavira/v1` controllers, schemas, permissions |
| `src/Admin/` | editor panels, columns, validation, bulk actions, notices |
| `src/Import/`, `src/Demo/`, `src/Migration/` | data in / data out / data converted — `Migration\LegacySchema` (the audited legacy field map) + `Migration\Migrator` (detect, dry-run, run, rollback; 0.11.0) |
| `src/Integrations/` | optional third-party bridges |
| `src/Support/` | autoloader, requirements, cache, logger, capabilities |
| `public-api.php` | **The only surface a theme may call** (`wavira_core_is_active`, `wavira_core_get_setting`, `wavira_core_related_posts`, `wavira_core_track_playback`, `wavira_core_enqueue_player`) — thin, guarded wrappers over the services |

## 4. Runtime boot sequence

```
1. wavira-core.php           requirements define() + autoloader + Requirements gate
2. plugins_loaded (prio 5)   Plugin::instance()->boot()  → load_plugin_textdomain
3. do_action('wavira_core_booted')  → each module's register()
        Content  → register_post_type / register_taxonomy / register_post_meta (init, prio 0)
        Settings → register_setting + rest schema
        Rest     → register_rest_route (rest_api_init)
        Admin    → admin_menu / metaboxes / columns
        Search / Related / Downloads → services, reached through the REST controllers
                                       (no public routes of their own beyond 0.4.0's additions)
4. after_setup_theme         theme: supports, menus, image sizes, i18n
5. wp_enqueue_scripts        theme: build-aware conditional assets
6. template render           theme reads Core via helpers; blocks call Core services
```

Boot order is explicit and testable: modules never instantiate each other; they receive dependencies
via constructor where needed, or call documented public functions.

## 5. Data ownership & model

Authoritative model lives in `docs/DATA-MODEL.md` (produced in phase 0.3.0). Summary:

| Entity | Storage | Key relation rules |
| --- | --- | --- |
| Artist | CPT `wavira_artist` | many-to-many with tracks/videos via `wavira_artist` meta (IDs), never free text |
| Album | CPT `wavira_album` | tracklist = ordered track IDs; album → artist relation |
| Track | CPT `wavira_track` | primary artist + featured artists (IDs), album (ID), genre terms, audio sources, lyrics |
| Video | CPT `wavira_video` | artist/album relations + source map (self-hosted/YouTube/Vimeo/Aparat) |
| Genre / Mood / Language / Label / Year | taxonomies | dynamic, never hardcoded |

**Registration standards (non-negotiable):**
- Every CPT: `public`, `show_in_rest`, `has_archive`, `rewrite` with stable slug, `supports` explicitly listed.
- Every meta: `register_post_meta()` / `register_term_meta()` with `type`, `single`,
  `sanitize_callback`, `auth_callback`, `show_in_rest` + `schema`.
- No serialized blobs for queryable data. No ACF. No `posts_per_page => -1`.
- Relations are IDs (or taxonomy terms) — never free-text strings (legacy finding D4).

## 6. Extension points (public API surface)

| Kind | Name | Phase |
| --- | --- | --- |
| Action | `wavira_core_booted` | 0.2.0 ✅ |
| Filter | `wavira_archive_per_page` | 0.3.0 |
| Filter | `wavira_setting` (read), `wavira_settings_schema` | 0.3.0 |
| Filter | `wavira_track_playback_payload`, `wavira_player_queue_items`, `wavira_player_settings` | 0.5.0 ✅ |
| DOM event | `wavira:player:<event>` on a player mount (`trackchange`, `play`, `pause`, `queuechange`, `queueend`, `volumechange`, `repeatchange`, `shufflechange`, `loading`, `ready`, `buffering`, `error`) | 0.5.0 ✅ |
| Filter | `wavira_related_ids`, `wavira_related_score` | 0.4.0 ✅ |
| Filter | `wavira_download_quality_matrix`, `wavira_download_quality_sources`, `wavira_download_access` | 0.4.0 ✅ |
| Filter | `wavira_searchable_types` | 0.4.0 ✅ |
| Action | `wavira_download_counted`, `wavira_download_served` | 0.4.0 ✅ |
| Filter | `wavira_settings_sanitized`, `wavira_rest_item` | 0.3.0 ✅ |
| Filter | `wavira_archive_per_page` *(documented seam; applied by the archive templates in 0.6.0)* | 0.6.0 |
| Filter | `wavira_icon` (theme) | 0.6.0 |
| Function | `wavira_get_setting()`, `wavira_has_core()`, `wavira_icon()`, `wavira_related_posts()` (theme) | 0.2.0 / 0.4.0 ✅ |
| Function | `wavira_core_artist_profile()`, `wavira_core_news_feed()` | 0.9.0 ✅ |
| Function | `wavira_core_structured_data()`, `wavira_core_seo_plugin_active()`, `wavira_core_credit_names()`, `wavira_core_cover_image()` | 0.10.0 ✅ |
| Filter | `wavira_core_seo_plugin_active`, `wavira_core_structured_data_enabled`, `wavira_core_structured_data`, `wavira_theme_seo_plugin_active` | 0.10.0 ✅ |
| Filter | `wavira_core_artist_profile`, `wavira_core_news_items` | 0.9.0 ✅ |
| Function | `wavira_core_date_style()`, `wavira_core_date_label()`, `wavira_core_digits()` | 0.10.1 ✅ |
| Filter | `wavira_core_date_style`, `wavira_core_date_format`, `wavira_core_date_label` | 0.10.1 ✅ |
| Block | `wavira/artist-profile`, `wavira/artist-gallery`, `wavira/news` (+ `wavira/tracklist`, `player`, `video`, `genre-chips`) | 0.7.0 / 0.9.0 ✅ |
| Shortcode | `[wavira_artist]`, `[wavira_gallery]`, `[wavira_news]` (+ the 0.7.0 set) | 0.9.0 ✅ |
| Function | `wavira_core_is_active()`, `wavira_core_get_setting()`, `wavira_core_related_posts()` (plugin `public-api.php`) | 0.4.0 ✅ |
| Function | `wavira_core_track_playback()`, `wavira_core_enqueue_player()` (plugin `public-api.php`) | 0.5.0 ✅ |
| Function | `wavira_player_mount()` (theme `inc/player.php`) — prints a mount point + no-JS `<audio>` fallback | 0.5.0 ✅ |
| Script handle | `wavira-player` (registered by Core, enqueued by the theme's mount helper) | 0.5.0 ✅ |
| Function | `wavira_core()` (plugin service accessor) | 0.3.0 |
| REST | `wavira/v1/*` | 0.3.0 |

Everything else is private. No module may be reached through a global variable.

## 7. Security, performance & accessibility as architecture

| Concern | Architectural enforcement |
| --- | --- |
| Escaping | Output escaping happens in the layer that renders (theme/blocks/admin). Services return data, never HTML. |
| Settings | One schema with sanitize callbacks; raw JS ad code is capability-gated (`unfiltered_html`). |
| REST | Permission callback + args schema + `sanitize_callback` on every route; no route without both. |
| Downloads | Authorization happens server-side before a URL is handed out; delivery is a `302` to the stored file (never a PHP byte proxy, never token obfuscation); counters increment atomically; **no DRM claims** (ADR 0013). |
| Caching | Expensive reads only through services implementing `Cacheable`; keys are generation-scoped so one content change invalidates the derived layer; TTLs (search 300 s, related 3600 s) are filterable; no per-user state under a shared key (ADR 0013 §3). |
| Queries | All list queries paginated; counts via `no_found_rows` or cached counters. |
| Assets | Build-aware conditional enqueue; no front-end jQuery; player bundle only where a player exists; gzipped budgets and zero third-party requests enforced by the `[PERF]` gate (ADR 0016 §4). |
| A11y | Components ship keyboard support and ARIA in the component itself (not bolted on at the template). |
| RTL/LTR | Logical CSS properties only; direction verified per component in both modes. |
| Calendar | One policy class (`Content\Dates`) decides between Jalali and Gregorian from the site locale; machine surfaces (REST, feeds, `<time datetime>`, JSON-LD, the admin) are never converted, and a second Jalali plugin can take over with one filter (ADR 0017). |

## 8. What is **not** in v1 (explicitly deferred)

Streaming infrastructure, DRM, subscriptions/billing, SaaS tenancy, mobile apps, AI recommendations,
artist dashboards, licence/update server. Seams exist (REST, hooks, service contracts) but no
implementations — see the brief's YAGNI rule.

## 9. Phase map (where each piece is added)

| Phase | Version | Adds |
| --- | --- | --- |
| 0.2.0 | ARCHITECTURE | this document, ADRs, skeleton, coding standard, CI | ✅ |
| 0.3.0 | DATA MODEL | `src/Content/*` (CPTs, taxonomies, 40 registered meta keys at the time; 46 today), `src/Settings/*`, `wavira/v1` REST, `wp wavira verify/seed`, `docs/DATA-MODEL.md` | ✅ implemented · static verification **VERIFIED** in CI (WPCS + PHPCompatibilityWP + `php -l` on 7.4/8.2/8.3) · runtime verification **NOT_STARTED** — see `docs/VERIFICATION.md` |
| 0.4.0 | MUSIC ENGINE | `src/Search/*`, `src/Related/*`, `src/Downloads/Counter.php`, REST `/search`, `/search/suggest`, `/{type}/{id}/related`, `/download/{id}`, public function API (`public-api.php`), boundary gate, ADR 0013, PHPUnit harness (`tests/`, 49 tests) | ✅ implemented · **VERIFIED**: static gates + 49 integration tests green against a real WordPress on PHP 7.4 and 8.2 (CI run `37301909854`) |
| 0.5.0 | PLAYER | `src/Player/*` + `assets/js/index.js` (state machine, queue, views, Media Session, keyboard, a11y), `wavira/v1/player/*` routes, `wavira_player_mount()`, `tests/js/player.test.mjs` (15 DOM-free tests) | ✅ implemented · **VERIFIED** locally: `node --test tests/js/player.test.mjs` 15/15, `tools/lint.sh` PASS, boundary gate PASS · runtime verification in `docs/VERIFICATION.md` |
| 0.6.0 | UI | tokens → components → templates/patterns, dark/light, RTL/LTR | ✅ implemented · **VERIFIED**: CSS/contrast/token gates, 11 theme JS tests, CI run `37309252018` 8/8 |
| 0.7.0 | BUILDERS | `blocks/*` (four dynamic blocks + editor script), `inc/markup.php`, `inc/blocks.php`, patterns migrated to native blocks, translatable template text (`patterns/hidden-*`), `[BLOCKS]` / `[I18N]` / `[MAPPING]` gates; Elementor widgets still open (see `docs/DECISIONS.md`) | ✅ implemented · **VERIFIED** in CI: runs `37311340378`/`37311334949` and `37314137090`/`37314145602` 8/8, `tests/test-blocks.php` in the integration suite |
| 0.8.0 | PERSIAN-FIRST | `languages/fa_IR.{po,mo}` in both artifacts, `tools/i18n.mjs` (extract/build/check), `[FA]` gate, PHP-printed editor strings | ✅ **VERIFIED** in CI (runs `37317660301`/`37317669115` 8/8, 83 tests / 716 assertions on PHP 7.4 + 8.2) |
| 0.9.0 | ARTIST + NEWS | `Content/ArtistProfile.php`, `News/NewsFeed.php`, `wavira_core_artist_profile()`, `wavira_core_news_feed()`, three blocks, two shortcodes, `single-wavira_artist.html`, `home.html`, `archive.html`, `docs/ARTIST-AND-NEWS.md` | ✅ verified: CI `37354185539`/`37354194397` 8/8; evidence in `docs/VERIFICATION.md` |
| 0.10.0 | SEO + PERF | `Seo\StructuredData`, `Seo\SeoSupport`, `Content\Credit`, `wavira_core_structured_data()`, `wavira_core_seo_plugin_active()`, `wavira_core_credit_names()`, `wavira_core_cover_image()`, `inc/seo.php`, `inc/performance.php`, `[PERF]` gate | ✅ **VERIFIED**: CI `37358851972`/`37358981113` 8/8, 118 tests / 948 assertions; `docs/SEO-AND-PERF.md`, ADR 0016 |
| 0.10.1 | LOCALISATION | `Content\Jalali`, `Content\Dates`, `wavira_core_date_style()`, `wavira_core_date_label()`, `wavira_core_digits()`, Persian demo seeder, Persian `tools/preview/` | ✅ **VERIFIED**: CI `37438117893`/`37438125487` 8/8, 134 tests / 1090 assertions on PHP 7.4 + 8.2; anchor set + forty-year round trip + policy + re-entrancy guard in `tests/test-jalali.php`; ADR 0017, `docs/PERSIAN-LOCALIZATION.md` |
| 0.11.0 | RC | migration tool, demo import, docs, packaging | 🚧 in progress: packaging (`tools/package.mjs`, `[PACKAGE]` gate, CI uploads, RC document, ADR 0019) and the bundled Vazirmatn typeface landed; the pre-upload items (screenshot, listing metadata, real-browser audit) are listed in `docs/RELEASE-CANDIDATE.md` §6. Earlier in the phase: `wp wavira migrate` (`LegacySchema` + `Migrator`, `tests/test-migration.php`) is **VERIFIED** (CI `37440743623`/`37440749435`, 146 tests / 1198 assertions on PHP 7.4 + 8.2); packaging and the docs pass are open |
| 1.0.0 | PRODUCTION | marketplace packages |

See `docs/DECISIONS.md` for the decision list and `docs/CODING-STANDARD.md` for the enforceable rules.
