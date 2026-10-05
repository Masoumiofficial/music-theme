# CODING-STANDARD.md — Wavira enforceable rules

Any change that violates a **MUST** is rejected in review, regardless of how well it "works".
Rules marked 🔒 are additionally checked by tooling (`tools/lint.sh`, CI) or by the pre-release grep gate.

---

## 1. PHP baseline

| # | Rule |
| --- | --- |
| P1 🔒 | **MUST** stay compatible with the declared floor: PHP 7.4 syntax, tested on 8.2/8.3. No 8.0+ only syntax (`match`, constructor promotion, enums, readonly, named args) until the floor is raised. |
| P2 🔒 | **MUST NOT** use deprecated APIs: `create_function`, `each()`, `wp_title()`, `the_author_email()`, `get_bloginfo('url')`, `wp_is_mobile()` for layout decisions, `$wpdb` without `prepare()`. |
| P3 🔒 | Every PHP file starts with a docblock (`@package`, description) and, for files directly reachable by URL, `defined( 'ABSPATH' ) \|\| exit;`. |
| P4 | **MUST** follow WordPress naming for theme functions (`wavira_*`), classes in `Wavira\Core\*` (PSR-4, one class per file, file name = class name). |
| P5 🔒 | Class names: `StudlyCaps`; methods/properties: `camelCase` for plugin classes, `snake_case` for WordPress-facing functions; hooks: `wavira_lowercase_with_underscores`. |
| P6 | **MUST NOT** exceed 400 lines per class or 50 lines per method without a documented reason (God-file rule from the audit). |
| P7 | **MUST NOT** use unprefixed globals, `extract()`, `$$var`, or `compact()`-style variable juggling. |
| P8 🔒 | Multi-line array literals: **MUST** align every `=>` in an alignment group to the **longest key of that group + one space** (WPCS `WordPress.Arrays.MultipleStatementAlignment`). A group is the run of sibling keys between two comments, statements or sibling arrays; nested arrays align independently, and the outer group continues *across* a nested array block. Alignment padding is spaces; indentation stays tabs (`phpcs.xml.dist`, CI annotations). |

## 2. WordPress API usage

| # | Rule |
| --- | --- |
| W1 🔒 | Content uses `register_post_type()` / `register_taxonomy()`; meta uses `register_post_meta()` / `register_term_meta()` with `type`, `single`, `sanitize_callback`, `auth_callback`, `show_in_rest` + schema. |
| W2 🔒 | **MUST NOT** register a CPT/taxonomy/meta outside `Wavira\Core\Content\*`. |
| W3 🔒 | No `posts_per_page => -1` (or equivalent unbounded query) anywhere. |
| W4 | Templates use the block-template hierarchy or the classic hierarchy consistently per template; a template never queries data that a block/part already provides. |
| W5 🔒 | Settings use the Settings API with a sanitize callback per field; **no** raw JS/HTML field is writable by a role without `unfiltered_html`. |
| W6 | Integrations (ACF, Elementor, Rank Math, Yoast, WPML) are optional adapters in `inc/integrations/` or `src/Integrations/` — never a hard dependency. |

## 3. Security (non-negotiable)

| # | Rule |
| --- | --- |
| S1 🔒 | Escape on output, always by context: `esc_html`, `esc_attr`, `esc_url`, `esc_js`, `wp_kses_post`, `wp_json_encode`. `echo $var;` without escaping is forbidden. |
| S2 🔒 | Sanitize on input: `sanitize_text_field`, `sanitize_key`, `sanitize_email`, `absint`, `esc_url_raw`, per-field callbacks. |
| S3 🔒 | Every state-changing request verifies a nonce (`wp_nonce_field`/`check_admin_referer`/`wp_verify_nonce`) **and** a capability (`current_user_can`). No exceptions. |
| S4 🔒 | Every REST route declares `permission_callback` (never `__return_true` for anything that writes) and an `args` schema with `sanitize_callback`. |
| S5 🔒 | `$wpdb` usage requires a prepared statement; preferred: WP_Query/WP_User_Query/WP_Term_Query. |
| S6 | File operations use `WP_Filesystem` where WordPress expects it; uploads are validated by type + size and never trusted for their extension. |
| S7 | Audio/download access control is server-side and documented; **never** claim DRM or "download protection" beyond what is implemented. |
| S8 | No third-party request, tracking pixel or telemetry without explicit opt-in and documentation. |

## 4. JavaScript

| # | Rule |
| --- | --- |
| J1 🔒 | No front-end jQuery (`$`, `jQuery`), no `window.jQuery` dependency. |
| J2 🔒 | ES modules with explicit imports; no globals. If a public surface is unavoidable it is exactly one: `window.Wavira`. |
| J3 | One instance of a component class per mount element; all state lives in the instance, never in module-level variables (legacy player bug: global `nowPlaying`, `document.getElementById('audio')`). |
| J4 🔒 | No `innerHTML` with dynamic data; use `textContent` / `createElement` / `<template>`. |
| J5 | Event delegation via a single listener per component root; remove listeners on destroy (`AbortController` or `destroy()`). |
| J6 | Accessibility ships with the component: keyboard handlers, ARIA state, focus management, `prefers-reduced-motion` respect. |
| J7 | Network calls: debounce ≥ 300 ms for typing, cancel in-flight requests, handle non-2xx with a user-visible message (never a raw error). |

## 5. CSS

| # | Rule |
| --- | --- |
| C1 🔒 | Tokens only: colours, spacing, radius, shadows and durations come from `--wavira-*` / `theme.json` presets. No magic hex values in components. |
| C2 🔒 | Logical properties only (`margin-inline`, `padding-inline`, `inset-inline`, `border-inline-*`); `left`/`right` are allowed only for values that are genuinely physical (e.g. `transform: translateX`). |
| C3 🔒 | No `!important` except in a documented reset (each use carries a comment). |
| C4 | Specificity stays shallow: max 3 levels of nesting in the source, no ID selectors. |
| C5 | Both themes (light/dark) and both directions (RTL/LTR) verified for every new component; contrast ≥ 4.5:1 body text, ≥ 3:1 large text/UI. |
| C6 🔒 | No duplicate declarations; no unused selectors (checked by the pre-release audit). |

## 6. Internationalisation

| # | Rule |
| --- | --- |
| I1 🔒 | Every user-facing string uses `__()`, `_e()`, `esc_html__()`, `esc_attr__()`, `_n()` with the correct text domain (`wavira` in the theme, `wavira-core` in the plugin). |
| I2 | No hardcoded Persian/English copy in templates. No translatable string built by concatenation — use `sprintf` with placeholders. |
| I3 | Dates/numbers use WordPress' localisation helpers (`wp_date`, `number_format_i18n`) — never PHP `date()`. |
| I4 🔒 | No direction-specific markup: `dir` comes from `language_attributes()` / `is_rtl()` only. |

## 7. Performance

| # | Rule |
| --- | --- |
| F1 🔒 | All queries paginated and limited; heavy reads go through a service implementing `Cacheable`. |
| F2 🔒 | Assets: conditional enqueue only; built files only; no library added without a size justification in the PR. |
| F3 🔒 | Never disable core `srcset`/`sizes`; every `<img>`/`get_the_post_thumbnail()` call passes `loading`, `decoding`, and `width`/`height` (or a `sizes` array that does). |
| F4 | Autoloaded options are kept small; new options are registered with `autoload: false` unless needed on every request. |
| F5 🔒 | No `autoplay`+`loop` media, no carousel autoplay without a pause control and reduced-motion support. |

## 8. Tests, docs, reviews

| # | Rule |
| --- | --- |
| T1 | Each module lands with: the code, its docblock-level documentation, and at least one verification path (WP-CLI seed script, PHPUnit test in `tests/`, or a documented manual test in `docs/QA.md`). |
| T2 🔒 | `tools/lint.sh` must pass (PHP syntax + WPCS where available, JS syntax, JSON validity, asset-size report). |
| T3 | Every architectural decision that changes these rules requires an ADR in `docs/adr/` and an update to this file. |
| T4 | Definition of Done (per brief): implemented · tested · documented · translated · accessible · responsive · secure · performant · compatible · reviewed. |

## 9. Pre-release "legacy-echo" gate (grep-based)

Run before every package build; every hit must be fixed or explicitly justified in the release notes:

```bash
# brand pollution
grep -rniE "javanseda|javan seda|جوان صدا|tarlanweb|rkianoosh|rtl-theme|rezakianoosh|09158856205" \
  wavira wavira-core --include="*.php" --include="*.js" --include="*.css" --include="*.json"
# forbidden legacy patterns
grep -rn "posts_per_page" wavira wavira-core | grep -E "[-']1['\"]?\s*[,)]"      # unbounded
grep -rn "wp_calculate_image_srcset" wavira wavira-core                          # srcset killers
grep -rn "wp_is_mobile" wavira wavira-core                                       # UA sniffing
grep -rn "getElementById( *[\"']audio" wavira wavira-core                        # global player id
grep -rn "create_function\|wp_title(" wavira wavira-core                         # deprecated APIs
grep -rn "!important" wavira/assets/css wavira-core/assets/css | wc -l           # must be documented uses only
grep -rn "jQuery\|\$(" wavira/assets/js wavira-core/assets/js                    # no front-end jQuery
```
