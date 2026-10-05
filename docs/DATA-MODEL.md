# DATA-MODEL.md — Wavira music model (authoritative from 0.4.0)

> **Verification status:** the model below is implemented, passes the static CI gates
> (WPCS + PHPCompatibilityWP + PHP 7.4/8.2/8.3 syntax) **and** is covered by the integration
> suite that runs against a real WordPress test library in CI (49 tests green on PHP 7.4 and
> 8.2). Per-claim evidence and what is still open: `docs/VERIFICATION.md`.


**Status:** IMPLEMENTED in code (`wavira-core/src/Content/*`) · **Supersedes:** the reconstructed legacy
model in `DATA-MODEL-AUDIT.md` (kept for migration purposes only).
**Contract:** ADR 0003 (real entities), ADR 0011 (slugs), ADR 0012 (relations as post IDs),
ADR 0013 (counters, delivery, caching).

---

## 1. Entities

| Entity | Post type | REST base | Archive | Public slug |
| --- | --- | --- | --- | --- |
| Artist | `wavira_artist` | `artists` | `/artists/` | `/artists/{slug}/` |
| Album | `wavira_album` | `albums` | `/albums/` | `/albums/{slug}/` |
| Track | `wavira_track` | `tracks` | `/tracks/` | `/tracks/{slug}/` |
| Music video | `wavira_video` | `videos` | `/videos/` | `/videos/{slug}/` |

All four are `public`, `show_in_rest`, `has_archive`, `with_front => false`, capability type `post`
with `map_meta_cap`, and support title, editor, thumbnail, excerpt, revisions, author.

## 2. Taxonomies

| Taxonomy | Slug | Optional? | Attached to |
| --- | --- | --- | --- |
| `wavira_genre` | `/genres/` | always on | track, album, video, artist |
| `wavira_mood` | `/moods/` | setting `enable_mood` (default on) | track, album |
| `wavira_language` | `/languages/` | setting `enable_language` (default on) | track, album, video |
| `wavira_label` | `/labels/` | setting `enable_label` (default on) | album |
| `wavira_year` | `/years/` | setting `enable_year` (default off) | track, album, video |

Genres are hierarchical (genre → subgenre); the rest are flat. Terms are editable content, never
hardcoded.

## 3. Registered meta

**46 keys** are registered with `register_post_meta()` (type + single + sanitize_callback +
auth_callback + REST schema). Source of truth: `src/Content/MetaSchema.php`; the count is asserted by
`wp wavira verify` and by `tests/test-meta-settings.php`.

### Relations (post IDs — never free text, ADR 0012)

| Key | Type | On | Meaning |
| --- | --- | --- | --- |
| `wavira_artist` | int | track, album, video | primary artist (ID of `wavira_artist`) |
| `wavira_featured_artists` | int[] | track, album | featured artists |
| `wavira_album` | int | track, video | parent album |
| `wavira_tracklist` | int[] | album | **ordered** list of track IDs |
| `wavira_related_artists` | int[] | artist | editorial "you may also like" |
| `wavira_credit_label` | text | track, album, video | display-only credit when it differs from the entity name |

### Track audio & metadata

| Key | Type | Notes |
| --- | --- | --- |
| `wavira_audio_128` | url | 128 kbps source (playback + download) |
| `wavira_audio_320` | url | 320 kbps source |
| `wavira_audio_external` | url | external streaming/mirror link |
| `wavira_download_enabled` | bool | per-track opt-out (unset = inherit site setting) |
| `wavira_file_size_128` / `_320` | int (bytes) | shown in the download panel |
| `wavira_duration` | int (seconds) | player + card display |
| `wavira_lyrics` | html (KSES allow-list) | text alternative for the audio |
| `wavira_isrc` | text | industry identifier |
| `wavira_explicit` | bool | explicit-content badge |
| `wavira_version_note` | text | e.g. "Remix", "Live" |

**Counters (plugin-only, added in 0.4.0)** — `wavira_download_count`, `wavira_download_count_128`,
`wavira_download_count_320`. Registered integers on tracks with **`show_in_rest => false`**: they are
accounting state, not public API. Increments are a single atomic
`UPDATE … SET meta_value = CAST(meta_value AS UNSIGNED) + %d` through `$wpdb->prepare()`
(`Downloads\Counter`), so concurrent downloads cannot lose a count. Writing them is the **plugin's**
job only; the theme never touches a counter (ADR 0013 §1).

### Editorial / listing

| Key | Type | On | Notes |
| --- | --- | --- | --- |
| `wavira_release_date` | date `Y-m-d` | track, album, video | sorting + display |
| `wavira_subtitle` | text | track, album, video | display subtitle (legacy `song` when it differed from the title): "feat. …", "Live", edition name |
| `wavira_featured` | bool | track, album, video | hero/featured surfaces (legacy `vip_song`) |
| `wavira_in_index_player` | bool | track | include in the global/index player (legacy `plym`) |
| `wavira_cover` | int (attachment) | all | cover override when the featured image is not the artwork |

### Album

| Key | Type | Values |
| --- | --- | --- |
| `wavira_album_type` | enum | `album`, `single`, `ep`, `compilation` |
| `wavira_catalog_number` | text | label catalogue number |
| `wavira_album_audio_128` / `_320` | url | album-level audio (legacy `album128` / `album320`), reserved for the full-album download; playback stays track-based |

### Video

| Key | Type | Notes |
| --- | --- | --- |
| `wavira_video_source` | enum | `self`, `youtube`, `vimeo`, `aparat`, `other` |
| `wavira_video_url` | url | embed/main source |
| `wavira_video_480` / `_720` / `_1080` | url | self-hosted qualities |
| `wavira_video_poster` | int (attachment) | poster frame |

### Artist

| Key | Type | Notes |
| --- | --- | --- |
| `wavira_artist_image` | int | square profile image |
| `wavira_artist_cover` | int | wide hero image |
| `wavira_verified` | bool | verification badge |
| `wavira_country` | text | ISO-ish country name or code |
| `wavira_website` | url | official site |
| `wavira_social_facebook` / `_instagram` / `_telegram` / `_x` / `_youtube` / `_aparat` | url | profile links |

## 3b. Derived payloads (no new storage)

Two surfaces read the model and build a payload; neither introduces a meta key, an option or a table.

| Payload | Source | Contract |
| --- | --- | --- |
| Artist profile — `wavira_core_artist_profile()` | `wavira_artist_image`/`_cover` + featured image (portrait), post content (biography), `wavira_social_*`, `wavira_artist` relation meta on albums/tracks/videos, images attached to the artist post | `docs/ARTIST-AND-NEWS.md` §1.2; bounded by `limit`/`gallery_limit` (1–24); drafts and other artists' works are excluded |
| News feed — `wavira_core_news_feed()` | published posts of a public post type, `post_excerpt`/content (excerpt fallback), featured image, categories, author | `docs/ARTIST-AND-NEWS.md` §2.2; newest first, 1–24 items, an unknown category yields nothing |
| SEO graph — `wavira_core_structured_data()` | the same fields read through `MetaValues`/`Cover`/`Credit`, plus the genre taxonomy and the release date | `docs/SEO-AND-PERF.md` §2; one node per published music post, album tracklist capped at `StructuredData::ALBUM_TRACK_LIMIT`, no node for posts/pages/drafts |

News is deliberately **not** a post type: the archive, the RSS feed, the sitemap and every SEO plugin
already understand posts (`docs/DECISIONS.md` 0.9.0).

## 4. Settings

One option (`wavira_settings`), one schema (`src/Settings/SettingsSchema.php`), one sanitizer:

| Group | Keys |
| --- | --- |
| Appearance | `dark_toggle`, `dark_default` |
| Content | `tracks_per_page` (6–60), `related_limit` (3–24), `enable_mood`, `enable_language`, `enable_label`, `enable_year` |
| Player | `player_sticky`, `player_autoplay`, `player_default_volume` (0–100) |
| Downloads | `downloads_enabled`, `downloads_require_login` |
| Footer | `copyright`, `socials[]` |
| Ads | `ads_html` (KSES-filtered) |
| Data | `remove_data_on_uninstall` (opt-in) |

Unknown keys are dropped on save; integers are clamped to their bounds; URLs pass `esc_url_raw`;
`ads_html` passes `wp_kses_post`; nothing accepts raw JavaScript.

## 5. REST API (`wavira/v1`)

| Route | Returns |
| --- | --- |
| `GET /wavira/v1/artists` · `/artists/{id}` | artist projections |
| `GET /wavira/v1/albums` · `/albums/{id}` | album projections incl. `tracklist` |
| `GET /wavira/v1/tracks` · `/tracks/{id}` | track projections incl. `player` + gated `downloads` |
| `GET /wavira/v1/videos` · `/videos/{id}` | video projections incl. quality sources |
| `GET /wavira/v1/genres` · `/genres/{id}` | genre terms with counts |
| `GET /wavira/v1/search?q=…` | rank-ordered cross-type search results (all registered collections) |
| `GET /wavira/v1/search/suggest?q=…` | lightweight type-ahead suggestions (titles only, capped) |
| `GET /wavira/v1/{artists\|albums\|tracks\|videos}/{id}/related` | scored related items (genre 3, artist 2, album 1, featured 1 — filterable) |
| `GET /wavira/v1/download/{id}?quality=320` | `302` to the file when authorized, `403` otherwise; `?format=json` returns the envelope instead |

Collection filters: `page`, `per_page` (≤ 50), `search`, `orderby` (`date`, `title`, `menu_order`,
`modified`, `rand`), `order`, `genre` (slug), `artist` (ID), `album` (ID), `featured`.
Pagination is reported through `X-WP-Total` / `X-WP-TotalPages`.

WordPress' own `/wp/v2/{rest_base}` routes remain available for full CRUD; the product namespace is the
lean read model used by the front end and headless consumers.

**Download honesty:** `downloads` is populated only when `Downloads\Access::can_download()` passes
(site setting → per-track opt-out → optional login requirement → `wavira_download_access` filter).
The download endpoint **authorizes and redirects** — it never proxies bytes, never hides the URL behind
a token, and never claims DRM (ADR 0013 §2). Anyone who can play the track can obtain the file; the
authorization chain controls the *offer*, not the possibility.

**Input limits (0.4.0):** `per_page` is clamped to 50, `page` to ≥ 1, search terms to 100 characters;
`rand` ordering is refused above 500 candidate posts. `search` and `related` answers are cached for
300 s / 3600 s respectively in generation-scoped keys (ADR 0013 §3).

### Player routes (0.5.0)

| Route | Returns |
| --- | --- |
| `GET wavira/v1/player/tracks/{id}` | Playback payload of one published track. `200` with empty `sources` when the track exists but has no audio; `404 wavira_not_found` for unknown IDs, drafts and non-track entities. |
| `GET wavira/v1/player/queue?context=<album\|artist\|genre\|tracks\|related>&id=&slug=&limit=&orderby=&order=` | `{ context, count, items: [payload, …] }` — bounded by `Queue::MAX_ITEMS` (100) and by the site's `tracks_per_page` default; source-less tracks are dropped. `400 wavira_missing_source` when a context needs a source it did not receive. |

Both routes are read-only, public (published content only) and carry
`Cache-Control: public, max-age=60`.

### Playback payload (`wavira/v1/player/*` and `payload.playback`)

| Key | Type | Notes |
| --- | --- | --- |
| `id`, `type`, `title`, `permalink` | int/string | identity and link to the canonical page |
| `duration`, `duration_label` | int/string | seconds and a localised `m:ss` label |
| `explicit`, `has_lyrics` | bool | presentation flags (lyrics text itself is not shipped in the player payload) |
| `artist`, `album` | object | `{ id, name\|title, permalink }`, empty when unset |
| `cover` | object | `{ id, url, alt, width, height, srcset, sizes }`, empty when the post has no artwork |
| `genres` | array | `{ id, slug, name, link }` |
| `sources` | object | quality (`320`, `128`) or `external` → URL; best quality first |
| `preferred` | int | quality the server recommends (`320`, `128`, or `0` for external-only) |
| `media_session` | object | `{ title, artist, album, artwork: [{ src, sizes, type }] }` for the OS controls |

The payload is produced by `Wavira\Core\Player\Payload` and filtered by
`wavira_track_playback_payload`; content items (`/{type}/{id}`) expose the same
structure under the additive `playback` key (the 0.3.0 `player` key is kept for
compatibility).

## 6. Read/write helpers

| Helper | Purpose |
| --- | --- |
| `MetaValues::int/bool/url/text/html()` | type-safe reads |
| `MetaValues::ids( $id, $key, $target_type )` | ID lists filtered by target post type |
| `MetaValues::tracklist( $album_id )` | ordered album tracklist |
| `MetaValues::socials( $artist_id )` | social map for templates/REST |
| `MetaValues::duration_label( $post_id )` | localised `m:ss` |
| `Settings::get/all/update()` | typed settings access |
| `Cache::{get,set,remember,flush,generation}` | versioned caching for services; `get()` returns `array{found,value}` so `null` stays cacheable |
| `Content\QueryFilters::{args,genre,relations,clamp_per_page}` | the single place collection queries are built (no ad-hoc `WP_Query` args in controllers) |
| `Downloads\Counter::{increment,total,for_quality,summary,reset,supports}` | atomic counters (above) |
| `Search\SearchService::{search,suggest,summarize,…}` | cross-type search + suggestions, cached |
| `Related\RelatedService::{supports,ids,posts,limit}` | scored related items, cached |
| `wavira_core_is_active()`, `wavira_core_get_setting()`, `wavira_core_related_posts()` (`wavira-core/public-api.php`) | the public function API: the only surface a theme may call (ARCHITECTURE §2, enforced by `tools/check-boundaries.mjs`) |

**Rule:** no code reads music meta through a literal string. Constants only (`MetaSchema::*`).

## 7. Verification

```bash
wp wavira verify          # post types, taxonomies, registered meta, content counts
wp wavira seed [--force]  # minimal licence-clean demo set (generated text only)
```

Phase 0.3.0 acceptance: `wp wavira verify` reports zero problems on a clean install and after seeding.
Phase 0.4.0 adds the automated suite below; its runtime status is tracked in `docs/VERIFICATION.md`
(the suite has run in CI but **has not yet been observed green** — no claim is made until it is).

```bash
composer install          # dev-only: phpunit + polyfills
composer test:install     # downloads the WordPress test library (needs MySQL/MariaDB)
composer test             # PHPUnit against the real WordPress test suite
```
Legacy data conversion is specified in `MIGRATION-BLUEPRINT.md` and implemented in phase 0.9.0.
