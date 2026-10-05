# Wavira Core — changelog

All notable changes to the plugin are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

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
