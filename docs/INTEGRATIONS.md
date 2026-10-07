# INTEGRATIONS.md — Interoperating with the publishing plugin

Wavira must not force a site to throw away the tool it already uses. The first integration this
product answers for is **“Sajad Music Publisher”**
(`github.com/Masoumiofficial/Music-Publisher`, v1.0.0, GPL-2.0-or-later) — the publishing plugin of
the same ecosystem as the legacy theme, used by Persian music sites to create songs, remixes,
nohas, albums, music videos and podcasts.

This document is the reader-side contract: **what that plugin writes, what Wavira does with it, and
what neither of them can do.** It was written from the plugin's own source
(`music-publisher.php`, `includes/class-smp-post-handler.php`, `includes/class-smp-settings.php`,
`includes/class-smp-admin-pages.php`), read read-only. No plugin file is copied, modified, bundled or
depended on at runtime: interop is a **migration-time mapping**, in the same tool that converts the
legacy theme (`docs/MIGRATION-BLUEPRINT.md`).

---

## 1. What the plugin actually writes

| Aspect | Finding | Evidence |
| --- | --- | --- |
| Content type | ordinary WordPress `post` — never a custom post type | `wp_insert_post()` in `SMP_Post_Handler` |
| Discriminator | meta `musics_type`, values `musicss`, `musicss_remix`, `musicss_nohe`, `musicss_video`, `musicss_album` | same |
| Audio kind | the three audio values share one field set; they differ by category and by `musics_type` only | the handler's `$music_type` switch |
| Video | `musics_type=musicss_video` + `video480` / `video720` / `video1080`; the video's MP3 is written into `music320` | the video branch reuses the audio keys |
| Album | `musics_type=musicss_album`, `album128` / `album320`, and a repeater meta **`album_dl`** whose rows are `{ title, al_url128, al_url320 }` | `album_dl` build loop |
| Track fields | `music128`, `music320`, `music_txt` (lyrics), `online_ply` (index player), `slider_song` (featured) | the audio branch |
| Cover | `fifu_image_url` (+ `fifu_image_alt`), written by the Featured Image From URL integration | the audio/video/album branch |
| Artist | meta `art_name` (display name) and `artist_en` (second language), plus the **`singer` taxonomy** | the handler |
| Role credits | taxonomies `songwriter`, `composer`, `regulator`, `mixmaster` | the handler |
| Other switches | `select_effect` (old-site hover class), `pplayer_in` (the plugin's own player switch) | the handler |
| Taxonomy definitions | **none** — the plugin registers no post type, no taxonomy and no settings page of its own; it writes into whatever the active theme registered | the plugin's bootstrap has no `register_post_type()` / `register_taxonomy()` call |

Two consequences follow, and they shape everything below:

1. **Interop is a reader problem, not an integration API.** There is no hook, no service, no shared
   class to talk to: the plugin's output is content in the database. Wavira therefore interops the
   way it interops with the legacy theme — by converting that content once, with a reviewable tool.
2. **The plugin assumes the theme provides `singer` and friends.** On a site where the legacy theme
   was replaced by Wavira, those taxonomies do not exist any more, so the migration is what gives the
   data a home. Nothing in Wavira has to mimic the plugin's UI to keep the content readable.

## 2. Source profile: `--source=music-publisher`

`Migration\LegacySchema` now carries **two source profiles** instead of one map, because both sources
write the same meta key with a different vocabulary. `--source` selects which one runs; the default is
`legacy`, so an existing migration is unchanged.

```bash
wp wavira migrate --detect --source=music-publisher     # read-only profile of a publishing plugin site
wp wavira migrate --dry-run --source=music-publisher    # the full plan
wp wavira migrate --source=music-publisher              # convert a batch
wp wavira migrate --source=music-publisher --kind=musicss_remix
wp wavira migrate --rollback                            # the recorded source decides the field map on the way back
```

The two vocabularies are disjoint by construction and a test asserts it
(`test_the_two_sources_do_not_share_a_kind_vocabulary`): a legacy `mp3` is never converted by the
publishing-plugin map, and a `musicss_remix` is never converted by the legacy map. A site that has
used both (a theme change the other way round) can be converted by running the tool twice with the
two sources; nothing collides.

## 3. Field map

| Publishing plugin | Wavira | Transform | Status |
| --- | --- | --- | --- |
| `musics_type=musicss` | `wavira_track` + kind `music` | convert post type | `[TESTED]` |
| `musics_type=musicss_remix` | `wavira_track` + kind `remix` | convert post type | `[TESTED]` |
| `musics_type=musicss_nohe` | `wavira_track` + kind `noha` | convert post type (`nohe` → the model's `noha` spelling) | `[TESTED]` |
| `musics_type=musicss_podcast` | `wavira_track` + kind `podcast` | convert post type | `[TESTED]` |
| `musics_type=musicss_video` | `wavira_video` | convert post type | `[TESTED]` |
| `musics_type=musicss_album` | `wavira_album` | convert post type | `[TESTED]` |
| `music128` | `wavira_audio_128` | URL, sanitised | `[TESTED]` |
| `music320` | `wavira_audio_320` | URL, sanitised | `[TESTED]` |
| `music_txt` | `wavira_lyrics` | KSES allow-list HTML; the original stays in `_migration_raw` | `[TESTED]` |
| `album128` / `album320` | `wavira_album_audio_128` / `wavira_album_audio_320` | URL | `[TESTED]` |
| `album_dl` rows | child `wavira_track` posts + `wavira_tracklist` order | `title` → post title, `al_url128/320` → `wavira_audio_128/320`, `menu_order` = row index | `[TESTED]` |
| `video480/720/1080` | `wavira_video_480/720/1080` | URL | `[TESTED]` |
| `art_name` | `wavira_credit_label` (+ resolved `wavira_artist`) | text; resolved by exact name/slug, unresolved → review queue | `[TESTED]` |
| `track_name` | `wavira_subtitle` when it differs from the post title | text | `[TESTED]` |
| `slider_song` | `wavira_featured` | boolean | `[TESTED]` |
| `online_ply` | `wavira_in_index_player` | boolean (`on`/`1`/`yes` → true) | `[TESTED]` |
| `fifu_image_url` | `wavira_cover` (attachment ID) | only when the URL is a **local** attachment; a remote URL stays in `_migration_raw` and is reported | `[TESTED]` |
| `singer` terms | `wavira_artist` posts | name/slug/bio/image/socials, then linked from every item | `[TESTED]` |
| `songwriter` / `composer` / `regulator` / `mixmaster` terms | `_migration_raw['contributors']` + a review line | recorded, never invented as artists | `[TESTED]` |

### Deliberately not mapped (`[DEFERRED]`, value preserved in `_migration_raw`)

| Key | Why |
| --- | --- |
| `artist_en`, `song_en` | the site's second-language SEO fields; v1 has no bilingual field, and inventing one from a migration would be a data model decision, not a mapping |
| `talbume128`, `talbume320` | folder URLs for a download host; the model stores the file URLs, not folders |
| `music320_video` | the MP3 of a music video; `wavira_video` stores video sources, and a second audio track has no home. **The value is kept**, so a site that needs it back can read `_migration_raw` |
| `select_effect` | a hover-effect class of the old site's templates, not content |
| `pplayer_in` | the plugin's own player switch; the player is the product's (ADR 0005) |
| `fifu_image_alt` | stored with the attachment; the cover resolves to the media-library entry |

Nothing in this table is dropped silently: `wp wavira migrate --detect` counts every deferred key and
prints how many posts carry it, before anything is written.

## 4. The kind taxonomy (`wavira_kind`)

The plugin's single most important piece of information is `musics_type`: it is what makes a remix a
remix and a noha a noha. Wavira models it as a **flat, always-on taxonomy** on tracks
(`Taxonomies::KIND`, slug `/kinds/`, REST base `kinds`), with the vocabulary
`music` · `remix` · `noha` · `podcast`.

| Decision | Reason |
| --- | --- |
| A taxonomy, not a meta field | a site lists remixes, filters by kind and shows the term in the block editor; terms are what WordPress does that with, and it gives `/kinds/noha/` a real archive |
| Always on | it is not a preference: without it an imported remix and an imported song are indistinguishable, which is exactly the information the migration is supposed to preserve |
| Attached to tracks only | a video and an album already have their own post type; the kind is what separates *audio kinds* from each other |
| Explicit alias map | the source values are `musicss*`; `music` is a substring of `musicss_remix`, so substring matching would file every remix as a single. The map also carries the `nohe` → `noha` transliteration pair |
| Unknown value → no term | a `musics_type` no alias claims stays unmapped and is reported by `--detect`; the tool never files content under a kind it cannot justify |

The demo ships a remix of one of its tracks on purpose (`Demo\Fixtures::persian()`), so the feature is
visible on a fresh install instead of being a claim in a document.

## 5. What this integration does *not* do

| Limit | Statement |
| --- | --- |
| No runtime coupling | Wavira never activates, requires or detects the plugin; a site may deactivate it after migrating |
| No data deletion | the original posts keep their ID, slug, status and every legacy value; `--rollback` restores them |
| No remote media download | a `fifu_image_url` outside the site's own uploads is **not** fetched; a third-party image may not be copied into a customer's media library by a migration script |
| No role-credit model | `songwriter` / `composer` / `regulator` / `mixmaster` are preserved as data but not exposed as fields: that is a v1 model decision (`docs/DECISIONS.md`), not an integration gap |
| No podcast *feed* | `musicss_podcast` becomes a track with the kind `podcast`; a real podcast needs an RSS feed with enclosures, which is a separate feature and is **not** claimed for v1 |
| No reverse export into the plugin | converting Wavira content back into `musicss_*` rows is not implemented; the export path is WXR (`wp wavira export-demo`), which is what the plugin's own importer would need anyway |

## 6. Verification

| # | Claim | Evidence |
| --- | --- | --- |
| I1 | a publishing-plugin track converts with sources, lyrics, credit, booleans and its kind | `tests/test-migration.php::test_publisher_track_arrives_with_its_kind_and_credits` |
| I2 | role credits are preserved as data and reported, never turned into artists | same test (`_migration_raw['contributors']`) |
| I3 | an `album_dl` album expands to ordered child tracks, once | `test_publisher_album_rows_expand_to_ordered_tracks_once` |
| I4 | `--source` converts only its own vocabulary | `test_a_source_only_converts_its_own_vocabulary` |
| I5 | every publishing-plugin kind maps to a kind term, and a video/album to none | `test_publisher_kinds_normalise_to_kind_terms` |
| I6 | the detection profile names the source and counts its deferred keys | `test_detection_reports_the_selected_source` |
| I7 | the kind taxonomy exists, is flat, track-only, and its vocabulary is the product's | `tests/test-content-registration.php::test_kind_taxonomy_is_registered_for_tracks` |
| I8 | the two source maps never collide | `test_the_two_sources_do_not_share_a_kind_vocabulary` |

The fixture is the plugin's **shape** (a `post` with `musics_type` and the keys above), never the
plugin's code: no plugin file ships with the product, and the tests would pass on a site where the
plugin was never installed.

**Status: `IMPLEMENTED` + `TESTED`** in the WordPress integration suite; the CI verdict for this
commit is recorded in `docs/RELEASE-CANDIDATE.md`. A real-site run against a live publishing-plugin
database remains a staging exercise (`docs/VERIFICATION.md`), as it is for every migration path in
this product: the tool's dry run is what a customer runs before trusting it.
