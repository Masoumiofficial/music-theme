# DATA-MODEL-AUDIT.md — What the Legacy Theme Actually Stores

**Status:** VERIFIED (from template/JS/admin code) · **No live database was supplied** — everything below
is reconstructed from read paths, so any server-only data (custom tables, ACF options, term meta
created in wp-admin) is `[INFERRED]` or `[UNKNOWN]`.

---

## 1. The core finding: there are no music entities

The legacy theme stores **everything as the WordPress `post` (or `page`) type** and distinguishes
music kinds with a post-meta field:

```php
// inc/songs_post.php (badge), taxonomy-singer.php, single.php (related fallbacks)
get_field_object('musics_type')  →  values seen in queries: 'mp3' | 'mp4' | 'album'
```

Evidence of quantification:

| Query pattern | Occurrences | Files |
| --- | ---: | --- |
| `meta_query` on key `musics_type` (values `mp3`, `mp4`, `album`) | 8 | `taxonomy-singer.php`, `single.php`, `inc/songs_post.php` |
| taxonomy `singer` usage | 17 | `single.php`, `tag.php`, `taxonomy-singer.php`, `artist-template.php` |
| ACF `artist` meta read/echoed | 12 | `single.php`, `inc/*` |
| ACF `song` meta | 11 | `single.php`, `inc/*` |

Consequences:
- No way to query "albums released in 2024 by X" without meta+tax joins.
- No track↔album relationship at all: an "album" post is a page with an ACF **repeater** of *files*,
  and its "tracks" are only `song_names` strings — they are not posts and cannot be clicked, played
  individually, counted or linked.
- No duration, file size, ISRC, explicit flag, release date, label or language fields exist.

---

## 2. Post meta inventory (as read by templates)

| Meta key | Type (observed) | Read by | Semantics | Migration target (new meta) |
| --- | --- | --- | --- | --- |
| `artist` | string (free text, ACF) | single, cards, related, widgets | display name of the artist on a track post | `wavira_artist_credit` (string) + resolved `wavira_artist` (post ID) |
| `song` | string (free text, ACF) | cards, player, related | track title | `wavira_subtitle` / track post title |
| `music128` | URL | player, downloads | 128 kbps audio file | `wavira_audio_128` (attachment ID/URL) |
| `music320` | URL | downloads | 320 kbps audio file | `wavira_audio_320` |
| `music_text` | HTML/text | single | lyrics | `wavira_lyrics` |
| `album128` | URL | downloads | album zip 128 | `wavira_album_128` (on album CPT) |
| `album320` | URL | downloads | album zip 320 | `wavira_album_320` |
| `album` (repeater) | array | single (album player) | rows of `{albumlink128, albumlink320, song_names}` | expanded to `wavira_track` children + `wavira_track_order` |
| `video480` / `video720` / `video1080` | URL | single (player + downloads) | video files | `wavira_video_sources` map |
| `musics_type` | enum `mp3`/`mp4`/`album` | all listings | content kind | replaced by post type: track/video/album |
| `vip_song` | flag (`1`, compared with `LIKE`) | homepage slider | featured on homepage | `wavira_featured` (bool) |
| `vip_img` | URL | homepage slider | custom slider image | `wavira_featured_image` (attachment) |
| `plym` | flag (`1`) | homepage player | include in index player | `wavira_in_index_player` (bool) |
| `views` | integer (external plugin) | popular box, widget | view counter | `wavira_views` (own table or plugin integration) |
| `_thumbnail_id` | core | cards | cover art | unchanged (featured image) |

**Term meta (on `singer` / tag terms, via ACF):**

| Key | Observed as | Migration target |
| --- | --- | --- |
| `aimg2` | artist image URL | `wavira_artist_image` |
| `afacebook`, `atelegram`, `ainstagram`, `atwitter`, `ayoutube` | social URLs | `wavira_social_*` |
| (taxonomy description) | artist bio | `wavira_artist_bio` (post_content on artist CPT) |

---

## 3. Taxonomies

| Taxonomy | Registered in | Used for | Notes |
| --- | --- | --- | --- |
| `singer` | black box (`functions.php`) `[BLACK_BOX_FUNCTIONALITY]` | primary artist model, on `post` | has archive `taxonomy-singer.php`; term fields via ACF |
| `musics_type` | black box `[BLACK_BOX_FUNCTIONALITY]` | also referenced through ACF `musics_type` field-object lookups | appears dual-purpose (taxonomy registration + ACF field); **risk**: two registries with one slug |
| `category` | WordPress core | genres **and** editorial categories mixed | no separation |
| `post_tag` | WordPress core | second artist model (tags used as artist pages when `reltag=on`) and true tags | data duplication; `single.php` builds tag links as "artist pages" |
| `post_format` (core) | unused | — | — |

**Genre is therefore not modelled at all** — it is whatever an editor put in a category.

---

## 4. Options (settings) inventory

Source of truth: `inc/theme-options.php` (OptionTree schema, read verbatim). Stored through
OptionTree's API into the `option_tree_settings` option + related option keys.

| Section | Option id | Type | Default | Purpose | New home |
| --- | --- | --- | --- | --- | --- |
| general | `favicon` | upload | images/favicon.png | favicon | Site Identity / theme.json (drop) |
| general | `logo` | upload | images/logo.png | header logo | Site Identity / custom logo |
| general | `sun_moon` | on-off | on | show dark-mode toggle | `wavira_settings['dark_toggle']` |
| general | `dark_modes` | on-off | off | dark by default | same key set |
| general | `head_h1` | text | «دانلود آهنگ جدید» | hidden H1 keyword | dropped (SEO anti-pattern) |
| general | `reltag` | on-off | off | related-by-tag vs related-by-taxonomy | replaced by Related service order |
| general | `navar_txt` | textblock | — | instructions + sales phone number in admin | removed |
| general | `reltag1` | on-off | off | related block placement switch | removed (always on, filterable) |
| homes | `fixbvip` | on-off | on | show VIP slider | `wavira_settings['featured_slider']` |
| homes | `vip_num` | text | 4 | slide count | slider block attribute |
| homes | `indpl` | on-off | on | show index player | block present in template |
| homes | `indpl` | — | — | **duplicate id, declared twice** | n/a |
| homes | `v_num` | text | 8 | tracks in index player | player block attribute |
| homes | `index_hj` | on-off | off | show latest box | template composition |
| homes | `index_ct` | text | «جدیدترین مطالب» | latest box title | block attribute |
| homes | `index_pv` | on-off | off | show popular box | template composition |
| homes | `index_pt` | text | «پربازدیدترین مطالب ماه» | popular box title | block attribute |
| homes | `index_nm` | text | 8 | popular box count | block attribute |
| homes | `hty` | list-item | — | per-row `{hty-cat, hty-t}` category boxes | block instances |
| homes | `arti_off` | on-off | on | show artist slider | block instance |
| homes | `arti_title` | text | «محبوبترین هنرمندان» | slider title | block attribute |
| homes | `arti_url` | text | — | "see more" URL | block attribute |
| homes | `siing_t` | list-item | — | manual artists `{siing_u, siing_img}` | dynamic block (manual override list) |
| socials | `fixbtn` | on-off | on | fixed TG/IG buttons | block/footer settings |
| socials | `telegram` `teltxt` `instagram` `instxt` `facebook` `twitter` `youtube` `aparat` | text | — | global social URLs + labels | `wavira_settings['socials']` (validated `esc_url_raw`) |
| footer | `upb` | on-off | on | back-to-top button | theme.json/template part |
| footer | `copyright` | text | «کلیه حقوق…» | copyright text | footer block attribute (KSES-filtered) |
| ads1 | `ads_bt`, `ads_sg` | textarea | — | ad HTML above content/story | Ad Slots (KSES-whitelist) |
| ads1 | `adsjs_bt`, `adsjs_sg` | javascript | — | **raw JS ad code** | Ad Slots with capability gate; **no raw JS for non-admins** |
| dl_pages | `pppf` | text | 12 | related count | Related service config |
| dl_pages | `share_off` | on-off | on | show share box | block/template |
| dl_pages | `tag_off` | on-off | on | show tags box | block/template |
| dl_pages | `cm_off` | on-off | on | comments visibility | per-post/global setting |

**Settings risks found:** raw `<script>` accepted as an option value and echoed unescaped
(`adsjs_*`, `ads_*`); `indpl` declared twice; no sanitize callbacks registered by the theme for its own
use (OptionTree performs some filtering); the admin schema file also embeds the author's sales phone
number and external links (brand pollution → §BRAND-DECISION vocabulary rules).

---

## 5. Reconstructed entity model (what the data *means*)

```
post (musics_type=mp3)      = TRACK      ~ meta: artist, song, music128, music320, music_text, views, plym, vip_song
post (musics_type=mp4)      = MUSIC VIDEO ~ meta: artist, song, video480/720/1080, views, vip_song
post (musics_type=album)    = ALBUM      ~ meta: artist, album128, album320, album[] repeater (fake tracklist), vip_img
post (default)              = BLOG POST  ~ editorial
page                        = PAGE
singer term + term meta     = ARTIST     ~ aimg2, socials, description
post_tag term + term meta   = ARTIST (2nd model, same fields)
category                    = GENRE-or-CATEGORY (mixed)
```

## 6. Target model (summary — full plan in `REBUILD-PLAN.md` / `MIGRATION-BLUEPRINT.md`)

```
wavira_artist (CPT)  ── name, bio, profile/cover image, verified, related artists,
                        socials (registered meta), country, links to albums/tracks/videos
wavira_album (CPT)   ── title, cover, artists (primary + featured), release date, type
                        (album/single/EP/compilation), label, genre terms, tracklist (ordered
                        track IDs), description
wavira_track (CPT)   ── title, primary artist, featured artists, album, genre terms,
                        release date, cover, audio_128, audio_320, external_url, lyrics,
                        duration, file_size, download_enabled, explicit, isrc, version (remix…)
wavira_video (CPT)   ── title, artist, album, source type (self-hosted/YouTube/Vimeo/Aparat),
                        sources per quality, poster, duration, description, release date
wavira_genre (tax)   ── dynamic; plus optional wavira_mood, wavira_language, wavira_label, wavira_year
wavira_tax_country?  ── P3
```

Registration rules: every meta via `register_post_meta()`/`register_term_meta()` with
`type`, `single`, `sanitize_callback`, `auth_callback`, `show_in_rest` + schema; taxonomy terms never
hardcoded; nothing stored in serialized ACF blobs.

---

## 6b. A second source outside the audited theme

The same ecosystem ships a **publishing plugin** (`Masoumiofficial/Music-Publisher`, v1.0.0) that
Persian music sites use when they do not use this theme. It is not part of the audit (it is GPL code
that is still maintained), but it writes the same `musics_type` discriminator with its own vocabulary,
so the migration tool converts it too — from the audited **shape**, with its own field map and its own
review rules. Findings that carry over unchanged: D2 (file URLs in a repeater instead of entities),
D4 (`art_name` free text next to the `singer` taxonomy), D7 (string booleans `online_ply` /
`slider_song`) and D8 (no duration or file-size metadata). One finding is new: the plugin stores role
credits (songwriter, composer, arranger, mix engineer) as **taxonomies**, and v1 has no field for
them, so they are preserved as migratable data and reported rather than modelled late in the release.
Full contract: `docs/INTEGRATIONS.md`.

---

## 7. Data-integrity findings

| # | Finding | Evidence | Severity |
| --- | --- | --- | --- |
| D1 | The same concept (artist) exists twice with duplicate metadata (`singer` term and `post_tag`) | `taxonomy-singer.php` vs `tag.php`; option `reltag` | High |
| D2 | Album tracklists are file URLs in a repeater, not entities | `have_rows('album')`, `song_names` | High |
| D3 | `musics_type` is both an ACF field object and (per black box) a taxonomy slug | `get_field_object('musics_type')`, queries by taxonomy key | Medium |
| D4 | Free-text `artist`/`song` duplicates the taxonomy/term relationship; two sources of truth for the same credit | `single.php` related branch on meta `artist` LIKE | High |
| D5 | Unbounded queries on artist pages (`posts_per_page => -1`) | `taxonomy-singer.php` ×3 | High |
| D6 | `views` stored as post meta and sorted numerically without index | `inc/mostviews_posts.php`, `inc/widgets.php` | Medium |
| D7 | Featured flags (`vip_song`, `plym`) are strings compared with `LIKE '1'` | `inc/vip_slider.php`, `inc/tarlanweb_player.php` | Medium |
| D8 | No duration/file-size metadata → download UI cannot inform users | `single.php` download block | Low (feature gap) |
| D9 | Term fields (`aimg2`, socials) are strings with raw URLs echoed unescaped | `tag.php`, `taxonomy-singer.php`, `artist-template.php` | High (security; see SECURITY-AUDIT) |
| D10 | No index exists for `musics_type`/`artist` meta queries; no object-cache awareness | `WP_Query` call sites | Medium |
