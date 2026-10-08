# ADR 0022 — A browser in CI is where the visual evidence is produced

* Status: Accepted
* Date: 2026-10-08
* Phase: 0.14.0
* Relates to: [0019](0019-release-packaging.md) (packaging), [0009](0009-accessibility-and-performance-gates.md)
  (gates), [0021](0021-front-page-composition.md) (the front page this gate can see)

## Context

Three items on the pre-upload checklist (`docs/RELEASE-CANDIDATE.md` §6) could not be closed by any tool in
this repository: `wavira/screenshot.png`, the Lighthouse/axe audit, and a rendered look at the product. All
three need a browser and a live WordPress. The authoring environment has neither, and that was written down
as a reason to leave the items open — `node tools/package.mjs --strict` fails on the missing screenshot on
purpose, so the gap could not be forgotten.

It could, however, be closed: **CI has both**. A runner can install WordPress, seed the demo, serve the
site, and drive a browser. Leaving a blocking item open because *the sandbox* cannot do it was a decision
about the sandbox, not about the product.

## Decision

1. **The render happens on a runner** (`wp-render` in `.github/workflows/ci.yml`): WordPress latest with
   the Persian locale, the theme and the plugin from the working tree, `wp wavira seed --force` for the
   demo, `wp server` for HTTP, and `tools/screenshot.mjs` for the images.
2. **The measurement and the verdict are separate files.** `tools/screenshot.mjs` renders, checks its own
   output (a PNG of the requested size, an HTTP 200, visible text on the page) and writes what axe found;
   `tools/check-axe.mjs` decides. The screenshot has to exist even when the audit fails — otherwise a
   contrast regression costs the image the review was going to look at — and a gate that measures nothing
   is not a gate.
3. **The screenshot is committed, the branch does not commit it.** `wavira/screenshot.png` must be in the
   tree for the packages to carry it, so the job commits it — but only on `main`. A workflow token's push
   does not trigger workflows, so a branch that commits its own screenshot can never go green: the commit
   that adds the file is not the commit the checks already passed on. On a branch the render runs, the
   image is uploaded as an artifact, and `--strict` proves the packaging path works with a screenshot
   present.
4. **`moderate` and `minor` axe findings are printed, not fatal**, and an allowance has to be named
   (`--allow=color-contrast`): a site owner's accent colour is not machine-checked for contrast (ADR 0020's
   stated limitation), so the rule that fires on it is reported as an allowance rather than suppressed.
5. **PHP notices are recorded, not yet fatal.** The render runs with `WP_DEBUG` on and uploads
   `debug.log`; a notice is annotated as a warning. The list has to be empty before it becomes a gate —
   wiring a new gate to a list nobody has looked at is how gates get switched off.

## Consequences

* §6 item 1 (`screenshot.png`) and item 4 (the real-browser audit) move from **NOT_STARTED** to produced,
  with the images kept in `docs/screenshots/` so a review needs no running site.
* The job adds a browser download and a WordPress install to every push on a branch. That is a few minutes
  for the only evidence of its kind in the repository, and it is the evidence a marketplace asks for.
* Lighthouse specifically is *not* run: it needs a Chromium build with a matching Lighthouse version and
  its score is a moving target across versions. The budgets it measures are already enforced statically
  (`tools/check-perf.mjs`) and axe covers the accessibility half. The item stays open for a store listing
  that requires the number, and that is stated rather than implied.
