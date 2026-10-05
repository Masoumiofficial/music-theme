# THIRD-PARTY-NOTICES.md

Every third-party component that ships with **Wavira** must be listed here with its licence and source.
Rule (ADR 0010): if the licence is unknown, the component does not ship.

**Current state: 0.2.0 (architecture).** No third-party runtime component is bundled yet — the product
is intentionally dependency-free at runtime (no ACF, no OptionTree, no jQuery, no page-builder
requirement). This file is therefore the *policy + register* that later phases must keep up to date,
and a release-package build fails if it is out of date.

## Register

| Component | Version | Licence | Source | Used by | Ship? |
| --- | --- | --- | --- | --- | --- |
| _(none yet)_ | — | — | — | — | — |

### Planned / candidate components (must be confirmed before bundling)

| Component | Version | Licence | Why it is considered | Status |
| --- | --- | --- | --- | --- |
| Vazirmatn (Persian + Latin font) | latest OFL release | SIL OFL 1.1 | primary UI typeface for fa_IR/Arabic; **replaces** the legacy Vazir/Yekan files | to verify + ship OFL text |
| Own SVG icon set | — | project-owned (GPL-2.0-or-later) | replaces the legacy IcoFont icon font | to create in phase 0.6.0 |
| Demo artwork (generated / CC0) | — | own or CC0 | replaces all legacy demo images | to create in phase 0.9.0 |

## Explicitly excluded from the product (audit result)

| Excluded item | Reason |
| --- | --- |
| `functions.php` and `RTL_License_41d6c5f704e3a746.php` (ionCube) | proprietary, encrypted, marketplace-bound — see `docs/LICENSE-AUDIT.md` L2/L3 |
| OptionTree 2.6.0 and its assets (incl. Yekan font) | obsolete; GPLv3 mixing risk; unclear font licence — L4/L5/L6/L14 |
| Legacy `screenshot.jpg` and `images/*` | unclear provenance — L15/L16 |
| IcoFont 1.0.1 (until its licence is verified in writing) | URL-only licence statement — L12 |
| `jquery.js`, `owl.carousel.js`, `jquery.cookie` bundle, `mediaqueries.js`, `html5shiv.js` | replaced by vanilla ES modules; IE-era payload — L7/L8/L9/L10/L11 |
| Legacy author/marketplace branding strings and URLs | third-party brand identity — L19, `docs/BRAND-DECISION.md` |

## How to add a component

1. Verify the licence allows commercial use, redistribution and modification (or that the component is
   not redistributed at all — e.g. an optional integration).
2. Add a row to the register above with exact version and source URL.
3. Bundle the licence text (or the required notice) in the shipped package.
4. If the component is a font: subset it, set `font-display: swap`, and ship the OFL text.
5. Mention it in the pull request — reviewers reject any dependency without a licence line.
