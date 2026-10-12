# LICENSE-AUDIT.md — Licence & IP Audit of the Legacy Theme

**Status:** mixed — `[VERIFIED]` where a licence file/header exists in the archive, `[NEEDS_REVIEW]`
where only a URL or convention exists, `[LEGAL_REVIEW_REQUIRED]` where provenance is unknown.

> **"GPL everywhere" is not an assumption in this audit.** Each component is examined for
> *commercial use*, *redistribution*, *modification* and *attribution* requirements separately.

---

## 1. Summary verdict

| Verdict | Components |
| --- | --- |
| **Cannot be shipped / must be excluded** | `functions.php` (ionCube), `RTL_License_41d6c5f704e3a746.php` (ionCube + marketplace product), theme `screenshot.jpg`, `rtl-theme.com` branding strings |
| **Must be replaced or cleared** | all `images/*` demo/media assets, `fonts/Yekan.*` (shipped inside OptionTree assets), Vazir fonts (verify OFL text), IcoFont (verify licence text) |
| **Safe to reuse as library** (with attribution) | OptionTree 2.6.0 (GPLv3, full licence text bundled), Owl Carousel 2.3.4 (MIT header), jQuery (MIT), `jquery.cookie` (MIT) |
| **Needs a licence text check** | `js/mediaqueries.js`, `js/html5shiv.js`, OptionTree's bundled `jquery-ui-timepicker.js` |

**Product-level conclusion:** the *code* of this theme cannot be relicensed or resold as-is, and the
encrypted core makes even a "modified legacy theme" strategy legally and technically unworkable.
The rebuild therefore starts from a clean, independently written codebase with its own GPL-compatible
licence, carrying forward only verified-licence libraries (or their modern replacements).

---

## 2. Component-by-component audit

| # | Name | Version | Source | Licence | Commercial use | Redistribution | Modification | Attribution | Risk |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| L1 | Theme PHP templates (`header/footer/single/…`) | 3.0 | author (Reza Kianoosh / tarlanweb) | **unknown** (no licence file in archive) | presumed paid-marketplace licence | **not permitted for resale** | depends on licence | author credit present | **LEGAL_REVIEW_REQUIRED** — code cannot be reused in the new product |
| L2 | `functions.php` | — | ionCube-encrypted, "www.Rtl-Theme.com", 2023-08-14 | proprietary/encrypted | unknown | **no** | **no** | — | **LEGAL_REVIEW_REQUIRED** — cannot audit, cannot modify, must not ship; treated as `[BLACK_BOX_FUNCTIONALITY]` |
| L3 | `RTL_License_41d6c5f704e3a746.php` | — | ionCube, product link `rtl-theme.com/?p=172924` | proprietary licence artefact | unknown | **no** | **no** | — | **LEGAL_REVIEW_REQUIRED** — purchasing-licence artifact tied to a single marketplace item |
| L4 | OptionTree | 2.6.0 | bundled `inc/option-tree/`, readme + `license.txt` | **GPLv3** `[VERIFIED — full GPLv3 text bundled]` | yes | yes (GPL terms, source required) | yes | keep notices | Low (but obsolete; drop from product) |
| L5 | `inc/option-tree/includes/ot-functions-admin.php` (edited) | 2.6.0 + local patch | tree | GPLv3 (inherits) | yes | yes, **as GPL** | yes | keep notices | Low — patch is `create_function` → closure (PHP 8 compat), documented in PROJECT-AUDIT §2.3 |
| L6 | OptionTree bundled assets: `option-tree-font.*`, `Yekan.*`, `ot-admin.css`, `ot-admin.js`, images, `jquery-ui-timepicker.js` 1.4.3 | — | bundled with OT | mixed: OT assets GPLv3; **Yekan font licence unstated**; timepicker = MIT (Trent Richardson) | OT assets yes | Yekan: **unclear** | — | — | **NEEDS_REVIEW** (Yekan) / Low (timepicker, MIT) |
| L7 | jQuery | 3.x (UMD build, exact version not in banner) `[INFERRED]` | bundled `js/jquery.js` | MIT | yes | yes | yes | keep header (banner was stripped — re-add in rebuild) | Low |
| L8 | Owl Carousel | 2.3.4 | bundled `js/owl.carousel.js` | MIT `[VERIFIED — header states MIT]` | yes | yes | yes | header retained (no separate LICENSE file bundled) | Low |
| L9 | `jquery.cookie` (js-cookie predecessor) | unknown | **embedded inside `js/scripts.js`** | MIT (typical for this plugin) `[INFERRED]` | yes | yes | yes | attribution stripped | **NEEDS_REVIEW** |
| L10 | `css3-mediaqueries.js` (as `js/mediaqueries.js`) | unknown | bundled | historically dual MIT/GPL `[NEEDS_REVIEW — no header retained]` | likely | likely | likely | stripped | **NEEDS_REVIEW** |
| L11 | `html5shiv.js` | 3.x-era | bundled | MIT/GPL dual (upstream) `[NEEDS_REVIEW — banner stripped]` | likely | likely | likely | stripped | **NEEDS_REVIEW** (also obsolete in rebuild) |
| L12 | IcoFont | 1.0.1 | bundled CSS + woff | header: "license - https://icofont.com/license/" only | depending on that URL | depending | depending | yes | **NEEDS_REVIEW** — read the licence page before any reuse; plan replacement with icon set with explicit licence or own SVG set |
| L13 | Vazir typeface | 4 weights (normal/bold/300/500) | bundled `fonts/vazir-*.woff` | upstream Vazir is distributed under SIL OFL 1.1 `[INFERRED — no licence file bundled]` | yes (OFL) | yes, with reserved-name rules | yes | OFL notice must ship | **NEEDS_REVIEW** — obtain the OFL text from upstream and ship it; confirm these are unmodified builds |
| L14 | Yekan typeface | eot/ttf/woff/svg inside OptionTree | bundled | company/author-specific; not a standard OFL release `[UNKNOWN]` | **unknown** | **unknown** | **unknown** | — | **LEGAL_REVIEW_REQUIRED** |
| L15 | Theme images: `error404.jpg`, `no-artist.jpg`, `nomusics.jpg`, `vip_img.jpg`, `def-singer.webp`, `logo.png`, `favicon.png` | — | bundled | **unknown provenance** (no licence, EXIF/PNG metadata only) | unknown | unknown | unknown | — | **LEGAL_REVIEW_REQUIRED** — do not ship; rebuild needs owned/CC0/generated demo art |
| L16 | `screenshot.jpg` | 250 KB | bundled | derived from theme design + likely third-party photos | — | — | — | — | **LEGAL_REVIEW_REQUIRED** — must be replaced entirely |
| L17 | `searchwp-live-ajax-search/` templates | — | bundled override pair for a third-party GPL plugin | GPL (plugin) / theme-authored wrapper | yes (as GPL) | yes | yes | — | Low, but it is an integration, not part of the theme's own value |
| L18 | Fonts used via Google Fonts API in OptionTree UI (`googleapis.com/webfonts`) | — | runtime API call | Google Fonts terms | yes | n/a | n/a | — | Low (admin-only, obsolete path) |
| L19 | Brand names/marks: "Javan Seda", "جوان صدا", `tarlanweb`, `rkianoosh`, "Reza Kianoosh", rtl-theme.com | — | throughout | third-party brand identity | **no** | **no** | **no** | — | **Must be removed** from all shipped artifacts (see BRAND-DECISION vocabulary rules) |

---

## 3. Licence-compatibility analysis for the new product

New product licence decision (recorded here, applied in the rebuild):

| Layer | Planned licence | Rationale |
| --- | --- | --- |
| Theme | GPLv2-or-later (WordPress convention, required by WP.org) | marketplace + WP.org compatibility |
| Core plugin | GPLv2-or-later | must run with GPL themes |
| Bundled libraries | Only MIT / GPL-compatible / OFL fonts / CC0 or own assets | redistribution-safe |
| Demo content | 100 % authored or licence-cleared (CC0 imagery, generated art) | marketplace demo rules |
| Encrypted or unclear-provenance legacy code/assets | **excluded from every package** | legal safety |

**GPLv2 vs v3 note:** OptionTree 2.6.0 is GPLv3 and was bundled *inside* the theme. Mixing GPLv3
library code into a GPLv2-only product is a compatibility trap. The rebuild removes OptionTree
entirely, so the question only matters for the audit record, not for the shipped product.

## 4. Required licence artifacts in the new packages

- [ ] `LICENSE.md` (GPLv2-or-later) for theme and plugin
- [ ] `THIRD-PARTY-NOTICES.md` listing every bundled dependency with version, licence and source URL
- [ ] OFL text file(s) for any bundled OFL font (Vazir or replacement)
- [ ] MIT text for any bundled MIT library (icons, carousel — if still needed)
- [ ] Demo asset provenance record (`docs/DEMO-ASSET-LICENCES.md`) — file → source → licence → proof
- [ ] Explicit exclusion list proving no encrypted file, no rtl-theme.com asset, no legacy screenshot,
      no OptionTree, no unverified image shipped

## 5. Actions

| # | Action | Owner | Blocking? |
| --- | --- | --- | --- |
| A1 | Do not ship, link or reference the two ionCube files anywhere in the new packages | Dev | Blocker for packaging |
| A2 | Replace all demo images with owned/generated/CC0 assets; keep a provenance log | Design/Dev | Blocker for marketplace submission |
| A3 | Verify Vazir OFL upstream, ship OFL text; or switch to another OFL Persian+Latin family | Dev | Blocker for i18n release |
| A4 | Replace IcoFont with an SVG icon system (own or MIT-licensed) | Dev | Blocker for release |
| A5 | Drop OptionTree, css3-mediaqueries, html5shiv, jquery.cookie bundle | Dev | Non-blocking (design decision already taken) |
| A6 | Legal review of any legacy asset the client insists on keeping | Legal | If applicable |
| A7 | File formal trademark clearance for the new brand before filing/marketplace launch | Legal | See BRAND-DECISION |
