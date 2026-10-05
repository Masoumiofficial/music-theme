# ADR 0006 — Asset strategy and build tooling

- **Status:** Accepted
- **Date:** 2026-10-05
- **Phase:** 0.2.0
- **Related:** `PERFORMANCE-AUDIT.md` (P1, P6, P7), `SECURITY-AUDIT.md` S8, brief §29–§30

## Context

The legacy theme ships 224 KB of JS (jQuery, Owl Carousel, IE-era polyfills, a cookie plugin) and a
single 62 KB CSS file, enqueued site-wide; the player uses global selectors. Performance budgets in
`PERFORMANCE-AUDIT.md` §3 cannot be met with that approach.

## Decision

1. **JavaScript:** vanilla **ES modules** only; no front-end jQuery; no global variables except the
   single documented `window.Wavira` surface. Modules are bundled by a small Node script
   (`tools/build-js.mjs`, plain esbuild-free concatenation or esbuild if available) into
   `assets/dist/theme.js` / `wavira-core/assets/dist/*.js`.
2. **CSS:** sources are layered (`tokens → base → components → utilities`) and concatenated/minified
   into `assets/dist/theme.css`. Design values come from `theme.json` presets / `--wavira-*` tokens only.
3. **Build output is git-ignored** and produced by `tools/build.mjs`. The repository must remain
   runnable **without a build**: if `assets/dist/*` is missing, templates degrade gracefully and the
   theme enqueues nothing (verified by `wavira_theme_stylesheet_uri()` returning an empty string).
4. **Assets are conditional:** the player bundle loads only where a player mounts; carousel code only
   where a carousel exists; no library is added without a size justification.
5. **No runtime dependency on Node or Composer.** Node is a *development* tool; Composer is optional
   for dev tooling (PHPCS). Neither is required on the customer's server.
6. **Linting:** `tools/lint.sh` runs PHP syntax checks (when PHP is present), JSON validation, JS
   syntax check (`node --check`), and reports bundle sizes; CI runs the same script (see
   `.github/workflows/ci.yml`).

## Consequences

- The product can be installed by uploading a ZIP; developers can rebuild assets but do not have to.
- All third-party assets must be re-evaluated before bundling (licence + size) — see ADR 0010.
- The performance budget becomes measurable in CI (bundle-size report is part of the lint output).
- Contributors need Node only for asset work; PHP-only contributors can still ship templates and
  plugin logic.
