# PROJECT-AUDIT.md — Forensic Audit of the Legacy Music Theme

**Audit date:** 2026-10-05
**Artifact audited:** `music-theme.zip` (1,659,763 bytes) in repo `Masoumiofficial/music-theme`
**Branch:** `arena/01a10b85-music-theme` (read-only audit; **no legacy file was modified, renamed, moved or deleted**)
**Method:** static inspection of the extracted tree; executable files were never run; the two ionCube
files were treated as a black box (no decryption, no loader installation, no bypass attempted).

---

## 1. Audit scope & status

| Area | Status | Report |
| --- | --- | --- |
| File inventory & classification | VERIFIED | this file, §2 |
| Feature extraction | VERIFIED (black box noted) | `FEATURE-MAP.md` |
| Data model | VERIFIED | `DATA-MODEL-AUDIT.md` |
| Architecture | VERIFIED | `ARCHITECTURE-LEGACY.md` |
| License / IP | NEEDS_REVIEW (assets) / LEGAL_REVIEW_REQUIRED (fonts, images, ionCube) | `LICENSE-AUDIT.md` |
| Security | VERIFIED for readable code; `[BLACK_BOX_FUNCTIONALITY]` for encrypted files | `SECURITY-AUDIT.md` |
| Performance | VERIFIED | `PERFORMANCE-AUDIT.md` |
| SEO | VERIFIED | `SEO-AUDIT.md` |
| UX / accessibility | VERIFIED | `UX-AUDIT.md` |
| Migration mapping | INFERRED from readable code + meta usage | `MIGRATION-BLUEPRINT.md` |
| Rebuild plan | PLANNED | `REBUILD-PLAN.md` |
| Tech debt register | VERIFIED | `TECH-DEBT.md` |

**Not done (explicitly out of scope in this phase):** decrypting/analysing `functions.php` and
`RTL_License_*.php`; installing or executing the theme; WordPress runtime testing; database analysis
(no DB dump was supplied); measuring real page performance (no staging environment supplied).

---

## 2. File inventory & classification

Archive root: `javanseda/` (Persian brand "جوان صدا" = *Javan Seda*). 116 zip entries / 97 files.
Sizes in bytes; md5 for every file was computed during inspection.

### 2.1 Root templates — `CLASS: THEME-CODE`
| File | Bytes | Notes |
| --- | ---: | --- |
| `functions.php` | 94,279 | **ionCube-encrypted** (`//ICB0 72:0 74:8258 81:fd4a`), "Encrypted by www.Rtl-Theme.com", 2023-08-14 07:58:23 → `[BLACK_BOX_FUNCTIONALITY]` |
| `RTL_License_41d6c5f704e3a746.php` | 63,792 | **ionCube-encrypted**, "Product Link https://www.rtl-theme.com/?p=172924" → `[LEGAL_REVIEW_REQUIRED]` |
| `style.css` | 61,949 | Theme header: `javanseda`, Version 3.0, Author Reza Kianoosh, URI rtl-theme.com/author/tarlanweb |
| `single.php` | 20,340 | post (music) single page |
| `myfunctions.php` | 9,567 | custom sidebars, comments callbacks, output-buffer hack |
| `taxonomy-singer.php` | 5,703 | artist (singer taxonomy) archive |
| `header.php` / `footer.php` | 2,480 / 2,393 | RTL layout shell, mobile header include |
| `tag.php` | 2,343 | tag archive doubling as artist page |
| `artist-template.php` | 2,212 | Page template "خواننده ها" (artist list) |
| `home.php` | 1,457 | homepage composition |
| `404.php` | 1,396 | error page + auto-redirect |
| `comments.php` | 1,234 | comment markup |
| `page.php` | 1,039 | static page |
| `category.php` / `search.php` / `index.php` | 860 / 795 / 707 | archive/search/index |
| `screenshot.jpg` | 250,279 | theme screenshot (branding asset — must not ship) |

### 2.2 `inc/` parts — `CLASS: THEME-CODE`
`theme-options.php` 11,752 · `widgets.php` 11,464 · `tarlanweb_player.php` 3,105 ·
`mobiles_header.php` 2,526 · `vip_slider.php` 1,722 · `no-content.php` 1,176 ·
`songs_post.php` 979 · `mostviews_posts.php` 950 · `artist_slider.php` 827 · `new_posts.php` 589 ·
`breadcrumbs.php` 409 · `pagination.php` 84

### 2.3 `inc/option-tree/` — `CLASS: THIRD-PARTY (GPLv3)`
OptionTree **2.6.0** (readme: "Tested up to: 4.4", License GPLv3, composer
`valendesigns/option-tree`). Included: `ot-loader.php` 24,554 · `includes/*.php` (12 files) ·
assets (`ot-admin.css` 98,786, `ot-admin.js`, `jquery-ui-timepicker.js`, fonts incl. Yekan) ·
`includes.zip` 81,507 · languages · `license.txt` (GPLv3 full text).

> **Deviation check (tamper analysis):** the tree's `includes/ot-functions-admin.php` is 195,843 bytes
> while `includes.zip` (dated 2026-07-28, inside the theme) carries 195,816 bytes for the same file.
> `diff` shows **one** benign difference at line 69: the removed-in-PHP-8
> `create_function('$caps', "return '$caps';")` was replaced by an anonymous closure
> (`function( $cap ) use ( $caps ) { return $caps; }`). All other files in `includes/` are identical.
> ⇒ [INFERRED] a PHP-8 compatibility patch was applied locally; **not** obfuscation, **not** malware.
> No other functional change was found.

### 2.4 Front-end assets — `CLASS: THIRD-PARTY`
| File | Bytes | License status |
| --- | ---: | --- |
| `js/jquery.js` | 89,390 | jQuery 3.x (UMD build; MIT) — banner stripped in bundle `[INFERRED version]` |
| `js/owl.carousel.js` | 90,033 | Owl Carousel **2.3.4**, header states MIT |
| `js/mediaqueries.js` | 14,939 | css3-mediaqueries-js-style polyfill `[NEEDS_REVIEW licence text]` |
| `js/html5shiv.js` | 10,339 | html5shiv (MIT/GPL dual) `[NEEDS_REVIEW]` |
| `js/scripts.js` | 19,195 | custom theme JS **plus** a bundled `jquery.cookie` plugin |
| `css/icofont.min.css` | 23,277 | IcoFont **1.0.1**, © 2015–2020, license URL only |
| `fonts/vazir-*.woff` (4) | 48,256 / 45,540 / 51,944 / 47,948 | Vazir typeface (SIL OFL expected) `[NEEDS_REVIEW]` |
| `fonts/icofont.woff` | 104,392 | IcoFont |

### 2.5 Media & extras
`images/*` (7 files, 252 KB): `no-artist.jpg`, `nomusics.jpg`, `vip_img.jpg`, `error404.jpg`,
`def-singer.webp`, `logo.png`, `favicon.png` → demo/provenance unknown, `[LEGAL_REVIEW_REQUIRED]` for
redistribution. `searchwp-live-ajax-search/` (2 files): template override pair for the SearchWP Live
Ajax Search plugin (dependency, not bundled). No dotfiles, no backups, no nested archives other than
`includes.zip`, and no hidden executable payloads were found.

---

## 3. Black-box inventory `[BLACK_BOX_FUNCTIONALITY]`

`functions.php` (94 KB of ionCube) is the theme's de-facto framework. From the *call sites* in readable
files, its public surface includes at least:

| Symbol used by templates | Uses | Behaviour inferred from call sites | Status |
| --- | ---: | --- | --- |
| `ot_get_option( $id, $default )` | 111 | OptionTree option getter (thin wrapper over `get_option('option_tree_settings')`-derived values) | `[INFERRED]` |
| `get_field/the_field`, `have_rows/the_sub_field` | 86 | **Advanced Custom Fields** API — ACF is a required, *not bundled* plugin | `[INFERRED]` |
| `pagination()` | 11 | paginated links markup | `[INFERRED]` |
| `the_views()` / `views` meta | 4 | post-view counter (WP-PostViews-like plugin) | `[INFERRED]` |
| `CSS_Menu_Maker_Walker` | 1 | nav-menu walker class used by the mobile menu | `[INFERRED]` |
| `comment_form_cd()`, `comment_loop_cd()` | 2 each | custom comment form/list callbacks (duplicated in `myfunctions.php` — see tech debt) | `[VERIFIED]` |
| `register_nav_menus`, `wp_enqueue_style/script`, `add_theme_support` extras, ACF field-group registration, sizing/`thumb1..thumb4` mappings | — | required by templates but **nowhere** in readable code (`thumb1..thumb4` are registered in `myfunctions.php`; the main enqueue of `style.css` is not) | `[BLACK_BOX_FUNCTIONALITY]` |

**Consequences for the rebuild**
1. The legacy theme cannot be shipped, sold, redistributed or even legally modified as a product:
   the encrypted files are proprietary, purchased on rtl-theme.com, and cannot be audited.
2. Any functionality that lives *only* in `functions.php` is `[BLACK_BOX_FUNCTIONALITY]` and must be
   **re-specified from observed behaviour** and **re-implemented from scratch** (rule §87:
   feature discovery ≠ code copying).
3. The rebuild must be able to run with the legacy theme uninstalled and the encrypted files deleted.

---

## 4. Dependency map (what actually must exist for the legacy theme to run)

| Dependency | Type | Bundled? | Consequence |
| --- | --- | --- | --- |
| Advanced Custom Fields (ACF) | Plugin | No | hard requirement for ~20 meta keys and all term fields |
| OptionTree 2.6.0 | Library (in-theme) | Yes (GPLv3) | obsolete; Settings API in legacy DB (`option_tree_settings`) |
| WP-PostViews-like view counter | Plugin | No | `views` meta + `the_views()` |
| SearchWP Live Ajax Search | Plugin | No (template override only) | live search UI (`data-swplive`) |
| kk Star Ratings | Plugin | No (soft, guarded by `is_plugin_active`) | rating widget |
| Rank Math **or** Yoast SEO | Plugin | No | breadcrumbs only (`inc/breadcrumbs.php`) |
| jQuery 3.x + Owl Carousel 2.3.4 + css3-mediaqueries + html5shiv | JS libs | Yes | front-end behaviour; all replaced in the rebuild |
| ionCube Loader (PHP extension) | Server extension | No | **required to boot the theme at all** |

---

## 5. Key audit findings (top 12)

1. **Encrypted core** — 158 KB of the product's logic is unauditable ionCube code, including whatever
   registers assets, nav menus and ACF field groups. This alone blocks any "modernization in place"
   strategy and mandates the rebuild. `[VERIFIED]`
2. **No custom post types.** Artists/albums/tracks/videos are all WordPress `post` rows distinguished
   by the `musics_type` post meta (`mp3` | `mp4` | `album`) plus the `singer` taxonomy. `[VERIFIED]`
3. **Dual artist model.** Artist pages exist twice: as the `singer` taxonomy archive
   (`taxonomy-singer.php`) *and* as tag archives with ACF term fields (`tag.php`), switched by the
   `reltag` option. Two competing data models for one concept. `[VERIFIED]`
4. **Player is global-ID bound.** Every player instance renders `<audio id="audio">` and
   `#volume-bar`, and `js/scripts.js` binds by `document.getElementById("audio")` + class selectors →
   at most one functional player per page, invalid duplicate IDs, no queue/state. `[VERIFIED]`
5. **`posts_per_page => -1` twice** in `taxonomy-singer.php` (album/audio queries) on an artist page,
   plus three meta+tax queries per artist page → unbounded, cache-hostile. `[VERIFIED]`
6. **Responsive images deliberately disabled** (`add_filter('wp_calculate_image_srcset','__return_false')`-style
   filter in `myfunctions.php`), no `srcset`, no lazy-load attributes. `[VERIFIED]`
7. **Hover-only navigation.** Desktop submenus open on `hover` with `jQuery.slideDown`, no keyboard or
   focus path; player controls are `<i>` glyphs with no `<button>`/`aria` semantics. WCAG 2.2 AA is
   unreachable without a rewrite. `[VERIFIED]`
8. **0% internationalization.** No `load_theme_textdomain`, no `languages/` directory, no `theme.json`,
   hardcoded `dir="rtl" lang="fa-IR"`; ~all UI strings are inline Persian (only ~758 `__()` calls, many
   with arbitrary text domains like `tarlanweb_ir_newp_domain`). `[VERIFIED]`
9. **No AJAX, no nonces, no REST, no SQL in readable code.** The theme never calls WordPress APIs that
   need capability checks; the live search is delegated to a plugin. Good news for the rewrite's
   security baseline; the only security surface is unsanitized *output* of admin/ACF values. `[VERIFIED]`
10. **Zero tests, zero build tooling, zero lint config**, no `.editorconfig`, no package.json,
    no composer.json at theme level (OptionTree ships its own composer.json only). `[VERIFIED]`
11. **Brand pollution**: author contact (name, personal email, phone `09158856205`), rtl-theme.com,
    `tarlanweb` prefixes, `rkianoosh` CSS classes and a purchased-product license banner are woven
    through templates, CSS classes, widget IDs and comments. `[VERIFIED]`
12. **Licensing risk concentrated in assets**, not code: bundled fonts (Vazir/Yekan), IcoFont, demo
    images/screenshot and the two ionCube files. `[LEGAL_REVIEW_REQUIRED]` — see `LICENSE-AUDIT.md`.

---

## 6. Risk register (audit-level)

| # | Risk | Impact | Likelihood | Treatment |
| --- | --- | --- | --- | --- |
| R1 | Encrypted `functions.php` hides behaviour (incl. remote calls, licensing callbacks) | High | Medium | treat as black box; rebuild; never ship; no reverse engineering |
| R2 | Redistribution of ionCube files breaches marketplace licence | High | High | exclude from all packages; document |
| R3 | Third-party asset licences unverifiable (fonts, icons, images) | High | Medium | replace with OFL/MIT/own assets; legal review where kept |
| R4 | Legacy data (posts + ACF meta) is unstructured; silent data loss during migration | High | Medium | migration tool with dry-run, mapping table and rollback |
| R5 | Site admins rely on OptionTree/Acf fields to keep publishing after switch | Medium | High | admin UX parity in Core plugin + migration of settings |
| R6 | SEO regression from hidden-H1 keyword pattern / 404 auto-redirect | Medium | High | remove patterns in rebuild; keep URLs via redirect map |

---

## 7. Audit exit criteria (satisfied)

- [x] Every file inventoried, hashed and classified
- [x] Encrypted artefacts identified and *not* touched
- [x] Feature set extracted with evidence (call sites, JS bindings, template flow)
- [x] Data model documented (meta keys, taxonomies, ACF fields, option keys)
- [x] Licence, security, performance, SEO, UX audits produced
- [x] Migration blueprint + rebuild plan + tech-debt register produced
- [x] No production code written; no legacy file modified
