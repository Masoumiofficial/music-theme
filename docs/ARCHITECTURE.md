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
| `src/Downloads/` | quality matrix, access gate, counters |
| `src/Search/` | cross-type search (debounced, cached) |
| `src/Related/` | related artist/album/track/video resolution |
| `src/Rest/` | `wavira/v1` controllers, schemas, permissions |
| `src/Admin/` | editor panels, columns, validation, bulk actions, notices |
| `src/Import/`, `src/Demo/`, `src/Migration/` | data in / data out / data converted |
| `src/Integrations/` | optional third-party bridges |
| `src/Support/` | autoloader, requirements, cache, logger, capabilities |

## 4. Runtime boot sequence

```
1. wavira-core.php           requirements define() + autoloader + Requirements gate
2. plugins_loaded (prio 5)   Plugin::instance()->boot()  → load_plugin_textdomain
3. do_action('wavira_core_booted')  → each module's register()
        Content  → register_post_type / register_taxonomy / register_post_meta (init, prio 0)
        Settings → register_setting + rest schema
        Rest     → register_rest_route (rest_api_init)
        Admin    → admin_menu / metaboxes / columns
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
| Filter | `wavira_related_items` | 0.4.0 |
| Filter | `wavira_download_quality_matrix` | 0.4.0 |
| Filter | `wavira_icon` (theme) | 0.6.0 |
| Function | `wavira_get_setting()`, `wavira_has_core()` (theme) | 0.2.0 ✅ |
| Function | `wavira_core()` (plugin service accessor) | 0.3.0 |
| REST | `wavira/v1/*` | 0.3.0 |

Everything else is private. No module may be reached through a global variable.

## 7. Security, performance & accessibility as architecture

| Concern | Architectural enforcement |
| --- | --- |
| Escaping | Output escaping happens in the layer that renders (theme/blocks/admin). Services return data, never HTML. |
| Settings | One schema with sanitize callbacks; raw JS ad code is capability-gated (`unfiltered_html`). |
| REST | Permission callback + args schema + `sanitize_callback` on every route; no route without both. |
| Downloads | Authorization happens server-side before a URL is handed out; signed URLs optional; **no DRM claims**. |
| Caching | Expensive reads only through services implementing `Cacheable`; cache keys include all arguments; flush on save. |
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
| 0.3.0 | DATA MODEL | `src/Content/*` (CPTs, taxonomies, 38 registered meta keys), `src/Settings/*`, `wavira/v1` REST, `wp wavira verify/seed`, `docs/DATA-MODEL.md` | ✅ implemented · static verification **VERIFIED** in CI (WPCS + PHPCompatibilityWP + `php -l` on 7.4/8.2/8.3) · runtime verification **NOT_STARTED** — see `docs/VERIFICATION.md` |
| 0.4.0 | MUSIC ENGINE | Search, Related, Downloads, counters |
| 0.5.0 | PLAYER | `assets/js/player/*` module, Media Session, a11y |
| 0.6.0 | UI | tokens → components → templates/patterns, dark/light, RTL/LTR |
| 0.7.0 | BUILDERS | blocks + Elementor widgets |
| 0.8.0 | SEO + PERF | SEO cooperation, budgets met |
| 0.9.0 | RC | migration tool, demo import, docs, packaging |
| 1.0.0 | PRODUCTION | marketplace packages |

See `docs/DECISIONS.md` for the decision list and `docs/CODING-STANDARD.md` for the enforceable rules.
