# Wavira Core — WordPress plugin

Content engine and business logic of the **Wavira** music-publishing ecosystem: artists, albums,
tracks, music videos, genres, lyrics, downloads, playback data, settings and the `wavira/v1` REST API.
Everything persistent lives here — **not** in the theme — so the catalogue survives any theme switch.

| | |
| --- | --- |
| Version | 0.3.0 (data model implemented — UI arrives in 0.6.0) |
| Requires | WordPress 6.6+ · PHP 7.4+ |
| Author | **Etehad WP — اتحاد وردپرس** · <https://etehadwp.com/> |
| Text domain | `wavira-core` |
| Namespace | `Wavira\Core\` (PSR-4, no Composer runtime dependency) |
| Constants | `WAVIRA_CORE_VERSION`, `WAVIRA_CORE_FILE`, `WAVIRA_CORE_DIR`, `WAVIRA_CORE_URI` |
| REST namespace | `wavira/v1` (registered in 0.3.0) |
| Licence | GPL-2.0-or-later |

## What exists today (0.3.0)

```
wavira-core.php               plugin header, constants, requirements gate, activation guard
src/Support/Autoloader.php    PSR-4 autoloader (path-validated, no Composer needed at runtime)
src/Support/Requirements.php  PHP/WordPress version gate (no fatal errors, admin notice instead)
src/Support/Cache.php         versioned caching helper (object cache + generation bump)
src/Support/CacheInvalidator.php  flushes caches on music saves, term changes, settings updates
src/Plugin.php                singleton, i18n, module registry, `wavira_core_booted` seam
src/Contracts/{Registrable,Cacheable}.php
src/Content/                  PostTypes, Taxonomies, MetaSchema, Meta, MetaValues, ContentModule
src/Settings/                 SettingsSchema (one typed schema) + Settings (register/read/update)
src/Rest/                     AbstractController, ContentController, GenresController, ContentRoutes
src/Downloads/Access.php      download authorization + quality matrix (no DRM claims)
src/Admin/Cli.php             `wp wavira verify`, `wp wavira seed` (licence-clean generated content)
src/{Player,Search,Related,Import,Demo,Migration,Integrations}/   defined seams for later phases
uninstall.php                 opt-in data removal (default: keep the catalogue)
```

Data model, meta keys, REST routes and settings are documented in `docs/DATA-MODEL.md`.
Static verification (WPCS, PHPCompatibilityWP, `php -l` on 7.4/8.2/8.3) runs in CI; the
per-claim evidence log is `docs/VERIFICATION.md`. Runtime verification on a live
WordPress install is still open.

## What arrives next (in phase order)

| Phase | Modules |
| --- | --- |
| 0.4.0 | `Search/*`, `Related/*`, counters; caching behind services |
| 0.5.0 | `Player/*` + the Player Engine assets (see ADR 0005) |
| 0.6.0 | `Admin/*` editor UX (panels, columns, validation) |
| 0.7.0 | `Integrations/*` (Elementor bridge) |
| 0.9.0 | `Import/*`, `Demo/*`, `Migration/*` (legacy → Wavira) |

## Quick start

```bash
wp plugin activate wavira-core
wp wavira verify     # post types, taxonomies, registered meta, counts
wp wavira seed       # optional: minimal demo set (generated text only)
curl https://example.com/wp-json/wavira/v1/tracks?per_page=5
```

## Rules this plugin obeys (enforced in review and CI)

1. No ACF, no OptionTree, no jQuery, no page-builder requirement (ADR 0004).
2. Every meta key registered with type + sanitize + auth + REST schema (ADR 0003, CODING-STANDARD W1).
3. No unbounded queries; expensive reads go through `Cacheable` services.
4. Every mutation: nonce + capability. Every REST route: permission callback + args schema.
5. User-facing strings use the `wavira-core` text domain; no hardcoded copy (ADR 0008).
6. The plugin works with any theme and headless setups: REST first, HTML never produced here.
7. Deactivation never destroys data; uninstall deletes only with explicit opt-in.

## Usage (theme authors and integrators)

```php
// Theme side — is the engine present?
if ( function_exists( 'wavira_has_core' ) && wavira_has_core() ) {
    // read settings through the documented helper
    $limit = wavira_get_setting( 'related_limit', 8 );
}

// Plugin side — extend a module's payload
add_filter( 'wavira_related_items', function ( array $items, $post_id ) {
    return $items; // reorder / decorate / filter
}, 10, 2 );

// Register your own module (e.g. in another plugin)
add_action( 'wavira_core_booted', function ( $plugin ) {
    // $plugin->register( new My_Module() ); — module list API lands in 0.3.0
} );
```

The stable public surface is listed in `docs/ARCHITECTURE.md` §6; everything else is private and may
change between minor versions until 1.0.0.
