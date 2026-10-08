# DECISIONS.md — Architecture Decision Records (index)

ADRs are immutable once accepted: to change a decision, add a new ADR that supersedes the old one and
update this index. Format: `docs/adr/NNNN-title.md`.

| ADR | Decision | Status | Date |
| --- | --- | --- | --- |
| [0001](adr/0001-product-scope-and-non-goals.md) | Product scope: music **publishing** ecosystem, not streaming/SaaS; explicit non-goals for v1 | Accepted | 2026-10-05 |
| [0002](adr/0002-theme-vs-core-plugin-split.md) | Persistent music data + business logic live in the **Wavira Core** plugin; the theme is presentation only | Accepted | 2026-10-05 |
| [0003](adr/0003-custom-post-types-over-post-meta.md) | Replace legacy `post` + `musics_type` meta with real CPTs (`artist`, `album`, `track`, `video`) + taxonomies | Accepted | 2026-10-05 |
| [0004](adr/0004-no-acf-no-optiontree.md) | No ACF and no OptionTree dependency; use registered meta + Settings API + `theme.json` | Accepted | 2026-10-05 |
| [0005](adr/0005-player-engine-design.md) | Instance-based, DOM-independent Player Engine; no global `#audio`; Media Session + full keyboard support | Accepted — **implemented in 0.5.0** (see the ADR's implementation notes) | 2026-10-05 |
| [0006](adr/0006-asset-strategy-and-tooling.md) | Vanilla ES modules, no front-end jQuery, build-aware conditional assets; no Node build required to run the plugin | Accepted | 2026-10-05 |
| [0007](adr/0007-php-and-wordpress-baseline.md) | PHP 7.4 floor (tested on 8.2/8.3), WordPress 6.6+ floor, autoloader without a Composer runtime requirement | Accepted | 2026-10-05 |
| [0008](adr/0008-i18n-and-rtl-first.md) | English source strings, RTL-first with full LTR parity, logical CSS properties, one text domain per artifact | Accepted | 2026-10-05 |
| [0009](adr/0009-accessibility-and-performance-gates.md) | WCAG 2.2 AA and the performance budget are merge gates, not follow-up work | Accepted | 2026-10-05 |
| [0010](adr/0010-licensing-and-third-party-policy.md) | Ship only GPL-compatible/OFL/own assets; exclude all legacy encrypted files and unclear-provenance media | Accepted | 2026-10-05 |
| [0011](adr/0011-slugs-and-permalinks.md) | Permalinks `/artists/ /albums/ /tracks/ /videos/ /genres/`, legacy post slugs preserved, 301 map for `/singer/*` and artist tags | Accepted (owner-approved 2026-10-05) | 2026-10-05 |
| [0012](adr/0012-relation-storage.md) | Relations are post IDs in registered meta (artist CPT + role-aware meta), **not** a shared taxonomy; no free-text credits | Accepted | 2026-10-05 |
| [0013](adr/0013-download-counters-and-delivery.md) | Download counters are atomic plugin-side meta increments (never REST-exposed); delivery is authorization + `302`, never a byte proxy, token obfuscation or a DRM claim | Accepted | 2026-10-05 |
| [0014](adr/0014-dark-mode-and-token-mapping.md) | Colour modes: one palette in `theme.json`, dark mode as a `--wp--preset--color--*` remap, `data-theme` on `<html>`, neutral plugin tokens mapped by the theme, and a gate that fails unresolvable token references | Accepted — **implemented in 0.6.0** (two casing defects found and fixed) | 2026-10-05 |
| [0015](adr/0015-persian-first-localisation.md) | Persian ships in the repository for both artifacts; one catalogue covers the front end, the admin and the editor; Jalali deferred with a named exit condition (§8, amended by 0017) | Accepted — **implemented in 0.8.0**; §6/§8 amended in 0.10.1 | 2026-10-05 |
| [0016](adr/0016-seo-cooperation-and-performance-gates.md) | The site's SEO plugin owns meta tags; the product always emits its own structured data and enforces the performance budget as a build gate | Accepted — **implemented in 0.10.0** | 2026-10-05 |
| [0017](adr/0017-jalali-dates-and-iranian-defaults.md) | Jalali (Shamsi) dates on `fa*` locales from a verified, anchored converter; machine surfaces stay Gregorian; Iranian defaults are applied by the seeder, and the demo/harness are Persian | Accepted — **implemented and verified in 0.10.1** (CI `37438117893`/`37438125487`, 134 tests / 1090 assertions) | 2026-10-05 |
| [0018](adr/0018-migration-tool-scope-and-safety.md) | The legacy migration tool is a service behind `wp wavira migrate`: copy-and-back-up before writing, report instead of guessing, structurally idempotent, bounded/resumable, reversible; the admin page is deferred and the CLI is the shipped surface | Accepted — **implemented in 0.11.0**, runtime verdict in `docs/VERIFICATION.md` | 2026-10-06 |
| [0019](adr/0019-release-packaging.md) | Release packaging: explicit includes plus a leak scan of the result, deterministic ZIPs written by our own dependency-free writer, the licence register checked against the archive, marketplace extras behind `--strict` | Accepted — built and install-tested in 0.11.0 | 2026-10-07 |
| [0020](adr/0020-theme-options-panel.md) | Theme options: the Customizer as the panel, one schema driving the panel and the front end, CSS variables and body classes instead of `!important`, a default site ships no extra CSS, Persian setup as a nonced action rather than a setting. Amended in 0.13.0: a second door (`Appearance → Wavira settings`) onto the same schema | Accepted — implemented and tested in 0.12.0, amended in 0.13.0 | 2026-10-07 |
| [0021](adr/0021-front-page-composition.md) | The front page is a composition of native query loops (`front-page.html`), `index.html` is the blog index, `home.html` does not exist, and which sections appear is the Site Editor's decision rather than a theme setting | Accepted — implemented in 0.14.0, guarded by `tools/lint.sh` | 2026-10-08 |
| [0022](adr/0022-browser-evidence-in-ci.md) | The browser runs in CI: the screenshot and the axe measurement happen on a runner against a real WordPress, the verdict is a separate tool, and only `main` commits the screenshot | Accepted — `wp-render` job, 0.14.0 | 2026-10-08 |

**Resolved open decisions** (were listed as "scheduled" in 0.2.0)

| Topic | Resolution | Where |
| --- | --- | --- |
| Permalink slugs and the 301 map | `/artists/ /albums/ /tracks/ /videos/ /genres/`; legacy slugs preserved; redirects specified | ADR 0011 |
| Relation storage (meta IDs vs. shared taxonomy) | Post IDs in registered meta, role-aware; internal index allowed later behind the service API | ADR 0012 |
| Own download counter vs. integration with popular plugins | **Own** lightweight atomic counter (`wavira_download_count*`, plugin-only, never REST-exposed); a third-party bridge stays possible behind the same `Counter` API | ADR 0013 §1 |
| REST caching strategy (transient vs. object cache vs. HTTP cache headers) | Generation-scoped `Support\Cache` keys (bump on content change), TTL 300 s search / 3600 s related, filterable; no per-user state on shared keys; HTTP caching left to the site | ADR 0013 §3 |

### 0.8.0 — Persian-first localisation (2026-10-05)

| Topic | Decision | Why |
| --- | --- | --- |
| Where the Persian catalogue lives | `wavira/languages/fa_IR.{po,mo}` and `wavira-core/languages/fa_IR.{po,mo}` are committed; the `.po` is the translation source of truth, the `.mo` is a committed build output | a theme that must be translated before it is usable is not a Persian product; and a release must not depend on the translator's toolchain (ADR 0015) |
| Translation toolchain | `tools/i18n.mjs` (extract, build, check) — no gettext, no WP-CLI, no Composer | the repository must be buildable and verifiable with the runtime it already declares (Node 18+), consistent with ADR 0006 |
| Editor strings | Printed from PHP by `wp_add_inline_script()` (`wavira_block_editor_strings()`, keyed by the English source string) | `wp_set_script_translations()` needs a hash-named JSON file (`wp i18n make-json`) that nothing in this repository generates; a wrong hash fails silently and leaves the editor English on a Persian site |
| Block metadata | Extracted from `block.json` with the exact contexts core uses (`block title`, `block description`, `block keyword`, from `wp-includes/block-i18n.json`) | core translates those fields itself (`translate_settings_using_i18n_schema()`), so the catalogue must use the same contexts or the inserter stays English |
| Non-Persian translations | Allowed only with an explicit `#, keep-latin` flag on the entry, reviewed in the diff (`%1$s (%2$d)`, `%1$s:%2$s`) | the gate must stay strict enough to catch an untranslated sentence, and explicit enough that a format string does not need a fake translation |
| Jalali (Shamsi) dates | **Deferred in 0.8.0**, delivered in 0.10.1: date output shipped with `wp_date()` and `fa_IR` locale data until the converter had a verified anchor set (`docs/REBUILD-PLAN.md`, ADR 0017) | an unverified calendar conversion would put wrong dates on every page; WordPress locale data is Gregorian, so this is a product decision, not a bug |
| Persian fonts | **Resolved in 0.11.0**: the unmodified Vazirmatn variable WOFF2 (OFL-1.1) ships with the theme, declared in `theme.json`, preloaded, with the licence text beside it and a gate that fails if any of the three goes missing | `docs/PERSIAN-LOCALIZATION.md` §Fonts, `THIRD-PARTY-NOTICES.md` |

### 0.10.1 — Persian localisation: Jalali dates and Iranian defaults (2026-10-05)

| Decision | Choice | Why | ADR |
| --- | --- | --- | --- |
| Calendar | **Jalali (Shamsi) on `fa*` locales**, Gregorian everywhere else | a Persian music site reads Shamsi dates; `fa_IR` locale data only translates Gregorian month names | ADR 0017 §2–§4 |
| Converter | Own PHP port of the Borkowski algorithm as published in `jalaali-js` (MIT), accurate 1178–1633 Jalali, clamped outside | no runtime dependency, and the algorithm is arithmetic rather than a package; the notice travels in `THIRD-PARTY-NOTICES.md` | ADR 0017 §1–§3 |
| Trust in the converter | **The anchor set is the test**: Nowruz 1400–1405, 2025-03-20 → 1403/12/30, 1357/11/22, a forty-year day-by-day round trip, leap rule, Saturday-first weekdays | the first port had a month-modulus bug (`4` instead of `12`) that the anchors caught; a calendar without anchors is a guess | ADR 0017 §3 |
| Conversion seam | `wp_date()` **and** the four `get_the_*` date functions, wired from `ContentModule` | core's own `core/post-date` block renders through `wp_date()`, so the product's markup and a site's own Query Loop cannot drift apart | ADR 0017 §5 |
| Machine surfaces | Never converted: `c U r Y-m-d Ymd Y-m-d H:i:s Y-m-d\TH:i:sP d/m/Y`, formats with a time part, and admin/REST/AJAX/cron/feeds/`robots.txt` | `<time datetime>`, JSON-LD, REST and the editor are read by machines; a Jalali date there is a bug | ADR 0017 §6 |
| Other Jalali plugins | `wavira_core_date_style` = `gregorian` makes the product step aside | two converters filtering `wp_date()` would fight over every date on the page | ADR 0017 §4 |
| Persian numerals | `Dates::digits()` → `wavira_core_digits()`, applied to dates, durations, bitrates, sizes, counts and tracklist indices | a Persian page must not mix numeral systems; machine output stays Latin | ADR 0017 §8 |
| Month and weekday names | **Calendar data as constants**, not translatable msgids | a Persian date is written the same way in every language; the catalogue must not grow twelve strings no translator would change | ADR 0017 §9 |
| Iranian site defaults | Applied by `wp wavira seed` only (`fa_IR`, `Asia/Tehran`, `start_of_week = 6`, `j F Y`, `H:i`, Persian description, `primary` menu), with `--english` / `--no-site` opt-outs; **no runtime option writes** | a default belongs where a site is created, not on every page view | ADR 0017 §10 |
| Demo content | Persian catalogue by default (artist, album + single, four tracks with generated lyrics, three genres, video, menu); the English fixture stays behind `--english` and keeps its translatable strings | content is data, not interface copy — it must not enter the interface catalogue, and the neutral fixture must stay useful | ADR 0017 §11 |
| Developer harness | `tools/preview/` renders the Persian demo (`dir="rtl"`, `lang="fa-IR"`, Persian titles, Jalali dates, Persian player strings from the shipped catalogue) | a harness that judges an RTL/Persian product in English only proves the Latin half | ADR 0017 §12 |

### 0.10.0 — SEO cooperation and the performance budget (2026-10-05)

| Decision | Choice | Why | ADR |
| --- | --- | --- | --- |
| Who writes `meta description`, OG, Twitter, canonical, sitemaps | **The site's SEO plugin**; the theme only falls back when none is active | two descriptions of one page is worse than one — the legacy theme's duplicate SEO surface is what the audit flagged | ADR 0016 §2 |
| Music structured data | **always emitted** (bounded, filterable) | every general-purpose SEO plugin is blind to `wavira_artist`/`_album`/`_track`/`_video`; the data already exists in the product's model | ADR 0016 §2 |
| Where the graph is built | **Core plugin** (`Seo\StructuredData`), printed by the theme | the graph is content logic (a headless consumer or another theme reuses it); the `<script>` tag is presentation | ADR 0016 §3 |
| Credit resolution | **one helper** (`Content\Credit`: primary, then featured, published only) | the schema, the document title and any future credit line must not be able to disagree | ADR 0016 §3 |
| Album tracklist inside the graph | **capped at 50 nodes** | machine-readable summary, not a payload larger than the page | ADR 0016 §3 |
| Release date vs. post date | **release date first** (`wavira_release_date`), post date as fallback | re-publishing an old album must not make it look new | ADR 0016 §3 |
| Provider URL mapping | **YouTube + Aparat only**, everything else keeps its own URL | the two providers the product documents; oEmbed still handles playback | ADR 0016 §3 |
| Performance promise | **a gate, not a paragraph**: `[PERF]` fails the build | budgets written in 0.1.0 and measured by hand drifted; the player bundle is already at 93 % of its 15 KB | ADR 0016 §4 |
| Image `loading`/`decoding`/`fetchpriority` | **never set by the theme** on the core path | hard-coded `loading="lazy"` silently cancels core's LCP promotion — the regression 0.10.0 removed | ADR 0016 §4 |
| Third-party requests | **zero by default**, including WordPress's own `s.w.org` hint | privacy and speed are product promises, and the emoji script/emoji styles buy nothing here | ADR 0016 §4 |
| XML sitemaps, breadcrumb graphs, per-post robots | **not built** | a plugin does them better and is already installed on the target sites | ADR 0016 §5 |

### 0.9.0 — artist profiles and the news section (2026-10-05)

| Topic | Decision | Why |
| --- | --- | --- |
| News content type | **Posts and categories**, no `wavira_news` post type (and therefore no core change in 0.9.0) | posts already carry the archive, the feed, the sitemap and every SEO plugin's expectations; a second type would split the site's archives and feeds in two and force each integration to learn it |
| News templates | `index.html`, `front-page.html` and `archive.html` use **core's Query Loop**, while the `wavira/news` block serves a feed inside a page or an article | pagination, `?paged=`, feeds and the archive title are core's business; the block must not reimplement a query loop the editor cannot inspect |
| News items payload | One service (`News/NewsFeed.php`) behind `wavira_core_news_feed()`, clamped to 1–24 items, `post_type` resolved through `get_post_type_object()` (a non-public type falls back to `post`) | the theme must not query content itself (ADR 0002), and a caller must never be able to surface a private type by guessing its name |
| Artist works | Read from the **existing** `wavira_artist` relation meta on albums, tracks and videos — the payload aggregates, it does not store a second list | the relation already exists for REST payloads and related items (ADR 0012); a profile-specific list would drift from it |
| Artist gallery | Images **attached** to the artist post (`post_parent`), in `menu_order` | WordPress-native: upload from the artist screen and the file is attached to it; no second gallery meta, no options row |
| Social channels | Labels are translated in Core (`Instagram`, `Telegram`, `YouTube`, `Aparat`, `Facebook`, `X (Twitter)`) and rendered as text chips, not brand logos | Aparat is a first-class Iranian platform, the labels are readable in Persian, and shipping third-party logo art is a trademark/asset question the product does not need |
| Artist section limits | `limit` (default 6) and `gallery_limit` (default 8), clamped to 1–24 (`ArtistProfile::MAX_ITEMS`); a section with more items links to its archive | the coding standard forbids unbounded queries; an artist page must stay a page |
| Artist payload caching | Once per request per artist + options (`wavira_artist_data()`, static cache); the profile block asks three times and the queries run once | the profile header, the works and the gallery are three helpers over one payload; a transient would need invalidation the theme cannot see |
| Persian calendar in the new surfaces | Dates were printed with `wp_date()`/`get_the_date()` and the locale while Jalali was deferred; **superseded in 0.10.1** — the news cards and artist pages now render through `Content\Dates` (ADR 0017) | the deferral's condition was a verified conversion; until then an unverified one would put a wrong date on every news card |

### 0.7.0 — block layer (2026-10-05)

| Topic | Decision | Why |
| --- | --- | --- |
| Block source of truth | Blocks are **dynamic** (theme-resident `block.json` + `render.php`) and render through `inc/markup.php`; shortcodes were reduced to delegates of the same helpers | `theme.json` cannot bind arbitrary post meta, so a static-block layer could never reach `wavira_audio_128`, lyrics or an ordered tracklist; and two implementations of "the album's tracklist" would diverge |
| Block metadata vs. registration | `block.json` carries metadata only; the editor script is registered by hand (`wp_register_script`) and passed through `editor_script_handles` | `block.json` cannot declare script dependencies, and the product must run with no build step (ADR 0006) |
| Missing-content behaviour | A dynamic block that has nothing to render prints nothing on the front end and a placeholder **only during a REST request** | an invisible block looks like a bug to the editor; a placeholder on the front end would be visible content nobody asked for |
| Registration order | Blocks register on `init` priority 5, before `register_block_pattern` work | a pattern that contains a `wavira/*` block must never be validated against a missing block |
| Genre context for the player | The genre player addresses the queue by **term slug** (`Queue::ids()` has no term-ID path) | the engine's queue contract is slug-based; inventing an ID path would have widened the REST surface for one template |
| What was **not** added | No static block styles beyond the shared layer, no block variations, no custom block category beyond `wavira-music`, no `wp.data` store | v1 scope: the blocks exist to render music content, not to become an editor framework |

| Where user-visible template text lives | In PHP patterns (`patterns/hidden-*.php`, `Inserter: no`), referenced from templates with `wp:pattern` | a block template is static HTML: any sentence written inside one is frozen in English, and this product is RTL-first for a non-English market. This is what the core themes do (`twentytwentyfour/hidden-404`), and `tools/check-i18n.mjs` keeps it that way |
| Non-translatable strings | An explicit `<!-- wavira:i18n-exempt reason -->` marker on the same line, e.g. the author attribution | the gate must never be silenced by a blanket ignore, and a proper noun has to be visible in the file as deliberate |
| Copyright line | Printed by PHP (`wp_date( 'Y' )`) instead of a literal year in the template part | the template's year was frozen; a pattern runs on every request |

**Deferred by the 0.7.0 schema reconciliation** (recorded, deliberately not implemented — see
`docs/MIGRATION-BLUEPRINT.md` §2 for the migration rows that now point at these)

| Item | Legacy source | Status |
| --- | --- | --- |
| Full-album download | `album128` / `album320` | **data shipped** (`wavira_album_audio_128/_320`); the download endpoint is track-based in v1 |
| View counter | `views` | **deferred** — needs a storage/privacy decision (download counts are implemented, views are not) |
| Script ad slot | `adsjs_bt` / `adsjs_sg` | **deferred** — needs a capability decision; the settings API ships KSES-allow-listed `ads_html` only |

**Still open (scheduled)**

| Topic | Phase | Note |
| --- | --- | --- |
| Elementor integration | 0.7.0+ | **decided 2026-10-05: deferred.** It cannot be verified in this environment (no Elementor install, no CI job) and the standing rule is to never claim compatibility without evidence. Re-open when marketplace demand is confirmed, with widgets that CI can test against a pinned Elementor version. |
| Update server (self-hosted vs. marketplace-native) | 0.9.0 | affects licence/update ADR |
| Localised slug bases for fa_IR (`/خواننده/` …) | 0.6.0 | supported via `wavira_rewrite_slugs` filter (ADR 0011 §5); decision = ship English default, document the filter |
| `custom.player.miniHeight` is a setting nothing consumes | 0.6.1 | either implement the compact/mini bar variant or remove the setting; recorded in ADR 0014 (Consequences) — no silent settings |

**Attribution decision (product owner, 2026-10-05)**

| Topic | Decision |
| --- | --- |
| Designer & author of the theme/plugin | **Etehad WP — اتحاد وردپرس** · <https://etehadwp.com/> — applied to theme `style.css`, plugin header, `composer.json`, `package.json`, `LICENSE.md`, README credits and `BRAND-DECISION.md` |
