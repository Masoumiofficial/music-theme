# DATA-MODEL.md — Wavira music model (authoritative from 0.3.0)

**Status:** IMPLEMENTED in code (`wavira-core/src/Content/*`) · **Supersedes:** the reconstructed legacy
model in `DATA-MODEL-AUDIT.md` (kept for migration purposes only).
**Contract:** ADR 0003 (real entities), ADR 0011 (slugs), ADR 0012 (relations as post IDs).

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

Every key is registered with `register_post_meta()` (type + single + sanitize_callback +
auth_callback + REST schema). Source of truth: `src/Content/MetaSchema.php`.

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

### Editorial / listing

| Key | Type | On | Notes |
| --- | --- | --- | --- |
| `wavira_release_date` | date `Y-m-d` | track, album, video | sorting + display |
| `wavira_featured` | bool | track, album, video | hero/featured surfaces (legacy `vip_song`) |
| `wavira_in_index_player` | bool | track | include in the global/index player (legacy `plym`) |
| `wavira_cover` | int (attachment) | all | cover override when the featured image is not the artwork |

### Album

| Key | Type | Values |
| --- | --- | --- |
| `wavira_album_type` | enum | `album`, `single`, `ep`, `compilation` |
| `wavira_catalog_number` | text | label catalogue number |

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

Collection filters: `page`, `per_page` (≤ 50), `search`, `orderby` (`date`, `title`, `menu_order`,
`modified`, `rand`), `order`, `genre` (slug), `artist` (ID), `album` (ID), `featured`.
Pagination is reported through `X-WP-Total` / `X-WP-TotalPages`.

WordPress' own `/wp/v2/{rest_base}` routes remain available for full CRUD; the product namespace is the
lean read model used by the front end and headless consumers.

**Download honesty:** `downloads` is populated only when `Downloads\Access::can_download()` passes
(site setting → per-track opt-out → optional login requirement → `wavira_download_access` filter).
This is authorization, **not** DRM; the product never claims otherwise.

## 6. Read/write helpers

| Helper | Purpose |
| --- | --- |
| `MetaValues::int/bool/url/text/html()` | type-safe reads |
| `MetaValues::ids( $id, $key, $target_type )` | ID lists filtered by target post type |
| `MetaValues::tracklist( $album_id )` | ordered album tracklist |
| `MetaValues::socials( $artist_id )` | social map for templates/REST |
| `MetaValues::duration_label( $post_id )` | localised `m:ss` |
| `Settings::get/all/update()` | typed settings access |
| `Cache::remember/flush` | versioned caching for services (phase 0.4.0) |

**Rule:** no code reads music meta through a literal string. Constants only (`MetaSchema::*`).

## 7. Verification

```bash
wp wavira verify          # post types, taxonomies, registered meta, content counts
wp wavira seed [--force]  # minimal licence-clean demo set (generated text only)
```

Phase 0.3.0 acceptance: `wp wavira verify` reports zero problems on a clean install and after seeding.
Legacy data conversion is specified in `MIGRATION-BLUEPRINT.md` and implemented in phase 0.9.0.
