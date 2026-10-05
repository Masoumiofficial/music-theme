# ADR 0015 — Persian-first localisation: shipped catalogues, one catalogue for the editor

- **Status:** Accepted
- **Date:** 2026-10-05
- **Phase:** 0.8.0
- **Related:** ADR 0008 (i18n and RTL-first, of which this is the delivery decision), ADR 0006 (no
  build step at runtime), ADR 0010 (OFL fonts), `tools/i18n.mjs`, `tools/check-css.mjs` rule 2,
  `wavira/languages/*`, `wavira-core/languages/*`, `tests/test-i18n.php`, `tests/js/i18n.test.mjs`

## Context

ADR 0008 fixed the direction: English source strings, `fa_IR` as the primary translation, logical CSS
properties, no hardcoded `dir="rtl"`. That left the delivery question open, and the 0.7.0 block layer
made it urgent: a theme whose interface is English until someone translates it is not a Persian
product, and the block editor added a second surface (block titles, block descriptions, the editor
script's own strings) that a `.mo` file alone does not cover by default.

Three concrete failures were possible, and each one is a real WordPress practice:

1. shipping the `.po` only and expecting the site owner to compile a `.mo`;
2. shipping the `.mo` only, so nobody can see or review what was translated;
3. leaving the editor in English because the editor script's strings need a hash-named JSON file that
   only `wp i18n make-json` knows how to produce (and which silently does nothing when the hash is
   wrong).

## Decision

1. **Persian ships in the repository for both artifacts.** `wavira/languages/fa_IR.{po,mo}` and
   `wavira-core/languages/fa_IR.{po,mo}` are committed. The `.po` is the source of truth for the
   translation; the `.mo` is a committed build output, because WordPress loads the `.mo` on a live
   site and a release that needs a toolchain to become Persian is not Persian.
2. **The pipeline is ours and dependency-free.** `tools/i18n.mjs` extracts strings from the PHP
   sources, reads block metadata, writes the `.pot`, compiles `.po` → `.mo` and implements the gate.
   No gettext, no WP-CLI, no Composer, no build step at runtime — consistent with ADR 0006.
3. **The gate is the promise.** `[FA]` in `tools/lint.sh` fails the build when:
   - a source string is missing from the catalogue, or its translation is empty;
   - a translation is still Latin script without an explicit `#, keep-latin` flag (the flag is the
     only way to opt out, and it is reviewed in the diff);
   - the placeholders of a translation differ from its source (a dropped `%s` is a fatal `sprintf`);
   - the committed `.pot` no longer matches the sources, or the committed `.mo` no longer matches the
     `.po` (`node tools/i18n.mjs build`);
   - an entry is left in the catalogue after its source string is gone.
4. **One catalogue covers the editor.** The editor script's strings are printed from PHP
   (`wavira_block_editor_strings()` + `wp_add_inline_script()` keyed by the English source string), so
   the same `fa_IR.mo` translates the front end, the admin and the editor. Block metadata
   (`title`, `description`, `keywords`) is translated by core itself through
   `translate_settings_using_i18n_schema()` — the extractor therefore reads `block.json` and emits the
   contexts core looks up (`block title`, `block description`, `block keyword`).
   `wp_set_script_translations()` and a JSON catalogue are deliberately **not** used: they need a
   hash-named file that no tool in this repository produces, and a wrong hash fails silently.
5. **Admin surfaces are first-class.** Settings labels, settings descriptions, post-type and taxonomy
   labels, REST argument descriptions and CLI output are all in the catalogue, and the plugin loads
   its text domain in `boot()` — before the modules register their post types — so the admin lists a
   Persian label rather than a translated-with-delay one.
6. **Iranian demo content.** The CLI seeder's sample content is Persian in the catalogue, so a fresh
   install demonstrates a Persian site instead of an English demo with Persian chrome.
7. **RTL is already gated, and stays that way.** `tools/check-css.mjs` rule 2 rejects physical
   direction properties; the RTL guarantee is therefore a build failure, not a review habit, and this
   ADR adds nothing to it.
8. **Jalali (Shamsi) dates are not implemented in 0.8.0.** Persian sites often need the Jalali
   calendar. Shipping a hand-rolled converter without a verified anchor set would put wrong dates on
   every page, and WordPress's own locale data is Gregorian. The decision is therefore explicit: date
   output uses `wp_date()` with locale data (Persian digits and month names come from `fa_IR` locale
   files), and a Jalali layer is a separate, tested change (see the open item in
   `docs/REBUILD-PLAN.md`) rather than an unverified line of arithmetic.
9. **Fonts.** The token set already prefers **Vazirmatn** (OFL-1.1, Iranian) with `Segoe UI` and
   system fallbacks; no font file is bundled yet. Bundling a subset OFL font is a packaging decision
   (size budget) that needs the licence file to travel with it (ADR 0010), not a code decision.

## Consequences

- A string cannot reach a release without its Persian translation and a compiled catalogue; the cost
  is one `npm run i18n:extract` + `npm run i18n:build` per change, enforced by CI rather than by
  memory.
- The `.po` diff is reviewable by a Persian speaker who is not a developer, which is the point of
  committing it.
- Reviewers at WordPress.org or a marketplace see a theme that is translated on install.
- Adding a second locale means dropping `xx_XX.po` next to `fa_IR.po` and running the same build; the
  gate treats each `.po` it finds identically.
- The player engine's strings travel inside the REST/localised payload (`wavira-core` catalogue), so a
  Persian player needs no JS-side translation file in either artifact.
