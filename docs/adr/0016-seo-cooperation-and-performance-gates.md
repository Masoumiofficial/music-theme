# ADR 0016 — SEO cooperation, music schema, and a performance budget that fails the build

- **Status:** Accepted (0.10.0)
- **Date:** 2026-10-05
- **Supersedes:** nothing
- **Related:** ADR 0002 (theme/core split), ADR 0005 (player engine), ADR 0009 (accessibility and
  performance gates), ADR 0012 (relation storage), `docs/FEATURE-MAP.md` F-73/F-77,
  `docs/PERFORMANCE-AUDIT.md` §3, `docs/SEO-AND-PERF.md`

## 1. Context

The legacy theme pretended to do SEO: its `header.php` printed a hand-written title logic on top of
`wp_title()`, a hidden H1 stuffed with keywords, and a soft-404 redirect. `FEATURE-MAP` F-73 records
that disposition as "no competing meta/schema, structured data only where plugins don't provide", and
F-77 records that the legacy theme *disabled* `srcset` — a theme that costs its owners Core Web Vitals
and search visibility. Both are P0 for the rebuild.

At the same time, the target audience is Iranian music sites: nearly all of them run Rank Math or
Yoast in Persian. A product that duplicates those plugins' output (a second `meta description`, a
second canonical, a second `Article` graph) creates exactly the duplicate-signal problem the audit
criticised.

## 2. Decision — who owns what

| Surface | Owner | Why |
| --- | --- | --- |
| `title` tag, canonical URL, sitemaps, robots, breadcrumbs, `Article` schema, `meta description`, Open Graph, Twitter cards | **The site's SEO plugin, if present** | Mature, translated, and configured by the site owner; competing with it is what the audit called a duplicate SEO surface |
| The same surfaces **when no SEO plugin is active** | **Wavira theme (fallback)** | A premium theme that leaves a site with no description at all has failed its owner; the fallbacks are complete but deliberately plain |
| Music entities: `MusicGroup`, `MusicAlbum`, `MusicRecording`, `MusicVideoObject` | **Wavira Core builds the graph, the theme prints it** | No general-purpose SEO plugin knows about `wavira_album`; the relationship data already exists in the product's own model |

Detection uses each plugin's stable marker (version constant for Yoast/SEOPress/AIOSEO, main class for
Rank Math) rather than options or function-name probing, and both switches are filters:
`wavira_core_structured_data_enabled` (default **on** — music schema is the gap) and
`wavira_theme_seo_plugin_active` (overrides detection).

## 3. Decision — the graph's shape

- Published music posts only; a draft is never described, and posts/pages produce no node at all.
- Credits come from one place (`Content\Credit`): primary artist, then featured artists, deduplicated
  and published-only — so a schema node, a document title and a future credit line cannot disagree.
- `byArtist` is an **inline** `MusicGroup`, not an `@id` reference: a track page usually carries no
  artist node of its own, and a reference to an absent node tells a consumer nothing.
- The album tracklist is bounded at `StructuredData::ALBUM_TRACK_LIMIT` (50) — a machine-readable
  summary, not a payload larger than the page.
- The release date is preferred over the post date: re-publishing an old album must not make it look
  new. Values come through `MetaValues`/`Cover`, never from raw meta reads.
- Provider URLs are mapped only for YouTube and Aparat (`embed_url()`); anything else keeps its own
  URL and the theme's existing oEmbed path handles playback.
- The payload is the API (`wavira_core_structured_data()`); the `<script>` tag is presentation. A
  headless consumer or another theme can reuse the same nodes.
- The printed JSON is encoded with `JSON_HEX_TAG`: a post title containing `</script>` must not be able
  to close the tag it sits in. This is an escaping property, and a test asserts it.

## 4. Decision — performance is a gate, not a paragraph

The budgets from `PERFORMANCE-AUDIT` §3 were written in 0.1.0 and measured by hand; a promise nobody
checks drifts. `tools/check-perf.mjs` now enforces, on the **built** files:

| Budget | Limit | Measured at 0.10.0 |
| --- | --- | --- |
| Theme CSS | ≤ 25 KB gzipped | 7.2 KB (29 %) |
| Theme JS | ≤ 30 KB gzipped | 2.5 KB (8 %) |
| Player bundle (`core.js`) | ≤ 15 KB gzipped | 13.9 KB (93 %) |

plus: no third-party URL in any shipped CSS/JS/SVG, no `posts_per_page => -1`, no `nopaging => true`,
no disabled or recalculated `srcset`. The player bundle sits at 93 % of its budget — the next feature
that lands in it must either shrink something or get an ADR note (ADR 0009 §2), which is exactly the
conversation the gate exists to force.

LCP is the one budget a static gate cannot measure, so the code rule is written down instead:
`wavira_get_image()` never passes `loading`, `decoding` or `fetchpriority` on the core path. Core
(`wp_get_loading_optimization_attributes()`) promotes the first, likely-LCP image to
`fetchpriority="high"` and lazily loads the rest; a theme that hard-codes `loading="lazy"` on its hero
image silently cancels that. The gate fails if those attributes come back.

## 5. Consequences

- **Positive:** a site with Rank Math gets music schema it could not get otherwise and no duplicated
  tags; a site with no SEO plugin gets a complete, sane minimum; the size promises are checked on every
  push, so they cannot regress unnoticed.
- **Negative / accepted:** the fallbacks are intentionally basic (no XML sitemaps, no breadcrumb
  graph, no per-post robots control). Site owners who need those install an SEO plugin — the product
  does not try to become one.
- **Open:** Lighthouse/LCP numbers on a live install remain `WP-RUNTIME` (no browser in this
  environment); the budgets are enforced statically, and `docs/VERIFICATION.md` records the gap
  explicitly rather than implying a measured field result.
- **Follow-up:** `docs/SEO-AND-PERF.md` documents the operator's view (what to install, what to switch
  off); legacy `music128`/`music320` download URLs are unaffected by any of this.
