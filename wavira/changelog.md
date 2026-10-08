# Wavira Music — changelog

All notable changes to the theme are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added — 0.14.0 (the front page a customer expects, and a real render)
- **`front-page.html`** — the blog index moved out of the way (the old `home.html` is deleted, so
  `index.html` serves native posts again, which is what core, feeds and SEO plugins expect) and the front
  page is now the composition the legacy theme sold and the product never shipped: the latest albums, the
  catalogue player, the latest tracks, the latest videos and the music news, each section a native Query
  Loop over the plugin's post types with its own heading pattern (docs/FEATURE-MAP.md F-20). Every section
  works with the plugin's own defaults, and every one disappears cleanly when its post type is empty.
- **`tools/screenshot.mjs`** — renders the theme in a real browser: the 1200×900 marketplace screenshot,
  extra pages, a dark-mode home and an axe-core report. `wavira/screenshot.png` was the one blocking item
  a marketplace requires that cannot be produced from source (docs/RELEASE-CANDIDATE.md §6, item 1), and
  the authoring environment has no browser — so the render happens in CI, on a real WordPress with the
  Persian demo seeded (`wp-render`), which is also the first time the product is photographed rather than
  measured. The tool refuses a screenshot whose size, status code or rendered text says it is not the page
  it claims to be, because a silently blank image is worse than none (9 unit tests in
  `tests/js/screenshot.test.mjs` cover exactly those refusals).
- **`tools/check-axe.mjs`** — the accessibility verdict, kept separate from the measurement so the
  screenshot and the report about it always come from the same render: a `serious` or `critical` axe
  finding fails the build, `moderate`/`minor` are printed, and anything allowed by `--allow=` is named in
  the output instead of disappearing.
- **`docs/screenshots/`** — the render's own record: the front page, an album, an artist profile and a
  dark-mode home, committed so a review needs no running site and a visual regression shows up in a diff.
  The dark home is byte-identical to the light one, which is honest: the theme's dark mode is a
  `prefers-color-scheme` switch a visitor owns, and `emulateMediaFeatures` sets the media query without a
  site that has dark colours defined yet — the file records that rather than hiding it.
  The render job commits them on a branch (never on a tag, where there is no branch to commit to), because
  an image that only exists as a build artifact is an image the packaged theme cannot carry.

- **`tools/check-render.mjs`** — the page has to be the front page: every front-page heading must be
  translated in the shipped `fa_IR.po` **and** on the page, and the page must carry the front-page
  template's own sections and Persian text. It reads the expected strings from the catalogue instead of a
  list typed into CI, so a heading that loses its translation fails the same gate as an English page
  (17 unit tests, one of which checks the repository's own catalogue).

### Fixed — 0.14.0
- **The theme shipped no front page at all** (`wavira/templates/`): `home.html` existed and `index.html`
  was `home.html` under a second name, so a WordPress default install — which uses the blog index as the
  front page — showed a news grid at the root and the music sections were reachable only on `/blog/`.
  A music theme whose front page does not show the music is a defect a screenshot catches and a static
  gate cannot. `home.html` is gone for good (the gate refuses it if it comes back), `index.html` is the
  blog index it always should have been, and `front-page.html` is the front page. The first version of
  this fix deleted `index.html` outright, and the first real run of the render job refused to activate the
  theme — «پوسته‌های مستقل باید یک پروندهٔ `templates/index.html` یا `index.php` داشته باشند» — so the gate
  now also fails when `index.html` is missing: the theme's one required file is a thing a static check can
  hold.
- **The front page had no `h1`** (found by the render's axe run, `page-has-heading-one`): the header
  renders the site title as a `<p>` on purpose, so every other template gets its heading level one from the
  post or page title and the front page got none. It carries its own now — the site name, visually hidden —
  and `tools/check-render.mjs` counts the page's landmarks and headings while it is checking the language.
- **The whole theme rendered as a 720px column** (`wavira/templates`, `wavira/parts`, `wavira/patterns`):
  every group wrapper carrying one of the theme's own container classes — `wavira-layout`, `wavira-section`,
  the header, the footer, the top bar, the surface, the player — was declared with a *constrained* block
  layout, and WordPress caps a constrained layout at `theme.json`'s `contentSize` (720px here) and centres
  it. The theme's own container never applied: `.wavira-layout`, the `container-width` setting and the wide
  grids were all overruled by an inline `max-width`, so a music catalogue rendered as a column of text with
  two thirds of the page empty. The wrappers are flow layouts now — the width belongs to the theme's CSS —
  and `tools/check-blocks.mjs` refuses a container class wearing a constrained layout. The first rendered
  screenshot is what found this, after five releases of green static gates.
- **A `doing-it-wrong` notice on every request** (`wavira-core`): the settings option was registered as an
  `array` — core has required an item schema for that type since 5.4, and the value is really a map of
  setting name to value, so it is registered as an `object` now. A notice in the debug log is a defect, and
  the render job's debug log is what found it (`tests/test-settings-registration.php` now runs that
  registration under a `doing_it_wrong_run` collector).

### Added — 0.13.0 (the settings screen)
- **Appearance → Wavira settings** (`inc/admin-panel.php`): the same 32-setting schema, now with a screen
  of its own in the admin menu — one tab per Customizer section, plus a **demo import** tab — so the panel
  is found without knowing that a block theme keeps its Customize link behind `customize_register`
  (`wp-admin/menu.php`). Registration is `add_theme_page()`, the capability is `edit_theme_options` (the
  Customizer's own), and the save path is a nonced `admin-post.php` action that writes the same
  `theme_mod`s through the same `wavira_sanitize_option()`. A value equal to its declared default is
  stored as *no row* (`remove_theme_mod()`), so the screen cannot turn a default site into a site with
  options. The one case where an absent field does carry a value — an unchecked box — has a test of its
  own: a switch whose default is on is stored as *off*, or the box could never be turned off.
- **A demo tab that tells the truth** (`wavira_admin_panel_demo_tab()`): with the plugin active it links
  to `Tools → Wavira demo content`; without it, it says what Wavira Core provides and links to the plugin
  installer — never a button that fails on click.
- **`assets/js/admin.js`** (2.5 KB gzipped, budgeted at 8 KB, enqueued on this screen only): a colour
  swatch that writes into the text field the form actually posts, a `wp.media` picker for the logo and the
  dark-mode logo with an explicit clear, and no jQuery (ADR 0006).
- **`tests/test-admin-panel.php`** (14 cases: registration and capability, the tabs being the sections plus
  `demo` last, every field rendering on its own tab, escaping, only the posted tab saving, a default value
  not being stored, the Persian notice, the demo tab with and without the plugin) and
  `tests/js/admin.test.mjs` (9 cases).

### Fixed — 0.13.0
- **The Persian notice whitelisted the wrong screen id** (`inc/site-defaults.php`): the demo screen is
  registered by the plugin under **Tools**, so its id is `tools_page_wavira-demo`. The 0.12.0 code checked
  `appearance_page_wavira-demo`, which is why a site owner standing on the screen the notice described saw
  no notice. The list is data now (`wavira_persian_notice_screens()`), both names are asserted in a test,
  and the notice links to the settings screen.
- **A cleared image field threw** (`assets/js/admin.js`): `renderPreview()` read `model.toJSON` before
  checking the model, so clearing an empty logo raised a TypeError and left the preview alone.
- **The colour field could store a typo** (`assets/js/admin.js`): the text field only moves the swatch for
  a `#rgb`/`#rrggbb` value, and clearing the text clears the swatch too.
- **The legacy gate tripped on its own documentation** (`tools/lint.sh`): comment lines are exempt from the
  jQuery grep, so a file may say "no jQuery" without failing the build.
- **`tools/po-merge.py` could not round-trip a catalogue**: it read a *plural* entry as untranslated (a
  plural entry has no `msgstr ""` line, so the merge refused to write the plugin's catalogue), it read the
  `msgid ""` header block as an entry and moved the header into the body, and `re.sub()` read the `\n` of a
  header value as a newline and split the field it was rewriting. Both catalogues now merge without a diff
  beyond the dates, and the header's `Project-Id-Version` follows the template.

### Added — 0.12.0 (theme options panel and the Persian setup)
- **The Wavira settings panel** (`inc/options.php`, `inc/customizer.php`): one schema — key, default,
  type, control, section, label, bounds — drives the Customizer panel *and* the front end, so a setting
  cannot exist in one and not the other. Sections: identity and logo (logo width, a separate logo for
  dark mode, site title and tagline switches), header (sticky, WordPress search block, announcement bar,
  player bar), appearance (first-visit colour mode, accent, content width, corner radius, card shadow,
  smallest card width, light/dark toggle), fonts (Vazirmatn · system · custom stack, base size, heading
  weight, preload), social networks, footer text, and additional CSS.
- **Values reach the page as CSS variables and body classes, never `!important`**: colours, sizes and
  stacks are printed in one `<style id="wavira-options-css">` element (the ADR 0014 mechanism, so every
  block follows them); heading weight and the sticky header are body classes, which win on specificity.
  A default site prints **nothing** — `wavira_option_css()` returns an empty string while every setting
  is at its declared default.
- **A dark-mode logo** (`get_custom_logo` filter) that follows the same `prefers-color-scheme` query as
  the palette, so `auto` mode shows it on a dark device without a second setting.
- **One-click Persian setup** (`inc/site-defaults.php`): a nonced, `manage_options`-gated action that
  installs the WordPress translation for `fa_IR` *first* — WordPress refuses to store a locale it has no
  translation for, so the other order would leave a site English — and then sets the site language,
  timezone, week start and date format, through `wavira_core_apply_persian_defaults()` when the plugin is
  active and on its own when it is not. The download is filterable
  (`wavira_download_core_language_pack`) for hosts that manage language packs themselves, or that have no
  way to reach wordpress.org; whatever is left undone is reported to the site owner instead of being
  silent, and a dismissible notice on Appearance → Themes and the Customizer explains the situation while
  the site language is not Persian.
- **Five social glyphs and a back-to-top icon** (`assets/icons/`): simple geometric shapes drawn for this
  theme, stroke-based and `currentColor`, not the networks' official artwork — nothing trademarked is
  redistributed and the accessible name comes from the translated label.
- **Live preview** (`assets/js/customizer.js`, built to `assets/dist/customizer.js`): every field that
  drives a CSS variable or a body class declares `postMessage` transport and updates in the preview
  without a reload — accent, widths, radius, shadows, base text size, heading weight, the four display
  switches, and the additional-CSS block. Fields that change markup stay on `refresh`, because only the
  server can render them. The preview's list is derived from the same `live` entries the option CSS is
  printed from, so a property name exists in one place; the script is loaded only inside the Customizer
  frame and is budgeted separately (≤ 10 KB gzipped).
- **`tests/test-theme-options.php`**: the schema is complete and every declared type has a sanitizer that
  refuses garbage; a hostile `</style>` value cannot leave its element; a default site prints no CSS;
  every switch reaches the front end (CSS, body class, or markup); each pattern renders only for its
  switch; the player bar is removed from the output rather than hidden; the Customizer registers every
  field in its declared section with the shared sanitizer; and the Persian action is nonced and applies
  the four documented options.
- **`tools/po-merge.py`**: fills a `.po` from its `.pot` in template order, carrying translations and
  `keep-latin` flags over and refusing to write a catalogue with an untranslated string — the tool that
  added the 100 Persian strings this phase needed (catalogue now 191/191 for the theme, 150/150 for the
  plugin).

### Added — 0.11.0 (release candidate: packaging, bundled typeface, release docs)
- **Vazirmatn ships with the theme.** One unmodified variable WOFF2 (weights 100–900, 109 KB) declared as a
  `fontFace` in `theme.json`, so WordPress prints the `@font-face` rule for the front end *and* the block
  editor; the SIL OFL 1.1 text travels beside it in `assets/fonts/vazirmatn/OFL.txt` (ADR 0010), and
  `tools/check-css.mjs` fails the build when the file, the licence or `font-display: swap` goes missing.
  Until now the stack only *preferred* Vazirmatn and fell back to whatever the visitor's system had.
- **`inc/performance.php` — the typeface is preloaded.** Core prints font-face rules on `wp_head` priority
  50; without a hint the browser cannot start the request until that CSS has been parsed and a rule has
  matched, so the text paints in a fallback face and re-lays out. The hint is same-origin, comes from the
  theme's own URL and is skipped when the file is absent.
- **`readme.txt`** — a WordPress-style listing document beside `README.md` (description, install steps,
  FAQ, screenshots, copyright and the third-party notices).
- **Release packaging.** The theme archive is now assembled by `tools/package.mjs` (deterministic, leak
  scanned, licence-checked, install-tested) and uploaded by CI, instead of being zipped by hand.

- **The kind archive** (`templates/taxonomy-wavira_kind.html`) — `/kinds/remix/`, `/kinds/noha/` and
  friends render a track listing with the term's description instead of falling back to the blog archive.
  The taxonomy itself is the plugin's (`Content\Taxonomies::KIND`); the template is the presentation.

### Changed — 0.11.0
- The header, `WAVIRA_THEME_VERSION` and `package.json` all read `0.11.0`; the release gate fails when they
  disagree, so "which version is this" has one answer.

### Added — 0.10.1 (Persian localisation)
- **Jalali dates on Persian sites.** Templates, cards, archives and the artist profile print Jalali
  (Shamsi) dates with Persian numerals through the plugin's date API — «۱۳ مهر ۱۴۰۵» — while `<time>`,
  schema.org and feeds stay Gregorian on purpose.
- **Persian numerals** wherever a number is user-visible (durations, quality labels, counts, tracklist
  indices).
- **`style.css`** declares the Persian-facing theme metadata (`Tags: rtl-language-support,
  translation-ready` unchanged, version aligned with the phase) and the catalogue was completed.

### Added — 0.10.0 (SEO cooperation and the performance budget)
- **`inc/seo.php`** — music structured data printed as one JSON-LD tag from the graph the plugin
  builds, encoded with `JSON_HEX_TAG` so a title containing `</script>` cannot close the tag; plus
  `meta description`, Open Graph and Twitter cards **as fallbacks only** — they print when no SEO
  plugin is active (`wavira_theme_seo_plugin_active` overrides). A music single's document title now
  carries its artist (`First track · Demo Artist`).
- **`inc/performance.php`** — the `s.w.org` DNS hint is dropped (the last third-party request
  WordPress adds); the admin and feed emoji filters go with the front-end ones.
- `tools/check-perf.mjs`, wired into `tools/lint.sh` as **`[PERF]`**: gzipped budgets, zero
  third-party URLs in shipped assets, no `posts_per_page => -1`, no `nopaging`, no disabled `srcset`.

### Changed — 0.10.0
- **LCP fix**: `wavira_get_image()` no longer hard-codes `loading="lazy"`/`decoding="async"` on the
  core path. Core promotes the first, likely-LCP image to `fetchpriority="high"`; the theme was
  cancelling that. A bare URL (no attachment) keeps the documented lazy fallback, and `[PERF]` fails
  the build if the attributes come back.
- Theme version 0.10.0; the catalogue stays at 215 strings, all translated (no new user-facing text —
  schema values are content, not interface).

### Added — 0.9.0 (artist profiles and the news section)
- **`include inc/artists.php`** — the artist page surfaces: portrait (with a music placeholder), name,
  quote, translated social chips, counts, the works grouped by album/single/video with per-section
  counts and "view all" links, the biography and the photo gallery. Renders the payload the core
  plugin builds (`wavira_core_artist_profile()`), read once per request (`wavira_artist_data()`).
- **`inc/news.php`** — the music-news feed as cards (`wavira_get_news()`, `wavira_get_news_categories()`)
  on top of `wavira_core_news_feed()`.
- **Three blocks** — `wavira/artist-profile`, `wavira/artist-gallery` and `wavira/news` (seven in
  total) with editor strings printed from PHP, so the Persian catalogue covers the editor too.
- **Three shortcodes** — `[wavira_artist]`, `[wavira_gallery]`, `[wavira_news]`; each delegates to the
  same helper as its block.
- **Shared card component** (`wavira_get_card()`, `wavira_get_image()`, `wavira_get_section_head()`) —
  the artist sections and the news feed render one component, and every image goes through
  `wp_get_attachment_image()`, so `srcset`/`sizes`/`width`/`height` always come from core.
- **Templates** — `single-wavira_artist.html` (profile + the artist's queue), `home.html` (the blog
  index as the news section, paginated) and `archive.html` (category/tag/author/date archives with the
  term title and description).
- **Pattern** — `hidden-heading-music-news.php`, because the news heading is template text and
  template text must stay translatable (`hidden-*.php`).
- **Icons** — `assets/icons/music.svg` (placeholder portrait) and `external.svg`.
- **Styles** — cards, artist profile, gallery and news grids in `components.css`, with editor parity in
  `editor.css`; logical properties and tokens only.

### Changed — 0.9.0
- Six of the ten theme templates now carry the artist/news surfaces; `wavira-news-grid` styles core's
  Query Loop in the blog templates instead of rendering a second card component.
- Theme version 0.9.0; both catalogues grew to 215 strings (91 theme + 124 core), all translated.

### Added — 0.8.0 (Persian-first)
- **Persian ships with the theme**: `languages/fa_IR.po` (58 strings, hand-written) and the compiled
  `languages/fa_IR.mo` WordPress loads, plus a generated `languages/wavira.pot`. The front-end copy,
  the block titles/descriptions/keywords and the block editor's own strings are all translated — the
  editor gets its strings from PHP (`wavira_block_editor_strings()` + `wp_add_inline_script()`) instead
  of a hash-named JSON file, so one catalogue covers everything.
- **`[FA]` gate** (`tools/i18n.mjs check`, wired into `tools/lint.sh`): a string with no Persian
  translation, a translation that is still Latin without an explicit `#, keep-latin` flag, changed
  placeholders, a stale `.pot` or a `.mo` that no longer matches its `.po` all fail the build.
- **`npm run i18n:extract` / `i18n:build` / `i18n:check`** for the whole pipeline, and
  `tests/js/i18n.test.mjs` + `tests/test-i18n.php` to pin the file format and the runtime behaviour.

### Changed — 0.8.0
- Version 0.8.0; the editor script no longer depends on `wp-i18n` or `wp-components` and takes its
  strings from `window.waviraBlocks`.
- Block metadata strings are extracted from `block.json` with the contexts core uses
  (`block title`, `block description`, `block keyword`).

### Added — 0.7.0 (patterns polish)
- **Translatable template text** (`patterns/hidden-*.php`): a block template cannot execute PHP, so
  every sentence in `templates/*.html` and `parts/*.html` moved into a hidden pattern
  (`Inserter: no`) referenced with `wp:pattern` — the practice the core themes use. The colour-mode
  button, both footer lines and the 404 copy are included.
- **`[I18N]` gate** (`tools/check-i18n.mjs`): no hard-coded text node and no text-bearing block
  attribute in a template or part, every `wp:pattern` reference must resolve, every hidden pattern must
  be referenced. Non-translatable strings (the author attribution) carry an explicit
  `wavira:i18n-exempt` marker.
- **`[MAPPING]` gate** (`tools/check-mapping.mjs`): every `wavira_*` name promised by
  `docs/MIGRATION-BLUEPRINT.md` must exist in the schema, unless the row is marked `[DEFERRED]`.
- Insertable patterns (`album-grid`, `featured-album`, `latest-tracks`, `genre-chips`) now translate
  their own headings and labels with `esc_html_x()`.

### Fixed — 0.7.0 (patterns polish)
- **The footer year was a literal `2026`** in `parts/footer.html` and could never change; it is now
  printed by `wp_date( 'Y' )` from the footer pattern.
- **The 404 search block stored `buttonText`/`label` literals** in the template; both are omitted so
  core's translated defaults apply.
- Six sections shipped untranslatable English headings ("Latest albums", "Listen now", "Latest
  tracks", "Music videos", "Tracks", "Watch") and a "Nothing published here yet." message; all of them
  now come from patterns.

### Added — 0.7.0 (block layer)
- **Four dynamic blocks** (`blocks/{tracklist,player,video,genre-chips}/`): each ships `block.json`
  (apiVersion 3, `category: wavira-music`, `textdomain: wavira`, `render: file:./render.php`,
  `supports.html: false`) and a `render.php`. Registered on `init` priority 5 — that is before the
  patterns, so a pattern that contains a `wavira/*` block is never validated against a missing block.
- **Shared markup** (`inc/markup.php`): `wavira_get_tracklist()`, `wavira_get_video()` and
  `wavira_get_genre_chips()` return escaped markup, their print wrappers echo it, and blocks,
  shortcodes and templates all render through them — the same content cannot look different because
  of the editor that produced the page. `wavira_block_placeholder()` prints an editor-only hint when
  a dynamic block has nothing to render (REST requests only), so an empty block does not look broken.
- **Block editor script** (`blocks/editor.js`): registers the four blocks against the `wp.*` globals
  with a generic `ServerSideRender` preview and `save() → null`. No build step and no `@wordpress/*`
  import in the shipped theme (ADR 0006).
- **Templates and patterns moved to native blocks**: all 10 shortcode blocks in `templates/*.html`
  and `patterns/*.php` are now `wp:wavira/*` blocks; `wp:shortcode` appears nowhere in the product.
- **New styles**: `.wavira-genre-chips`, `.wavira-tracklist__subtitle` and the block-editor preview
  classes (`.wavira-block`, `.wavira-block-hint`, `.wavira-block-placeholder`).
- **Block gate**: `tools/check-blocks.mjs`, wired into `tools/lint.sh` as `[BLOCKS]` — metadata,
  renderer, registrar and editor registrations must agree, and the renderer must refuse direct access.

### Changed — 0.7.0
- `[wavira_tracklist]` gained `duration` and `subtitle` attributes and now delegates to the shared
  helper; `[wavira_video]` follows the same path, so shortcode and block output are identical.
- Theme version 0.7.0.

### Added — 0.6.0 (theme UI)
- **Four CSS layers** (`assets/css/tokens.css`, `base.css`, `components.css`, `utilities.css`): design
  tokens (incl. the `--wavira-on-accent` token), element defaults, components (header, footer, hero,
  sections, tracklist, chips, video frame, site player, theme toggle) and layout utilities. Logical
  properties only, no `!important`; 5.9 KB gzipped together (budget 25 KB).
- **Dark mode** (`tokens.css` + `styles/dark.json` style variation): the palette is remapped for dark,
  the mode lives on `<html>` as `light|dark|auto`, and `auto` follows the system through
  `prefers-color-scheme` (ADR 0014).
- **Editor parity** (`assets/css/editor.css`, loaded through `add_editor_style()`): palettes, cover
  radius, chips, hero grid and the shortcode placeholder match the front end.
- **Templates and parts**: `index`, `page`, `single`, `search`, `404`, `single-wavira_{album,track,
  artist,video}`, `archive-wavira_{album,track,artist,video}`, `taxonomy-wavira_genre` — 14 templates —
  plus `parts/{header,footer,player-bar}.html`.
- **Patterns** (`patterns/{featured-album,album-grid,latest-tracks,genre-chips}.php`) and the
  `wavira-music` pattern category.
- **Shortcodes** (`inc/shortcodes.php`): `[wavira_tracklist]` and `[wavira_video]`, both resolving
  `current` to the queried object, so a block-less site keeps the classic workflow.
- **Player mounting** (`inc/player.php`): `[wavira_player context=… id=… slug=… limit=… track=…
  orderby=… order=… autoplay=… sticky=… fallback=… class=…]`; the mount point and its `<audio>`
  fallback render even when the plugin is inactive (theme-switch safety, ADR 0002).
- **Assets pipeline** (`inc/assets.php`): conditional enqueue, `filemtime` cache busting, the
  `wavira_theme_settings` filter, and the pre-paint colour-mode script on `wp_head`.
- Theme version 0.6.0.

### Fixed — 0.6.0
- **`--wp--custom--player--barSpace` never resolved** (camelCase vs. WordPress' kebab-cased custom
  properties), so the fixed-bar space setting had no effect. Corrected to `--player--bar-space`; the
  new `tools/check-css.mjs` rule 6 fails the build for unresolvable token references (ADR 0014).
- **Accent-chip text failed WCAG in dark mode** (1.51:1 — a light-mode colour was hard-coded on the
  dark accent fill). Replaced by the `--wavira-on-accent` token; `tools/check-contrast.mjs` now checks
  21 mode-specific pairs, including this one.
- `Theme URI` no longer points at the unregistered `wavira.com` (see `docs/BRAND-DECISION.md`).

### Added — 0.5.0
- `inc/player.php`: `wavira_player_mount()` prints a player mount point (`data-*` contract, no element
  IDs) and a native `<audio>` fallback with the preferred source, so a track page works without
  JavaScript; `wavira_player_preferred_source()` picks 320 → 128 → external. The helper asks Core for
  the bundle through `wavira_core_enqueue_player()` and for the payload through
  `wavira_core_track_playback()` — the theme still names no plugin class (gate R3).
- Theme version aligned with the product (0.5.0). No template renders the mount point yet; that is the
  first task of 0.6.0.

### Changed — 0.4.0
- Version aligned with the product roadmap so the theme and **Wavira Core** 0.4.0 ship as one
  release. **No theme code changed in this phase:** search, related items, counters and the download
  endpoint are plugin services (ADR 0002), and the theme still renders through
  `wavira_get_setting()`/`wavira_has_core()` only. Templates and components arrive in 0.6.0.

### Changed — 0.2.1
- Author/designer credit set to **Etehad WP — اتحاد وردپرس** (`https://etehadwp.com/`) in the theme
  header, READMEs, `composer.json`, `package.json` and `LICENSE.md` (owner decision 2026-10-05).

### Added — 0.2.0 (architecture phase)
- Repository skeleton: `inc/`, `templates/`, `parts/`, `patterns/`, `blocks/`, `assets/`, `languages/`.
- Thin `functions.php` bootstrap (constants + `require_once` only — no God file).
- `inc/setup.php`: theme supports, nav menus, music artwork image sizes, text domain.
- `inc/assets.php`: build-aware, conditional asset loading (no front-end jQuery).
- `inc/helpers.php`: `wavira_has_core()`, `wavira_get_setting()`, `wavira_icon()`.
- `inc/hooks.php`: RTL/context body classes, emoji-asset cleanup, excerpt length.
- `theme.json`: design tokens (colour, typography, spacing, radius, shadow, motion, player metrics).
- Architecture documentation and ADRs (repo `docs/`).

### Removed (vs. the legacy theme)
- ionCube-encrypted `functions.php` and marketplace licence file — never carried over.
- OptionTree settings framework (2.2 MB), IE-era polyfills, icon font, `srcset`-disabling filter,
  UA sniffing, hover-only menus, global `#audio` player.
