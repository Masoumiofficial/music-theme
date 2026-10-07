# RELEASE-CANDIDATE.md — Wavira 0.11.0 (RC)

> **Status: release candidate.** The product is feature-complete for v1: every phase from 0.5.0 to 0.11.0
> has landed and is verified by CI where CI can see it. What is left is *release mechanics* — the items in
> §6 are the difference between "the code is ready" and "an upload is ready", and each one is either done,
> or written down with the reason it is not.

| | |
| --- | --- |
| Product | **Wavira** — music-publishing ecosystem for WordPress (theme + core plugin) |
| Version | `0.11.0` (release candidate; `1.0.0` is the production release) |
| Author | Etehad WP (اتحاد وردپرس) — https://etehadwp.com/ |
| Licence | GPL-2.0-or-later (bundled third-party: Vazirmatn, SIL OFL 1.1; Jalali algorithm, MIT) |
| Requires | WordPress 6.6+, PHP 7.4+ (tested 7.4 / 8.2 / 8.3) |
| Packages | `dist/wavira-theme-0.11.0.zip`, `dist/wavira-core-0.11.0.zip`, `dist/wavira-0.11.0-bundle.zip` |
| Build | `node tools/build.mjs && node tools/package.mjs` (no dependencies, no bundler, deterministic) |

---

## 1. What ships

| Package | Size | Contents |
| --- | --- | --- |
| `wavira-core-0.11.0.zip` | ~153 KB | The plugin: content model, REST API, player engine, downloads, SEO data, admin surfaces, Persian catalogue, migration tool |
| `wavira-theme-0.11.0.zip` | ~213 KB | The theme: templates, patterns, blocks, compiled CSS/JS, Vazirmatn with its licence text, Persian catalogue |
| `wavira-0.11.0-bundle.zip` | ~389 KB | Both packages plus `README-FIRST/` (install note, user guide, licences, localisation and migration notes) |
| `dist/manifest.json` | — | Every file in every archive with its size and SHA-256, so a reviewer can verify what was delivered |
| `dist/SHA256SUMS` | — | The three archive digests |

`dist/` is generated, never committed (`.gitignore`), and rebuilt identically from the tag: entry order
sorted, timestamps from `SOURCE_DATE_EPOCH`, permissions normalised, and `tools/package.mjs` rebuilds one
archive on the spot and compares bytes before it reports success.

## 2. What was verified, and how

Full evidence lives in `docs/VERIFICATION.md`; this is the release view.

| Area | Verdict | Evidence |
| --- | --- | --- |
| Data model, REST, player, downloads, search, SEO, Persian/Jalali, artist & news pages | **VERIFIED** | CI runs `37438117893` (0.10.1) and earlier — 8/8 jobs, `OK (134 tests, 1090 assertions)` on PHP 7.4 + 8.2 |
| Legacy migration tool | **VERIFIED** | CI `37440743623` / `37440749435` — `OK (146 tests, 1198 assertions)` on PHP 7.4 + 8.2, after the tool was exercised against a real WordPress in a local lab (43 + 20 checks) |
| The shipped archives install and run | **TESTED** (local WordPress 6.7.2 + SQLite lab) | unzip → activate → switch on the real archives: 13 checks (WordPress recognises theme and plugin, versions match the headers, built assets, `.mo` catalogue, font licence and test suite placement) + 17 checks on the next request (post types, taxonomy, 46 meta keys, `@font-face` resolved to the packaged file and present in the rendered head, `font-display: swap`, preload, no `s.w.org` hint, player bundle present) |
| Static gates | **PASS** | `tools/lint.sh`: PHP syntax, WPCS + PHPCompatibilityWP 0 errors/0 warnings over 82 files, JS/JSON, blocks, i18n 215/215, migration mapping, CSS rules, WCAG contrast pairs, performance budgets, legacy-echo gate, module boundaries, `[PACKAGE]` |
| Real-browser audit (Lighthouse, axe, RTL/LTR screenshots) | **NOT_STARTED** | needs a browser: the build environment has none (`WP-RUNTIME` and the pre-upload checklist, §6) |
| 10 000-post dry run and dead-link crawl (blueprint M1 at scale, M8) | **NOT_STARTED** | staging exercises with a real catalogue, not unit tests |

## 3. The Persian claim, restated

Because the product is sold to Persian-language markets, the claim is worth stating precisely:

* **Translated:** the front end, the admin surfaces, the block editor and the content the WP-CLI commands
  create — 215/215 strings across both catalogues, compiled `.mo` files in the packages.
* **Persian dates:** Jalali (Shamsi) with Persian numerals on every front-end date of an `fa*` site, from a
  converter that is anchored against published Nowruz dates and round-tripped over 14 610 days
  (`tests/test-jalali.php`); machine surfaces (`<time datetime>`, schema.org, feeds, REST, admin) stay
  Gregorian on purpose.
* **Iranian defaults:** `fa_IR`, `Asia/Tehran`, week starting Saturday, `j F Y` dates, 24-hour time, a
  Persian demo site from one command.
* **Typeface:** Vazirmatn (Iranian, OFL-1.1) ships in the theme — no font CDN, no third-party request.
* **Not translated, deliberately:** WP-CLI operator output and migration reports are English, because an
  operator greps them and compares reports between sites (`docs/PERSIAN-LOCALIZATION.md`).

## 4. Install path a customer follows

```
1. Plugins → Add New → Upload → wavira-core-0.11.0.zip → Activate
2. Appearance → Themes → Add New → Upload → wavira-theme-0.11.0.zip → Activate
3. Settings → Permalinks → save a pretty structure      (the archives need /artists/…, /albums/…)
4. wp wavira seed --force                               (optional Persian demo content)
5. wp wavira migrate --detect / --dry-run / …            (optional, for an existing legacy site)
```

## 5. Support surface at release

| Surface | Where |
| --- | --- |
| Buyer documentation (Persian) | `docs/fa/USER-GUIDE.md` — ships as `README-FIRST/fa/USER-GUIDE.md` in the bundle |
| Install note (Persian) | generated into the bundle at `README-FIRST/fa/INSTALL-AND-START.md` |
| Technical documentation | `README.md`, `docs/ARCHITECTURE.md`, `docs/DATA-MODEL.md`, `docs/MIGRATION-BLUEPRINT.md` |
| In-product verification | `wp wavira verify` (post types, taxonomies, 46 meta keys, settings, REST routes, counters) |

## 6. Pre-upload checklist (the blocking items)

| # | Item | State | What it needs |
| --- | --- | --- | --- |
| 1 | `wavira/screenshot.png` (1200×900, theme preview) | **MISSING** | a rendered site screenshot. The build environment has no browser (`storage.googleapis.com` is unreachable, no Chrome/Firefox/WebKit, no headless renderer), so this cannot be generated here — it must be captured from a real render. `node tools/package.mjs --strict` fails while it is missing, on purpose |
| 2 | Marketplace listing copy (title, description, feature bullets, FAQ) | **PARTIAL** | `wavira/readme.txt` is written for a WordPress-style listing; the marketplace-specific fields (price, category, demo URL, support terms) need the account |
| 3 | Demo site for reviewers | **NOT_STARTED** | one command (`wp wavira seed --force`) produces it; it needs a host |
| 4 | Real-browser audit (Lighthouse ≥ 90, axe clean, RTL/LTR and dark/light screenshots) | **NOT_STARTED** | a browser and a live install; the static gates already enforce the budgets the audit measures |
| 5 | `Tested up to` in the headers | **NEEDS_REVIEW** | CI installs `latest`; a run after 0.11.0 annotates the exact WordPress version, and the header should be set from that annotation |
| 6 | Trademark clearance | **LEGAL_REVIEW_REQUIRED** | preliminary screening only (`docs/BRAND-DECISION.md`) — professional clearance is still recommended |

## 7. Known limitations (stated rather than hidden)

* No DRM, no streaming service, no marketplace: the product publishes music on the customer's own site.
* The migration tool is CLI-only by design (ADR 0018 §7): no admin page in v1.
* Elementor integration is deferred (`docs/REBUILD-PLAN.md`); Gutenberg blocks are the supported editor
  path in v1.
* Multisite and theme-switch behaviour are **NOT_STARTED** as tests, though the theme/plugin split is
  designed for the switch (`docs/ARCHITECTURE.md`).
* The demo content is generated text and gradient placeholders — deliberately, so nothing of unclear
  provenance ships (ADR 0010).

## 8. Release procedure

```bash
# 1. Green gates
PATH="$PHP_BIN:$PATH" bash tools/lint.sh          # includes [PACKAGE]

# 2. Built assets (the packages ship the compiled CSS/JS)
node tools/build.mjs

# 3. The packages, deterministically
SOURCE_DATE_EPOCH=1767225600 node tools/package.mjs

# 4. The release-only requirements (currently: screenshot.png)
node tools/package.mjs --check --strict

# 5. Verify what was built
unzip -t dist/*.zip
sha256sum -c dist/SHA256SUMS                  # from inside dist/
```

CI runs steps 1–3 on every push and uploads the archives as the run artefact
`wavira-0.11.0-packages`, so a package can always be traced to the commit it was built from
(`manifest.json` → `built_from`).
