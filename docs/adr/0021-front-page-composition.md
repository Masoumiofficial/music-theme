# ADR 0021 — The front page is a composition of native query loops

* Status: Accepted
* Date: 2026-10-08
* Phase: 0.14.0
* Relates to: [0020](0020-theme-options-panel.md) (the settings screen), [0016](0016-seo-cooperation-and-performance-gates.md)
  (query discipline), [0002](0002-theme-vs-core-plugin-split.md) (theme vs plugin)

## Context

`docs/FEATURE-MAP.md` F-20 is the legacy theme's homepage: six fragments in a hardcoded order, each
toggleable, with a slider and genre rows — the thing a music-site owner buys a music theme for. The
rebuild had shipped every section it needs (`wavira/latest-tracks`, `wavira/album-grid`, `wavira/genre-chips`,
`wavira/featured-album`, the player block, the news loop) and no front page that composed them.

What shipped instead was `home.html`: the blog index with a news query, and `index.html` as a second copy
of it. On a default WordPress install the front page *is* the blog index until an owner sets otherwise, so
the site's root showed three news cards and the music lived under `/blog/`, `/albums/` and `/tracks/`.
Nothing failed: the templates were registered, the loops rendered, the gates passed — a static gate cannot
see that a page is empty of the thing it exists to show. A screenshot can.

## Decision

1. **`templates/front-page.html` is the front page**, in this order: latest albums, the catalogue player,
   latest tracks, latest videos, music news. Each section is a **native Query Loop** (`core/query` +
   `core/post-template`) over a post type the plugin registers, under its own heading pattern
   (`wavira/hidden-heading-*`), so a section is translated, editable in the Site Editor and removable
   without touching code.
2. **`templates/home.html` does not exist, and `templates/index.html` is the blog index.** `index.html`
   is what core, feeds, sitemaps and SEO plugins expect at `/` when the owner sets a static front page,
   and it is the fallback when they do not; `home.html` would have shadowed both the blog index and the
   front page, which is exactly the defect. Two names for one template was the problem; the fix is one
   name for each job. `tools/lint.sh` fails the build if `home.html` comes back — **and if `index.html` is
   missing**: the first version of this change deleted it, and the first real run of the render job refused
   to activate the theme («پوسته‌های مستقل باید یک پروندهٔ `templates/index.html` یا `index.php` داشته باشند»).
   A block theme without an index template is not installable, which is a thing a static gate can hold.
3. **Every section uses the plugin's own defaults.** No query in the front page sets a `tax_query`, a
   `meta_query` or an ordering by a field that needs data the demo might not have: each one renders from
   an empty install, and each has a `core/query-no-results` branch. A front page that only works on demo
   content is a front page that breaks on a real site.
4. **Sections are not options.** Which sections appear is a Site Editor decision (the template is
   editable) rather than a checkbox in the theme panel: `front-page.html` is the composition, and a
   customer who wants a different order or a different section edits the template like any block
   template. The panel (ADR 0020) keeps the things a *setting* can express — colours, widths, fonts,
   header, footer text — and does not grow switches that duplicate the editor.

## Consequences

* The legacy theme's homepage order survives as the default composition, without the slider or the genre
  rows: those remain available as patterns an owner can insert, which is the part of F-20 that is real
  (a section is a pattern, not a hardcoded fragment).
* The front page issues five bounded queries. `tools/check-perf.mjs`'s query-discipline rule covers the
  PHP-side ones; the block queries are core's own, paginated, and no `posts_per_page => -1` appears
  anywhere (the legacy gate greps for it).
* The blog index (`index.html`) is now reachable at its own URL rather than only at `/blog/`, and the news
  section on the front page is the same query the blog index runs — one feed, two doors, no duplication of
  content.
* The screenshot job (`wp-render`, ADR 0022) is the gate that would have caught this: it renders the front
  page and refuses an image with no text on it. F-20 is now marked delivered in `docs/FEATURE-MAP.md`.
