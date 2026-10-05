# VERIFICATION.md — evidence log

Every claim the project makes about itself is backed by an entry here. Nothing is
"done" because it was written; it is done when the row below shows evidence.

**Status vocabulary** — `NOT_STARTED` · `IN_PROGRESS` · `IMPLEMENTED` (code exists,
no evidence yet) · `TESTED` (executed somewhere, result recorded) · `VERIFIED`
(executed **and** the recorded result passes on the declared platform) ·
`BLOCKED` · `NEEDS_REVIEW`.

Rule: static analysis never substitutes for runtime testing, and CI syntax checks
never substitute for a real WordPress install.

---

## Environments used

| Ref | Environment | Available since | What it can prove |
| --- | --- | --- | --- |
| `CI` | GitHub Actions `ubuntu-latest`: PHP 7.4 / 8.2 / 8.3, WPCS 3.x + PHPCompatibilityWP (testVersion `7.4-`), Node 20 | commit `423c020` | PHP syntax, WordPress Coding Standards, i18n domains, PHP compatibility floor, JS/JSON syntax, build dry-run, legacy artifact hash |
| `LOCAL-AUTHORING` | Sandbox without PHP/Composer (Node 22, Python 3.11 only) | — | JS/JSON lint, grep gates, structural cross-reference checks, array/equals alignment approximation — **never** PHP syntax or PHP behaviour |
| `WP-CI` | GitHub Actions `wp-integration` job: WordPress (latest) + MariaDB 10.11 + the WordPress test library, PHP 7.4 and 8.2, `composer test`; since 0.5.0 it also builds the front-end bundle (`node tools/build.mjs`) so the enqueue path is exercised for real | commit `d5e0f6e`, first green run `37301535701`; latest verified run `37311340378` — commit `8217e13` (76 tests, 673 assertions on both PHP legs) | CPT/taxonomy/meta **registration**, settings persistence and clamping, REST dispatch (routes, headers, args), download authorization and counters, search/related services, cache invalidation — everything the 49 integration tests cover |
| `WP-RUNTIME` | A real WordPress site, owner-provided or a full WP install with WP-CLI | **not yet available** | Rewrite resolution after activation, `wp wavira verify/seed`, admin UI, real HTTP responses, front-end rendering, performance budgets |

---

## 0.3.0 — Music data model

| Claim | Status | Evidence |
| --- | --- | --- |
| All PHP files parse on the declared floor and above | **VERIFIED** | CI run on `e54e6a3…f00e6d1…1a5e1e0` (`php-lint` matrix): `php -l` passes on 7.4, 8.2, 8.3 |
| Code meets WordPress Coding Standards (Core/Docs/Extra) | **VERIFIED** | CI job `WPCS + PHP compatibility` → success, zero errors and zero warnings |
| Code stays compatible with PHP ≥ 7.4 | **VERIFIED** | same CI job, `PHPCompatibilityWP` with `testVersion=7.4-` |
| i18n text domains are `wavira` / `wavira-core` | **VERIFIED** | `WordPress.WP.I18n` configured with those domains; part of the passing WPCS job |
| Legacy `music-theme.zip` is byte-identical to the audit baseline | **VERIFIED** | CI job `Legacy artifact integrity`, md5 `a23269c2b92a3ba08721dba79a50f1dd` |
| JS/JSON assets parse; build dry-run has no missing sources | **VERIFIED** | CI job `JS, JSON, gates, build` → success |
| Post types, taxonomies and the 46 registered meta keys register correctly | **VERIFIED** | `WP-CI` run `37301909854` → `Test_Content_Registration` (post types, archive slugs, rewrite bases, `show_in_rest`, meta registry incl. the counters staying out of REST) |
| REST routes answer with the documented shapes and headers | **VERIFIED** | `WP-CI` run `37301909854` → `Test_Rest_Api` (collection pagination headers, `X-WP-Total(-Pages)`, `per_page` clamp, draft exclusion, search/suggest/related payloads) |
| Download authorization chain behaves as documented | **VERIFIED** | `WP-CI` run `37301909854` → `Test_Downloads::test_access_rules` + `test_forbidden_download_is_refused` (403/401 without leaking a URL) |
| Cache invalidation fires on the intended hooks | **VERIFIED** | `WP-CI` run `37301909854` → `Test_Cache` (content save, settings update incl. the first save, generation isolation) |
| Settings persist, clamp and drop unknown keys | **VERIFIED** | `WP-CI` run `37301909854` → `Test_Meta_Settings::test_settings_sanitizer_bounds_and_unknown_keys` |
| Rewrite rules resolve after activation (one-time flush) | **NOT_STARTED** | needs a real HTTP request against a permalink — `WP-RUNTIME` |
| `wp wavira verify` reports zero problems on a clean install | **NOT_STARTED** | needs WP-CLI on a real install (the CI job runs PHPUnit, not WP-CLI) — scheduled with the 0.9.0 packaging job |

Deliberate limitation recorded here: the authoring environment has no PHP, so
`NOT_STARTED` rows were **not** silently downgraded to "probably fine". They stay
open until they run on WordPress.

---

## 0.4.0 — Music engine and the verification harness

| Claim | Status | Evidence |
| --- | --- | --- |
| The integration suite (49 tests) passes on a real WordPress with PHP 7.4 | **VERIFIED** | CI run `37301909854`, job `WordPress integration (PHP 7.4)` → `OK (49 tests, 440 assertions)` |
| …and on PHP 8.2 | **VERIFIED** | same run, job `WordPress integration (PHP 8.2)` → `OK (49 tests, 440 assertions)` |
| The suite genuinely exercises the product (not a smoke test) | **VERIFIED** | it found six defects on its first two runs, all fixed and re-verified: double-counted first download, ignored per-track opt-out, ignored schema defaults for optional taxonomies, missing cache flush on the first settings save, `sanitize_title` returning the request object for an empty REST argument, and `absint()` flipping an out-of-range negative setting |
| Search ranks and paginates as documented | **VERIFIED** | `WP-CI` → `Test_Rest_Api::test_search_route`, `test_search_arguments_are_bounded`, `Test_Search_Related` |
| Related items are scored from genre/artist/album/featured and cached | **VERIFIED** | `WP-CI` → `Test_Search_Related` (scoring order, cache generation, filter seam) |
| Download counters increment atomically and exactly once per served request | **VERIFIED** | `WP-CI` → `Test_Downloads::test_counters_increment_and_read_back`, `test_download_endpoint_redirects_by_default` (`X-Wavira-Download-Count: 1`) |
| The public function API of the plugin behaves as documented | **VERIFIED** | `WP-CI` → `Test_Public_Api` (`wavira_core_is_active/get_setting/related_posts`, schema defaults, safe fallbacks) |
| The architecture's dependency rules hold in the code | **VERIFIED** | `tools/check-boundaries.mjs`, gate 7 of `tools/lint.sh`, passing locally and in the `JS, JSON, gates, build` job |
| PHPUnit harness runs locally as documented (`composer test`) | **IMPLEMENTED** | `composer.json` scripts + `phpunit.xml.dist` + `bin/install-wp-tests.sh`; executed on CI, not yet re-run by the owner locally |

---

## 0.5.0 — Player engine

| Claim | Status | Evidence |
| --- | --- | --- |
| The engine's state machine, queue maths and persistence run without a DOM | **TESTED** | `LOCAL-AUTHORING`: `node --test tests/js/player.test.mjs` → 15/15 passing on Node v22.22.3 (engine loaded in `node:vm` with no `document`); the CI job `JS, JSON, gates, build` runs the same command |
| Repeat/shuffle/queue-removal semantics are the documented ones | **TESTED** | same suite: `advance walks the queue and honours repeat`, `shuffleOrder keeps every index and starts at the current track`, `queue edits keep the current track playing`, `playback starts from the first queue item when nothing is selected` |
| Volume, mute, repeat and shuffle persist under `wavira.player.*`, never in cookies | **TESTED** | same suite: `volume and mute are clamped and persisted under the player prefix`, `the shipped engine keeps the documented safety locks` (no `document.cookie`, no `eval`, no `innerHTML=`, no global element ID) |
| A failed fetch produces a user-visible error state, not an exception | **TESTED** | same suite: `engines survive a failed fetch and report a usable error` |
| The player bundle stays inside the ≤ 15 KB (gzip) budget | **TESTED** | `LOCAL-AUTHORING`: `node tools/build.mjs` → `wavira-core/assets/dist/core.js` = 53.1 KB raw, **13.6 KB gzipped** (`gzip -9`); the CI job prints the shipped size on every run |
| Playback payload, queue builders and the `wavira/v1/player/*` routes answer as documented | **VERIFIED** | `WP-CI` run `37304843180` (both PHP legs, `OK (66 tests, 569 assertions)`) → `Test_Player`: payload shape, artwork with `srcset`/`sizes`, empty sources for a source-less track, both filters, five queue contexts and their order, `MAX_ITEMS`, route status codes (200/404/400), `Cache-Control`, settings contract, public API, audio-key mapping, no private paths |
| The engine bundle is registered and configured by the server, and the theme only asks for the handle | **VERIFIED** | `WP-CI` run `37304843180` → `Test_Player::test_public_api_exposes_playback_and_enqueue` (`wavira-player` registered, `waviraPlayerSettings` in the inline script); both integration legs build the bundle (`node tools/build.mjs`) before running the suite |
| A track page works without JavaScript | **IMPLEMENTED** | `wavira_player_mount()` renders a native `<audio>` fallback with the preferred source; the engine removes it when it takes over. Browser verification is an 0.6.0 template task (`WP-RUNTIME`) |
| Media Session metadata and action handlers | **IMPLEMENTED** | feature-detected adapter; devices/browsers cannot be verified in this environment — scheduled for manual verification with the 0.6.0 UI |
| The documented keyboard map works | **IMPLEMENTED** | handler code + map in ADR 0005 §4; browser verification pending with the 0.6.0 UI |
| Architecture boundaries still hold with the new layers | **VERIFIED** | `tools/lint.sh` gate `[BOUNDARIES]` (`check-boundaries.mjs`): 37 plugin files, 11 theme files → `RESULT: PASS` (`LOCAL-AUTHORING`, 2026-10-05) |
| No legacy string, jQuery or forbidden pattern entered the player code | **VERIFIED** | `tools/lint.sh` gate 6 (legacy-echo gate) → PASS; the JS lock test additionally forbids `innerHTML=`, `eval(`, `document.cookie` and a global audio ID |
| Every gate the product ships through is green on the player code | **VERIFIED** | `WP-CI` run `37304843180` (commit `88b6b67`): all 8 jobs success — WPCS + PHP compatibility, PHP 7.4/8.2/8.3 syntax, `WordPress integration` (PHP 7.4 and 8.2), `JS, JSON, gates, build`, legacy artifact integrity |
| A class member that does not exist fails locally instead of costing a CI round | **VERIFIED** | `tools/lint.sh` gate `[REFS]` (`tools/check-class-refs.py`) → `checked 58 file(s); 0 problem(s)`; the gate resolves every `Class::member()` / `new Class()` against the repository and would have caught the missing `MetaSchema::audio_key()` and the missing `Terms` import |

The first two 0.5.0 CI runs were red, and the defects they found are fixed and re-verified by run
`37304843180`: `MetaSchema::audio_key()` was called before it existed (every player request fatal),
`Rest\ContentController` used `Content\Terms` without importing it, `Queue` let WordPress re-sort an
album tracklist by date instead of keeping the editor's playing order (ADR 0012), a queue shortened
itself when a candidate had no source, the `player` string the engine uses as the mount's accessible
name was missing from the server's string table, and WPCS flagged four violations (three missing
translators comments, a missing string, a blank line before a closing brace). The local gate that
catches the first class of defect now ships as `tools/check-class-refs.py`.

Deliberate limitation: the engine's *visual* behaviour (Media Session surfaces, focus rings,
`prefers-reduced-motion`) needs a browser and a rendered template, which does not exist before 0.6.0.
Those rows stay `IMPLEMENTED` rather than being reported as verified.

---

---

## 0.6.0 — Theme UI

| Claim | Status | Evidence |
| --- | --- | --- |
| The stylesheets obey the product's CSS rules: no `!important`, logical properties only | **VERIFIED** | `LOCAL-AUTHORING`: `node tools/check-css.mjs` → 6 stylesheets pass rules 1–2 |
| Every `theme.json` palette colour is remapped for dark mode | **VERIFIED** | same run → `dark-mode parity: 9 palette colour(s) remapped`, so a palette entry cannot silently stay light |
| Every `--wp--preset--*`, `--wp--custom--*` and `--wp--style--*` reference resolves to something WordPress generates from `theme.json` | **VERIFIED** | `[CSS]` rule 6 → `design tokens: 11 custom properties resolvable`; **negative test performed**: reverting `--wp--custom--player--bar-space` to camelCase makes the gate fail with that exact reference |
| Contrast meets WCAG 2.2 AA in both colour modes | **VERIFIED** | `LOCAL-AUTHORING`: `node tools/check-contrast.mjs` → `OK 21 contrast pair(s)` (light focus ring 9.80:1, dark 12.09:1); the gate found and fixed a real dark-mode defect — light-mode chip text on the dark accent measured **1.51:1**, now the `--wavira-on-accent` token |
| The theme script's colour modes, labels, storage and player bootstrapping are covered by unit tests | **TESTED** | `node --test tests/js/theme.test.mjs` → 11/11 on Node v22.22.3 (modes + `nextMode` cycle, defensive storage, junk-mode rejection, toggle labelling, click cycling, `initPlayers` present/absent/throwing, source locks, and a PHP↔JS storage-key parity assertion against `inc/assets.php`) |
| The player view actually mounts the queue list it builds | **TESTED** | source-lock test in `tests/js/player.test.mjs` — the omission (built, populated, never appended) survived a whole phase because no DOM-free suite can see it |
| Built bundles stay inside the ADR 0009 budgets | **VERIFIED** | `[CSS]`/`[SIZE]`: theme CSS 5.9 KB gzip (≤ 25), theme script 2.5 KB (≤ 10), player CSS 2.7 KB (≤ 6), engine 13.9 KB (≤ 15) |
| Templates, parts, patterns and shortcodes render real music content | **NOT_STARTED** | needs a rendered WordPress page — `WP-RUNTIME`. Templates are complete on disk but no template has produced an HTML response yet |
| RTL/LTR parity, dark/light and the 360→1920 breakpoint matrix are verified in a browser | **NOT_STARTED** | the authoring sandbox has no browser; `tools/preview/` renders the shipped CSS/JS with stubbed REST data so a human can perform the pass — the rows stay `NOT_STARTED` until someone records the result |
| Editor styles match the front end | **IMPLEMENTED** | `assets/css/editor.css` via `add_editor_style()`; visual comparison needs a real editor session (`WP-RUNTIME`) |
| Every gate runs in CI on the 0.6.0 commit | **VERIFIED** | CI run `37309015331` (push, commit `beed214`): all 8 jobs success — `WPCS + PHP compatibility`, PHP 7.4/8.2/8.3 syntax, `JS, JSON, gates, build` (runs `tools/lint.sh` with PHP present, so the `[CSS]` and `[CONTRAST]` gates ran there too), `WordPress integration` on PHP 7.4 and 8.2, legacy artifact integrity |
| The theme has PHP-level test coverage of its own | **NOT_STARTED** | the integration suite covers plugin behaviour; theme templates and helpers are exercised only once a real install renders them (`WP-RUNTIME`) |

**Defects found and closed in 0.6.0** (all with the evidence above): the queue `<ol>` was never
appended; `--wp--custom--player--barSpace` and `--wp--custom--player--barHeight` never resolved
(camelCase vs. WordPress' kebab-casing), so two settings silently did nothing; dark-mode chip text
failed contrast at 1.51:1; the shipped product URIs pointed at the unregistered `wavira.com`.

## 0.8.0 — Persian-first localisation

| Claim | Status | Evidence |
| --- | --- | --- |
| Both artifacts ship a complete Persian catalogue | **VERIFIED** | `[FA]` gate (`node tools/i18n.mjs check`): 174/174 strings translated — 58 in `wavira` (patterns, block metadata, template strings, player payload) and 116 in `wavira-core` (settings, labels, REST descriptions, player strings, CLI demo content) — with the `.pot` matching the sources and the `.mo` matching the `.po` |
| The `.mo` WordPress loads is a valid GNU catalogue | **VERIFIED** | `tests/js/i18n.test.mjs`: a compiled catalogue round-trips through the reader; the string table keeps every id before every value and the ids are sorted (the writer's first version interleaved the offsets — the file looked fine and translated nothing, which is why this is a test and not a review) |
| WordPress returns Persian for the strings the product prints | **VERIFIED** | `tests/test-i18n.php` loads both shipped `.mo` files with `load_textdomain()` and asserts the front-end, admin, block-metadata, player and placeholder strings come back in Persian; a string outside the catalogue falls through to its source instead of printing nothing |
| Block metadata is translated in the contexts core looks it up with | **VERIFIED** | the extractor reads `block.json` and emits `block title` / `block description` / `block keyword` (the contexts of `wp-includes/block-i18n.json`, applied by core's `translate_settings_using_i18n_schema()`); `Test_I18n::test_block_metadata_is_persian` asserts the lookup end to end |
| The editor is Persian without a JSON script translation | **VERIFIED** | `wavira_block_editor_strings()` is printed into `window.waviraBlocks` by `wp_add_inline_script()`; `Test_Blocks::test_editor_strings_are_in_the_catalogue` asserts every key is a msgid of the shipped catalogue, so a string edited in one place only cannot leave the editor English |
| Admin labels are translated before they are registered | **VERIFIED** | `Plugin::boot()` calls `load_textdomain()` before the modules register post types, taxonomies and settings, so `PostTypes` labels are built from translated strings (code path read in `wavira-core/src/Plugin.php`; the strings themselves are asserted in `Test_I18n::test_admin_strings_come_back_in_persian`) |
| RTL is a build failure, not a habit | **VERIFIED** | `tools/check-css.mjs` rule 2 rejects physical direction properties (`margin-left`, `padding-right`, `text-align: left`, `left:`/`right:`, `inset-left/right`) across all six stylesheets; the RTL guarantee therefore holds for every future component too |
| A translation cannot break `sprintf` | **VERIFIED** | the `[FA]` gate compares the placeholders of every translation with its source (`%d kbps` → `%d کیلوبیتبرثانیه`), and `Test_I18n::test_placeholders_survive_translation` asserts the rendered result |
| Jalali (Shamsi) dates | **NOT_STARTED** | decided in ADR 0015 §8 / `docs/DECISIONS.md`: a calendar conversion ships only with verified anchor dates; until then `wp_date()` with `fa_IR` locale data is used |
| A bundled Persian font | **NOT_STARTED** | the token set already prefers Vazirmatn (OFL-1.1) with system fallbacks; bundling a subset font is a packaging decision (ADR 0010 licence handling, ADR 0009 size budget) |

**CI evidence.** Commit `42aedc6` shipped the pipeline, both catalogues, the tests, ADR 0015 and the
docs; `d99260f` added the WPCS fix (an alignment warning in the editor string map) and the regenerated
POT. Runs `37317400311`/`37317406843` (7/8, only the coding-standard job red) and
`37317660301`/`37317669115` (8/8) both ran the updated suites: `OK (83 tests, 716 assertions)` on PHP
7.4 and 8.2 — including `tests/test-i18n.php` (the shipped `.mo` files actually return Persian) and the
editor-catalogue cross-check in `tests/test-blocks.php`.

**Notes from writing the 0.8.0 catalogue:** the theme's translatable surface is small (58 strings) because
template text already lives in patterns (0.7.0); the plugin's 116 strings are almost entirely admin and
player surfaces, which is exactly where an Iranian site owner spends their time. The catalogue was
generated with `tools/i18n.mjs extract` and then translated by hand — the gate compares the two, so a
string added later cannot slip through untranslated.

## 0.7.0 — Builders: dynamic blocks

| Claim | Status | Evidence |
| --- | --- | --- |
| A block cannot be half-added: metadata, renderer, registrar and editor registration agree | **VERIFIED** | `[BLOCKS]` gate (`tools/check-blocks.mjs`) → 4 blocks, each `apiVersion` 3, `render: file:./render.php`, `supports.html: false`, direct-access guard in the renderer, no remote asset, listed in `inc/blocks.php` **and** registered in `blocks/editor.js`; runs locally and in the CI job `JS, JSON, gates, build` |
| Our own templates, parts and patterns contain no shortcode blocks | **VERIFIED** | same gate, rule 5 → 32 content files (14 templates + 3 parts + 15 patterns) and not one `wp:shortcode`; the shortcodes remain for hand-written classic content only |
| The blocks actually render on a real WordPress | **VERIFIED** | `WP-CI` run `37311340378` → `Test_Blocks` (10 tests, part of `OK (76 tests, 673 assertions)` on both PHP legs): the shipped `block.json` files are parsed by `register_block_type()` and rendered through the shipped `render.php` files |
| The album tracklist renders in the editor's order and hides drafts | **VERIFIED** | `Test_Blocks::test_tracklist_block_renders_published_tracks_in_order` — a draft and a non-existent ID among the entries are skipped, the two published tracks keep their order |
| Stored text is escaped, never printed as markup | **VERIFIED** | `Test_Blocks::test_tracklist_block_escapes_stored_text` — a title containing `<b>` is delivered as `&lt;b&gt;…`, a meta subtitle carrying `&` and `"` as `&amp;`/`&quot;` |
| The subtitle from the 0.7.0 schema reaches the markup | **VERIFIED** | `Test_Blocks::test_tracklist_block_renders_the_subtitle` (`.wavira-tracklist__subtitle`) |
| A dynamic block with nothing to render prints nothing on the front end | **VERIFIED** | `Test_Blocks::test_empty_album_renders_nothing` — no `wavira-tracklist` markup ever, and an empty string for a tracklist and a video without a source when the process is not rendering a REST request (`REST_REQUEST` is process-wide, so the test asserts both branches; the REST branch expects `wavira-block-placeholder`, which is what the editor needs — `WP-RUNTIME` for the visual half) |
| Video falls back to a link when the provider cannot be embedded | **VERIFIED** | `Test_Blocks::test_video_block_renders_hosted_and_embedded_sources` — hosted file becomes `<video>`; a YouTube URL that cannot be reached becomes `p.wavira-video-link`; the test stubs `pre_http_request`, so the suite never calls the network |
| Genre chips list terms in the requested order with counts | **VERIFIED** | `Test_Blocks::test_genre_chips_block_lists_terms` — `orderby=count` puts the most used genre first, `showCount` renders `Popular genre (2)`, no terms means no output |
| The player block emits the documented mount contract | **VERIFIED** | `Test_Blocks::test_player_block_emits_the_mount_contract` — on a singular album: `data-wavira-player="1"`, `data-context="album"`, `data-id="<album>"`, `class="wavira-player…"` |
| Block and shortcode output are the same markup | **VERIFIED** | `Test_Blocks::test_blocks_and_shortcodes_share_the_markup` — `do_blocks()` output is compared to `do_shortcode()` output, so the two editors cannot diverge |
| The query-loop context (`block->context['postId']`) and the genre-archive path resolve as documented | **IMPLEMENTED** | the renderers implement both (`usesContext: postId`; the genre view takes the queried term's slug because `Queue::ids()` addresses genres by slug); rendering a Query Loop or a real genre archive needs an editor/`WP-RUNTIME` session |
| Editor preview matches the front end | **IMPLEMENTED** | `blocks/editor.js` registers all four blocks with a generic `ServerSideRender` preview and `save() → null`; the visual comparison needs a real editor session (`WP-RUNTIME`) |
| Every gate is green on the 0.7.0 commit | **VERIFIED** | CI run `37311340378` (commit `8217e13`): all 8 jobs success — `WPCS + PHP compatibility`, PHP 7.4/8.2/8.3 syntax, `JS, JSON, gates, build` (includes the new `[BLOCKS]` gate), `WordPress integration` on PHP 7.4 and 8.2, legacy artifact integrity |
| Template and part text is translatable | **VERIFIED** | `[I18N]` gate (`tools/check-i18n.mjs`) → 17 template/part files with no text node and no text-bearing block attribute, 15 patterns, 21 `wp:pattern` references all resolving; the strings live in `patterns/hidden-*.php` because a block template is static HTML — the mechanism core documents in `wp-includes/block-template.php` and uses itself (`twentytwentyfour/hidden-404`) |
| Every `wp:pattern` reference points at a pattern that exists, and every hidden pattern is used | **VERIFIED** | same gate, second half: a reference to a missing slug, a duplicate slug, a pattern without `Title:`/`Slug:`, or a hidden pattern no template references each fail the build |
| No block attribute freezes a string in one language | **VERIFIED** | same gate: `buttonText`, `label`, `placeholder`, `alt`, `caption`, `text`, `content`, `moreText`, `summary` are rejected in `templates/*.html` and `parts/*.html`; the 404 search block now relies on core's translated defaults |
| The footer year and the colour-mode button come from PHP, not from the template | **VERIFIED** | `patterns/hidden-footer-legal.php` prints `wp_date( 'Y' )`; the toggle lives in `patterns/hidden-theme-toggle.php` and keeps the `data-wavira-theme-toggle` / `data-wavira-theme-label` hooks the theme script binds to (`tests/js/theme.test.mjs`) |
| Non-translatable text is a deliberate, visible decision | **VERIFIED** | the author attribution carries `<!-- wavira:i18n-exempt author attribution -->` on its own line and the gate reads that marker; there is no blanket ignore |
| `docs/MIGRATION-BLUEPRINT.md` only names keys that exist | **VERIFIED** | `[MAPPING]` gate (`tools/check-mapping.mjs`) → 36 `wavira_*` names resolve against `MetaSchema`, `PostTypes`, `Taxonomies` and `SettingsSchema`; rows marked `[DEFERRED]` are exempt by design. Writing the gate found two promises the code did not keep (`wavira_track_order`, `wavira_views`) and two rows that promised a settings key generically — all four are now either implemented or explicitly deferred |
| The theme has PHP-level test coverage of its own | **IMPLEMENTED** | `tests/test-blocks.php` loads the theme's own PHP from the repository (constants + `inc/*.php`, exactly what `functions.php` requires) and renders the shipped block files; templates and parts still need a real install (`WP-RUNTIME`) |

**Notes from writing the 0.7.0 suite:** it is green on its first run — no product defect surfaced. Two
environment hazards were removed while writing it instead of being left as future flakes: the
escaping fixture is built from text that survives a KSES-enabled save (`<b>` and a meta value), so the
assertion does not depend on whether the test user has `unfiltered_html`; and the oEmbed fallback test
stubs `pre_http_request`, so a unit test never reaches the network. The block registration is exercised
through `wavira_register_blocks()` — the same function `init` calls — rather than by re-registering
blocks by hand.

## 0.2.0 — Architecture

| Claim | Status | Evidence |
| --- | --- | --- |
| CI enforces the gates above (plus module boundaries and the integration suite) | **VERIFIED** | `.github/workflows/ci.yml`: 8 jobs — `tools/lint.sh` (named gates: `[PHP] [PHPCS] [JS] [JSON] [REFS] [BLOCKS] [I18N] [MAPPING] [CSS] [CONTRAST] [LEGACY] [BOUNDARIES] [SIZE]`), WPCS + PHP compatibility, PHP 7.4/8.2/8.3 syntax, legacy artifact hash, and `WordPress integration` on 7.4/8.2 |
| No legacy code, legacy echoes or jQuery in the new product | **VERIFIED** | `tools/lint.sh` gate `[LEGACY]` (grep) — passes |
| Coding standard rules marked 🔒 are machine-checked | **IMPLEMENTED** | partially: grep gates + WPCS in CI; the JS/CSS/CSP halves arrive with the assets |

## 0.1.0 — Forensic audit

| Claim | Status | Evidence |
| --- | --- | --- |
| 97 legacy files inspected; ionCube in exactly 2 of them | **VERIFIED** | `docs/PROJECT-AUDIT.md`, `docs/SECURITY-AUDIT.md` |
| No malicious/obfuscated code beyond the licensed ionCube loader | **VERIFIED** | hash inventory + pattern scan, `docs/SECURITY-AUDIT.md` S1–S10 |
| Legacy license situation documented | **VERIFIED** | `docs/LICENSE-AUDIT.md` L1–L19 |

---

## How to re-run everything

```bash
# full local gate (the PHP and PHPCS gates skip when they are absent; CI runs them)
bash tools/lint.sh            # named gates incl. [BLOCKS] tools/check-blocks.mjs, [I18N] tools/check-i18n.mjs,
                              #   [MAPPING] tools/check-mapping.mjs, [CSS] tools/check-css.mjs,
                              # [CONTRAST] tools/check-contrast.mjs, [REFS] tools/check-class-refs.py,
                              # [BOUNDARIES] tools/check-boundaries.mjs
node tools/build.mjs --check  # sources exist; node tools/build.mjs builds wavira/assets/dist + wavira-core/assets/dist

# both DOM-free unit suites (no browser)
node --test tests/js/player.test.mjs tests/js/theme.test.mjs   # or: npm run test:js

# the component harness (dev only): real browser, real shipped CSS/JS, stubbed REST
node tools/preview/serve.mjs  # → http://localhost:4173/tools/preview/ (colour modes, RTL/LTR, 360→1920)

# the authoritative static gate (needs PHP + Composer)
composer install
vendor/bin/phpcs --standard=phpcs.xml.dist -q

# the runtime gate (needs MySQL/MariaDB; downloads the WordPress test library)
composer test:install
composer test                # 76 tests: data model, settings, REST (incl. player), downloads, search, related,
                             # cache, public API, and the theme's own block layer (tests/test-blocks.php)

# the full-site gate (needs a real install + WP-CLI/site owner)
wp plugin activate wavira-core
wp wavira verify
curl "$SITE/wp-json/wavira/v1/tracks?per_page=5"
```
