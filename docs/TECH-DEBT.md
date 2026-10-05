# TECH-DEBT.md — Legacy Technical Debt Register

Each item: what it is, evidence, impact, and the disposition chosen for the rebuild
(`REPLACE` = new implementation, `DROP` = not carried over, `MIGRATE` = data concern only).

| # | Debt item | Evidence | Impact | Disposition |
| --- | --- | --- | --- | --- |
| T01 | Encrypted core (`functions.php`, `RTL_License_*.php`) — no source, no audit, no modification | ionCube headers; `//ICB0` | Blocks maintenance, security review, legal resale | **DROP** (black box behaviour re-specified) |
| T02 | Obsolete settings framework (OptionTree 2.6.0, "Tested up to 4.4") | `inc/option-tree/**` | Security surface, bloat (2.2 MB), no future support | **REPLACE** with Settings API + `theme.json` |
| T03 | Duplicate option id `indpl` in the settings schema | `inc/theme-options.php` (declared twice) | Unpredictable saves | **DROP** (schema redesign) |
| T04 | Music data as `post` + `musics_type` meta (no CPTs) | `taxonomy-singer.php`, `song*/single.php` | No structure, no relations, no REST, theme-switch data loss | **REPLACE** (CPTs in Core) |
| T05 | Two artist models (`singer` taxonomy + artist tags) with duplicated term meta | `taxonomy-singer.php` vs `tag.php`; option `reltag` | Duplicate content, split SEO signals, double maintenance | **REPLACE** (single Artist entity + 301 map) |
| T06 | Album tracklists as an ACF repeater of file URLs (`song_names`) | `single.php` `have_rows('album')` | Tracks not addressable/playable/countable individually | **REPLACE** + `MIGRATE` |
| T07 | Free-text `artist` meta duplicating taxonomy relations | meta `artist` (12 call sites) + LIKE queries | Two sources of truth, fragile related block | **REPLACE** + `MIGRATE` |
| T08 | Global `#audio` element + `nowPlaying` global; one player per page | `single.php`, `inc/tarlanweb_player.php`, `js/scripts.js` | Multiple players broken; duplicate IDs | **REPLACE** (Player Engine) |
| T09 | Player controls are icon glyphs, mouse-only | `.large-toggle-btn` `<i>` markup; class-bound JS | Accessibility failure (WCAG) | **REPLACE** |
| T10 | `if($rkian = 1)` assignment bug in the index player | `inc/tarlanweb_player.php` | Logic always true → markup branch uncontrolled | **DROP** (rewrite) |
| T11 | `posts_per_page => -1` on artist pages (×3) + count queries | `taxonomy-singer.php` | Unbounded memory/time on large catalogs | **REPLACE** (pagination + cache) |
| T12 | Responsive images disabled (`wp_calculate_image_srcset` → false) | `myfunctions.php` | Bandwidth waste, poor CWV | **REPLACE** |
| T13 | Whole-response `ob_start` attribute rewriting | `myfunctions.php` | Fragile, cache/stream unfriendly, hides errors | **DROP** |
| T14 | `wp_is_mobile()` UA sniffing changes markup and image sizes | `header.php`, `inc/songs_post.php` | Cache poisoning, duplicated templates | **REPLACE** (responsive DOM) |
| T15 | Hover-only desktop menus, `removeAttr('href')` in mobile menu | `js/scripts.js` | Keyboard/touch unusable, links destroyed | **REPLACE** |
| T16 | Two headers + inline mobile-only `<style>` (padding hack) | `header.php`, `inc/mobiles_header.php` | Layout hacks, duplicate DOM | **REPLACE** |
| T17 | Custom comment form/list duplicating core | `myfunctions.php` | Diverges from core, breaks on updates | **REPLACE** with core APIs |
| T18 | Inline `<script>` redirects on 404/empty states | `404.php`, `inc/no-content.php` | Soft-404, malware-scan noise | **DROP** |
| T19 | Hidden H1 keyword option (`head_h1`) | `header.php` + CSS `.h1_hidden_home` | Search manipulation pattern | **DROP** |
| T20 | `wp_title()` usage instead of `title-tag` | `header.php` | Deprecated API, plugin conflicts | **REPLACE** |
| T21 | No i18n: no `load_theme_textdomain`, no `languages/`, hardcoded Persian, ad-hoc text domains | templates, `inc/widgets.php` | Cannot ship internationally | **REPLACE** |
| T22 | Hardcoded `dir="rtl" lang="fa-IR"` | `header.php` | LTR/multilingual broken | **REPLACE** |
| T23 | Single 62 KB stylesheet, 70 `!important`, no tokens, no `:root` | `style.css` | Unmaintainable design; dark mode by override soup | **REPLACE** (design system) |
| T24 | Icon font used for UI controls; missing icon semantics | `css/icofont.min.css` usage | A11y + performance + licence questions | **REPLACE** (SVG icons) |
| T25 | jQuery + Owl Carousel + IE8-era polyfills shipped everywhere | `js/` (224 KB) | Performance budget impossible | **REPLACE** |
| T26 | Widgets overwrite `$wp_query` and read undefined globals | `inc/widgets.php` | Broken pagination/loops; PHP notices | **REPLACE** (blocks/patterns; widget bridge optional) |
| T27 | Unescaped output of options/ACF/term meta; raw JS ad option | `header.php`, `single.php`, `tag.php` | Stored-XSS surface | **REPLACE** (escape policy) |
| T28 | No tests, no lint config, no build tooling, no CI | repo-wide | No regression safety | **REPLACE** (tooling in 0.2.0) |
| T29 | Dead code: `shapeSpace_script_loader_tag` targets a never-enqueued handle; `mediaqueries.js`/`html5shiv.js` unused by modern browsers; commented-out blocks in templates | `myfunctions.php`, `js/`, `single.php` | Confusion, payload weight | **DROP** |
| T30 | Author-brand pollution (names, phone, emails, marketplace URLs) across code/comments/admin text | `style.css`, `inc/theme-options.php`, `searchwp-live-ajax-search/search-results.php`, black-box headers | Cannot ship as a product | **REPLACE** (brand vocabulary rules) |
| T31 | `RTL_License_*` file name **and** ionCube dependency tie the product to a marketplace purchase | archive root | Legal/packaging blocker | **DROP** |
| T32 | Nested archive `includes.zip` shipped inside the theme (redundant copy) | `inc/option-tree/includes.zip` | Package bloat, tamper confusion (already investigated) | **DROP** |
| T33 | Legacy option/settings storage in a single serialized option (`option_tree_settings`) | `inc/theme-options.php` | No schema, no validation, opaque diffs | **MIGRATE** into typed settings |
| T34 | External plugin dependencies not declared anywhere (ACF, WP-PostViews-like, SearchWP) | call sites only | Site breaks silently if a plugin is missing | **REPLACE** with self-contained Core + explicit integration docs |
| T35 | Fonts/icons/images with unclear redistribution rights | `fonts/`, `css/icofont*`, `images/`, `screenshot.jpg` | Legal blocker for marketplace | **REPLACE** (OFL/own/CC0) |

## Debt summary

| Disposition | Count | Notes |
| --- | ---: | --- |
| DROP (not carried over) | 10 | encrypted core, OptionTree internals, hacks |
| REPLACE (new implementation) | 22 | the product itself |
| MIGRATE (data concern only) | 3 | settings, meta, relations |

**Debt control rule for the new product:** no item from this register may reappear. The Definition of
Done for each phase (REBUILD-PLAN §6) plus the merge gates in PERFORMANCE/SECURITY/UX audits are the
enforcement mechanism; a pre-release "legacy-echo grep" (brand tokens, `-1` queries, `srcset` filters,
`!important` count, jQuery presence) is part of the release checklist.
