# ADR 0010 — Licensing and third-party asset policy

- **Status:** Accepted
- **Date:** 2026-10-05
- **Phase:** 0.2.0
- **Related:** `LICENSE-AUDIT.md` (L1–L19, actions A1–A7), brief §43–§45

## Context

The legacy theme bundles assets with unclear or incompatible provenance: two ionCube-encrypted files
tied to a marketplace purchase, demo images and a screenshot with unknown rights, a Yekan font without
a licence statement, an icon font distributed under a URL-only licence, and GPLv3 OptionTree mixed
into a GPLv2-era theme. None of that can ship in a commercial product.

## Decision

1. **Ship only:** GPL-compatible code (GPLv2-or-later for the product itself), MIT/BSD libraries,
   SIL OFL fonts, CC0/public-domain or **own/generated** media. Anything else is excluded, even if
   "probably fine".
2. **Product licence:** theme and plugin distributed under **GPL-2.0-or-later**, matching WordPress
   ecosystem expectations and marketplace requirements.
3. **Absolute exclusions from every package:** both ionCube files, `RTL_License_*`, OptionTree and its
   assets, `screenshot.jpg`, all legacy `images/*`, Yekan font, IcoFont unless its licence is verified
   in writing, IE-era polyfills, and any string/asset carrying the legacy authors' or marketplace's
   branding.
4. **Provenance is recorded, not assumed:** `THIRD-PARTY-NOTICES.md` lists every bundled dependency
   (name, version, licence, source URL) and `docs/DEMO-ASSET-LICENCES.md` records demo media
   (file → source → licence → evidence). A package build fails if either file is out of date.
5. **Demo content is authored or licence-clear:** generated artwork, CC0 photography, or content
   created for the product; no scraped artist imagery, no real lyrics from third parties.
6. **Fonts and icons:** OFL fonts only, with the OFL text shipped; icons as an owned SVG set
   (no icon-font redistribution question, better performance and accessibility control).
7. **Trademark hygiene:** the brand "Wavira" is used consistently (see `BRAND-DECISION.md`), and
   professional trademark clearance is completed before commercial launch; DNS findings are treated as
   a signal, never as proof.
8. **No licence/update lock-in:** the product must work fully offline; any future licence server must
   degrade gracefully (ADR to be written in phase 0.9.0 if pursued).

## Consequences

- Some legacy visual identity (fonts, icons, placeholder art) is replaced — accepted as a startup cost
  for a legally clean product.
- Each new dependency requires a one-line licence note in the PR; reviewers reject "unknown licence".
- Packaging is gated on the notices/provenance files, which also become marketplace submission
  evidence.
