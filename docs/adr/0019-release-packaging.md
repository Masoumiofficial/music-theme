# ADR 0019 — Release packaging: explicit includes, deterministic archives, and a gate for what may not ship

- **Status:** Accepted (0.11.0)
- **Date:** 2026-10-07
- **Deciders:** engineering (with the product owner's brief: "put the theme and the plugin in the
  repository with documentation, commit and push each phase")
- **Related:** ADR 0006 (no build dependency), 0010 (licensing policy), 0018 (migration tool),
  `docs/RELEASE-CANDIDATE.md`, `THIRD-PARTY-NOTICES.md`

## Context

Three things had to be decided before the first upload, and none of them are visible in the product
itself:

1. **What is inside the archive.** A release package is built from the same tree the tests run in, which
   means `tests/`, `tools/`, `docs/`, `dist/` and the repository metadata are one careless `zip -r` away
   from a customer's server. A leak is not a crash: it is a support burden and, in the case of the audited
   legacy artifact, a licensing problem.
2. **Whether two builds of one commit are the same file.** Marketplaces publish checksums and reviewers
   diff versions; if the archive changes between runs (timestamps, entry order, permissions), a checksum
   means nothing and "what exactly did we ship" has no answer.
3. **Who guarantees the third-party licence texts.** One component ships today (a font). A font without its
   OFL text is a licence violation that no reviewer would catch before a customer does.

## Decision

1. **Explicit includes, then a leak scan on the result.** `tools/package.mjs` builds each artifact from a
   declared file list plus per-artifact exclusions (each with a reason), and then scans the *result* for
   the paths that must never appear (tests, tools, docs, bin, `node_modules`, `vendor`, `.git*`,
   `music-theme.zip`). A gate that only consulted its own ignore list would pass while a new directory
   name leaked; scanning the outcome does not.
2. **Determinism is a property of the tool, not a habit of the operator.** Entry order is sorted, names are
   ASCII, the timestamp comes from `SOURCE_DATE_EPOCH` (fixed default), permissions are normalised, and the
   builder re-builds one archive and compares bytes before it reports success. The ZIP writer is ~120 lines
   in the repository (ADR 0006: no build dependency), and it sets the UTF-8 name flag when a name needs it.
3. **The register is checked against the archive, not against intent.** Any file inside `assets/fonts/`
   must be named in `THIRD-PARTY-NOTICES.md`, and the font's licence text must be in the package; the
   release gate fails otherwise.
4. **Required files are required; marketplace extras are explicit.** Both artifacts declare the files a
   package must contain (headers, built bundles, compiled `.mo`, licence files). Files that only matter for
   a marketplace listing (`screenshot.png`) are declared as *optional* and reported on every run, and
   `--strict` turns them into failures for the release procedure. CI runs the non-strict check, because a
   green build must not depend on an artefact only the release machine can produce.
5. **The bundle is the saleable unit.** `wavira-<version>-bundle.zip` carries both artifacts plus
   `README-FIRST/` (Persian install note and user guide, licences, localisation and migration notes), so a
   buyer who downloads one file has everything, and the file names are ASCII so older Windows extractors
   do not turn Persian names into mojibake.
6. **The archives are downloadable from CI.** Every push builds the packages and uploads them as the run
   artifact, with `manifest.json` recording the commit (`built_from`) and the SHA-256 of every file, so a
   package is never "the one somebody built last week".

## Consequences

- **Good:** an upload is reproducible and reviewable; the licence register cannot drift behind the archive;
  a dev-only directory cannot silently reach a customer; the exact bytes that were tested are the bytes
  that can be shipped.
- **Cost:** the file list is explicit, so a new product directory must be added to `ARTIFACTS` deliberately
  (that is the point, but it is friction); the hand-written ZIP writer is code we own and test indirectly
  (the archives are opened by `unzip -t` and by PHP's `ZipArchive` in the install test).
- **Not covered:** code signing (WordPress does not use it for themes/plugins), marketplace submission
  metadata, and the demo screenshot — which needs a rendering browser and is therefore an explicit
  pre-upload item in `docs/RELEASE-CANDIDATE.md` rather than something this tool fakes.
