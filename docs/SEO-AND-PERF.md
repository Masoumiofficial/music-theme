# SEO and performance

Phase **0.10.0**. Decisions and their rationale: ADR 0016. Per-claim evidence:
`docs/VERIFICATION.md` § 0.10.0. This document is the operator's view — what the product does for
search, what it deliberately leaves to a plugin, and which numbers it promises.

## 1. The one-paragraph version

Wavira describes **music**. It emits schema.org JSON-LD for artists, albums, tracks and videos —
relationships, durations, release dates, credits, provider URLs — because no general-purpose SEO
plugin knows what a `wavira_album` is. Everything generic (`title`, canonical, sitemaps, robots,
`meta description`, Open Graph, Twitter cards, `Article` schema) belongs to the site's SEO plugin, and
when no such plugin is active the theme prints plain, complete fallbacks instead. Nothing is emitted
twice, and every switch is a filter.

## 2. What the product emits

| View | Node | Fields |
| --- | --- | --- |
| Artist | `MusicGroup` | `name`, `url`, `description`, `image`, `sameAs` (all social profiles + website), `genre` |
| Album | `MusicAlbum` | `name`, `url`, `datePublished`, `byArtist`, `numTracks`, `track[]` (≤ 50), `image`, `genre` |
| Track | `MusicRecording` | `name`, `url`, `duration` (ISO 8601), `datePublished`, `byArtist`, `inAlbum`, `isrcCode`, `description` |
| Video | `MusicVideoObject` | `name`, `url`, `uploadDate`, `thumbnailUrl`, `contentUrl` (hosted) or `embedUrl` (YouTube, Aparat mapped) |
| Blog post, page | — | Left to the SEO plugin on purpose |

Rules that hold everywhere:

- **Published only.** A draft is never described, and a credit to a draft artist never appears.
- **One credit source.** `Wavira\Core\Content\Credit` answers "who is this by?" for the schema, the
  document title and any future credit line, so they cannot disagree.
- **Bounded.** The album tracklist in the graph is capped at 50 entries.
- **Escaped.** The JSON is encoded with `JSON_HEX_TAG`, so a title containing `</script>` cannot close
  the tag it sits in (asserted by `Test_Seo`).
- **One source of truth.** Every value comes through `MetaValues`/`Cover`; no raw meta reads.

## 3. What a site owner can switch

| Filter | Default | Effect |
| --- | --- | --- |
| `wavira_core_structured_data_enabled` | `true` | Turns the whole music graph off (for a site whose SEO plugin builds its own) |
| `wavira_core_structured_data` | the nodes | Rewrites the graph before it is returned |
| `wavira_core_seo_plugin_active` | detected | Overrides plugin detection (Yoast, Rank Math, SEOPress, AIOSEO) |
| `wavira_theme_seo_plugin_active` | follows the core answer | Stops the theme's `meta description`/OG/Twitter fallbacks |
| `wavira_core_credit_names`' source (`Credit`) | stored meta | Credit order is primary-then-featured, deduplicated |

A WordPress site with Rank Math active therefore gets: Rank Math's titles, canonical, sitemap, robots
and Open Graph — plus Wavira's music graph, and nothing else. That is the intended configuration.

## 4. Performance budget

Measured by `tools/check-perf.mjs` (`[PERF]` in `tools/lint.sh`, run in CI on every push):

| Bundle | Budget (gzipped) | 0.10.0 |
| --- | --- | --- |
| Theme CSS (`theme.css`) | ≤ 25 KB | 7.2 KB |
| Theme JS (`theme.js`) | ≤ 30 KB | 2.5 KB |
| Player bundle (`core.js`) | ≤ 15 KB | 13.9 KB |

The player bundle is at 93 % of its budget: the next feature that lands in it must shrink something or
carry an ADR note (ADR 0009 §2). The gate also fails on any third-party URL in a shipped asset, on
`posts_per_page => -1`, on `nopaging => true`, and if `wavira_get_image()` starts hard-coding
`loading="lazy"` again (which cancels core's LCP promotion).

Runtime rules the gate cannot see, enforced in code and by the PHP suite:

- Images: the theme never sets `loading`, `decoding` or `fetchpriority` on the core path; core promotes
  the first, likely-LCP image and lazily loads the rest (`inc/performance.php`).
- Requests: the emoji script, its styles and the `s.w.org` DNS hint are removed, on the front end and
  in the admin. No remote fonts, no analytics, no CDN: zero third-party requests by default.
- Assets: the theme stylesheet and script are enqueued once; the script is deferred; the player bundle
  loads only where a player is rendered (`wavira_core_enqueue_player()`).
- Queries: `no_found_rows` where pagination is not needed, bounded limits everywhere (1–24 in feeds and
  profiles), per-request caches in the theme helpers, and the unbounded-query rule enforced by the gate.

## 5. Not measured here

- **Lighthouse / field Core Web Vitals** need a live install and a browser; the budget is enforced
  statically and the runtime rows stay `WP-RUNTIME` in `docs/VERIFICATION.md` until a real site run
  happens. No number is claimed that was not measured.
- **Hosting variance**: TTFB, image weight and third-party plugins on the owner's site are outside the
  product's control; the product's promise is its own bytes and its own requests.
- **XML sitemaps, breadcrumb graphs, per-post robots controls**: deliberately not built (install an SEO
  plugin). ADR 0016 §5.
