# Wavira Core — changelog

All notable changes to the plugin are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added — 0.9.0 (artist profile and news payloads)
- **`src/Content/ArtistProfile.php`** — one payload answers a whole artist page: portrait (featured
  image → `wavira_artist_image` → `wavira_artist_cover`), biography (post content, else the excerpt),
  the translated social channels (`wavira_social_*`, Aparat included), counts, the works grouped by
  type from the existing `wavira_artist` relation meta, and the images attached to the artist post.
  Bounded (1–24 per section via `ArtistProfile::MAX_ITEMS`), public-only, filterable
  (`wavira_core_artist_profile`).
- **`src/News/NewsFeed.php`** — the site's news as lean items (title, excerpt, permalink, date,
  thumbnail, categories, author). Posts and categories, newest first, 1–24 items; a non-public post
  type falls back to `post`; an unknown category yields nothing. Filterable (`wavira_core_news_items`).
- **Two public functions** — `wavira_core_artist_profile()` and `wavira_core_news_feed()`, both
  degrading to an empty array when the plugin is inactive or a service is removed.
- `docs/ARTIST-AND-NEWS.md` documents both payloads field by field.

### Changed — 0.9.0
- `src/News/` is a **service** layer in the boundary gate (it may read data, never the REST layer);
  `src/Content/ArtistProfile.php` stays in the data layer.
- Plugin version 0.9.0; the Persian catalogue grew to 124 strings, all translated.

### Added — 0.8.0 (Persian-first)
- **Persian ships with the plugin**: `languages/fa_IR.po` (116 strings, hand-written) and the compiled
  `languages/fa_IR.mo`. Settings labels and descriptions, post-type and taxonomy labels, REST argument
  descriptions, every player string (play, shuffle, repeat, queue, buffering, errors) and the CLI
  demo content are Persian. The text domain is loaded in `Plugin::boot()` before the modules register,
  so admin labels are built from translated strings.

### Changed — 0.8.0
- Version 0.8.0.

### Added — 0.7.0 (data for the block layer)
- **Three schema keys** (`src/Content/MetaSchema.php`, now **46 registered keys**):
  `wavira_subtitle` (display subtitle on track, album and video — the legacy `song` value when it was
  never the real title), and `wavira_album_audio_128` / `wavira_album_audio_320` (album-level audio,
  legacy `album128`/`album320`, reserved for the full-album download; playback stays track-based).
- **Track subtitle in the album tracklist**: `wavira_core_album_tracklist()` rows now carry
  `subtitle` beside `title`, `permalink`, `duration` and `duration_label`, so a theme renders an
  album without a second data source.
- Version 0.7.0.

### Added — 0.6.0 (theme UI, player component skin)
- **Player stylesheet** (`assets/css/player.css`, new): the component's own neutral skin and token
  contract (`--wavira-player-bg/surface/fg/muted/accent/accent-fg/border/danger/focus/radius/gap/
  shadow/bar-height/cover`), logical properties throughout, a sticky bar variant, and a fixed-bar
  space reservation. 2.7 KB gzipped (budget 6 KB).
- **Style registration** (`src/Player/Assets.php`): handle `wavira-player-style`, registered next to
  the engine and enqueued by `wavira_core_enqueue_player()`, so a page without a player loads neither
  file; version falls back to the product version when the build artefact is absent.
- **Two public theme helpers** (`public-api.php`): `wavira_core_album_tracklist()` (album rows with
  permalink, duration and label) and `wavira_core_video_source()` (hosted file, poster, or oEmbed
  URL), so the theme renders album and video content without naming a plugin class.
- **Player view contract**: the controls are now a `div.wavira-player__controls` group and every
  instance owns an `ol.wavira-player__queue` with `aria-controls` wiring; `data-queue-open` on the
  mount exposes the disclosure state to CSS. Each view gets a unique id from the instance sequence.
- Version 0.6.0.

### Fixed — 0.6.0
- **The queue list is appended to the player root.** It was built and populated but never mounted, so
  the queue panel could not appear; a source-lock test now asserts the append.
- **`--wp--custom--player--barHeight` never resolved.** WordPress kebab-cases `settings.custom` keys
  when it compiles them, so the camelCase reference fell back to the literal and the bar-height
  setting had no effect on the rendered page. Reference corrected to `--player--bar-height`; a new
  `tools/check-css.mjs` rule fails the build for any unresolvable `--wp--preset--*`, `--wp--custom--*`
  or `--wp--style--*` reference (ADR 0014).
- Product URIs in metadata no longer point at the unregistered `wavira.com` (see `docs/BRAND-DECISION.md`).

### Added — 0.5.0 (player engine)
- **Player engine** (`assets/js/index.js`): framework-free, one instance per mount point, its own
  `<audio>` element (no global element ID), a DOM-independent state machine
  (`currentTrack, queue, currentIndex, isPlaying, isLoading, isBuffering, duration, currentTime,
  volume, muted, repeatMode, shuffleMode, error`), queue/shuffle/repeat(off·all·one)/remove/clear,
  loading + buffering + error states, controlled autoplay, Media Session metadata and action
  handlers, the documented keyboard map, ARIA state with live regions, and `localStorage`
  preferences under `wavira.player.*` (never cookies). 13.6 KB gzipped (53 KB raw).
- **Playback data** (`src/Player/Payload.php`, `src/Player/Queue.php`): one payload per track
  (sources best-quality-first with a server-declared `preferred`, artwork incl. `srcset`/`sizes`,
  artist/album/genre relations, Media Session text) and five queue contexts
  (`album`, `artist`, `genre`, `tracks`, `related`) that drop source-less tracks and never exceed
  100 items. A queue is filled to the requested length even when the catalogue contains unplayable
  tracks, because the candidate window is wider than the queue.
- **REST** (`src/Rest/PlayerController.php`): `GET /wavira/v1/player/tracks/{id}` and
  `GET /wavira/v1/player/queue`, read-only, `Cache-Control: public, max-age=60`; `404 wavira_not_found`
  for unknown/draft/non-track items and `400 wavira_missing_source` for a context without its source.
- **Bundle registration** (`src/Player/Assets.php`): handle `wavira-player`, registered only when the
  built file exists, with `window.waviraPlayerSettings` (route templates, defaults from the site
  settings, translated strings) attached as an inline script; `wavira_player_settings` filter.
- **Shared content helpers** (`src/Content/Cover.php`, `Terms.php`, `OrderArgs.php`): artwork
  resolution (with `srcset`/`sizes`), term payloads and the sort vocabulary now have one
  implementation used by both the REST controllers and the player.
- **Public API**: `wavira_core_track_playback()` and `wavira_core_enqueue_player()`.
- **Content payloads**: tracks expose the same structure under the additive `playback` key; artwork
  payloads gained `width`, `height`, `srcset` and `sizes` (the 0.3.0 keys are unchanged).
- **Tests**: `tests/js/player.test.mjs` (15 DOM-free unit tests via `node:vm`, run by
  `npm run test:js` and by CI) and `tests/test-player.php` (payload, queues, routes, settings
  contract, public API). CI now builds the bundle before the integration suite so the enqueue path
  is exercised for real.

### Fixed — 0.5.0 (defects the first 0.5.0 CI run found)
- `MetaSchema::audio_key()` — the one place that maps a bitrate to a meta key — was called by
  `Player\Payload` but had never been committed: every player request died with a fatal error.
  The helper now exists, and the new `tools/check-class-refs.py` gate (run by `tools/lint.sh`)
  fails a build that calls a class member the repository does not declare.
- `Rest\ContentController` called `Content\Terms::genres()` without importing the class, so the
  track/album payload threw `Class "Wavira\Core\Rest\Terms" not found`. Imported.
- `Player\Queue` let WordPress re-sort the ID list of an album tracklist by date, losing the
  playing order the editor chose (ADR 0012). A bounded `post__in` list now sorts by `post__in`.
- The settings string table was missing `player`, the accessible name of the mount point, so the
  engine fell back to its own copy instead of the translated one.
- WPCS: translators comments for the three strings that use placeholders, the `remove` string was
  missing from the table entirely, and `Content\Cover` had a blank line before its closing brace.
- Tests: the seeded album owns its tracklist (the queue route needs it), the artwork fixture pins
  `_wp_attached_file` so `Cover::url()` has a file to resolve, and the settings contract compares
  route templates after `rawurldecode()` so it holds on plain permalinks too.
- WPCS: the two assignments of the queue's candidate window were one space short of the alignment
  `Generic.Formatting.MultipleStatementAlignment` requires, which was the only violation left in the
  WPCS job.

### Added — 0.4.0 (music engine: search, related, downloads, verification)
- **Search** (`src/Search/SearchService.php`): cross-type search and type-ahead suggestions over
  titles, excerpts and lyrics; TTL 300 s in generation-scoped cache keys; `per_page` clamped to 50,
  terms to 100 characters; `wavira_searchable_types` filter.
- **Related** (`src/Related/RelatedService.php`): scored related items (genre ×3, artist ×2, album ×1,
  featured ×1) with a `wavira_related_score` filter, TTL 3600 s cache, `MAX_ITEMS` 50.
- **Counters** (`src/Downloads/Counter.php`): atomic single-statement increments
  (`CAST(meta_value AS UNSIGNED) + %d` through `$wpdb->prepare()`), per-quality breakdown, `summary()`
  and an explicit `reset()`; `wavira_download_count*` is registered with `show_in_rest => false`
  (ADR 0013).
- **REST**: `GET /wavira/v1/search`, `/search/suggest`, `/{artists|albums|tracks|videos}/{id}/related`
  and `/download/{id}` (302 to the file when authorized, 403 otherwise, JSON envelope on request).
- **Query building** (`src/Content/QueryFilters.php`): one place for collection filters, relation
  filters and the `per_page` clamp — controllers no longer build `WP_Query` arguments inline.
- **ADR 0013**: counters are plugin-side and never public; delivery is authorization plus a redirect,
  never a byte proxy, token obfuscation or a DRM claim; caches are generation-scoped.
- **Verification harness**: PHPUnit 9 integration suite (`tests/`, 49 tests) running against a real
  WordPress test library, `bin/install-wp-tests.sh` (vendored, MIT), `phpunit.xml.dist`,
  `composer.json` (`composer test`) and a CI job on PHP 7.4 and 8.2 with MariaDB.

### Fixed — 0.4.0 (defects the new integration suite found on its first run)
- `Counter::bump()` counted the first download of a track **twice** (it created the meta row with the
  increment and then ran the same `UPDATE` again).
- `Access::can_download()` ignored an explicit per-track opt-out when the stored value was `false`
  (an empty string in the database) — the opt-out is now detected with `metadata_exists()`.
- `Settings::get()` allowed a caller-supplied fallback to override the documented schema default, so
  optional taxonomies (mood/language/label) never registered on a site whose settings had not been
  saved yet.
- `CacheInvalidator` did not flush on the **first** save of the settings option, because
  `update_option()` creates the row and fires `add_option_{$option}` instead of
  `update_option_{$option}`.

### Added — 0.3.0 (data model)
- **Content layer** (`src/Content/`): post types `wavira_artist`, `wavira_album`, `wavira_track`,
  `wavira_video` (slugs `/artists/ /albums/ /tracks/ /videos/`, REST bases, archives — ADR 0011);
  taxonomies `wavira_genre` (always) plus optional `wavira_mood`, `wavira_language`, `wavira_label`,
  `wavira_year`; 40 registered meta keys with type, sanitizer, auth callback and REST schema
  (ADR 0003, ADR 0012); typed readers in `MetaValues`; one-time rewrite flush per plugin version.
- **Settings** (`src/Settings/`): single option `wavira_settings`, one typed schema with bounds and
  sanitizers, REST exposure, `Settings::get/all/update()`, cache invalidation on update.
- **REST product API** (`src/Rest/`, namespace `wavira/v1`): collections and single items for artists,
  albums, tracks, videos and genres with pagination headers, filters (`genre`, `artist`, `album`,
  `featured`, `search`, ordering) and lean projections incl. `player`, `downloads`, `tracklist`,
  `video` payloads.
- **Downloads** (`src/Downloads/Access.php`): authorization chain (site setting → per-track opt-out →
  optional login) plus the quality matrix with a `wavira_download_quality_matrix` seam. Explicitly
  authorization only — no DRM claims.
- **Caching** (`src/Support/Cache.php`, `CacheInvalidator.php`): generation-based cache helper and one
  place that decides invalidation.
- **WP-CLI**: `wp wavira verify` (data-model verification) and `wp wavira seed [--force]`
  (licence-clean generated demo content).
- Documentation: `docs/DATA-MODEL.md` (authoritative model), ADR 0011 (slugs/redirects),
  ADR 0012 (relation storage), resolution of the open decisions list.

### Verified
- PHP syntax on 7.4 / 8.2 / 8.3, WordPress Coding Standards (Core/Docs/Extra) with zero
  warnings, and PHPCompatibilityWP against the 7.4 floor — all green in CI.
  Per-claim evidence: `docs/VERIFICATION.md`. Runtime behaviour on a live WordPress
  install is not verified yet.

### Added — 0.2.0 (architecture phase)
- Plugin bootstrap `wavira-core.php`: constants, requirements gate (`Requirements`), activation guard,
  deactivation cleanup, admin notice, `wavira_core_booted` action.
- `src/Support/Autoloader.php`: dependency-free, path-validated PSR-4 autoloader for `Wavira\Core\`.
- `src/Plugin.php`: singleton, i18n loading, module seam (no god class — modules register themselves).
- `src/Contracts/Registrable.php`, `src/Contracts/Cacheable.php`: module and caching contracts.
- Directory contracts for `Content/`, `Settings/`, `Player/`, `Rest/`, `Search/`, `Related/`,
  `Downloads/`, `Admin/`, `Import/`, `Demo/`, `Migration/`, `Integrations/`, `Support/`.
- `uninstall.php` with opt-in data removal (default: keep the catalogue).

### Design decisions recorded
- ADR 0002 (theme vs. core split), ADR 0003 (CPTs over post meta), ADR 0004 (no ACF/OptionTree),
  ADR 0005 (player engine), ADR 0007 (PHP/WP baseline).
