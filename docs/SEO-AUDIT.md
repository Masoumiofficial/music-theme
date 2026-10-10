# SEO-AUDIT.md — Legacy Theme SEO Review

**Method:** static review of `<head>` construction, headings, URL/redirect behaviour, structured data,
plugin interplay and content patterns. No live crawl was possible (no staging URL provided).

---

## 1. Summary

The legacy theme is a classic 2015-era RTL download-site theme: it relies on plugins for
meta/sitemaps, hand-rolls the document title, contains one deliberate *keyword-stuffing* pattern
(hidden H1), and converts 404/empty pages into homepage redirects (soft-404). The rebuild must invert
every one of these defaults.

| Area | Legacy | Verdict |
| --- | --- | --- |
| Document title | hand-built `wp_title('')` + `bloginfo('name')` | **Fail** (`wp_title` deprecated since WP 4.4) |
| Meta description / OG / Twitter cards | not emitted (delegated to plugins) | Acceptable **if** plugin present; no theme-level duplication — good instinct to keep |
| Canonical | core/plugin | Pass |
| Breadcrumbs | Yoast/Rank Math only, no fallback | Acceptable |
| Headings | multiple `<h1>`, hidden keyword H1 on home | **Fail** |
| Structured data | none; no MusicGroup/MusicAlbum/MusicRecording schema | **Gap** (opportunity, not spam) |
| Pagination | custom `pagination()` (black box) | Unknown semantics; no rel next/prev |
| 404 handling | JS redirect to home after 15 s | **Fail** (soft-404) |
| URL structure | `/singer/{slug}` taxonomy archives + `?p=ID` shortlinks | partly good; two competing artist URLs (tag + singer) = duplicate content |
| Language / dir | hardcoded `dir="rtl" lang="fa-IR"` | **Fail** for multilingual/LTR |
| Image SEO | `alt`/`title` duplicated from titles, decorative images often missing context, `srcset` disabled | **Fail** |
| Feeds / pingback | RSS link + pingback emitted | OK |
| Content pattern | music downloads with lyrics, artist pages, related sets → strong topical clusters | **Strength to preserve** |

---

## 2. Findings

### E1 — Deprecated title API (**High**)
`header.php`:
```php
<title><?php if(is_front_page() || is_home()){echo get_bloginfo('name');} else{echo wp_title('');}?></title>
```
`wp_title()` has been deprecated since WordPress 4.4; with modern caching it can produce untitled or
duplicated results, and it competes with SEO plugins.
**Rebuild:** `add_theme_support('title-tag')` and never print `<title>` manually.

### E2 — Hidden H1 keyword block + duplicated `alt` (**High**)
```php
<h1 class="h1_hidden_home"><?php echo ot_get_option('head_h1'); ?></h1>   // CSS: display:none;visibility:hidden
```
and the same option value is used as logo `alt`/`title`.
Search engines classify hidden keyword text as manipulation; a user-facing "دانلود آهنگ جدید" is not a
meaningful H1. Also: the visible page title on archives is often another `<h1>`, producing two H1s.
**Rebuild:** one meaningful `<h1>` per page (site/section name on home, term/entity title on archives),
logo alt = site name from WordPress, no hidden text.

### E3 — 404 and empty states become homepage redirects (**High**)
`404.php` and `inc/no-content.php` both render a 404-styled block **and** inject a JS redirect to the
homepage after 15 seconds. Search engines index the redirect as a soft-404 → crawl-budget waste,
no true "content gone" signal.
**Rebuild:** real 404 with helpful navigation and HTTP 404 status preserved; empty archives show an
empty-state pattern, never a redirect.

### E4 — Duplicate artist URLs / split signals (**High**)
Artist pages exist as `/singer/{slug}` **and** as tag archives `/tag/{slug}` (option `reltag` selects
which one the related block uses). Both render a hero and a listing of the same items → duplicate
content across two URL families for the same entity, and the internal-link graph depends on a toggle.
**Rebuild:** one canonical artist entity URL (`/artists/{slug}` or the CPT archive), tag pages reduced
to editorial tags, 301 map from legacy `/singer/` and artist-tag URLs.

### E5 — No structured data for music entities (**Opportunity**)
Nothing in the archive emits `MusicRecording`, `MusicAlbum`, `MusicGroup`, `VideoObject` or
`BreadcrumbList`. The data exists (title, artist, album, duration could be added).
**Rebuild rules:** emit contextual schema **only** where the SEO plugin does not already provide it;
prefer Rank Math/Yoast integration hooks over duplicating; never spam schema on archives.

### E6 — Pagination semantics unknown (**Medium**)
`inc/pagination.php` calls `pagination()` from the encrypted core `[BLACK_BOX_FUNCTIONALITY]`.
`artist-template.php` hand-builds `paginate_links()` (with an undefined `$paged` local) and outputs a
`<div class="clear">` **inside** a `<ul>` — invalid markup that search engines and screen readers both
misread. No `rel="next"/"prev"`, no `aria-label`.
**Rebuild:** `the_posts_pagination()`/`paginate_links()` with correct markup, `aria-label`, and no
duplicate-content traps on filtered/paged URLs.

### E7 — Hardcoded language/`dir` and no alternate-language strategy (**Medium**)
`<html dir="rtl" lang="fa-IR">` is static. For an international product this blocks LTR layouts,
`hreflang` strategies and WPML/Polylang behaviour (they expect `language_attributes()`).

### E8 — Title/alt duplication in cards and sliders (**Low-Medium**)
`inc/songs_post.php`, `inc/vip_slider.php`, `inc/artist_slider.php` set both `alt` and `title` to the
same text (title attributes are ignored or harmful for a11y/SEO) and, when a thumbnail is missing,
use the same generic fallback image (`nomusics.jpg`) everywhere.
**Rebuild:** descriptive `alt`, no redundant `title`, meaningful placeholder or CSS-based empty state.

### E9 — No canonical control for the duplicated single-track URL forms (**Low**)
Shortlink `/?p=ID` is offered in a copy field (fine), but nothing canonicalises filter/query variants
(e.g. related-block-driven views). Core/plugin canonical is assumed.

### E10 — Positive: strong topical content model (**Preserve**)
Track pages with lyrics + downloads, artist pages with discography, album pages with tracklists, and
related-content blocks form exactly the cluster structure music search rewards. The rebuild keeps
these clusters and adds proper internal linking (artist ↔ album ↔ track ↔ video), semantic headings
and clean URLs.

---

## 3. Compatibility requirements (Rank Math + Yoast)

| Requirement | Legacy | Rebuild plan |
| --- | --- | --- |
| No duplicate `<title>`/meta | violated (manual title) | `title-tag` only; never emit meta description/OG |
| Breadcrumbs hook | plugin-only (acceptable) | keep plugin-first + provide `wavira_breadcrumbs()` fallback behind a filter |
| Schema cooperation | none emitted | emit only when the SEO plugin has not; use plugin filters to enrich |
| Sitemap inclusion of music entities | n/a (posts only) | ensure CPTs/taxonomies are `public`, `show_in_rest`, and registered with SEO-plugin-compatible labels |
| Clean URLs | mixed | `/artists/`, `/albums/`, `/tracks/`, `/videos/`, `/genres/` + 301 map for `/singer/` |
| Pagination | broken markup | valid, labelled pagination |
| Social cards | absent in theme | rely on plugin/careful OG in Core only if no plugin |

## 4. Pre-launch SEO checklist for the rebuild

- [ ] One `<h1>` per view; heading order validated per template
- [ ] `title-tag` support; no manual `<title>`
- [ ] 404/empty states return proper status and never redirect
- [ ] 301 map: legacy `/singer/*`, artist tags, `?p=` shortlinks used publicly, old `category` genre URLs
- [ ] Structured data: track/album/artist/video only where the SEO plugin is absent
- [ ] `srcset`/`sizes` restored; `alt` quality rules; no duplicate `title` attributes
- [ ] `hreflang` ready (WPML/Polylang compatible), `language_attributes()` in use
- [ ] Pagination: valid markup, `aria-label`, duplicates avoided
- [ ] Verify with Rank Math **and** Yoast, then both disabled (theme must still be sane)
