# FEATURE-MAP.md — Feature Extraction & Re-implementation Decisions

Every feature observed in the legacy theme, with purpose, source, dependencies, data, user flow,
current implementation, problems, new implementation and priority.

Priority key: **P0** mandatory for v1 · **P1** important · **P2** optional · **P3** future.
Status key: `NOT_STARTED` (all, as implementation is forbidden before audit sign-off).

> Where the source is `functions.php` (ionCube) the row carries `[BLACK_BOX_FUNCTIONALITY]` and the
> behaviour was reconstructed from call sites only. None of these features will be copied — each is
> re-specified below as *new* work.

---

## A. Content domain

| ID | Feature | Purpose | Source | Deps | Data | User flow | Current implementation | Problems | New implementation (v1) | Pri |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| F-01 | **Tracks** (songs) | publish a song with audio/lyrics/downloads | `songs_post.php`, `single.php`, ACF | ACF, black box | `post` + `musics_type=mp3`, meta `music128`, `music320`, `artist`, `song`, `music_text` | admin; visitor: archive → single → play/download | post row rendered by shared card + single template | no CPT, no structured fields, no duration/size, meta strings not registered | CPT `wavira_track` + registered meta (`wavira_audio_128`, `…320`, `wavira_lyrics`, `wavira_duration`, …) + REST | P0 |
| F-02 | **Albums** | publish album with tracklist | `single.php` (`have_rows('album')`) | ACF repeater | post + ACF repeater `album[]` (sub: `albumlink128`, `albumlink320`, `song_names`) | single page playlist | ACF repeater inside a post | tracks are not entities (only source URLs), impossible to query/relate | CPT `wavira_album` + `wavira_album` CPT relation **or** track→album meta + editable tracklist | P0 |
| F-03 | **Artists (primary model)** | artist archive + biography | `taxonomy-singer.php` | taxonomy (registered in black box), ACF term fields | term `singer` + term meta `aimg2`, socials | visitor: /singer/{slug} | WP_Query by meta+tax, 3 unbounded loops | term-as-entity limits fields (no bio field in code, no verification, no related artists) | CPT `wavira_artist` + taxonomy `wavira_artist_tax`? → decision: **CPT artist** with term-mirror for legacy compat (see REBUILD-PLAN §Data) | P0 |
| F-04 | **Artists (legacy tag model)** | same purpose via post tags | `tag.php` | ACF term fields | tag term + same fields | /tag/{slug} | duplicate hero markup | duplicated code + duplicate data model; option `reltag` switches behaviour | consolidated into one artist model; tags remain editorial only; legacy tag pages 301 → artist pages | P0 |
| F-05 | **Music videos** | video publish + download | `single.php` | ACF | post + meta `video480/720/1080`, `musics_type=mp4` | watch + download | `<video>` with one chosen source; separate download list | single `<video>` cannot hold 3 qualities; no poster, no captions | CPT `wavira_video` + `wavira_video_source` map (mp4/hls/embed) + download matrix | P0 |
| F-06 | **Genres** | classify music | category.php (WP categories) | core | category taxonomy | browse | WP categories reused as genres | genre and blog categories mixed | taxonomy `wavira_genre` (dynamic, no hardcoding) + optional legacy category import | P0 |
| F-07 | **Lyrics** | show lyrics under player | `single.php` `get_field('music_text')` | ACF | meta `music_text` | read | plain field dump | no escaping (=HTML/JS injection vector), no structure, no i18n | `wavira_lyrics` registered meta, `wp_kses_post`-safe render, optional plain-text/structured split | P0 |
| F-08 | **Downloads (128/320)** | let visitor download audio | `single.php` | ACF | `music128/320`, `album128/320` | click link | direct `<a href>` to ACF URL, no rel, no counting | no size/format info, no permission gate, no analytics, no rel/noopener | Download service: quality matrix, file size, optional capability gate, optional signed URL, GA-free hit counter (own table, opt-in) | P0 |
| F-09 | **Video downloads (480/720/1080)** | video quality downloads | `single.php` | ACF | `video480/720/1080` | click link | plain links | same as F-08 + one source is also used as the player | unified Download service with `type=video` + `quality` | P1 |
| F-10 | **VIP / featured content** | promote selected songs | `inc/vip_slider.php` | ACF, Owl | meta `vip_song=1`, `vip_img` | homepage slider | `WP_Query` meta LIKE `vip_song` | meta flag not registered; `LIKE` on numeric flag | `wavira_featured` boolean meta + block/pattern + REST `featured=true` | P1 |
| F-11 | **Editorial posts / blog** | news, articles | `single.php`, `index.php` | core | posts | read | shared song card for every post type | music and editorial mixed in one loop | keep `post` for editorial, separate templates, separate loops | P1 |
| F-11b | **News section** (the editorial loop, delivered) | the blog index, category archives, a feed block | `index.html`, `archive.html`, `front-page.html`, `wavira/news`, `[wavira_news]` | core posts + categories | posts | read | news is posts, so feeds/sitemaps/SEO plugins keep working | — | `docs/ARTIST-AND-NEWS.md` §2 | P1 ✅ 0.9.0 |
| F-12b | **Artist profile page** (delivered) | works by type, biography, socials, gallery | legacy `single-artist.php` + ACF fields | core: `wavira_artist` relation meta, `wavira_social_*`, attached images | `wavira_artist` | read | one payload, three sections, bounded queries | — | `docs/ARTIST-AND-NEWS.md` §1 | P1 ✅ 0.9.0 |
| F-12 | **Music pages / static pages** | about, contact | `page.php` | core | posts (page) | read | page template + sidebar | sidebar hardcoded (`left_side1`) | use theme.json templates/parts; sidebars optional via patterns | P1 |

## B. Discovery & navigation

| ID | Feature | Purpose | Source | Current implementation | Problems | New implementation | Pri |
| --- | --- | --- | --- | --- | --- | --- | --- |
| F-20 | **Homepage composition** (delivered) | showcase everything | `home.php` + `inc/*` | hardcoded order of 6 fragments, each toggleable by OptionTree | not reorderable, cache-hostile, no builder support | `front-page.html`: five native Query Loops (albums, the catalogue player, tracks, videos, news) under translatable heading patterns, editable and reorderable in the Site Editor; the slider and genre rows stay available as patterns | P0 ✅ 0.14.0 |
| F-21 | Latest music | fresh content | `inc/new_posts.php`, `index.php` | main loop, title from option | option-based titles, no filter controls | "Latest Tracks" block with query controls | P0 |
| F-22 | Popular / most-viewed | social proof | `inc/mostviews_posts.php` | `meta_key=views`, `orderby=meta_value_num`, 1-month `date_query` | unindexed meta sort = filesort; `views` provided by external plugin | either integrate with a popular counter plugin **or** own counter table + transient cache; documented as *not* duplicating plugins | P1 |
| F-23 | Category boxes | homepage rows per genre | `home.php` (`hty` list) | OptionTree repeatable list → `WP_Query(cat)` per row | N queries on one page, no cache | "Track Grid by Genre" block, paginated/limited, object-cache aware | P1 |
| F-24 | Artist slider | promote artists | `inc/artist_slider.php` | OptionTree manual list `siing_t` | manual duplication of data that already exists as taxonomy | "Featured Artists" dynamic block with manual override | P1 |
| F-25 | VIP slider | promote hero items | F-10 | Owl carousel autoplay | autoplay with no pause control, no keyboard | accessible carousel (play/pause, keyboard, reduced-motion) | P1 |
| F-26 | Search | find music | `search.php`, `header.php` (data-swplive) | core search; live search delegated to SearchWP | no cross-type search UX, plugin-dependent | Search engine over tracks/artists/albums/videos/posts via REST + debounce ≥300 ms, no per-keystroke DB hits | P0 |
| F-27 | Pagination | browse | `inc/pagination.php` → `pagination()` (black box) | unknown markup `[BLACK_BOX_FUNCTIONALITY]` | unknown, likely `<div>` list, no `the_posts_pagination` semantics | `the_posts_pagination()` / `paginate_links()` with aria | P0 |
| F-28 | Breadcrumbs | orientation | `inc/breadcrumbs.php` | Yoast or Rank Math breadcrumbs only | no theme-level fallback | keep plugin-first; add neutral hook + optional built-in breadcrumb block | P1 |
| F-29 | Related content | retention | `single.php` | two branches: meta `artist` LIKE, else taxonomy `singer`; `pppf` count | `LIKE` on meta (no index), duplicated markup | Related service: same-artist → same-album → same-genre, cached, capped, filterable | P1 |
| F-30 | Menus | navigation | black box `register_nav_menus` + templates | 3 locations: top/ft/mobile, hover-only desktop submenus | no keyboard/focus support, `removeAttr('href')` breaks links, separate mobile header markup | one responsive nav (no UA sniffing), `aria-expanded`, keyboard + focus, `wp_nav_menu` standard API | P0 |
| F-31 | Widgets / sidebars | marketing slots | `myfunctions.php`, `inc/widgets.php` | 3 sidebars (`left_side`, `left_side1`, `left_side2`) + 3 custom widgets | global `$wp_query` overwrite, notices | block-based sidebars (patterns) + optional legacy-widget bridge in Core | P2 |

## C. Player & playback (product core)

| ID | Feature | Purpose | Source | Current implementation | Problems | New implementation | Pri |
| --- | --- | --- | --- | --- | --- | --- | --- |
| F-40 | Single-track player | play a song | `single.php`, `js/scripts.js` | custom HTML5 audio + class-bound JS + global `#audio` | one instance per page, no state, no a11y, no Media Session | **Player Engine**: independent component/web-component-style class with instance state (`currentTrack, queue, index, isPlaying, isLoading, duration, currentTime, volume, repeatMode, shuffleMode`), DOM-agnostic | P0 |
| F-41 | Album player / playlist | play a tracklist | `single.php` (repeater) | all sources preloaded into one `<audio>` | preloads full album, no lazy loading, breaks with multiple players | queue built from track entities, `preload="none"`, lazy source swap | P0 |
| F-42 | Index/global player | play without leaving page | `inc/tarlanweb_player.php` | second player with same global ID | duplicate IDs, bug `if($rkian = 1)` | persistent mini/sticky player driven by one global state; survives navigation (History API / Pjax-free approach: full re-mount from localStorage) | P1 |
| F-43 | Playlist row / now playing | track selection | `.play-list-row`, `data-track-row` | JS toggles classes; per-track title only | no durations, no keyboard, broken for multi-player | accessible listbox/queue UI, keyboard shortcuts, `aria-current` | P1 |
| F-44 | Volume / mute | control | `#volume-bar` range | jQuery change → `nowPlaying.volume` | global ID, no keyboard step semantics, no mute button | volume + mute with saved user preference (localStorage, privacy-documented) | P1 |
| F-45 | Progress / seek / buffer | control | `.progress-box` children | custom click-to-seek math from `offsetX/layerX` | fragile, no touch support guarantees, no aria-slider | `<input type="range">`-based seek with aria + pointer events + buffered track | P0 |
| F-46 | Media Session / keyboard | OS integration + shortcuts | — | **absent** | — | Media Session API metadata + hardware keys; documented shortcuts (space, ←/→, ↑/↓, M) | P1 |
| F-47 | Player error / empty states | resilience | — | browser `<source>` fallback text only | no UX for failed audio | explicit states: loading / buffering / error / unsupported / missing file | P0 |

## D. Social, engagement & sharing

| ID | Feature | Purpose | Source | Current implementation | Problems | New implementation | Pri |
| --- | --- | --- | --- | --- | --- | --- | --- |
| F-50 | Social shares | distribution | `single.php`, `footer.php` | hardcoded FB/X/WhatsApp/Telegram links (X shares the permalink as text only — broken) | no Web Share API, no per-network correct params, X link is malformed | Share service: Web Share API where available + correct per-network URLs + copy-link | P1 |
| F-51 | Artist social links | cross-promotion | term fields | 5 raw `<a href>` from ACF | unescaped, `rel` missing on some | artist meta socials with `esc_url`, `rel="noopener"`, icon system | P1 |
| F-52 | Comments | community | `comments.php`, `myfunctions.php`, black box callbacks | custom form + list callbacks duplicating core | duplicates core (breaks on core updates), no a11y labels, strings hardcoded | `comment_form()` + `wp_list_comments()` with theme-styled defaults, i18n'd | P1 |
| F-53 | Ratings | feedback | `single.php` | kk Star Ratings if active | third-party dependency, no fallback | integrate (don't duplicate) + optional owned rating meta if product requires | P3 |
| F-54 | View counter | popularity signal | `views` meta + `the_views()` | external plugin/black box | unindexed sort; plugin coupling | own lightweight counter (privacy-aware, cached) or documented plugin integration | P2 |
| F-55 | Shortlink / copy | sharing | `single.php` | `?p=ID` input with select-on-click | not keyboard friendly, no copy button | copy-to-clipboard button with accessible feedback | P2 |

## E. Admin / operations

| ID | Feature | Purpose | Source | Current implementation | Problems | New implementation | Pri |
| --- | --- | --- | --- | --- | --- | --- | --- |
| F-60 | Theme settings | configure the site | `inc/theme-options.php` (OptionTree) | `option_tree_settings` option, 6 sections, ~40 options incl. repeatable lists, ads JS fields, `indpl` defined twice | obsolete library, stored JS/HTML (XSS vector), duplicated field ids, no validation | Settings API + Settings/REST + `theme.json` for visual tokens; **no** OptionTree; one typed schema, sanitize callbacks, capability checks | P0 |
| F-61 | Music content editor UX | fast publishing | ACF meta boxes | classic editor + ACF | slow workflow, no validation, no duration/size helpers, no cover preview in list | dedicated meta panels (blocks sidebar or custom metaboxes) + admin columns (artist, type, duration, downloads) + validation | P0 |
| F-62 | Bulk management | operations at scale | — | **absent** | editors cannot fix 100 tracks | bulk assign artist/album/genre, bulk status, bulk meta (P2 subset of prompt §38) | P2 |
| F-63 | Import / export | migration & backup | — | **absent** | lock-in | CSV/JSON import-export for tracks/albums/artists with dry-run, duplicate detection, error report, rollback | P2 |
| F-64 | Demo import | onboarding | — | **absent** | no one-click demo | Demo importer (own, licence-clean demo content) | P1 |
| F-65 | Ads slots | monetisation | options + templates | raw HTML/JS echo into `ads_bt/adsjs_bt/ads_sg/adsjs_sg` | unescaped stored JS, no placement model | Ad Slots with registered placements + `wp_kses` policy for JS (capability-gated) | P2 |

## F. Platform / technical features

| ID | Feature | Purpose | Source | Current implementation | Problems | New implementation | Pri |
| --- | --- | --- | --- | --- | --- | --- | --- |
| F-70 | Dark / light mode | comfort | `header.php`, `js/scripts.js`, `.night` CSS | body class + jQuery cookie, JS applies after paint | FOUC, cookie-only, CSS duplicated per theme | CSS custom properties + `prefers-color-scheme` + user toggle persisted in localStorage, inline pre-paint script (no flash), `theme.json` aware | P0 |
| F-71 | RTL / LTR | both markets | hardcoded RTL | `dir="rtl"` fixed; CSS uses left/right | LTR broken; i18n impossible | logical properties (`margin-inline`, `padding-inline`, `inset-inline`, `border-inline`), `language_attributes()`, `is_rtl()` where needed, per-locale stylesheet | P0 |
| F-72 | Internationalization | ship multi-language | — | no text domain, no `.pot`, hardcoded Persian | not translatable | text domain `wavira`, `languages/`, `.pot` generation, RTL/LTR style variations, no hardcoded strings | P0 |
| F-73 | SEO layer | search visibility | `header.php` title logic, breadcrumbs | `wp_title()`, hidden H1 keyword block, plugin breadcrumbs | soft-404 redirect, keyword-stuffing pattern, duplicate SEO surface | `title-tag` support, semantic headings, Rank Math + Yoast compatibility, no competing meta/schema, structured data only where plugins don't provide | P0 ✅ 0.10.0 — music JSON-LD in Core, `meta description`/OG/Twitter only when no plugin is active; `docs/SEO-AND-PERF.md` |
| F-74 | Gutenberg / blocks | modern editing | — | **absent** (`theme.json` missing) | no block editing experience | `theme.json`, block templates/parts, patterns for hero/sliders, dynamic blocks (Track Player, Track Card, Artist Card, Album Card, Music Grid, Latest/Popular Tracks, Artist/Album/Genre grids, Music Video, Lyrics, Player) | P0 |
| F-75 | Elementor integration | builder market | — | **absent** | no page-builder support | Elementor widgets mirroring the blocks + dynamic tags + editor preview (only if Elementor is installed; graceful degradation) | P1 |
| F-76 | REST API | headless/future | — | **absent** | no API | `wavira/v1` namespace: artists, albums, tracks, videos, genres, player, search (versioned, permission-aware, cacheable, schema'd) | P1 |
| F-77 | Performance layer | speed = sales | `myfunctions.php` (srcset disabled) | no srcset, jQuery everywhere, no critical CSS | Core Web Vitals fail | conditional asset loading, no jQuery for front-end, defer/modules, image `srcset`+`sizes`+`loading`+`fetchpriority`, WebP/AVIF friendly, object-cache-aware queries, no `-1` queries | P0 ✅ 0.10.0 — `[PERF]` gate on the gzipped budgets, third-party URLs, query and srcset rules; LCP override removed; `docs/SEO-AND-PERF.md` §4 |
| F-78 | Accessibility layer | WCAG 2.2 AA | — | none | unshippable in regulated markets | keyboard-complete UI, focus-visible, ARIA for player/nav, contrast tokens, reduced-motion, form labels, live regions | P0 |
| F-79 | Automatic updates | product ops | — | **absent** | customers can't update | update client for theme + core plugin (independent versions, rollback-aware); **P2** | P2 |
| F-80 | Licence system | commercial | `RTL_License_*.php` (black box) | encrypted licence tied to rtl-theme.com | unusable and unauditable | **not in v1** — optional future licence API with graceful degradation (site must work offline) | P3 |
| F-81 | Telemetry | product insight | — | absent | — | **never hidden**; if added, opt-in + documented | P3 |
| F-82 | Favourites / playlists / history | user features | — | absent | — | P2 (localStorage-first, no accounts) | P2 |
| F-83 | Artist dashboard / front-end submission | growth | — | absent | — | P3 | P3 |
| F-84 | Migration tool (legacy → new) | upgrade path | — | absent | — | separate tool: map post types, taxonomies, meta, images, audio URLs, slugs; dry-run + rollback; never blind | P0 |

---

## Feature-count summary

| Priority | Count | Notable items |
| --- | ---: | --- |
| P0 | 22 | CPTs, player engine, settings, i18n/RTL, dark mode, SEO, a11y, performance, blocks, migration |
| P1 | 20 | featured/VIP, video downloads, popular, related, sticky player, Media Session, sharing, Elementor, REST, demo import |
| P2 | 8 | widgets bridge, bulk edit, import/export, ads slots, view counter, favourites/playlists, updates |
| P3 | 3 | ratings ownership, licence API, artist dashboard |

**Explicitly not built** (YAGNI, per prompt §54): streaming infrastructure, DRM, SaaS, AI recommendations,
mobile apps, subscription billing — architecture will leave seams, not implementations.
