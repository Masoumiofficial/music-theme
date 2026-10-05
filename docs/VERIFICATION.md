# VERIFICATION.md — evidence log

Every claim the project makes about itself is backed by an entry here. Nothing is
"done" because it was written; it is done when the row below shows evidence.

**Status vocabulary** — `NOT_STARTED` · `IN_PROGRESS` · `IMPLEMENTED` (code exists,
no evidence yet) · `TESTED` (executed somewhere, result recorded) · `VERIFIED`
(executed **and** the recorded result passes on the declared platform) ·
`BLOCKED` · `NEEDS_REVIEW`.

Rule: static analysis never substitutes for runtime testing, and CI syntax checks
never substitute for a real WordPress install.

---

## Environments used

| Ref | Environment | Available since | What it can prove |
| --- | --- | --- | --- |
| `CI` | GitHub Actions `ubuntu-latest`: PHP 7.4 / 8.2 / 8.3, WPCS 3.x + PHPCompatibilityWP (testVersion `7.4-`), Node 20 | commit `423c020` | PHP syntax, WordPress Coding Standards, i18n domains, PHP compatibility floor, JS/JSON syntax, build dry-run, legacy artifact hash |
| `LOCAL-AUTHORING` | Sandbox without PHP/Composer (Node 22, Python 3.11 only) | — | JS/JSON lint, grep gates, structural cross-reference checks, array/equals alignment approximation — **never** PHP syntax or PHP behaviour |
| `WP-CI` | GitHub Actions `wp-integration` job: WordPress (latest) + MariaDB 10.11 + the WordPress test library, PHP 7.4 and 8.2, `composer test` | commit `d5e0f6e`, first green run `37301535701`; latest verified run `37301909854` | CPT/taxonomy/meta **registration**, settings persistence and clamping, REST dispatch (routes, headers, args), download authorization and counters, search/related services, cache invalidation — everything the 49 integration tests cover |
| `WP-RUNTIME` | A real WordPress site, owner-provided or a full WP install with WP-CLI | **not yet available** | Rewrite resolution after activation, `wp wavira verify/seed`, admin UI, real HTTP responses, front-end rendering, performance budgets |

---

## 0.3.0 — Music data model

| Claim | Status | Evidence |
| --- | --- | --- |
| All PHP files parse on the declared floor and above | **VERIFIED** | CI run on `e54e6a3…f00e6d1…1a5e1e0` (`php-lint` matrix): `php -l` passes on 7.4, 8.2, 8.3 |
| Code meets WordPress Coding Standards (Core/Docs/Extra) | **VERIFIED** | CI job `WPCS + PHP compatibility` → success, zero errors and zero warnings |
| Code stays compatible with PHP ≥ 7.4 | **VERIFIED** | same CI job, `PHPCompatibilityWP` with `testVersion=7.4-` |
| i18n text domains are `wavira` / `wavira-core` | **VERIFIED** | `WordPress.WP.I18n` configured with those domains; part of the passing WPCS job |
| Legacy `music-theme.zip` is byte-identical to the audit baseline | **VERIFIED** | CI job `Legacy artifact integrity`, md5 `a23269c2b92a3ba08721dba79a50f1dd` |
| JS/JSON assets parse; build dry-run has no missing sources | **VERIFIED** | CI job `JS, JSON, gates, build` → success |
| Post types, taxonomies and the 43 registered meta keys register correctly | **VERIFIED** | `WP-CI` run `37301909854` → `Test_Content_Registration` (post types, archive slugs, rewrite bases, `show_in_rest`, meta registry incl. the counters staying out of REST) |
| REST routes answer with the documented shapes and headers | **VERIFIED** | `WP-CI` run `37301909854` → `Test_Rest_Api` (collection pagination headers, `X-WP-Total(-Pages)`, `per_page` clamp, draft exclusion, search/suggest/related payloads) |
| Download authorization chain behaves as documented | **VERIFIED** | `WP-CI` run `37301909854` → `Test_Downloads::test_access_rules` + `test_forbidden_download_is_refused` (403/401 without leaking a URL) |
| Cache invalidation fires on the intended hooks | **VERIFIED** | `WP-CI` run `37301909854` → `Test_Cache` (content save, settings update incl. the first save, generation isolation) |
| Settings persist, clamp and drop unknown keys | **VERIFIED** | `WP-CI` run `37301909854` → `Test_Meta_Settings::test_settings_sanitizer_bounds_and_unknown_keys` |
| Rewrite rules resolve after activation (one-time flush) | **NOT_STARTED** | needs a real HTTP request against a permalink — `WP-RUNTIME` |
| `wp wavira verify` reports zero problems on a clean install | **NOT_STARTED** | needs WP-CLI on a real install (the CI job runs PHPUnit, not WP-CLI) — scheduled with the 0.9.0 packaging job |

Deliberate limitation recorded here: the authoring environment has no PHP, so
`NOT_STARTED` rows were **not** silently downgraded to "probably fine". They stay
open until they run on WordPress.

---

## 0.4.0 — Music engine and the verification harness

| Claim | Status | Evidence |
| --- | --- | --- |
| The integration suite (49 tests) passes on a real WordPress with PHP 7.4 | **VERIFIED** | CI run `37301909854`, job `WordPress integration (PHP 7.4)` → `OK (49 tests, 440 assertions)` |
| …and on PHP 8.2 | **VERIFIED** | same run, job `WordPress integration (PHP 8.2)` → `OK (49 tests, 440 assertions)` |
| The suite genuinely exercises the product (not a smoke test) | **VERIFIED** | it found six defects on its first two runs, all fixed and re-verified: double-counted first download, ignored per-track opt-out, ignored schema defaults for optional taxonomies, missing cache flush on the first settings save, `sanitize_title` returning the request object for an empty REST argument, and `absint()` flipping an out-of-range negative setting |
| Search ranks and paginates as documented | **VERIFIED** | `WP-CI` → `Test_Rest_Api::test_search_route`, `test_search_arguments_are_bounded`, `Test_Search_Related` |
| Related items are scored from genre/artist/album/featured and cached | **VERIFIED** | `WP-CI` → `Test_Search_Related` (scoring order, cache generation, filter seam) |
| Download counters increment atomically and exactly once per served request | **VERIFIED** | `WP-CI` → `Test_Downloads::test_counters_increment_and_read_back`, `test_download_endpoint_redirects_by_default` (`X-Wavira-Download-Count: 1`) |
| The public function API of the plugin behaves as documented | **VERIFIED** | `WP-CI` → `Test_Public_Api` (`wavira_core_is_active/get_setting/related_posts`, schema defaults, safe fallbacks) |
| The architecture's dependency rules hold in the code | **VERIFIED** | `tools/check-boundaries.mjs`, gate 6 of `tools/lint.sh`, passing locally and in the `JS, JSON, gates, build` job |
| PHPUnit harness runs locally as documented (`composer test`) | **IMPLEMENTED** | `composer.json` scripts + `phpunit.xml.dist` + `bin/install-wp-tests.sh`; executed on CI, not yet re-run by the owner locally |

---

## 0.2.0 — Architecture

| Claim | Status | Evidence |
| --- | --- | --- |
| CI enforces the gates above (plus module boundaries and the integration suite) | **VERIFIED** | `.github/workflows/ci.yml`: 8 jobs — `tools/lint.sh` (7 gates incl. `check-boundaries.mjs`), WPCS + PHP compatibility, PHP 7.4/8.2/8.3 syntax, legacy artifact hash, and `WordPress integration` on 7.4/8.2 |
| No legacy code, legacy echoes or jQuery in the new product | **VERIFIED** | `tools/lint.sh` gate 5 (grep) — passes |
| Coding standard rules marked 🔒 are machine-checked | **IMPLEMENTED** | partially: grep gates + WPCS in CI; the JS/CSS/CSP halves arrive with the assets |

## 0.1.0 — Forensic audit

| Claim | Status | Evidence |
| --- | --- | --- |
| 97 legacy files inspected; ionCube in exactly 2 of them | **VERIFIED** | `docs/PROJECT-AUDIT.md`, `docs/SECURITY-AUDIT.md` |
| No malicious/obfuscated code beyond the licensed ionCube loader | **VERIFIED** | hash inventory + pattern scan, `docs/SECURITY-AUDIT.md` S1–S10 |
| Legacy license situation documented | **VERIFIED** | `docs/LICENSE-AUDIT.md` L1–L19 |

---

## How to re-run everything

```bash
# full local gate (PHP parts skip when PHP is absent)
bash tools/lint.sh
node tools/build.mjs --check

# the authoritative static gate (needs PHP + Composer)
composer install
vendor/bin/phpcs --standard=phpcs.xml.dist -q

# the runtime gate (needs MySQL/MariaDB; downloads the WordPress test library)
composer test:install
composer test                # 49 tests: data model, settings, REST, downloads, search, related, cache, public API

# the full-site gate (needs a real install + WP-CLI/site owner)
wp plugin activate wavira-core
wp wavira verify
curl "$SITE/wp-json/wavira/v1/tracks?per_page=5"
```
