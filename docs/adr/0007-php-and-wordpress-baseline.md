# ADR 0007 — PHP and WordPress baseline

- **Status:** Accepted
- **Date:** 2026-10-05
- **Phase:** 0.2.0
- **Related:** `CODING-STANDARD.md` §1, brief §61–§62

## Context

The legacy theme targets a WordPress/PHP era around 2015 and depends on a PHP extension (ionCube) to
even boot. The target market (Iran and international) still includes shared hosts on PHP 7.4, while
modern WordPress releases are fully supported on PHP 8.x.

## Decision

1. **PHP floor: 7.4.** Code must parse and run on PHP 7.4 — i.e. no PHP 8.0+ only syntax. The product is
   tested on **8.2 / 8.3** (CI matrix) and must not emit deprecation notices there.
2. **WordPress floor: 6.6.** Supported range documented as "6.6 – latest at release". Block themes,
   `theme.json` v3, script `strategy` (defer/async) and modern REST/taxonomy APIs are available at this
   floor; nothing targets deprecated or ancient releases.
3. **No Composer requirement at runtime.** A small PSR-4 autoloader (`src/Support/Autoloader.php`)
   loads `Wavira\Core\*`. Composer stays available for development tooling only (PHPCS, PHPUnit).
4. **Version declarations are synchronized** across: theme `style.css` header, plugin header,
   `WAVIRA_*_VERSION` constants, `readme.txt`/`README.md`, and the changelog — checked by the release
   checklist.
5. **Deprecated API ban** (also in `CODING-STANDARD.md` P2): `create_function`, `each()`, `wp_title()`,
   `$wpdb` without prepare, `wp_is_mobile()` for layout, `get_bloginfo('url')`.
6. **Upgrade safety:** activation/deactivation must never destroy user data; uninstall deletes data
   only with explicit opt-in (`uninstall.php`), and CPT/rewrite changes carry an upgrade routine that
   runs once (version option) rather than on every request.

## Consequences

- Features that would require PHP 8-only syntax are written differently (or deferred with an ADR).
- The plugin can be dropped into almost any currently maintained WordPress site without a server
  upgrade battle; the CI matrix proves 8.x compatibility.
- `Requires PHP`/`Requires at least` headers (and the equivalent in documentation) carry the real
  numbers, so no customer is surprised at activation time.
