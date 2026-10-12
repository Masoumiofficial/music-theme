# PERFORMANCE-AUDIT.md — Legacy Theme Performance Review

**Method:** static analysis (query construction, asset inventory, CSS/JS weight, caching interactions).
No staging runtime was provided, therefore no Lighthouse/Query Monitor numbers are claimed — findings
below are code-evidenced, and each is marked with the evidence.

---

## 1. Asset weight (measured from the archive)

| Group | Files | Bytes | Notes |
| --- | ---: | ---: | --- |
| `style.css` | 1 | 61,949 | 682 rule blocks, 70 `!important`, unminified, not critical-path split |
| JS total | 5 | 224,196 | `jquery.js` 89,390 · `owl.carousel.js` 90,033 · `scripts.js` 19,195 · `mediaqueries.js` 14,939 · `html5shiv.js` 10,339 |
| OptionTree (admin) | ~30 | ≈ 2.2 MB dir | admin-only, but loaded on theme-options screen |
| Icons | 1 css + 1 woff | 23,277 + 104,392 | full icon font for ~40 icons actually used |
| Fonts | 4 woff | 193,688 | Vazir 300/400/500/700 — all four requested on every page |
| Images | 7 | 252 KB | `error404.jpg` 131 KB alone; no WebP/AVIF of the same art |
| **Front-end payload (approx.)** | — | **≈ 0.65 MB** static assets before content images | plus per-page media |

## 2. Findings with evidence

### P1 — Responsive images intentionally disabled (**High**)
```php
function meks_disable_srcset( $sources ) { return false; }
add_filter( 'wp_calculate_image_srcset', 'meks_disable_srcset' );
```
`myfunctions.php`. Mobile users download desktop-sized images; `sizes` attributes are inert.
**Rebuild:** keep core `srcset`/`sizes`, add `loading="lazy"`, `decoding="async"` and
`fetchpriority="high"` only for the LCP image; enforce `width`/`height` to prevent CLS.

### P2 — Unbounded queries on artist pages (**High**)
`taxonomy-singer.php` runs **three** `WP_Query` loops with `'posts_per_page' => -1`
(albums, mp3, mp4) *plus* three count queries for the same three sets, each with a
`meta_query (musics_type)` **and** a `tax_query (singer)` → 6 heavy queries per artist page,
two of them unbounded. `inc/widgets.php` and `inc/artist_slider.php` add more `WP_Query` calls.
**Rebuild:** paginated queries, `no_found_rows` where counts are not needed, cached counts via
transients/object cache, indexed meta (or, better, taxonomy-based relations).

### P3 — Sort by unindexed `views` meta (**Medium-High**)
`inc/mostviews_posts.php` and `tarlanweb_ir_popular`:
```php
'meta_key'=>'views','orderby'=>'meta_value_num','date_query'=>[1 month]
```
This forces `wp_postmeta` filesort on every homepage render. **Rebuild:** own counter table (indexed)
or documented popular-posts plugin integration + object-cache layer.

### P4 — `LIKE` on a boolean-ish meta value (**Medium**)
`inc/vip_slider.php`: `meta_query` `vip_song` `compare=LIKE` `value=1`.
`inc/tarlanweb_player.php`: same pattern for `plym`. `LIKE` prevents index use and can match `11`.
**Rebuild:** boolean meta registered as `type=boolean`, compared with `=`.

### P5 — Related-posts meta `LIKE` on free-text artist (**Medium**)
`single.php` (when `reltag=on`) queries `meta_query => key 'artist', compare 'LIKE', value $artist`
— unbounded user-facing string plus `posts_per_page` from option `pppf` (12) but no cache.
**Rebuild:** relations via taxonomy/CPT IDs, cached related sets.

### P6 — jQuery + Owl Carousel loaded on every page (**Medium-High**)
`header.php` calls `wp_enqueue_script("jquery")` in `<head>` for the whole site; Owl Carousel is
enqueued for every page although sliders appear only on the homepage and artist pages (enqueue logic
lives in the black box `[BLACK_BOX_FUNCTIONALITY]`). **Rebuild:** no front-end jQuery dependency,
conditional loading, `defer`/modules, carousel only where a carousel exists (and prefers-reduced-motion
aware).

### P7 — Render-blocking CSS and no critical path strategy (**Medium**)
One 62 KB stylesheet, no split, no inlining of critical CSS, no `preload` of fonts (four woffs
requested without `preload` except whatever the browser discovers through CSS), no `font-display` on
the 500 weight (the other three weights declare `swap`).
**Rebuild:** tokens/utilities split, critical CSS inlined, non-critical deferred, `font-display: swap`
everywhere, subset only the weights used, consider woff2.

### P8 — Full-page output buffering (**Low-Medium**)
`myfunctions.php` starts an `ob_start()` callback on `template_redirect` for every request to
`str_replace` a handful of attributes. Extra buffering + regex-ish string work on every response;
breaks streaming/non-HTML responses. **Rebuild:** fix markup at the source; no buffer rewriting.

### P9 — UA sniffing changes markup and images (**Medium, caching**)
`wp_is_mobile()` gates the entire desktop header (`header.php`), the mobile header include
(`footer.php`), and the card thumbnail size (`inc/songs_post.php`). With full-page caching, one
variant is served to both device classes → wrong layout, wrong image size.
**Rebuild:** one responsive DOM, CSS-driven behaviour, `picture`/`srcset` instead of UA branches.

### P10 — Sliders preload media and autoplay (**Low-Medium**)
Owl Carousel `autoplay:true`, `loop:true` for VIP and artist sliders; the index player renders all
tracks as `<source>` children in a single `<audio>` (browser may fetch metadata for all).
**Rebuild:** lazy-init carousels on visibility, `preload="none"`, single active source.

### P11 — No object-cache / page-cache awareness (**Medium**)
None of the query results are cached; no transients; no `wp_cache_*` usage; no nonce misuse (good) but
also no cache-key discipline. Widgets overwrite `$wp_query`, which also damages cache-friendly
behaviour of host plugins. **Rebuild:** all expensive reads behind a small cache API
(transient + object cache + invalidation on save), and never touch globals.

### P12 — Admin-era bundles weigh more than the theme (**Informational**)
OptionTree (≈2.2 MB directory) plus `includes.zip` (81 KB, redundant copy of 12 files) ship in the
theme. On the front end they are inert, but they inflate the package, slow theme installs/updates and
create the confusion documented in the licence audit. **Rebuild:** no OptionTree, no nested archives.

### P13 — Dead defer filter (**Low**)
`myfunctions.php` defines `shapeSpace_script_loader_tag()` that only acts on handle
`my-plugin-javascript-handle`, which is never enqueued in readable code → dead code that gives a
false impression of async/defer strategy.

---

## 3. Performance budget for the rebuild (targets to verify in QA)

| Metric | Target | Rationale |
| --- | --- | --- |
| CSS shipped per page | ≤ 25 KB gzipped (critical) + deferred remainder | premium marketplace bar |
| JS shipped per page (non-player) | ≤ 30 KB gzipped, no jQuery | interaction budget |
| Player bundle | ≤ 15 KB gzipped, loaded only where a player exists | core feature, still lazy |
| LCP | ≤ 2.5 s on 4G, mid-tier Android | Core Web Vitals |
| CLS | ≤ 0.05 | no layout shift from images/carousels |
| INP | ≤ 200 ms | player interactions must stay instant |
| DB queries per music page | ≤ 20 with object cache warm | query discipline |
| `posts_per_page => -1` | **0 occurrences** | hard rule |
| `srcset` disabled | **0 occurrences** | hard rule |
| Third-party requests on public pages | 0 by default | privacy + speed |

## 4. Test protocol (to be executed after the rebuild)

1. Query Monitor: count and inspect every query on home / artist / album / track / search pages with
   cold and warm object cache.
2. Lighthouse (mobile + desktop) on the same five templates; capture LCP/CLS/INP.
3. Network panel: assert deferred/lazy loading and that no page loads a carousel/player it does not use.
4. Full-page cache test (WP Rocket / LiteSpeed / Cloudflare) with the player active — verify player
   state is client-side only and never poisons a cached page.
5. Image audit: confirm `srcset`/`sizes` present, `width`/`height` set, modern formats served.
