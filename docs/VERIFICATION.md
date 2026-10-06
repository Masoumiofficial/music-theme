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

## 0.10.0 — SEO cooperation and the performance budget

| Claim | Status | Evidence |
| --- | --- | --- |
| An artist page produces a `MusicGroup` node with its profiles | **VERIFIED** | `Test_Seo::test_artist_graph_is_a_music_group_with_profiles` — `@type`, `name`, `url`, `description`, `#musicgroup` fragment and `sameAs` carrying the Telegram and website URLs |
| An album node lists its published tracks and keeps the editorial release date | **VERIFIED** | `Test_Seo::test_album_graph_lists_published_tracks` — a draft in the tracklist is neither counted nor listed (`numTracks` 1), `datePublished` is `2024-03-05T00:00:00+00:00` from `wavira_release_date`, and `byArtist` names the artist |
| A track node carries duration, album and ISRC | **VERIFIED** | `Test_Seo::test_track_graph_carries_duration_and_album` — 222 s → `PT3M42S`, `inAlbum.name`, `isrcCode`, credit and description |
| A hosted video exposes its file; YouTube and Aparat become embed URLs | **VERIFIED** | `Test_Seo::test_video_graph_maps_hosted_and_provider_sources` + `test_embed_url_handles_short_and_unknown_providers` — `contentUrl` for the file, `https://www.youtube.com/embed/AbC123`, `youtu.be/Ty2`, `music.youtube.com`, Aparat `/v/demo1`; an unknown provider maps to an empty string (the URL is kept as `contentUrl` in the node) |
| Posts, pages, drafts and unknown IDs never produce a node | **VERIFIED** | `Test_Seo::test_only_published_music_produces_a_graph` |
| The graph is a documented switch, not a hard-coded behaviour | **VERIFIED** | `Test_Seo::test_structured_data_can_be_disabled` — `wavira_core_structured_data_enabled` empties both the payload and the markup |
| The printed JSON-LD cannot be escaped out of | **VERIFIED** | `Test_Seo::test_markup_embeds_json_ld_and_cannot_be_closed_early` — a title injected as `Closer </script> Artist` appears hex-escaped (`\u003C/script\u003E`) and the markup contains exactly one `</script>` |
| Plugin detection defaults to off and follows both filters | **VERIFIED** | `Test_Seo::test_seo_plugin_detection_defaults_to_false_and_follows_the_filter` — core answer, theme wrapper, and a theme-level override |
| The fallback meta tags print only when nothing else does | **VERIFIED** | `Test_Seo::test_social_meta_is_a_fallback_only` — `meta description`, `og:type` (`article` for a post), `og:locale`, `twitter:card`, `article:published_time`; then empty output with `wavira_theme_seo_plugin_active` true |
| A music single's title gains its artist; other titles are untouched | **VERIFIED** | `Test_Seo::test_document_title_parts_add_the_artist_to_music_singles` — `First track · Demo Artist`, and a blog post unchanged |
| Credits are ordered, deduplicated and published-only | **VERIFIED** | `Test_Seo::test_credit_names_are_ordered_and_published_only` — `[ 'Demo Artist', 'Featured Artist' ]` even when the featured list is unordered and contains a draft |
| The gzipped budgets are met by the built bundles | **VERIFIED** | `[PERF]` gate → `theme.css` 7402 B of 25600 B, `theme.js` 2610 B of 30720 B, `core.js` (player bundle) 14271 B of 15360 B; runs locally and in the CI job `JS, JSON, gates, build` |
| Shipped assets load nothing from another origin | **VERIFIED** | `[PERF]` gate, remote-URL rule → 10 shipped CSS/JS/SVG files, zero third-party URL; the one hint WordPress adds (`s.w.org`) is removed by `wavira_resource_hints()` and asserted in `Test_Performance::test_sw_org_resource_hint_is_removed` |
| The theme does not cancel core's image optimisation | **VERIFIED** | `Test_Performance::test_theme_images_leave_loading_attributes_to_core` — `wavira_get_image()` output is identical to `wp_get_attachment_image()` for the same arguments, attribute for attribute; the `[PERF]` gate fails if `loading`/`decoding` come back |
| A payload without an attachment still renders, lazily | **VERIFIED** | `Test_Performance::test_bare_url_images_still_render_lazily` |
| The emoji script and styles are gone, front end and admin | **VERIFIED** | `Test_Performance::test_emoji_assets_are_removed_everywhere` — the five core callbacks (`wp_head`, `wp_print_styles`, both admin pairs, `wp_mail`) are gone after the two functions run, and the `init` wiring is locked by a source assertion, because WordPress's test case restores `$wp_filter` after every test |
| The player bundle loads on request, never on every page | **VERIFIED** | `Test_Performance::test_player_bundle_loads_only_on_request` — with the handle registered, `wavira_core_enqueue_player()` enqueues script **and** stylesheet; on an un-built checkout it reports false instead of printing a 404 |
| The theme script is deferred; an un-built theme enqueues nothing | **VERIFIED** | `Test_Performance::test_theme_script_is_deferred_when_built` — `strategy` is `defer` when the built file exists, and the handle stays unregistered when it does not |
| No unbounded query or disabled `srcset` anywhere | **VERIFIED** | `[PERF]` gate scans 78 product PHP files for `posts_per_page => -1`, `nopaging => true` and `srcset` overriding; the `[LEGACY]` grep gate keeps its own rule |
| Lighthouse / field Core Web Vitals numbers | **NOT_STARTED** | no browser and no live install in this environment: the budget is enforced statically and no field number is claimed (ADR 0016 §5) |
| Editor-side SEO panels (per-post overrides, social previews) | **NOT_STARTED** | an admin UX feature, scheduled with the 0.11.0 release-candidate work; a site that wants it today runs Rank Math |
| Every gate is green on the 0.10.1 commit | **VERIFIED** | CI runs `37438117893` (push) and `37438125487` (pull request) at head `e9362e6`: all 8 jobs success — WPCS 0 findings over 80 files, PHP 7.4/8.2/8.3 syntax, `JS, JSON, gates, build`, `Legacy artifact integrity`, and `WordPress integration` on PHP 7.4 **and** 8.2 → `OK (134 tests, 1090 assertions)` on both legs (0.10.0: 118 tests / 948 assertions + the 16 new Jalali tests). The three red runs before it (`5c4abf9`, `1a8bbca`, `6c6f410`) each found a real defect and are described below |
| Every gate is green on the 0.10.0 commit | **VERIFIED** | CI runs `37358851972` (pull request) and `37358981113` (push) at head `982c261`: all 8 jobs success — WPCS 0 findings, PHP 7.4/8.2/8.3 syntax, `JS, JSON, gates, build` (includes the new `[PERF]` gate), `WordPress integration` on PHP 7.4 **and** 8.2 → `OK (118 tests, 948 assertions)` on both legs, legacy artifact integrity. The two earlier red runs (`79eb882`, `581e506`) found three test defects and one WPCS finding, all fixed in `581e506`/`982c261` |

**What the 0.10.0 suite found.** Two of the new tests were wrong before the product was: the
JSON-LD escaping test asserted a raw string suffix (the tag body is wrapped in newlines) and tried to
keep `</script>` in a stored title (KSES strips it) — the claim now injects the string through the
filter the builder reads, which is what "the encoder escapes it" actually means. The emoji test
assumed `init` hooks had run; by the time a test class loads a theme file, `init` is long past, so it
now asserts the wiring and the effect separately. The `[PERF]` gate's first real number is worth
recording: the player bundle is at **93 %** of its 15 KB budget, which is a constraint for the next
core feature, not a comfortable margin.

## 0.9.0 — Artist profiles and the music-news section

| Claim | Status | Evidence |
| --- | --- | --- |
| One call answers a whole artist page | **VERIFIED** | `Test_Artist_Profile::test_payload_carries_profile_works_and_gallery` — name, biography, quote, socials, counts, the three work sections and the gallery come back from `wavira_core_artist_profile()`; `docs/ARTIST-AND-NEWS.md` §1.2 is the field-by-field contract |
| The works are the artist's own, newest first, drafts excluded | **VERIFIED** | same suite — another artist's album never appears, drafts are neither listed nor counted, and the two albums come back in date-descending order (`test_drafts_and_unknown_ids_are_refused`) |
| Sections and the gallery are bounded | **VERIFIED** | `test_limits_are_honoured_and_capped` — `limit: 2` returns two items with the full count, `limit: 0` returns one (not every) item, `ArtistProfile::MAX_ITEMS` is 24 |
| The gallery is the images attached to the artist | **VERIFIED** | `test_payload_carries_profile_works_and_gallery` — an attachment whose `post_parent` is the artist appears with its caption; the count in `counts.gallery` matches |
| Unknown or unpublished artists are refused | **VERIFIED** | `test_drafts_and_unknown_ids_are_refused` — a non-existent ID and a track ID both return an empty payload; the theme renders an empty string for either (`test_rendering_an_unknown_artist_is_empty`) |
| The theme renders profile, works and gallery, and escapes stored text | **VERIFIED** | `Test_Artist_Profile::test_theme_markup_renders_profile_works_and_gallery` + `test_theme_markup_escapes_stored_text` — a title with `&`/`"` reaches the page as `&amp;`/`&quot;`, a `<b>` title as `&lt;b&gt;`, and a `<script>` in a biography is stripped |
| Shortcode and helper produce the same markup | **VERIFIED** | `Test_Artist_Profile::test_shortcode_and_helper_share_the_markup` — `[wavira_artist]` renders through `wavira_get_artist()` |
| The news feed is newest first and never shows a draft | **VERIFIED** | `Test_News::test_feed_is_newest_first_and_skips_drafts` |
| A category restricts the feed; an unknown category yields nothing | **VERIFIED** | `Test_News::test_feed_can_be_restricted_to_a_category` — the unknown-slug case is asserted explicitly, because "no filter" and "empty filter" must not behave alike |
| Items carry the fields a card needs, and an excerpt always exists | **VERIFIED** | `Test_News::test_items_carry_card_fields_and_an_excerpt` — a post without an excerpt gets a trimmed, markup-free one; a post with one keeps it; thumbnail URL, date, permalink, the author's display name and the categories are present |
| A private or unknown post type cannot be surfaced | **VERIFIED** | `Test_News::test_non_public_post_types_cannot_be_read` — a registered non-public type falls back to `post` and its published post ID is absent from the feed |
| The theme renders the feed as cards and the markup path honours its own filters | **VERIFIED** | `Test_News::test_theme_markup_renders_news_cards` + `test_news_categories_render_as_chips` — `wavira-news__list`, `wavira-card--news`, `<time datetime=`, category chips, and an empty string for an empty category |
| The three new blocks are registered, render on a real WordPress and degrade to an editor hint | **VERIFIED** | `Test_Blocks` — `wavira_block_names()` (7 blocks) is asserted against metadata, registrar and editor registration; `test_artist_profile_block_renders_the_profile`, `test_artist_gallery_block_renders_attached_images`, `test_news_block_renders_cards` render them through `do_blocks()` |
| The new template text is translatable and the pattern references resolve | **VERIFIED** | `[I18N]` gate → 19 template/part files, 16 patterns, 24 references; the news heading lives in `patterns/hidden-heading-music-news.php` |
| Both artifacts ship the new strings in Persian | **VERIFIED** | `[FA]` gate → 215/215 strings translated (91 theme + 124 core), POT/PO/MO in sync; `Test_I18n::test_artist_and_news_strings_come_back_in_persian` asserts the shipped `.mo` returns Persian for `Instagram`, `Aparat`, `Singles`, `Works` and the block titles |
| The new CSS keeps the product's promises | **VERIFIED** | `[CSS]` gate — no `!important`, no physical direction property (so the artist and news layouts mirror on an RTL site), every token resolvable, and the size budget still met |
| The template path (blog index and archives) actually paginates | **IMPLEMENTED** | `home.html` and `archive.html` use core's Query Loop with `inherit: true` and `wp:query-pagination`; rendering the paginated archive needs a real install (`WP-RUNTIME`) |
| The editor preview matches the front end | **IMPLEMENTED** | `blocks/editor.js` registers all seven blocks with a `ServerSideRender` preview and `save() → null`; the visual comparison needs a real editor session (`WP-RUNTIME`) |
| Jalali dates in the new surfaces | **VERIFIED** (0.10.1) | `Content\Dates::label_for_post()` fills `date_label` in the news and artist payloads, so the news cards print «۱۳ مهر ۱۴۰۵» on a Persian site; anchor table, policy tests and the re-entrancy guard in `tests/test-jalali.php` (ADR 0017 §5, §10) |
| A browsable artist directory | **NOT_STARTED** | `templates/archive-wavira_artist.html` renders the archive hero only; a grid of artists is deliberate design work (it needs a per-artist card), recorded in `docs/ARTIST-AND-NEWS.md` §3 rather than shipped as a second temporary query loop |
| Every gate is green on the 0.9.0 commit | **VERIFIED** | CI runs `37354185539` (push) and `37354194397` (pull request) at head `25ce032`: all 8 jobs success — `WPCS + PHP compatibility` (0 findings), PHP 7.4/8.2/8.3 syntax, `JS, JSON, gates, build`, `WordPress integration` on PHP 7.4 **and** 8.2 → `OK (100 tests, 871 assertions)` on both legs, legacy artifact integrity. Run `37353466951`/`37353475364` was the first, red one; its three findings are fixed in `510beef`/`25ce032` and the WPCS one in code, not by silencing the sniff |

| The migration tool converts a legacy site without inventing data | **IMPLEMENTED** (0.11.0) | `Migration\LegacySchema` + `Migration\Migrator` behind `wp wavira migrate`; `tests/test-migration.php` covers M1/M3/M4/M5/M6/M7/M9/M10 of `docs/MIGRATION-BLUEPRINT.md` §7 on a real WordPress (dry run writes nothing, slugs survive, 128/320 sources arrive, lyrics are KSES-filtered, the artist duplicate merges, rollback restores the legacy state, a second run changes nothing) plus "every map target is a schema constant"; ADR 0018. CI verdict pending |
| The migration tool never deletes legacy data | **IMPLEMENTED** (0.11.0) | a post keeps its ID, slug, dates and status; `_migration_backup` (written once) and `_migration_raw` hold the legacy values; the only deletion is a child track the tool created, during `--rollback`, and the test asserts it |
| A legacy value the model does not implement is copied, not reinterpreted | **IMPLEMENTED** (0.11.0) | `views`, `thumb1..4`, `adsjs_*`, `share_off`, `navar_txt` are listed in `LegacySchema::deferred()` with a reason each, counted by `--detect`, and stored in `_migration_raw`; the test asserts `views` lands there and that no `wavira_views` key is invented |
| Dead external links after a migration | **NOT_STARTED** | blueprint §7 M8 needs a live crawl of the migrated site (`WP-RUNTIME`) |
| A 10 000-post dry run | **NOT_STARTED** | blueprint §7 M1 at scale is a staging exercise with a real catalogue; the unit-level dry run is covered and the tool is bounded/resumable by construction |

**What the 0.10.1 runs found.** Three red runs, three real defects — none of them silenced:

1. `5c4abf9` — the **WPCS** job rejected the new files (array-item spacing, alignment, a stand-alone
   post-increment), which also aborted both integration jobs. Fixed in `1a8bbca`. The local `[PHPCS]` gate
   skips when `vendor/bin/phpcs` is absent, which is why the finding was CI-only in a sandbox with no PHP:
   any environment that runs `composer install --dev` executes the identical standard set (the same
   pinned packages CI installs) before pushing.
2. `1a8bbca` — WPCS green, and both integration jobs **hung until the runner killed them** (exit 143):
   `Dates` converted by calling `wp_date()`, which WordPress runs through the very filter
   `Dates::register()` had added, so the first Persian request recursed (`get_the_date()` reaches the
   class twice over). Fixed in `6c6f410` with a re-entrancy guard the conversion owns (ADR 0017 §10) and
   a test for it; proven without WordPress by a harness that aborts a re-entered `wp_date()` after 200
   calls (unguarded file: aborts; guarded file: «۱۳ مهر ۱۴۰۵»).
3. `6c6f410` — the suite ran for the first time and found one product defect and two wrong expectations:
   the clamp answered `1177/10/11` for 1500-01-01 (a year outside the accurate table) and `1632/10/11`
   for 2500-01-01, and two Jalali assertions expected Gregorian output. Fixed in `e9362e6`: the range is
   decided on the Julian day number against the exact boundary days, the expectations now assert the
   documented rules, and every one of them was executed locally first through the WP-free harness
   (39 checks) instead of being run for the first time in CI.

**What the first CI run found.** The three new test files ran for the first time in `37353466951` and
found four defects, three of them in the product: `wavira_get_news()` ignored a `category` that arrived
without `source => 'category'`, so a typo'd slug rendered the whole blog (a slug now decides the source,
and `source: category` without a slug is an empty feed); WPCS found an unqualified core hook name
(`the_content`, now a justified ignore), a reserved parameter name (`$class` → `$class_name`) and one
alignment. The fourth was the fixture, twice over: WordPress's post factory injects a default
`post_excerpt` — so the "the editor wrote no excerpt" case was never reached — and publishes with
`post_author => 0`, whose display name is empty. Both fixtures now build the situation the claim is
about, and the author assertion checks the id and the name instead of "not empty".

**Notes from building the 0.9.0 surfaces.** The aggregation lives in the plugin and the markup in the
theme, which is what made the tests cheap: the payload is asserted field by field without a browser,
and the rendering is asserted on the same fixtures. Two behaviours were decided *by* the tests instead
of by taste: `limit: 0` returns one item rather than everything (an unbounded profile is the unbounded
query the coding standard forbids), and a non-public post type silently falls back to `post` rather
than erroring (a feed caller must not be able to enumerate private types by guessing names).

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
| Jalali (Shamsi) dates | **VERIFIED** (0.10.1) | `Content\Jalali` (Borkowski port, MIT notice in `THIRD-PARTY-NOTICES.md`) + `Content\Dates` policy; `tests/test-jalali.php` pins Nowruz 1400–1405, 1979-02-11 → 1357/11/22, 2025-03-20 → 1403/12/30, a forty-year round trip, the leap rule, the Saturday-first weekday order, both calendars, the machine-surface guards, and that the filters do not recurse (`test_conversion_does_not_recurse`); ADR 0017 §10 |
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
                              #   [MAPPING] tools/check-mapping.mjs, [FA] tools/i18n.mjs check, [CSS] tools/check-css.mjs,
                              # [CONTRAST] tools/check-contrast.mjs, [REFS] tools/check-class-refs.py,
                              # [BOUNDARIES] tools/check-boundaries.mjs
node tools/build.mjs --check  # sources exist; node tools/build.mjs builds wavira/assets/dist + wavira-core/assets/dist

# both DOM-free unit suites (no browser)
node --test tests/js/player.test.mjs tests/js/theme.test.mjs tests/js/i18n.test.mjs   # or: npm run test:js

# the component harness (dev only): real browser, real shipped CSS/JS, stubbed REST
node tools/preview/serve.mjs  # → http://localhost:4173/tools/preview/ (colour modes, RTL/LTR, 360→1920)

# the authoritative static gate (needs PHP + Composer)
composer install
vendor/bin/phpcs --standard=phpcs.xml.dist -q

# the runtime gate (needs MySQL/MariaDB; downloads the WordPress test library)
composer test:install
composer test                # 90+ tests: data model, settings, REST (incl. player), downloads, search, related,
                             # cache, public API, the Persian catalogues (tests/test-i18n.php), the theme's own
                             # block layer (tests/test-blocks.php), artist profiles (tests/test-artist-profile.php)
                             # and the news feed (tests/test-news.php)

# the full-site gate (needs a real install + WP-CLI/site owner)
wp plugin activate wavira-core
wp wavira verify
curl "$SITE/wp-json/wavira/v1/tracks?per_page=5"
```
