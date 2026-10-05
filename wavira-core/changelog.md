# Wavira Core — changelog

All notable changes to the plugin are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

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
