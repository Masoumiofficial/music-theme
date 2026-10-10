# ARCHITECTURE-LEGACY.md — Architecture of the Legacy Theme

**Status:** VERIFIED (static reading) · `[BLACK_BOX_FUNCTIONALITY]` where noted
**Rule respected:** legacy code was read only; nothing modified, nothing decrypted.

---

## 1. Bird's-eye view

```
javanseda/  (WordPress theme, RTL, fa-IR, WP-classic era ≈2015)
│
├── functions.php           ← ionCube BLACK BOX (94 KB): "the framework"
│       • enqueues style.css/JS (not visible elsewhere)
│       • registers nav menus (top_menu, ft_menu, mobiles_menu)
│       • registers ACF field groups + taxonomies (singer, musics_type)
│       • defines ot_get_option(), pagination(), the_views()?, CSS_Menu_Maker_Walker, comment callbacks
│       • contains licence/verification logic (paired with RTL_License_*.php)
│
├── RTL_License_*.php       ← ionCube BLACK BOX (64 KB): purchase/licence artefact (rtl-theme.com p=172924)
│
├── myfunctions.php         ← sidebar registrations + comments + output-buffer hack
├── inc/                    ← "template parts" (no parts/ or template-parts/ era conventions)
│      theme-options.php  → OptionTree settings schema (6 sections, 40+ fields)
│      widgets.php        → 3 custom WP_Widget classes
│      tarlanweb_player.php / vip_slider.php / new_posts.php / mostviews_posts.php /
│      artist_slider.php / songs_post.php / no-content.php / pagination.php / breadcrumbs.php /
│      mobiles_header.php
├── single.php · index.php · home.php · page.php · category.php · tag.php · search.php · 404.php
├── taxonomy-singer.php · artist-template.php (page template)
├── header.php / footer.php / comments.php
├── style.css               ← single 62 KB stylesheet, 682 rules, 12 media queries
├── js/ (jquery, owl.carousel, scripts.js, mediaqueries, html5shiv)
├── css/icofont.min.css · fonts/ (Vazir, IcoFont) · images/ (7 assets)
└── searchwp-live-ajax-search/  ← override for a third-party plugin
```

There is **no** `template-parts/`, `parts/`, `templates/`, `theme.json`, `inc/setup.php`,
`inc/enqueue.php`, `languages/`, `blocks/`, `elementor/`, `docs/`, `package.json` or build pipeline.
The architecture is *procedural fragment templating*: PHP templates call global helper functions and
`get_template_part()` fragments; state lives in globals (`$post`, `$wp_query`, `$current_post_id`).

---

## 2. Rendering pipeline (as observed)

### 2.1 Shell
```
header.php
  ├─ hardcoded <html dir="rtl" lang="fa-IR">
  ├─ <title> manual (is_front_page ? bloginfo(name) : wp_title(''))   ← wp_title deprecated
  ├─ ot_get_option('favicon'|'logo') echoed into <link>/<img>          ← unescaped admin values
  ├─ wp_enqueue_script("jquery"); wp_head('');                         ← jQuery forced in header
  ├─ if wp_is_mobile(): inline <style> body{padding-top:60px}          ← UA sniffing, cache-hostile
  ├─ body_class('night') when dark_modes enabled                       ← dark mode = body class + cookie
  ├─ desktop <header id="header">: logo, top_menu, search form (data-swplive), dark toggle
main
footer.php
  ├─ get_template_part('inc/mobiles_header')                           ← mobile menu (also on desktop DOM!)
  ├─ back-to-top, fixed telegram/instagram buttons, ft_menu, socials, copyright
  └─ wp_footer()
```

### 2.2 Homepage (`home.php`)
```
inc/vip_slider.php      meta vip_song=1   → Owl carousel (vip_img or thumb1)
inc/tarlanweb_player.php meta plym=1      → index audio player (music128 sources, v_num tracks)
inc/new_posts.php       main loop         → "جدیدترین مطالب" (option index_hj default off)
inc/mostviews_posts.php meta views        → popular in last month (index_nm)
hty (OptionTree list)   per-row: category box (hty-cat × hty-t) via WP_Query(cat)
inc/artist_slider.php   siing_t (OptionTree list) → Owl carousel of manual artist links
```

### 2.3 Single (music post) — `single.php`
```
ads (ads_bt/adsjs_bt) → article header (title, kk-star-ratings if active, category, views, comments)
→ the_content()
→ ACF music_text (lyrics)
→ one <video> with 480 → 720 → 1080 fallback
→ CASE A: music128 present → single-track player (<audio id="audio"> + custom controls)
→ CASE B: have_rows('album') → album player (all rows as <source> in ONE <audio id="audio">,
  playlist rows with song_names + per-row download links albumlink128/albumlink320)
→ dl_links_box: music128 / music320 / album128 / album320
→ dl_linksv_box: video1080 / video720 / video480
→ footer: date, shortlink input  → share (FB/Twitter/WhatsApp/Telegram)
→ tags box → related block:
      if reltag=on  : WP_Query on meta 'artist' LIKE $artist   (+ tag links)
      else          : WP_Query on taxonomy 'singer' (terms of this post)
→ widget area left_side ; then left_side2 sidebar
→ comments_template()
```

### 2.4 Artist archive — `taxonomy-singer.php`
```
hero: aimg2 (or def-singer.webp), social links from term fields (afacebook/atelegram/ainstagram/atwitter/ayoutube)
counts: 3 × WP_Query(meta musics_type = mp3 | mp4 | album, tax singer = term) → found_posts
body:   3 × WP_Query(posts_per_page => -1, meta musics_type = …, tax singer = …) → three grids
```
`tag.php` renders the *same* hero from **tag** ACF fields — the second, competing artist model
(controlled by the `reltag` option). `artist-template.php` is a Page Template listing 18 `singer`
terms per page with manual `paginate_links()` (uses an undefined `$paged` local).

---

## 3. State & coupling

| Mechanism | Where | Consequence |
| --- | --- | --- |
| Global single `<audio id="audio">` | `single.php`, `inc/tarlanweb_player.php` | one player per page, duplicate IDs, JS binds to first match |
| `nowPlaying = document.getElementById('audio')` | `js/scripts.js` | global variable, breaks with multiple players |
| `$wp_query` reassignment | `inc/widgets.php` (widgets set `global $wp_query; $wp_query = new WP_Query(...)`) | corrupts the main query/pagination for anything after the widget |
| `$current_post_id`, `$post` used in widget scope | `inc/widgets.php`, `single.php` fragments | undefined-variable notices; wrong "now playing" highlight |
| `ob_start()` buffer rewrite of the whole response | `myfunctions.php` | strips `type="text/javascript"`, `frameborder`, `scrolling` from **every** response; fragile, breaks non-HTML/JSON responses, adds full-page buffering |
| `wp_is_mobile()` UA sniffing for layout | `header.php`, `inc/songs_post.php` | different markup per UA → page-cache poisoning; served twice |
| Option lookups everywhere (`ot_get_option`, 111 call sites) | all templates | every render hits OptionTree/OptionTree cache; no typed settings object |

---

## 4. Assets & CSS architecture

- One 61,949-byte `style.css`: 682 rule blocks, 70 `!important`, 12 `max-width` breakpoints
  (250/350/450/500/600/700/800/900/1000/1100/1200/1300), `body{line-height:1px}` reset, no `:root`,
  **no CSS custom properties**, dark mode implemented as `.night …` selector overrides (≈20+ rules).
- Fonts: 4 Vazir `@font-face` entries (normal/bold/300/500) — the 500 weight is missing
  `font-display: swap`.
- Icons: IcoFont CSS + woff, used as `<i class="icofont-…">` for *interactive* controls.
- JS: jQuery + Owl Carousel + jquery.cookie (bundled inside `scripts.js`) + leftovers
  (`mediaqueries.js`, `html5shiv.js`) that target IE8-era browsers.
- `inc/songs_post.php` switches thumbnail size per UA (`thumb4` mobile / `thumb2` desktop).

## 5. Admin architecture

- Theme settings: OptionTree 2.6.0, schema in `inc/theme-options.php` (sections: general, socials,
  homes, dl_pages, ads1, footer; ~40 options incl. repeatable `hty` category-box list and `siing_t`
  artist list). Saved as the single option `option_tree_settings` / values via OptionTree API.
- Meta boxes: provided by ACF (field groups registered in the black box) — the legacy editor is a
  classic-editor post screen with ACF fields and a taxonomy meta box.
- Custom widgets: `tarlanweb_ir_newp` (latest), `tarlanweb_ir_popular` (by `views` meta),
  `tarlanweb_ir_rand` (random). They reassign `$wp_query`, don't validate `$instance` keys and have no
  `esc_*` output for titles (partially `strip_tags()` on save).
- No custom admin columns, no bulk edit, no import/export, no onboarding, no capability model.

## 6. Architectural verdict

| Dimension | Verdict |
| --- | --- |
| Separation of concerns | **Poor** — content/business logic split across encrypted core, templates, OptionTree and ACF |
| Theme-switch safety | **Fatal** — music data is `post` + meta; switching themes *loses* rendering and settings semantics |
| Reusability | **Poor** — fragment parts are coupled to globals and per-page options |
| Extensibility | **Poor** — no hooks/filters surface documented; behaviour hidden in encrypted code |
| Testability | **None** — no build, no lint, no tests, no DI |
| Upgrade path to WordPress ≥6.x (block themes/PHP 8.x) | **Blocked** by legacy settings, classic editor assumptions and the encrypted core |

**Conclusion:** the only viable route is `LEGACY KNOWLEDGE → NEW SPECIFICATION → NEW ARCHITECTURE →
NEW IMPLEMENTATION` (§87). Feature behaviour is re-specified in `FEATURE-MAP.md`, data mappings in
`MIGRATION-BLUEPRINT.md`, target structure in `REBUILD-PLAN.md`.
