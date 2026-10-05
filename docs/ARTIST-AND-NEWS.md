# Artist profiles and the music-news section (0.9.0)

Two surfaces separate a Persian music site from a music-site *theme*: the **artist page** — where a
listener arrives from a search result and decides in five seconds whether this is the artist they
meant — and the **news section**, which is what keeps a music site alive between releases. This
document describes what 0.9.0 ships, where every piece of data lives, and what is deliberately not
there.

Everything here follows the product split (ADR 0002): **data and aggregation in Wavira Core, markup in
the theme**. The theme never queries the music model itself.

- Payload: `wavira_core_artist_profile()` → `Wavira\Core\Content\ArtistProfile::for_artist()`
- Markup: `wavira/inc/artists.php` (`wavira_get_artist()`, `wavira_get_artist_gallery_only()`)
- Blocks: `wavira/artist-profile`, `wavira/artist-gallery` (+ `wavira/news`, `wavira/genre-chips`)
- Shortcodes: `[wavira_artist]`, `[wavira_gallery]`, `[wavira_news]`
- Templates: `single-wavira_artist.html`, `home.html`, `archive.html`
- News feed: `wavira_core_news_feed()` → `Wavira\Core\News\NewsFeed::items()`
- Tests: `tests/test-artist-profile.php`, `tests/test-news.php`, `tests/test-blocks.php`

## 1. The artist page

### 1.1 What it shows, and where the data comes from

| Section | Data source | Notes |
| --- | --- | --- |
| Portrait | featured image → `wavira_artist_image` → `wavira_artist_cover` | the same precedence as `Cover::id()`, so the card and the page agree; no portrait renders a music placeholder instead of an empty box |
| Name | post title | printed as the page `<h1>` |
| Quote | excerpt, else a trimmed body | short line under the name; 24 words |
| Biography | post content | rendered through `the_content` (like a post body) and printed through `wp_kses_post()`; a site owner may write it with blocks or the classic editor |
| Social channels | `wavira_social_{instagram,telegram,youtube,aparat,facebook,x}` | labels are translated in Core, so a site that drops a platform drops it once; **Aparat is a first-class network**, not an afterthought |
| Counts | live counts of published works + attached photos | `Albums`, `Singles`, `Videos`, `Photos`; every number is `number_format_i18n()`, so Persian sites get Persian digits from the locale |
| Works | `wavira_album`, `wavira_track`, `wavira_video` posts whose `wavira_artist` meta is this artist | three groups, newest first, each with a count in its heading and a "view all" link to the archive when more exist |
| Gallery | images **attached** to the artist post | the WordPress-native gallery: upload from the artist screen and the file is attached to it, so no second gallery meta exists |

### 1.2 The payload contract

`wavira_core_artist_profile( $artist_id, $args )` returns:

```
id, slug, name, link, context            # identity + the quote
biography                                # rendered HTML (content, or a paragraph from the excerpt)
avatar    { id, url, alt }               # empty array when the artist has no image
socials[] { network, label, url }        # schema order, emptied of blank channels
sections{ albums|tracks|videos }{
    label, post_type, count, more, items[] { id, type, title, subtitle, permalink,
                                             date, date_label, cover{id,url}, genres[] }
}
gallery[] { id, url, alt, caption, width, height }
counts    { albums, tracks, videos, gallery }
```

Rules the payload keeps:

- **Bounded.** `limit` (default 6) and `gallery_limit` (default 8) are clamped to 1–24
  (`ArtistProfile::MAX_ITEMS`). The product's coding standard forbids unbounded queries; an artist
  page with 400 singles links to the archive instead of rendering 400 cards.
- **Public only.** Drafts, private posts and works credited to another artist never appear; an
  unpublished artist returns an empty payload.
- **Data, never markup.** Attachment IDs (not `<img>` strings) so the theme can let core emit
  `srcset`/`sizes`/`width`/`height`; term payloads from `Terms::genres()`.
- **Filterable.** `wavira_core_artist_profile` — one seam for a site that wants to add a section.
- **Read once per request.** `wavira_artist_data()` caches the payload per artist + options, so the
  header, the works and the gallery do not each repeat the queries.

### 1.3 Editor surfaces

| Surface | Attributes |
| --- | --- |
| `wavira/artist-profile` | `artistId` (0 = the artist being viewed / the loop's post), `sections[]`, `limit`, `galleryLimit`, `columns`, `showQuote`, `showBio`, `showSocials`, `showCounts`, `showWorks`, `showGallery` |
| `wavira/artist-gallery` | `artistId`, `limit`, `columns` (2–4) |
| `[wavira_artist]` | `id` (`current` on an artist page), `sections`, `limit`, `gallery`, `columns` |
| `[wavira_gallery]` | `id`, `limit`, `columns` |

A block with nothing to resolve (no `artistId`, not inside an artist) prints an editor-only hint —
never a random artist — and the front end stays silent (`wavira_block_placeholder()`).

### 1.4 Accessibility and RTL notes

- The portrait is a `<figure>`; the placeholder is decorative (`aria-hidden`) because the artist name
  is right beside it.
- Social links are text-labelled chips (`rel="me nofollow noopener external"`), not icon-only
  targets: a Persian label is easier to read than a brand glyph and avoids shipping third-party
  logos. The chip is at least 44 px tall on a coarse pointer (CODING-STANDARD C-rules).
- Counts are a `<dl>`: label and value stay associated for a screen reader.
- Section headings carry their count (`آلبومها (۱۲)`), so a reader knows what "view all" means.
- Every layout rule is logical (`margin-inline`, `gap`, `grid`), so the page mirrors correctly on an
  RTL site with no second stylesheet — enforced by `tools/check-css.mjs` rule 2.

## 2. The music-news section

### 2.1 News is posts — deliberately

News ships as **ordinary posts and categories**, not a `wavira_news` post type:

- the editor, the RSS feed, the sitemap, the archive hierarchy and every SEO plugin already understand
  posts, so the section works the day the theme is activated;
- a second content type would split the site's archives, feeds and sitemaps in two and force every
  SEO integration to learn it;
- a site that wants a magazine structure gets it from categories (`Concerts`, `Interviews`,
  `Releases`) and the menu — the WordPress-native way.

The theme provides the presentation: `home.html` (the blog index, paginated), `archive.html` (every
category/tag/author/date archive, with the term title and description) and the `wavira/news` block for
a feed inside any page or article. Both template paths use **core's Query Loop**, so pagination,
`?paged=`, feeds and the archive title stay core's business.

### 2.2 The feed payload

`wavira_core_news_feed( array $args )` returns newest-first items:

```
id, title, excerpt, permalink, date, date_label,
thumbnail { id, url }, categories[] { name, link }, author { id, name }
```

- `excerpt` falls back to a trimmed body (28 words) with markup stripped, so a card always has text.
- `limit` is clamped to 1–24; `offset` and `exclude` allow a hand-built second feed.
- `post_type` is resolved through `get_post_type_object()`: anything that is not public **and**
  publicly queryable falls back to `post`, so a caller cannot surface a private type by guessing its
  name.
- `category` restricts the feed to one slug; an unknown slug yields nothing, never everything.
  In the theme helpers a slug *is* a request for that category, so `category` decides the source
  on its own; `source: category` with no slug is an empty feed, never the whole blog.
- Filter: `wavira_core_news_items`.

### 2.3 Editor and template surfaces

| Surface | How |
| --- | --- |
| `wavira/news` block | `source` (`blog`/`category`), `category`, `perPage`, `columns` (1–4), `showCategories`, `showDate`, `showExcerpt`, `showImage` |
| `[wavira_news]` | `count`, `category`, `date`, `excerpt`, `image` |
| `home.html` | heading pattern + Query Loop (9 per page) + pagination |
| `archive.html` | archive title + term description + the same grid + pagination |

The block's cards and the templates' loop cards share one component (`.wavira-card`), so a restyle
cannot fix one and miss the other. Category chips above the feed come from
`wavira_get_news_categories()` (or core's own Categories block in a template).

## 3. What is *not* in 0.9.0 (and why)

| Not shipped | Reason |
| --- | --- |
| A Jalali (Shamsi) calendar | an unverified conversion would put a wrong date on every news card; WordPress's locale data is Gregorian. Decision recorded in ADR 0015 §8 and `docs/DECISIONS.md` |
| A bundled Persian font | Vazirmatn is already preferred in the token set with system fallbacks; shipping a subset font is a packaging/licence decision (ADR 0010, ADR 0009) |
| Artist *events* / concert calendars | an events feature needs dates, venues, tickets and structured data — its own phase, not a by-product of the profile |
| Artist registration / user profiles | the product is a publishing ecosystem, not a community platform (ADR 0001 non-goals) |
| Music charts | needs verified play counts across sites, which the product deliberately does not phone home for |
| An Elementor widget for the new blocks | deferred by decision: it cannot be verified in this environment, and the standing rule is never to claim compatibility without evidence (`docs/DECISIONS.md`) |

## 4. How a site owner uses it (the five-minute path)

1. **Plugins → Wavira Core** active, **Appearance → Themes → Wavira** active.
2. Add an artist: title, portrait as featured image, biography in the content, the networks in the
   artist panel (Instagram, Telegram, YouTube, **Aparat**, Facebook, X).
3. Upload the artist's stage photos while editing the artist — they attach to it and appear in the
   gallery.
4. Add albums, singles and music videos and pick the artist in each one's `wavira_artist` field; the
   profile groups them automatically and the counts follow.
5. Publish news as posts in categories; the blog page shows them with pagination, and
   `wavira/news` puts a feed wherever you want one.

Every string of both surfaces ships in Persian (`wavira/languages/fa_IR.{po,mo}`,
`wavira-core/languages/fa_IR.{po,mo}`; the `[FA]` gate in `tools/lint.sh` fails the build when a new
string arrives without a translation).
