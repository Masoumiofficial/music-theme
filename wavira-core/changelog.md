# Wavira Core — changelog

All notable changes to the plugin are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added — 0.15.0 (one download route, four kinds of file — and demo media that exists)

- **`Downloads\Sources`** — one resolver behind the download route: `qualities()`, `meta_key()`,
  `type()`, `available()`, `resolve()`, `quality_label()` and `url()` cover a **track** (128 and 320 kbps
  audio), an **album** (its master file), a **video** (the hosted file at 480/720/1080 by frame height — an
  embed is never downloadable, because there is no file to serve) and an **image** (a published
  attachment). A quality is the number a visitor recognises, and the label is the number plus its unit.
- **`Access::allows()`** — the one place that answers “may this be downloaded”: the file has to exist, the
  post has to be published, the per-post opt-out (`wavira_download_enabled`) has to be untouched, and the
  site's login rule still applies. `Rest\DownloadController` is type-aware now: the JSON response carries
  the type, the quality and the label, a missing file is a `404`, a forbidden one is `401`/`403`, and
  `wavira_download_served_post` fires on every successful delivery so a counter or a log can hook it.
- **Public API** — `wavira_core_download_url()`, `wavira_core_can_download()` and
  `wavira_core_download_qualities()` in `public-api.php`, so the theme (and any other theme) can build a
  download button without reaching into the plugin's internals. `MetaSchema::DOWNLOAD_ENABLED` now covers
  tracks, albums and videos.
- **`Demo\Placeholders` and generated demo media** — the demo used to point at files that did not exist,
  which is exactly the defect the theme's download rule forbids. The installer now *makes* the media: a
  **PNG encoder** (`png()`, IHDR/IDAT/IEND with real CRCs) and a **WAV writer** (`wav()`, 8 kHz mono
  16-bit PCM, the 320 kbps take deliberately louder than the 128 so the two qualities differ audibly) in
  pure PHP — no GD, no Imagick, no ffmpeg, because a host that cannot generate a cover must still get a
  working demo. Every generated file is stored as an attachment, marked `_wavira_demo_media`, attached to
  the post it illustrates (album covers, track covers, a video poster, an artist portrait, six gallery
  photos, the track audio in two qualities, the album masters) and counted in the import report
  (`report['media']`), which the CLI and the admin notice both print. **23 files on a fresh install**, and
  nothing is fetched from a third party.

### Fixed — 0.15.0

- **The demo menu's Home link is a path, not an absolute URL.** It was the one
  item the importer has to write into the database as a string — every other
  item is an archive or a term, resolved when the page renders — and stored as
  `home_url()` it is the first thing that breaks when the site moves to another
  domain, which is how a menu ends up pointing at the host the demo was imported
  on. `wp_make_link_relative()` keeps a site installed in a subdirectory right,
  which a plain `/` would not.
- **Importing the demo twice no longer doubles the menu.** The importer reuses
  the menu it finds and added the six items to whatever was already in it, so a
  second click on Import — what a site owner does when the first attempt was
  interrupted — left them a menu with «خانه» twice. A re-run deletes the menu's
  items before writing them, which is what "the demo's menu" has always meant.
- **The WP-CLI name trap, caught in the second place it hides.** `class_exists(
  'WP_Classic_To_Block_Menu_Converter' )` inside `namespace Wavira\Core\Demo`
  asks PHP for `Wavira\Core\Demo\WP_Classic_To_Block_Menu_Converter` — and
  `class_exists()` answers *true* for it, because that is the name as written and
  the autoloader is free to fail. The guard passes; the next line fatals. It is
  the same defect as the unqualified `WP_Post` of the previous commit in a
  different coat, and the gate that caught that one only looked after
  `instanceof`, `new` and `catch` — so it now looks anywhere a class is named,
  including a name given as a string, and reports one finding per use in source
  order. A fully qualified `\WP_CLI::log()` is not a finding, and neither is a
  class name written in a docblock.
- **The importer registers the content types it writes into.** They are
  registered on `init`, and an import does not always get a request of its own: a
  setup wizard, or a command that activates the plugin and seeds the site in one
  breath, runs after that hook has fired. WordPress will still insert a post of an
  unregistered type, so the content appeared — but a menu item for an archive
  whose post type is unknown is *invalid*, and `wp_get_nav_menu_items()` drops it
  without a word. The demo's menu came out as «خانه» and nothing else, on a site
  that had four archives full of music behind it.
- **A re-import now updates the menu visitors see, not just the one in
  wp-admin.** The theme's Navigation blocks name no menu, so core renders the
  most recently published `wp_navigation` post — and on a site with none it
  converts the classic menu into one, once, the first time a page renders.
  Every import after that changed the classic menu and left the front page
  showing the first one. The importer publishes the converted menu itself now
  (core's own `WP_Classic_To_Block_Menu_Converter`, so it is the markup the
  editor would write), and updates it in place on a re-run.
- **`wavira_core_download_qualities()` is an authorization decision too.** It
  returned every file a post has, and the theme prints a quality list and a
  download link from exactly that answer — so on a site that turned downloads
  off, or a track with the per-post opt-out, a theme could offer a link the
  endpoint would refuse. It asks `Access::allows()` first now and returns an
  empty list when the visitor may not download, which is the same answer the
  route gives, decided once (`tests/test-theme-downloads.php` covers both the
  opt-out and, on a real install, the render job).
- **A forced import deletes the media it generated.** `--force` removed the demo
  posts and left twenty-three attachments behind; a repeated import filled the
  uploads folder with orphans. The importer deletes the attachments carrying
  `Placeholders::MARKER` first — bounded, marker-scoped, and unable to touch a
  file the owner uploaded (ADR 0024).
- **The demo generates its media whatever the scope of the import.** Choosing the
  narrowly-scoped import (no site-language changes) also meant no cover, no
  gallery photo and no audio — so the artist page had an empty gallery and the
  album page offered no download, which is precisely what 0.15.0 exists to fix.
  `site` now governs the site settings only, and `tests/test-demo.php` asserts the
  media exists, is local, is marker-tagged and is counted.

- **The plugin's own strings are Persian on the front end.** The catalogue was
  loaded on `plugins_loaded` only, and WordPress 6.7 warns about a translation
  load that early and skips it — so an artist page printed “Albums (۲)” and
  “۳۲۰ kbps” under Persian headings, with every theme string translated and
  every check green. The domain is loaded again on `init` (priority 0) and, if
  WordPress still refused it, from the plugin's own `languages/<locale>.mo`
  directly. `tools/check-fa-labels.mjs` reads the expected Persian from the
  shipped catalogue and fails the render job when a served page prints an
  English section heading.

- **The import's “is this a post?” check asked the wrong class.** `WP_Post` and
  `WP_Term` were named unqualified inside `namespace Wavira\Core\Demo`, and PHP
  resolves an unqualified class name against the current namespace *without*
  falling back to the global one: `$post instanceof WP_Post` asked for
  `Wavira\Core\Demo\WP_Post`, got `false`, and skipped every item — silently,
  with a green syntax check and a return value of zero. The two classes are
  imported now, `tools/check-php-structure.mjs` reports the pattern, and
  `tests/test-demo.php` asserts the result instead of the intention.

- **WordPress's own sample content is Persian after the import.** A fresh install
  is not Persian because the theme and the plugin are: core creates “Hello
  world!”, a sample page and an “Uncategorized” category, and the front page's
  news section shows them — «اخبار موسیقی» above an English post, which is the
  first thing a visitor notices and the last thing a check looked at. The import
  (site scope) replaces both texts and renames the category from the catalogue,
  by the canonical slugs core itself writes, so anything an owner renamed is left
  alone. The render job's Persian gate found it.

- **The player's engine is registered before the template renders.** The bundle's
  handle was registered on `wp_enqueue_scripts`, and a block theme renders its
  template *before* `<head>` — core's `template-canvas.php` does it on purpose,
  “so that blocks can add scripts and styles in `wp_head()`”. The theme's mount
  point therefore asked for the bundle before the handle existed,
  `wavira_core_enqueue_player()` answered `false` on every page, and the player
  shipped as an empty `<div>`: mounts on every page, no engine, nothing to play.
  Registration happens on `init` now, the render job's new player verdict is what
  found it, and both the integration test (which used to fire
  `wp_enqueue_scripts` by hand) and the render preflight ask the question the
  browser asks.

- **Twenty-five Persian plurals lost their نیم‌فاصله.** The plugin's own
  strings: «نسخهها»، «سالهای»، «شبکههای»، «صفهای» and the post type label every
  archive page prints, «قطعهها». Persian separates the suffix «ها» from a stem
  that joins forward, and a catalogue can be complete — 163 of 163 translated,
  the POT and the MO in sync — and still print a page that reads wrong. Fixed in
  the catalogue, and `node tools/i18n.mjs check` refuses the next one: it names
  the string, the word and the word it should have been.
### Changed — 0.15.0

- The demo-import notice now counts what it made: «%1$d release, %2$d track and %3$d generated file».
- `Sources::quality_label()` reads `%d pixels` for a video height instead of `%dp`, so the label is a
  translatable phrase rather than a fragment with an English letter glued to it.

### Changed — 0.14.0 (shipped with the theme)
- **A `doing-it-wrong` notice on every request, fixed.** The settings option was registered with
  `type => 'array'` while its value is a map of setting name to value; core has required an item schema for
  an `array` type since 5.4 and logged «you must specify the schema for each array item» on every request
  that registered the setting. It is registered as an `object` now, which is what it is. Found by the
  `wp-render` job (the theme's render job reads `debug.log` with `WP_DEBUG` on), covered by
  `tests/test-settings-registration.php`, and the REST schema names its `html` and `url` fields as strings
  explicitly instead of by falling through to the default.
- **No theme-facing behaviour change**: the front page this release adds is the theme's, and it renders the
  content the plugin already exposes — the four post types, the genre taxonomy and the player. The plugin is
  versioned and shipped in lockstep with the theme so `Dashboard → Updates` shows one number for the pair.

### Changed — 0.13.0 (shipped with the theme)
- **No behaviour change**: 0.13.0 is a theme release (the settings screen, ADR 0020's amendment). The
  plugin is versioned and shipped in lockstep so the theme and the plugin a customer has installed never
  disagree about which release they are, and so `Dashboard → Updates` shows one number for the pair. The
  only source change in this release is the version header.

### Added — 0.12.0 (the Persian setup, shared)
- **`wavira_core_apply_persian_defaults()`** (`public-api.php`) — the Iranian defaults (locale, timezone,
  week start, date and time format, and a blog description nobody wrote on purpose) exposed as a public
  function, so the theme's one-click Persian setup and the demo installer run the *same* code instead of
  two lists that drift. Narrow by design: it publishes nothing, changes no user, and only ever runs when a
  caller with `manage_options` decides to run it.
- `Demo\Installer::apply_site_defaults()` is now `public` for the same reason, with its docblock saying
  who calls it.

### Added — 0.11.0 (release candidate: the legacy migration tool and the release package)
- **The legacy migration tool** (`src/Migration/LegacySchema.php`, `src/Migration/Migrator.php`) behind
  `wp wavira migrate`: `--detect`, `--dry-run`, `--status`, `--rollback`, `--kind`, `--batch`, `--offset`,
  `--report=<file>`. It copies the original post type and every legacy value into `_migration_backup` (once)
  and `_migration_raw` before the first write, never publishes/unpublishes or deletes, is structurally
  idempotent, bounded and resumable, expands the legacy album repeater into real child tracks once, and
  reports what it refuses to guess (an unmatched artist, a non-local image, an unknown `musics_type`) in a
  review queue instead of inventing data. `--rollback` restores the legacy state and deletes exactly the
  children the tool created (ADR 0018).
- **`tests/test-migration.php`** — the blueprint's acceptance list (M1, M3–M7, M9, M10) plus the schema
  contract, on legacy-shaped fixtures: real WordPress, real database, no legacy code.
- **The packages** are built by `tools/package.mjs`, which also validates that the plugin archive contains
  what it promises (headers, compiled `.mo`, built bundles) and nothing dev-only.

- **Interop with the publishing plugin** (`--source=music-publisher`). `src/Migration/LegacySchema.php` now
  carries two source profiles: the audited theme (`mp3`/`mp4`/`album`) and “Sajad Music Publisher”
  (`musicss*`), which writes ordinary posts with its own meta (`art_name`, `music_txt`, `fifu_image_url`,
  `online_ply`, `slider_song`, `album_dl` rows) and its own taxonomies. The migration converts either, from
  one engine, and the two vocabularies are proven disjoint. Role credits the v1 model has no field for
  (songwriter, composer, arranger, mix engineer) are preserved as data and reported — never invented as
  artists. Contract and limits: `docs/INTEGRATIONS.md`.
- **`Content\Taxonomies::KIND`** (`wavira_kind`, `/kinds/`, always on, tracks only) — single, remix, noha,
  podcast. This is what keeps an imported remix from arriving as just another song; `normalize_kind()`
  matches an explicit alias map, so `musicss_remix` cannot land in `music`, and a value nothing claims
  gets no term at all.
- **The demo importer without WP-CLI** (`src/Demo/`): `Fixtures` (the Persian catalogue and the neutral
  English fixture), `Installer::install()` (idempotent; a forced run replaces only posts carrying the
  `_wavira_demo` marker, never the site owner's content) and `Admin\DemoPage` — the screen
  `Tools → Wavira demo content`, behind `manage_options` and a nonce. `wp wavira seed` and the screen call
  the same installer, so the demo cannot drift between them.
- **`wp wavira export-demo`** (`src/Demo/Exporter.php`) — the site's music content as WXR, for moving it to
  another installation; the same document is a download button on the screen. It wraps WordPress' own
  `export_wp()` rather than reimplementing the format, and it knows the one thing Core does not say out
  loud: `export_wp()` declares its `wxr_*()` helpers *inside* itself, so a second call in the same request
  is a fatal "Cannot redeclare wxr_cdata()" error. A second export — Wavira's own, or one a third-party
  plugin already ran in this request — is refused with a reason instead of being allowed to take the
  request down.
- **`tests/test-demo.php`** — the demo path on a real WordPress: the catalogue, the absence of fabricated
  media URLs, idempotency, a forced import that leaves the owner's post alone, the English fixture leaving
  site options alone, the Iranian defaults, the fixture shape, and the export.

### Changed — 0.11.0
- The plugin header and `WAVIRA_CORE_VERSION` read `0.11.0`; `Migrator::unmapped_kinds()` no longer calls
  an admin-only function (it was fatal on a front-end or test request), and `expand_album()` reads the
  legacy repeater by its own sub-field name.

### Added — 0.10.1 (Persian localisation)
- **`Content\Jalali`** — a PHP port of the Borkowski algorithm as published in `jalaali/jalaali-js` (MIT;
  notice in `THIRD-PARTY-NOTICES.md`), accurate for Jalali 1178–1633, clamping input outside the table
  instead of inventing a date.
- **`Content\Dates`** — one date policy: Jalali on `fa*` locales, Gregorian elsewhere, with
  `wavira_core_date_style` / `_date_format` / `_date_label` filters and one filter to step aside for another
  Jalali plugin. Machine surfaces (machine formats, time-carrying formats, REST, AJAX, cron, feeds, robots,
  admin) are never converted, and conversion is idempotent because it recomputes from the timestamp.
- **`wavira_core_digits()`** — Persian numerals for user-visible numbers, exposed in `public-api.php`.
- **The Persian demo** — `wp wavira seed` now creates a Persian music site: Iranian defaults (`fa_IR`,
  `Asia/Tehran`, week starting Saturday, `j F Y`), a Persian menu on the `primary` location, and generated
  Persian demo content (artist, album, tracks with lyrics, single, video, genres). `--english` still
  produces the neutral fixture for development.

### Added — 0.10.0 (music structured data and the SEO seams)
- **`src/Seo/StructuredData.php`** — the schema.org graph of a music post: `MusicGroup`,
  `MusicAlbum` (with its bounded tracklist, `ALBUM_TRACK_LIMIT` = 50), `MusicRecording` (ISO 8601
  duration, `isrcCode`, `inAlbum`) and `MusicVideoObject` (hosted `contentUrl`, or `embedUrl` mapped
  for YouTube and Aparat). Published music only; posts and pages produce no node, because the generic
  surface belongs to the site's SEO plugin.
- **`src/Seo/SeoSupport.php`** — detection of Yoast, Rank Math, SEOPress and All in One SEO by their
  stable markers, plus the two switches (`wavira_core_seo_plugin_active`,
  `wavira_core_structured_data_enabled`).
- **`src/Content/Credit.php`** — one answer to "who is this by?": primary artist then featured
  artists, deduplicated, published only. The schema, the document title and any credit line share it.
- **Four public functions** — `wavira_core_structured_data()`, `wavira_core_seo_plugin_active()`,
  `wavira_core_credit_names()`, `wavira_core_cover_image()`. The graph is the API; the `<script>` tag
  is the theme's business (ADR 0016).

### Changed — 0.10.0
- `src/Seo/` is a **service** layer in the boundary gate (it reads the data layer, never the REST or
  admin layers).
- Plugin version 0.10.0; catalogue unchanged at 124 strings, all translated.

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
