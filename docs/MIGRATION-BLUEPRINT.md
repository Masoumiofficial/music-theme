# MIGRATION-BLUEPRINT.md — Legacy → Wavira Migration Plan

**Principle:** migration is a **separate tool**, never part of the theme or core plugin's runtime.
It must be dry-runnable, resumable, idempotent and reversible. No blind data moves; ambiguous rows are
reported, not guessed.

---

## 1. Source → target entity map

| Legacy representation | Target | Strategy |
| --- | --- | --- |
| `post` with `musics_type=mp3` | `wavira_track` | convert post type, keep ID and slug; copy meta |
| `post` with `musics_type=mp4` | `wavira_video` | convert; single chosen source → quality map; the other two → download sources |
| `post` with `musics_type=album` | `wavira_album` | convert; expand the ACF `album` repeater into child `wavira_track` posts preserving order (`menu_order` on the track, plus the album's `wavira_tracklist` meta, ADR 0012) |
| `post` (no `musics_type`) | `post` (editorial) | untouched |
| `page` | `page` | untouched |
| `singer` term | `wavira_artist` (CPT) | create artist post per term: name = term name, slug = term slug, bio = term description, image/socials from term meta; then link all posts to their artist entity |
| `post_tag` used as artist (when `reltag=on`) | `wavira_artist` | merge with `singer` entity when name/slug matches; otherwise create and mark `needs_review` |
| `category` used as genre | `wavira_genre` term | convert all categories that contain music posts; editorial categories keep `category` |
| `views` meta | **`[DEFERRED]`** — no view counter in the model | the value is copied to `_migration_raw`; downloads are counted (`wavira_download_count*`), views are a separate decision (`docs/DECISIONS.md`) |

## 2. Meta field map

| Legacy meta | New meta | Transform | Risk |
| --- | --- | --- | --- |
<!-- Verified against the implemented schema in 0.7.0: every target key below is a constant in
     wavira-core/src/Content/MetaSchema.php (meta) or a field in SettingsSchema::all() (options).
     Rows marked [DEFERRED] are recorded, not implemented; tools/check-mapping.mjs fails the build
     when a row points at a key that does not exist. -->
| `artist` (free text) | `wavira_credit_label` + resolved `wavira_artist` | copy text; resolve to artist post by exact name / slug match; unresolved → `needs_review` list | Medium (misspellings, multiple artists in one string) |
| `song` | track **title** if title is generic, else `wavira_subtitle` | prefer existing post title; keep album track name in subtitle when needed | Low |
| `music128` | `wavira_audio_128` | keep URL; if local attachment → remap to attachment ID; record size/duration by probing metadata if available | Medium (external/hotlinked files) |
| `music320` | `wavira_audio_320` | as above | Medium |
| `music_text` | `wavira_lyrics` | store cleaned HTML (KSES allow-list), keep raw copy in a `_migration_raw` meta for audit | Low |
| `album128` / `album320` | `wavira_album_audio_128` / `wavira_album_audio_320` | move to the album post; the album audio is reachable through the REST API and reserved for the full-album download in the migration phase | Low |
| `album` (repeater) | child tracks + order | each row → track with title from `song_names`, sources from `albumlink128/320`, `wavira_album` = album ID, `menu_order` = index | **High** (URLs may be dead) |
| `video480/720/1080` | `wavira_video_480` / `wavira_video_720` / `wavira_video_1080` | map to the matching quality key; verify the playable URL format and record the provider in `wavira_video_source` | Medium |
| `musics_type` | — | dropped; recorded as `_migration_legacy_type` | Low |
| `vip_song` | `wavira_featured` | `1`/`LIKE 1` → boolean true | Low |
| `vip_img` | `wavira_cover` (attachment ID) | import a local image as an attachment and store its ID; a remote URL cannot become a registered attachment ID, so it is kept in `_migration_raw` and reported for manual import | Low |
| `plym` | `wavira_in_index_player` | boolean | Low |
| `views` | **`[DEFERRED]`** — the new model counts downloads (`wavira_download_count*`), not views | the value is copied to `_migration_raw`; a view counter needs its own decision (storage + privacy), tracked in `DECISIONS.md` | Low |
| `_thumbnail_id` | unchanged | — | — |
| term meta `aimg2` | `wavira_artist_image` | URL → attachment if local | Medium |
| term meta `afacebook/atelegram/ainstagram/atwitter/ayoutube` | `wavira_social_facebook` · `wavira_social_telegram` · `wavira_social_instagram` · `wavira_social_x` · `wavira_social_youtube` (plus `wavira_social_aparat` when the legacy site had it) | `esc_url_raw`, keep; `atwitter` becomes `_x` because the platform was renamed | Low |

## 3. Option (settings) map

| Legacy option | New option | Note |
| --- | --- | --- |
| `sun_moon`, `dark_modes` | `wavira_settings['dark_toggle']`, `['dark_default']` | boolean casts |
| `fixbvip`, `vip_num`, `indpl`, `v_num`, `index_hj`, `index_pv`, `index_nm`, `index_ct`, `index_pt`, `arti_off`, `arti_title`, `arti_url` | **block/template attributes** (no option) | homepage composition is template content in the new product, not a settings screen; per-section counts become block attributes (`perPage`) |
| `hty` (list) | block instances in the front page | import as "Track Grid" blocks with genre + count |
| `siing_t` (list) | Artist Slider block instance (`manual` list) | images imported as attachments/URLs |
| `pppf` | `wavira_settings['related_limit']` | clamp 3–24 |
| `share_off`, `tag_off`, `cm_off`, `upb`, `fixbtn` | **`[DEFERRED]`** — no matching option | the new product ships no share-button or fixed-button feature, and comment/tag visibility belongs to core's own settings; recorded so nothing is silently reinterpreted |
| `copyright` | footer block attribute | KSES-filtered |
| `telegram`, `teltxt`, `instagram`, `instxt`, `facebook`, `twitter`, `youtube`, `aparat` | `wavira_settings['socials']` | `esc_url_raw` |
| `ads_bt`, `ads_sg` | `wavira_settings['ads_html']` | one KSES-allow-listed field; placement is decided by where the block sits, not by a second option |
| `adsjs_bt`, `adsjs_sg` | **`[DEFERRED]`** — no script ad slot exists | a JS ad slot needs its own capability decision; until then the migration reports it and copies nothing |
| `favicon`, `logo` | Site Identity | do not migrate as theme options |
| `head_h1` | **dropped** | SEO anti-pattern (see SEO-AUDIT E2) |
| `navar_txt` | **dropped** | contained the original author's sales contact |

## 4. URL preservation & redirects

| Legacy URL family | New URL | Rule |
| --- | --- | --- |
| `/{postname}/` (music post) | `/{postname}/` | **preserve slug exactly** — highest SEO value |
| `/singer/{slug}/` | `/artists/{slug}/` (or preserved if permalink chosen) | 301 map + canonical switch |
| `/tag/{artist-slug}/` | `/artists/{slug}/` when the tag was used as an artist | 301; keep the tag only if it is a real tag |
| `/category/{genre}/` (music categories) | `/genres/{slug}/` | 301 map; retain pagination params |
| `/?p=ID` | unchanged target (`/postname/`) | 301 from old shortlink usage in the wild |
| Attachment/media URLs | unchanged (same uploads dir) | keep `wp-content/uploads` as-is; **never re-import media unless licences allow** |
| Feeds, sitemaps | regenerate | ensure SEO plugin rebuilds sitemap after CPT registration |

## 5. Media migration rules

1. **Do not copy remote media** unless the site owner owns it or the licence allows redistribution.
   The tool keeps external URLs and records them as `external`.
2. Local attachments: keep IDs, keep files in place, never rewrite the uploads tree.
3. Featured images: regenerate intermediate sizes after migration (WP-CLI `media regenerate`),
   because the new theme registers *different* image sizes (and will **not** use `thumb1..thumb4`).
4. If the legacy site used `thumb1 (640×360)` as the slider art, import a note recommending
   regeneration so `srcset` has usable candidates.
5. Audio/video files: record duration and size when the local file is readable (server-side probe);
   otherwise leave duration empty and let the player report "unknown".

## 6. Tool architecture (built in Phase 10)

```
Legacy Migration Tool  (admin page + WP-CLI command, inside Wavira Core, feature-flagged)
├── 1. Detection     scan posts/meta/terms/options; produce a site profile + risk list
├── 2. Dry run       write a full mapping report (JSON + HTML) — nothing is changed
├── 3. Mapping       apply mappings in batches (posts → CPTs, terms → CPTs, meta, options)
├── 4. Verification  count reconciliation, broken-URL report, missing-media report, duplicate report
├── 5. Rollback      revertible per batch (original type/meta stored in `_migration_backup`)
└── 6. Report        exportable log + "needs review" queue (unresolved artists, dead files, oddities)
```

Hard requirements
- Idempotent: running twice never duplicates content.
- Batched (default 200 posts/batch) with progress + resume, safe on shared hosting.
- Never deletes legacy data; `_migration_backup` meta keeps the original values (with an opt-in cleanup
  after N days).
- No content is published or unpublished by the tool.
- Produces a machine-readable `migration-report.json` for QA sign-off.

## 7. Acceptance tests for migration (Phase 11)

| # | Test | Pass condition |
| --- | --- | --- |
| M1 | Dry run on a 10k-post fixture | report generated, zero writes (verify DB row counts unchanged) |
| M2 | Full migrate | track/album/video/artist counts equal legacy counts (± documented exceptions) |
| M3 | Slugs | all music post slugs identical before/after |
| M4 | Playback | every migrated track plays 128 and 320 sources that existed before |
| M5 | Lyrics | 100 % of non-empty `music_text` present in `wavira_lyrics` (HTML-allow-listed) |
| M6 | Downloads | quality matrix reflects exactly the legacy URL set |
| M7 | Artist merge | `singer` + tag duplicates produce one artist entity, no orphan tracks |
| M8 | Broken links | report lists every dead external URL (acceptable) but **no** newly dead internal link |
| M9 | Rollback | batch rollback restores legacy types/meta exactly (checksum on a sample) |
| M10 | Idempotency | second run changes nothing (diff = empty) |

---

## 8. Implementation notes (0.11.0, in progress)

The tool ships **inside Wavira Core but outside its boot path**: `Migration\LegacySchema` (the audited
field map) and `Migration\Migrator` (the engine) are only ever constructed by the WP-CLI command, so a
normal request pays nothing for them and no migration code can run by accident.

```bash
wp wavira migrate --detect                 # read-only site profile: legacy kinds, deferred fields, unknown values
wp wavira migrate --dry-run                # the full plan; nothing is written, no report is stored
wp wavira migrate [--batch=200] [--kind=mp3] [--offset=0] [--report=/tmp/migration.json]
wp wavira migrate --rollback [--batch=200] # restore from `_migration_backup`, delete created tracks
wp wavira migrate --status                 # the stored report of the last run
```

| Guarantee | How it is structural, not promised |
| --- | --- |
| Never deletes legacy data | the post keeps its ID, slug, dates and status; the original type and every legacy value are copied into `_migration_backup` (**never overwritten**) and `_migration_raw` before the first write |
| Idempotent | a legacy post is `post` with a known `musics_type`, a migrated one is a Wavira post type, so the selection cannot see it twice; `_migration_version` is a second guard |
| Never publishes or unpublishes | the tool writes `post_type` and meta only — no `post_status` write anywhere |
| Bounded and resumable | every query is bounded (`--batch`, default 200) and ordered by ID; `--offset` continues; counts page instead of loading everything |
| Reports instead of guessing | an artist that matches no entity → `wavira_credit_label` + the `needs_review` list; a slider image that is not a local attachment → raw audit meta + review (licence: never download third-party media); an unknown `musics_type` → reported and left alone |
| Reversible | `--rollback` restores the type and meta from the backup, deletes exactly the child tracks this tool created (recorded in `_migration_created`), and reports anything a human changed afterwards instead of touching it |

**Acceptance coverage** (`tests/test-migration.php`, `tests/test-jalali.php` style — real WordPress, real
DB, legacy-shaped fixtures, never legacy code):

| # | Test | Where |
| --- | --- | --- |
| M1 | dry run writes nothing (post type, meta and stored report all unchanged) | `test_dry_run_writes_nothing` |
| M2 | counts reconcile per kind | `Migrator::detect()` + `test_report_is_stored_after_a_run` |
| M3 | slugs survive | `test_track_is_migrated_with_its_sources_lyrics_and_slug` |
| M4/M6 | 128/320 sources arrive exactly | same test + `test_album_repeater_expands_to_ordered_tracks_once` |
| M5 | lyrics arrive, KSES allow-listed | same test (`assertStringNotContainsString( '<script' )`) |
| M7 | artist merge, no duplicate entities | `test_artist_directory_is_built_and_duplicates_merge` |
| M9 | rollback restores the legacy state | `test_rollback_restores_the_legacy_state` |
| M10 | second run changes nothing | `test_migration_is_idempotent` |
| — | every map target is a real schema constant (no promise without a key) | `test_every_target_key_is_a_schema_constant` |

M8 (dead external links) needs a live crawl and stays `WP-RUNTIME`; a 10 000-post fixture (M1 at scale)
is a staging exercise, not a unit test, and is recorded as such in `docs/VERIFICATION.md`.

**Open in 0.11.0:** the admin page (§6 named one) is not built — the CLI is the shipped surface, because
it is scriptable, dry-runnable and reviewable, and an admin screen needs its own capability and UI
decision; the packaging script and the final docs pass are the rest of the phase.
