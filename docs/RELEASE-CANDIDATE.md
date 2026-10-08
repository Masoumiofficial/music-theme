# RELEASE-CANDIDATE.md — Wavira 0.13.0 (RC)

> **Status: release candidate.** 0.13.0 makes the settings panel findable: **Appearance → Wavira
> settings**, a screen of its own built from the same schema as the Customizer panel (ADR 0020's
> amendment), and it fixes the two defects a real installation reported — a Persian-notice screen id that
> named a screen the plugin does not register, and two faults in the new screen's script. §0.2 is this
> increment; §0.1 is the record of 0.12.0; everything below that describes the 0.11.0 candidate. The
> numbers in §1 belong to the current release.

## 0.2 Added in 0.13.0 — the settings screen, and the two defects behind the report

| Addition | Why it mattered | Where | Tests |
| --- | --- | --- | --- |
| **Appearance → Wavira settings** — the same 32 settings, one tab per Customizer section, plus a demo tab last | 0.12.0's panel existed, but the report from a real installation was "there is no settings panel". On a **block theme** WordPress prints the Appearance → Customize link only when something hooks `customize_register`; Wavira does, so the link is there — but a site owner looking for a screen of its own does not necessarily read "Customize" as it, and a panel nobody finds is a panel that does not exist | `inc/admin-panel.php`, ADR 0020's amendment | `tests/test-admin-panel.php` (14 cases) |
| **One schema, two doors** — tabs from `wavira_customize_sections()`, fields from `wavira_options_schema()`, values through `wavira_sanitize_option()`, storage in the same `theme_mod`s | a second panel that drifts from the first *is* two panels, and the drift is invisible until a customer finds it | `wavira_admin_panel_values()`, `wavira_admin_panel_apply()`, `wavira_admin_panel_save()` | `test_the_tabs_are_the_sections_plus_the_demo_tab`, `test_saving_one_tab_leaves_the_others_alone`, `test_the_screen_refuses_what_the_customizer_refuses`, `test_a_default_value_is_not_stored`, `test_an_unchecked_box_whose_default_is_on_is_stored_as_off` |
| **A demo tab that never offers a button which fails** | "the demo import was not there at all" in the report: it lives in the plugin under **Tools** | `wavira_admin_panel_demo_tab()` | `test_the_demo_tab_points_at_the_plugin_when_it_is_active` — both states: `tools.php?page=wavira-demo` with the plugin, `plugin-install.php?tab=upload` without it |
| **`assets/js/admin.js`** — colour swatch ↔ text field, a `wp.media` picker for the two logo fields, an explicit clear, no jQuery | a settings screen has to feel like a WordPress screen, and the value the form posts is the text field | built to `assets/dist/admin.js`, enqueued on this screen only, with `wp_enqueue_media()` | `tests/js/admin.test.mjs` (9 cases, stub DOM) |
| **Fixed: the Persian notice whitelisted a screen that does not exist** | the plugin registers the demo screen under Tools, so its id is `tools_page_wavira-demo`; 0.12.0 checked `appearance_page_wavira-demo`, which is why the notice described a screen and then never appeared on it | `wavira_persian_notice_screens()` in `inc/site-defaults.php` | `test_the_persian_notice_is_shown_on_the_settings_screen` (asserts both real ids and that the 0.12.0 guess is *not* in the list) |
| **Fixed: two defects in the screen's script** | `renderPreview()` read `model.toJSON` before checking the model, so clearing an empty image field raised a TypeError; the colour text field moved the swatch for any string at all, typo included | `assets/js/admin.js` | the two `tests/js/admin.test.mjs` cases that failed on the first run of the new suite |
| **Fixed: `tools/po-merge.py` could not round-trip a catalogue** | a plural entry has no `msgstr ""` line, so the merge read it as untranslated and refused to write the plugin's catalogue; the `msgid ""` header was read as an entry and moved into the body; `re.sub()` read the `\n` of a header value as a newline and split the field it was rewriting | `tools/po-merge.py` | both catalogues now merge with no diff beyond the dates and `Project-Id-Version` |
| **The catalogue stayed complete** | 33 new strings arrived with the screen | `wavira/languages/*` | `node tools/i18n.mjs check` → **374/374, POT/PO/MO in sync** (theme 224, plugin 150) |

**Verification of this increment** — `bash tools/lint.sh` → **RESULT: PASS** (PHPCS with the pinned
standards, 93 PHP files parsed, CSS/contrast/perf/boundaries/legacy gates), `npm run test:js` → **54/54**,
`node tools/i18n.mjs check` → **374/374**, `node tools/package.mjs` → three archives, rebuild
byte-identical; the WordPress integration job of CI run `37775907971` ran the 14 new PHP cases against
a real WordPress on both PHP legs → **OK (198 tests, 2382 assertions)**. Digests of the archives this tree
produces with CI's own fixed timestamp (see §8):

| Archive | Bytes | SHA-256 |
| --- | --- | --- |
| `wavira-theme-0.13.0.zip` | 286 008 | `278ee95cad4119ef9e7ce9973781e225e2f28e6bf0857c3d4103e8a19bc35375` |
| `wavira-core-0.13.0.zip` | 180 036 | `8c35d2c6e230f8e6b6303d15af1795ec006d6e7a949e2125bebf55c6ef9501db` |
| `wavira-0.13.0-bundle.zip` | 499 146 | `f44a87b82046824118bd0ba481412baea2cc5789a8beeddba0ea8d0c78a29f22` |

The theme and the plugin archives are also committed in the repository root, byte-identical to the release
assets, so a link keeps working even where a release asset does not.

## 0.1 Added in 0.12.0 — the settings panel and the Persian setup

| Addition | Why it mattered | Where | Tests |
| --- | --- | --- | --- |
| **The Wavira settings panel** — 32 settings in eight sections (language and Persian setup, identity and logo, header, appearance, fonts, social networks, texts and footer, tools) | "a professional theme settings panel": logo, colours, fonts, widths, social links and footer text, editable without touching code, with a live preview | `inc/options.php` (the schema), `inc/customizer.php` (the panel), ADR 0020 | `tests/test-theme-options.php` (the schema, the sanitizers, the Customizer registration) |
| **Values as CSS variables and body classes** | a setting reaches every block — including blocks added later — without `!important` and without a second source of truth; a site that changes nothing ships no extra CSS at all | `wavira_option_css()`, `wavira_option_body_classes()`, `assets/css/components.css` | `test_settings_reach_the_front_end`, `test_a_default_site_gets_no_extra_css` |
| **Live preview** (`assets/js/customizer.js`) | the preview shows the change while the control moves; fields that change markup refresh instead of pretending | built to `assets/dist/customizer.js`, loaded only in the Customizer frame | `tests/js/customizer.test.mjs` (12 tests) + `test_the_preview_list_matches_the_schema` |
| **One-click Persian setup** | the theme's own screens are Persian already, but WordPress is not until the *site* language changes: one nonced action installs the core language pack first — WordPress refuses to store a locale it has no translation for — then sets the language, the timezone, the week start and the date format, and reports what it could not do | `inc/site-defaults.php`, `wavira_core_apply_persian_defaults()` in the plugin, an admin notice on Appearance → Themes | `test_persian_setup_is_a_guarded_admin_action`, `test_the_language_pack_check_is_a_file_question` |
| **Demo import, one click away** | a buyer who never opens a terminal still needs the demo content; the panel links straight to `Tools → Wavira demo content` | `wavira_customize_site_description()` | the link target is asserted in the panel-description code path |
| **Five social glyphs, a back-to-top button, an announcement bar, a dark-mode logo** | the surfaces a music site actually fills in on day one | `assets/icons/`, `patterns/hidden-*.php`, `wavira_custom_logo()` | `test_optional_surfaces_render_only_when_they_are_on`, `test_the_dark_logo_is_added_beside_core_markup` |
| **The catalogue stayed complete** | 100 new strings arrived with the panel and the Persian setup; a half-Persian panel is worse than none | `wavira/languages/*`, `tools/po-merge.py` | `node tools/i18n.mjs check` → **341/341, POT/PO/MO in sync** (theme 191, plugin 150) |

**Verification of this increment** — `bash tools/lint.sh` → **RESULT: PASS** (PHPCS with the pinned
standards, CSS/contrast/perf/boundaries/legacy gates), `npm run test:js` → **45/45**,
`node tools/i18n.mjs check` → **341/341**, `node tools/package.mjs` → three archives, rebuild
byte-identical, and CI green on the tagged commit (below). Digests of the archives this tree
produces with CI's own fixed timestamp (see §8):

| Archive | Bytes | SHA-256 |
| --- | --- | --- |
| `wavira-theme-0.12.0.zip` | 266 921 | `889fa7c36d649b824ec02f7008594a9452c2bb9ae13302bd59eeea3cd0f5ab19` |
| `wavira-core-0.12.0.zip` | 179 841 | `d8602a7f9643a76282175a797eca4229105938c3707ac257e317ed6c7dde7035` |
| `wavira-0.12.0-bundle.zip` | 479 864 | `11dfd749138777ef009338fd82dd12f255c73c44a0981d2321299f0982c75f82` |

The candidate is published: tag `v0.12.0-rc` → `b804ae2`; run `37760791865` (push on the tag, the
`Release assets` job included) and run `37760785528` (push on the branch) are both green, and the release
carries the five files whose digests are listed above. GitHub's own SHA-256 for the three archives is identical to
`dist/SHA256SUMS`; `manifest.json` differs by design, because the runner records the commit it built
from (`"built_from": "b804ae2…"`) where a local build says `"working tree"`.

The remaining item before a marketplace upload is the same one 0.11.0 recorded (§6): `screenshot.png`. It
needs a rendered page and a browser, and the authoring environment has neither — the file is `optional`
in `tools/package.mjs`, so `--check` passes and `--strict` reports it. Everything else in the panel, the
Persian setup and the catalogue is machine-verified above.

---

> The sections below were written for the 0.11.0 candidate and are kept as that release's record.

## 0. Added after the release-candidate verdict

The RC was verified green (`37618881223`/`37618887341`) and then three gaps that a Persian buyer would
have hit on the first day were closed, still inside 0.11.0 — no version bump, because nothing that was
verified changed, only things that were missing were added:

| Addition | Why it was not optional | Where | Tests |
| --- | --- | --- | --- |
| **Publishing-plugin interop** (`--source=music-publisher`) | the ecosystem's other publishing tool writes `post` + `musics_type` with its own vocabulary; a site built with it could not convert its catalogue | `Migration\LegacySchema` source profiles, `Migrator::source()`, `CLI --source`; `docs/INTEGRATIONS.md` | `tests/test-migration.php` (I1–I8) |
| **`wavira_kind` taxonomy** | a remix, a noha and a podcast episode are all audio; without a kind they arrive indistinguishable from a song | `Content\Taxonomies::KIND`, `/kinds/`, `taxonomy-wavira_kind.html` | `tests/test-content-registration.php`, `test_publisher_kinds_normalise_to_kind_terms` |
| **Demo import without WP-CLI** | most buyers never open a terminal; a demo only a developer can install is a demo most customers never see | `Demo\Fixtures`, `Demo\Installer`, `Admin\DemoPage` (`Tools → Wavira demo content`), `wp wavira export-demo` | `tests/test-demo.php` |

The demo now also ships a remix of one of its tracks, so the kind feature is visible on a fresh
install. The CI verdict for these additions is `37625734821` (push) / `37625726155` (PR) on `taef48bd`:
**9/9 jobs green**, `OK (167 tests, 1435 assertions)` on WordPress 7.1.3 with PHP 7.4 and 8.2, WPCS
0 findings, archives built and uploaded (the CI-built archives are byte-identical to the local ones:
theme `619ebbabcbd5…`, plugin `075704d678af…`, bundle `57e953f2b79d…`) — the detail is in
`docs/VERIFICATION.md` under "after the RC verdict". The RC table below keeps the verdict of the code it was recorded against.

| | |
| --- | --- |
| Product | **Wavira** — music-publishing ecosystem for WordPress (theme + core plugin) |
| Version | `0.12.0` (release candidate; `1.0.0` is the production release) |
| Author | Etehad WP (اتحاد وردپرس) — https://etehadwp.com/ |
| Licence | GPL-2.0-or-later (bundled third-party: Vazirmatn, SIL OFL 1.1; Jalali algorithm, MIT) |
| Requires | WordPress 6.6+ (policy floor), PHP 7.4+ |
| Tested on | WordPress **7.1.3** (CI runs `37618881223`/`37618887341`, annotated from the installed core) and 6.7.2 (local lab); PHP **7.4 / 8.2 / 8.3** (CI syntax + integration) |
| Packages | `dist/wavira-theme-0.12.0.zip`, `dist/wavira-core-0.12.0.zip`, `dist/wavira-0.12.0-bundle.zip` |
| Build | `node tools/build.mjs && node tools/package.mjs` (no dependencies, no bundler, deterministic) |

---

## 1. What ships

| Package | Size | Contents |
| --- | --- | --- |
| `wavira-core-0.12.0.zip` | 176 KB | The plugin: content model, REST API, player engine, downloads, SEO data, admin surfaces, Persian catalogue, migration tool, `wavira_core_apply_persian_defaults()` |
| `wavira-theme-0.12.0.zip` | 260 KB | The theme: templates, patterns, blocks, the options panel and its live preview, compiled CSS/JS, Vazirmatn with its licence text, Persian catalogue |
| `wavira-0.12.0-bundle.zip` | 468 KB | Both packages plus `README-FIRST/` (install note, user guide, licences, localisation and migration notes) |
| `dist/manifest.json` | — | Every file in every archive with its size and SHA-256, so a reviewer can verify what was delivered |
| `dist/SHA256SUMS` | — | The three archive digests |

`dist/` is generated, never committed (`.gitignore`), and rebuilt identically from the tag: entry order
sorted, timestamps from `SOURCE_DATE_EPOCH`, permissions normalised, and `tools/package.mjs` rebuilds one
archive on the spot and compares bytes before it reports success.

## 2. What was verified, and how

Full evidence lives in `docs/VERIFICATION.md`; this is the release view.

| Area | Verdict | Evidence |
| --- | --- | --- |
| The whole suite on the release commit | **VERIFIED** | CI `37618881223` (push) / `37618887341` (PR): **9/9 jobs green**, `OK (146 tests, 1198 assertions)` on WordPress 7.1.3 with PHP 7.4 and 8.2, WPCS 0 findings, and the packages built and uploaded |
| Data model, REST, player, downloads, search, SEO, Persian/Jalali, artist & news pages | **VERIFIED** | CI runs `37438117893` (0.10.1) and earlier — 8/8 jobs, `OK (134 tests, 1090 assertions)` on PHP 7.4 + 8.2 |
| Legacy migration tool | **VERIFIED** | CI `37440743623` / `37440749435`, and the whole suite re-ran green on WordPress 7.1.3 in `37618881223` (`OK (146 tests, 1198 assertions)`) — `OK (146 tests, 1198 assertions)` on PHP 7.4 + 8.2, after the tool was exercised against a real WordPress in a local lab (43 + 20 checks) |
| The shipped archives install and run | **TESTED** (local WordPress 6.7.2 + SQLite lab); CI builds the same archives on every push and uploads them, and their SHA-256 matched the local build byte for byte (`0e9419e2e524…` theme, `f87b0c14c2a4…` plugin, `071f653e9ab6…` bundle) | unzip → activate → switch on the real archives: 13 checks (WordPress recognises theme and plugin, versions match the headers, built assets, `.mo` catalogue, font licence and test suite placement) + 17 checks on the next request (post types, taxonomy, 46 meta keys, `@font-face` resolved to the packaged file and present in the rendered head, `font-display: swap`, preload, no `s.w.org` hint, player bundle present) |
| Static gates | **PASS** | `tools/lint.sh`: PHP syntax, WPCS + PHPCompatibilityWP 0 errors/0 warnings over 82 files, JS/JSON, blocks, i18n 215/215, migration mapping, CSS rules, WCAG contrast pairs, performance budgets, legacy-echo gate, module boundaries, `[PACKAGE]` |
| Deterministic packaging across machines | **VERIFIED** | CI `37618881223` (push) / `37618887341` (PR), job *Release packages*: 9/9 jobs green with the CI-built digests identical to the local ones above; the tool also rebuilds one archive in-process and compares bytes |
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
4. Tools → Wavira demo content → Import demo content    (optional Persian demo, no terminal needed)
   or: wp wavira seed                                   (the same installer, from WP-CLI)
5. wp wavira migrate --detect / --dry-run / …            (optional, for an existing site)
   add --source=music-publisher for a site built with that plugin (docs/INTEGRATIONS.md)
6. Tools → Wavira demo content → Download WXR file       (move the content to another install)
```

## 5. Support surface at release

| Surface | Where |
| --- | --- |
| Buyer documentation (Persian) | `docs/fa/USER-GUIDE.md` — ships as `README-FIRST/fa/USER-GUIDE.md` in the bundle |
| Install note (Persian) | generated into the bundle at `README-FIRST/fa/INSTALL-AND-START.md` |
| Technical documentation | `README.md`, `docs/ARCHITECTURE.md`, `docs/DATA-MODEL.md`, `docs/MIGRATION-BLUEPRINT.md` |
| In-product verification | `wp wavira verify` (post types, taxonomies, 46 meta keys, settings, REST routes, counters) |
| Demo import (no terminal) | `Tools → Wavira demo content` — import the Persian demo, or download the content as WXR |
| Interop contract | `docs/INTEGRATIONS.md` — what the publishing plugin writes, what converts, what is preserved instead |

## 6. Pre-upload checklist (the blocking items)

| # | Item | State | What it needs |
| --- | --- | --- | --- |
| 1 | `wavira/screenshot.png` (1200×900, theme preview) | **MISSING** | a rendered site screenshot. The build environment has no browser (`storage.googleapis.com` is unreachable, no Chrome/Firefox/WebKit, no headless renderer), so this cannot be generated here — it must be captured from a real render. `node tools/package.mjs --strict` fails while it is missing, on purpose |
| 2 | Marketplace listing copy (title, description, feature bullets, FAQ) | **PARTIAL** | `wavira/readme.txt` is written for a WordPress-style listing; the marketplace-specific fields (price, category, demo URL, support terms) need the account |
| 3 | Demo site for reviewers | **NOT_STARTED** | one command (`wp wavira seed --force`) produces it; it needs a host |
| 4 | Real-browser audit (Lighthouse ≥ 90, axe clean, RTL/LTR and dark/light screenshots) | **NOT_STARTED** | a browser and a live install; the static gates already enforce the budgets the audit measures |
| 5 | `Tested up to` in the headers | **DONE** (upper bound) | CI now annotates the version it installed: **WordPress 7.1.3** (runs `37618881223`/`37618887341`), so the headers say `Tested up to: 7.1`. The **floor** is still a policy floor, not a tested one: 6.6 is what the code requires by decision (ADR 0007) and 6.7.2 is the oldest version this phase actually ran the product on (`docs/VERIFICATION.md`) |
| 6 | Trademark clearance | **LEGAL_REVIEW_REQUIRED** | preliminary screening only (`docs/BRAND-DECISION.md`) — professional clearance is still recommended |

## 7. Known limitations (stated rather than hidden)

* No DRM, no streaming service, no marketplace: the product publishes music on the customer's own site.
* The **migration** tool is CLI-only by design (ADR 0018 §7): no admin page in v1. (The **demo**
  importer has one, because it installs fixtures rather than converting a customer's live content.)
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

## 9. Where a customer downloads it from

The stable, public URL for a version is its GitHub release, not the build machine:

| Artifact | Link that works today (tag-pinned) |
| --- | --- |
| Theme | `https://github.com/Masoumiofficial/music-theme/releases/download/v0.13.0-rc/wavira-theme-0.13.0.zip` |
| Core plugin | `https://github.com/Masoumiofficial/music-theme/releases/download/v0.13.0-rc/wavira-core-0.13.0.zip` |
| Both, with `README-FIRST/` | `https://github.com/Masoumiofficial/music-theme/releases/download/v0.13.0-rc/wavira-0.13.0-bundle.zip` |
| The release page (notes, digests, all five assets) | `https://github.com/Masoumiofficial/music-theme/releases/tag/v0.13.0-rc` |
| The previous candidate, still downloadable | `https://github.com/Masoumiofficial/music-theme/releases/tag/v0.12.0-rc` |
| All releases | `https://github.com/Masoumiofficial/music-theme/releases` |

The `releases/latest/download/<file>` shorthand names only *published*, non-prerelease releases, and both
candidates are marked **prerelease** — checked on 2026-10-08: the `latest` forms still answer `404` and
start working when `1.0.0` is published without the flag. Use the tag-pinned links above until then.

The `release-assets` job (`.github/workflows/ci.yml`) attaches the archives whenever a `v*` tag is
pushed: it runs the same `build` → `package` pair with the same `SOURCE_DATE_EPOCH`, so the attached
bytes are the ones the other jobs verified. It then reads the release's asset list back and fails with an
`::error::` naming any file that did not attach — the first tag run of this candidate reported a
successful upload and left the release with nothing on it, and a release page with no files on it looks
fine until someone clicks a link. An upload is a `POST` to `uploads.github.com`, which is
why it happens on a runner rather than from a sandbox that cannot reach that host. `--prerelease`
releases are excluded from `releases/latest`, so those links only resolve once `1.0.0` is published
without the flag.
