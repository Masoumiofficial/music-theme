# tools/ — development tooling

Nothing in this folder ships with the product. These scripts exist so that quality gates can run
locally and in CI. Node is required for asset work; PHP is required for PHP linting (when absent, the
script skips PHP checks and says so).

| Script | What it does | Requires |
| --- | --- | --- |
| `lint.sh` | Full gate: PHP syntax (via `php -l`), PHPCS (if installed), JS syntax (`node --check` on every `.js/.mjs`), JSON validity, legacy-echo grep gate, module boundaries, asset-size report | bash; PHP/Node optional |
| `check-boundaries.mjs` | Module-boundary gate (ARCHITECTURE §2): Core never reaches into the theme; dependencies only point downwards (foundation → data → services → entry points); the theme names no Core class; every product file has a direct-access guard; global functions carry the documented prefix | Node 18+ |
| `annotate-log.mjs` | Turns a log tail into GitHub annotations (job logs are not reachable from every environment) | Node 18+ |
| `junit-annotate.mjs` | Turns a PHPUnit JUnit report into annotations plus one summary annotation listing every problem | Node 18+ |
| `build.mjs` | Builds `wavira/assets/dist/theme.css` + `theme.js` and `wavira-core/assets/dist/*.js` from sources in `assets/` | Node 18+ |
| `lint-js.mjs` | JS-only syntax check (used by npm scripts) | Node 18+ |

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
bash tools/lint.sh          # run all gates, print a summary
node tools/build.mjs        # build assets (writes assets/dist/)
node tools/build.mjs --check # report what would be built, write nothing
```

The same scripts run in CI (`.github/workflows/ci.yml`), so local success predicts CI success.
