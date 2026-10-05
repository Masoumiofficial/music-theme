# tools/ — development tooling

Nothing in this folder ships with the product. These scripts exist so that quality gates can run
locally and in CI. Node is required for asset work; PHP is required for PHP linting (when absent, the
script skips PHP checks and says so).

| Script | What it does | Requires |
| --- | --- | --- |
| `lint.sh` | Full gate, named gates: `[PHP]` syntax, `[PHPCS]` (if installed), `[JS]` syntax (`node --check` on every `.js`/`.mjs`; the plugin sources must parse as ES modules because they ship unbundled), `[JSON]`, `[REFS]` class references, `[BLOCKS]` block consistency, `[I18N]` text, `[MAPPING]` migration targets, `[CSS]`, `[CONTRAST]`, `[LEGACY]` echo grep, `[BOUNDARIES]`, `[SIZE]` report | bash; PHP/Node/Python optional |
| `check-boundaries.mjs` | Module-boundary gate (ARCHITECTURE §2): Core never reaches into the theme; dependencies only point downwards (foundation → data → services → entry points); the theme names no Core class; every product file has a direct-access guard; global functions carry the documented prefix | Node 18+ |
| `annotate-log.mjs` | Turns a log tail into GitHub annotations (job logs are not reachable from every environment) | Node 18+ |
| `junit-annotate.mjs` | Turns a PHPUnit JUnit report into annotations plus one summary annotation listing every problem | Node 18+ |
| `build.mjs` | Builds `wavira/assets/dist/theme.css` + `theme.js` and `wavira-core/assets/dist/core.js` from sources in `assets/` (verbatim concatenation in layer order — no bundler, no minifier; measure the shipped bytes with `gzip -9`) | Node 18+ |
| `lint-js.mjs` | JS-only syntax check (used by npm scripts) | Node 18+ |
| `check-css.mjs` | CSS gate: no `!important`; logical properties only; dark-mode parity for every `theme.json` palette colour; component-class parity (every `.wavira-player*` selector is a class the product renders); ADR 0009 size budgets; **design-token resolvability** — every `--wp--preset--*`, `--wp--custom--*` and `--wp--style--*` reference must be producible from `theme.json` (WordPress kebab-cases preset slugs and custom keys, so `--wp--custom--player--barSpace` silently resolves to nothing) | Node 18+ |
| `check-blocks.mjs` | Block gate: every `blocks/<name>/block.json` is valid, server-rendered (`render: file:./render.php`, `supports.html: false`), guarded against direct access, listed in `inc/blocks.php` **and** registered in `blocks/editor.js`, with no remote asset, and no template/part/pattern that still ships a shortcode block — a block that is half-added renders on the front end but shows as unsupported in the editor | Node 18+ |
| `check-i18n.mjs` | Text gate: no hard-coded text node and no text-bearing block attribute in `templates/*.html` / `parts/*.html` (a block template cannot run PHP, so such a string is frozen in English); every `wp:pattern` reference resolves to a pattern file; every hidden pattern is referenced; non-translatable strings carry an explicit `wavira:i18n-exempt` marker | Node 18+ |
| `check-mapping.mjs` | Documentation-truth gate: every `wavira_*` meta key, post type, taxonomy and settings key named in `docs/MIGRATION-BLUEPRINT.md` resolves against the implemented schema, unless the row is marked `[DEFERRED]` — the migration plan cannot promise a key that does not exist | Node 18+ |
| `check-contrast.mjs` | WCAG 2.2 AA gate: 21 mode-specific colour pairs (light and dark) computed from the `theme.json` palette and `tokens.css`; reports the ratio of every pair and fails below the minimum for its size/weight | Node 18+ |
| `tests/js/player.test.mjs` | Player-engine unit tests (DOM-free, `node:vm`); `npm run test:js` or `node --test tests/js/player.test.mjs` | Node 18+ |
| `tests/js/theme.test.mjs` | Theme-script unit tests (colour modes, storage, toggle labels, player bootstrapping, PHP↔JS storage-key parity) | Node 18+ |
| `preview/` | Component harness: `node tools/preview/serve.mjs` → `http://localhost:4173/tools/preview/` renders the shipped CSS/JS with a stubbed player REST API for the colour-mode, RTL/LTR and 360→1920 checks. Development only — see `tools/preview/README.md` | Node 18+ |

## Legacy-echo gate (part of `lint.sh`)

Fails the build when a legacy pattern reappears in product code:

| Pattern | Why forbidden |
| --- | --- |
| `javanseda`, `جوان صدا`, `tarlanweb`, `rkianoosh`, `rtl-theme`, `rezakianoosh`, `09158856205` | brand pollution from the legacy artifact (ADR 0010) |
| `posts_per_page` … `-1` | unbounded queries (PERFORMANCE-AUDIT P2) |
| `wp_calculate_image_srcset` | responsive images must never be disabled (P1) |
| `wp_is_mobile` | no UA sniffing for layout (P9) |
| `getElementById('audio'` / `id="audio"` | the global player-ID anti-pattern (ADR 0005) |
| `create_function`, `wp_title(` | deprecated APIs (ADR 0007) |
| `jQuery` / `$( ` in `assets/js` | no front-end jQuery (ADR 0006) |

## PHP class-reference gate (`check-class-refs.py`)

PHP cannot be installed in every development sandbox, so a missing `use` statement or a method
that was never written would otherwise surface only as a fatal error in CI (0.5.0 lost a full CI
round to `MetaSchema::audio_key()` being called before it existed). This pass walks every product
and test PHP file, collects the classes, constants and methods the repository declares, and reports
`Class::member()` / `new Class()` references that resolve to nothing. Comments are stripped first,
so prose never looks like code; fully-qualified references are left to PHPCS.

```
python3 tools/check-class-refs.py
checked 53 file(s); 0 problem(s)
```

The gate is a static heuristic, not a PHP parser: it checks names, not signatures, and it cannot
see callables built at runtime. PHPCS and the integration suite stay authoritative.

## Module-boundary gate (`check-boundaries.mjs`)

Enforces the rules `docs/ARCHITECTURE.md` §2 states, because "documented" is not "checked":

| Rule | Meaning |
| --- | --- |
| R1 | Core never references the theme (`get_template_directory*`, theme constants, theme helpers) — the plugin must stay theme-independent (ADR 0002). |
| R2 | Layer direction: a file may use its own layer or a lower one — `foundation` (Contracts, Autoloader/Requirements/Cache) < `data` (Content, Settings) < `services` (Downloads, Search, Related, Player, CacheInvalidator, CLI) < `entry point` (Rest, Admin, Plugin, public API, bootstrap). So a service can never depend on a controller. |
| R3 | The theme names **no** `Wavira\Core\*` class: it calls the public functions in `wavira-core/public-api.php`, actions and filters only. |
| R4 | Every product PHP file refuses direct access (`defined( 'ABSPATH' ) || exit;`, or `WP_UNINSTALL_PLUGIN` in `uninstall.php`). |
| R5 | Global functions are prefixed `wavira_core_` (plugin) or `wavira_` (theme). |

## Usage

```bash
bash tools/lint.sh            # run all gates, print a summary
node tools/check-css.mjs      # CSS rules, token resolvability and size budgets
node tools/check-contrast.mjs # WCAG 2.2 AA colour pairs, both modes
node tools/build.mjs          # build assets (writes assets/dist/)
node tools/build.mjs --check  # report what would be built, write nothing
node --test tests/js/player.test.mjs tests/js/theme.test.mjs
node tools/preview/serve.mjs  # component harness (dev only)
```

The same scripts run in CI (`.github/workflows/ci.yml`), so local success predicts CI success.
