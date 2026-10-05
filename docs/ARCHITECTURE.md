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

**Enforcement:** `tools/check-boundaries.mjs` (gate 6 of `tools/lint.sh`, `docs/CODING-STANDARD.md` A1–A5) checks
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
| `templates/` | block templates | PHP template hierarchy duplicates |
| `parts/` | template parts | logic |
| `patterns/` | editor patterns (`register_block_pattern`) | data queries beyond what blocks expose |
| `assets/css/` | token → base → components → utilities sources | framework dumps |
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
| `src/Player/` | queue building, playback payloads, Media Session data |
| `src/Downloads/` | quality matrix, access gate, **atomic counters** (0.4.0 ✅) |
| `src/Search/` | cross-type search + suggestions, `SearchService` (0.4.0 ✅) |
| `src/Related/` | scored related resolution, `RelatedService` (0.4.0 ✅) |
| `src/Rest/` | `wavira/v1` controllers, schemas, permissions |
| `src/Admin/` | editor panels, columns, validation, bulk actions, notices |
| `src/Import/`, `src/Demo/`, `src/Migration/` | data in / data out / data converted |
| `src/Integrations/` | optional third-party bridges |
| `src/Support/` | autoloader, requirements, cache, logger, capabilities |
| `public-api.php` | **The only surface a theme may call** (`wavira_core_is_active`, `wavira_core_get_setting`, `wavira_core_related_posts`) — thin, guarded wrappers over the services |

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
| Action | `wavira_before_play`, `wavira_after_play` (playback events) | 0.5.0 |
| Filter | `wavira_archive_per_page` | 0.3.0 |
| Filter | `wavira_setting` (read), `wavira_settings_schema` | 0.3.0 |
| Filter | `wavira_track_playback_payload` | 0.5.0 |
| Filter | `wavira_related_ids`, `wavira_related_score` | 0.4.0 ✅ |
| Filter | `wavira_download_quality_matrix`, `wavira_download_quality_sources`, `wavira_download_access` | 0.4.0 ✅ |
| Filter | `wavira_searchable_types` | 0.4.0 ✅ |
| Action | `wavira_download_counted`, `wavira_download_served` | 0.4.0 ✅ |
| Filter | `wavira_settings_sanitized`, `wavira_rest_item` | 0.3.0 ✅ |
| Filter | `wavira_archive_per_page` *(documented seam; applied by the archive templates in 0.6.0)* | 0.6.0 |
| Filter | `wavira_icon` (theme) | 0.6.0 |
| Function | `wavira_get_setting()`, `wavira_has_core()`, `wavira_icon()`, `wavira_related_posts()` (theme) | 0.2.0 / 0.4.0 ✅ |
| Function | `wavira_core_is_active()`, `wavira_core_get_setting()`, `wavira_core_related_posts()` (plugin `public-api.php`) | 0.4.0 ✅ |
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
| Assets | Build-aware conditional enqueue; no front-end jQuery; player bundle only where a player exists. |
| A11y | Components ship keyboard support and ARIA in the component itself (not bolted on at the template). |
| RTL/LTR | Logical CSS properties only; direction verified per component in both modes. |

## 8. What is **not** in v1 (explicitly deferred)

Streaming infrastructure, DRM, subscriptions/billing, SaaS tenancy, mobile apps, AI recommendations,
artist dashboards, licence/update server. Seams exist (REST, hooks, service contracts) but no
implementations — see the brief's YAGNI rule.

## 9. Phase map (where each piece is added)

| Phase | Version | Adds |
| --- | --- | --- |
| 0.2.0 | ARCHITECTURE | this document, ADRs, skeleton, coding standard, CI | ✅ |
| 0.3.0 | DATA MODEL | `src/Content/*` (CPTs, taxonomies, 40 registered meta keys), `src/Settings/*`, `wavira/v1` REST, `wp wavira verify/seed`, `docs/DATA-MODEL.md` | ✅ implemented · static verification **VERIFIED** in CI (WPCS + PHPCompatibilityWP + `php -l` on 7.4/8.2/8.3) · runtime verification **NOT_STARTED** — see `docs/VERIFICATION.md` |
| 0.4.0 | MUSIC ENGINE | `src/Search/*`, `src/Related/*`, `src/Downloads/Counter.php`, REST `/search`, `/search/suggest`, `/{type}/{id}/related`, `/download/{id}`, public function API (`public-api.php`), boundary gate, ADR 0013, PHPUnit harness (`tests/`, 49 tests) | ✅ implemented · **VERIFIED**: static gates + 49 integration tests green against a real WordPress on PHP 7.4 and 8.2 (CI run `37301909854`) |
| 0.5.0 | PLAYER | `assets/js/player/*` module, Media Session, a11y |
| 0.6.0 | UI | tokens → components → templates/patterns, dark/light, RTL/LTR |
| 0.7.0 | BUILDERS | blocks + Elementor widgets |
| 0.8.0 | SEO + PERF | SEO cooperation, budgets met |
| 0.9.0 | RC | migration tool, demo import, docs, packaging |
| 1.0.0 | PRODUCTION | marketplace packages |

See `docs/DECISIONS.md` for the decision list and `docs/CODING-STANDARD.md` for the enforceable rules.
