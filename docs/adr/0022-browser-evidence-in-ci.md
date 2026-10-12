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
3. **The screenshot is committed on a branch, never on a tag.** `wavira/screenshot.png` must be in the
   tree for the packages to carry it and for a reviewer to see it, so the job commits it when it changed —
   on a branch. A tag ref is a detached HEAD with no branch to commit to, and the tag run keeps the
   strict-packaging check instead. A workflow token's push triggers no workflows of its own, so the commit
   that adds the image is verified by the *next* push rather than by itself; the image is a binary asset,
   not a code path, and the render that produced it was verified in the same run. The first version of this
   rule only committed on `main` — and the image the deliverable needed stayed an artifact nobody could
   open (0.14.0).
4. **`moderate` and `minor` axe findings are printed, not fatal**, and an allowance has to be named
   (`--allow=color-contrast`): a site owner's accent colour is not machine-checked for contrast (ADR 0020's
   stated limitation), so the rule that fires on it is reported as an allowance rather than suppressed.
5. **The page is checked, not just photographed.** `tools/check-render.mjs` reads the fetched HTML and
   fails when the front page is not Persian — comparing against the strings in the shipped catalogue rather
   than a list typed into the workflow, so a missing translation and an un-translated page are the same
   failure — when the front-page template's sections are absent (a blog index at the root, the defect of
   ADR 0021) and when the page carries no Persian at all. A screenshot can be of the wrong page; the job
   that takes it has to say which page it is.
6. **PHP notices are recorded, not yet fatal.** The render runs with `WP_DEBUG` on and uploads
   `debug.log`; a notice is annotated as a warning. The list has to be empty before it becomes a gate —
   wiring a new gate to a list nobody has looked at is how gates get switched off.

## Consequences

* §6 item 1 (`screenshot.png`) and item 4 (the real-browser audit) move from **NOT_STARTED** to produced,
  with the images kept in `docs/screenshots/` so a review needs no running site.
* **The first successful render paid for the job twice over.** It showed the theme's own container being
  overruled by a constrained block layout — every page rendering as a 720px column with two thirds of it
  empty — which five releases of static gates had not seen: the templates were registered, the blocks were
  consistent, the budgets held. It also showed the demo's missing cover art, which is deliberate (no
  fabricated media, ADR 0010) and now visible in a picture rather than in a paragraph.
* The job adds a browser download and a WordPress install to every push on a branch. That is a few minutes
  for the only evidence of its kind in the repository, and it is the evidence a marketplace asks for.
* Lighthouse specifically is *not* run: it needs a Chromium build with a matching Lighthouse version and
  its score is a moving target across versions. The budgets it measures are already enforced statically
  (`tools/check-perf.mjs`) and axe covers the accessibility half. The item stays open for a store listing
  that requires the number, and that is stated rather than implied.
