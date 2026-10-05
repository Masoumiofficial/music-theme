# tools/ — development tooling

Nothing in this folder ships with the product. These scripts exist so that quality gates can run
locally and in CI. Node is required for asset work; PHP is required for PHP linting (when absent, the
script skips PHP checks and says so).

| Script | What it does | Requires |
| --- | --- | --- |
| `lint.sh` | Full gate: PHP syntax (via `php -l`), PHPCS (if installed), JS syntax (`node --check` on every `.js/.mjs`), JSON validity, legacy-echo grep gate, asset-size report | bash; PHP/Node optional |
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

## Usage

```bash
bash tools/lint.sh          # run all gates, print a summary
node tools/build.mjs        # build assets (writes assets/dist/)
node tools/build.mjs --check # report what would be built, write nothing
```

The same scripts run in CI (`.github/workflows/ci.yml`), so local success predicts CI success.
