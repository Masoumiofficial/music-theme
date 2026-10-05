# Wavira Core — WordPress plugin

Content engine and business logic of the **Wavira** music-publishing ecosystem: artists, albums,
tracks, music videos, genres, lyrics, downloads, playback data, settings and the `wavira/v1` REST API.
Everything persistent lives here — **not** in the theme — so the catalogue survives any theme switch.

| | |
| --- | --- |
| Version | 0.2.0 (architecture phase — content model arrives in 0.3.0) |
| Requires | WordPress 6.6+ · PHP 7.4+ |
| Text domain | `wavira-core` |
| Namespace | `Wavira\Core\` (PSR-4, no Composer runtime dependency) |
| Constants | `WAVIRA_CORE_VERSION`, `WAVIRA_CORE_FILE`, `WAVIRA_CORE_DIR`, `WAVIRA_CORE_URI` |
| REST namespace | `wavira/v1` (registered in 0.3.0) |
| Licence | GPL-2.0-or-later |

## What exists today (0.2.0)

```
wavira-core.php              plugin header, constants, requirements gate, activation guard
src/Support/Autoloader.php   PSR-4 autoloader (path-validated, no Composer needed at runtime)
src/Support/Requirements.php PHP/WordPress version gate (no fatal errors, admin notice instead)
src/Plugin.php               singleton, i18n, `wavira_core_booted` action, module seam
src/Contracts/Registrable.php  every module registers its own hooks
src/Contracts/Cacheable.php    every cached service exposes a key + flush
src/{Content,Settings,Player,Rest,Search,Related,Downloads,Admin,Import,Demo,Migration,
     Integrations,Support}/   empty directories with a defined responsibility (see ARCHITECTURE.md)
```

## What arrives next (in phase order)

| Phase | Modules |
| --- | --- |
| 0.3.0 | `Content/*` (CPTs, taxonomies, registered meta), `Settings/*` (typed schema), `Rest/*` skeleton |
| 0.4.0 | `Search/*`, `Related/*`, `Downloads/*`, counters |
| 0.5.0 | `Player/*` + the Player Engine assets (see ADR 0005) |
| 0.6.0 | `Admin/*` editor UX, admin columns, validation |
| 0.9.0 | `Import/*`, `Demo/*`, `Migration/*` (legacy → Wavira) |

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
