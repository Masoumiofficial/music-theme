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
| `WP-RUNTIME` | A real WordPress install (site owner or CI container) | **not yet available** | CPT/taxonomy/meta registration, REST responses, WP-CLI commands, admin UI, front-end rendering |

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
| Post types, taxonomies and the 40 registered meta keys register correctly | **NOT_STARTED** | requires `WP-RUNTIME` — `wp wavira verify` is the intended check |
| REST routes answer with the documented shapes and headers | **NOT_STARTED** | requires `WP-RUNTIME` |
| Download authorization chain behaves as documented | **NOT_STARTED** | requires `WP-RUNTIME` (unit tests planned with the test suite in 0.6.0) |
| Cache invalidation fires on the intended hooks | **NOT_STARTED** | requires `WP-RUNTIME` |
| Settings persist, clamp and drop unknown keys | **NOT_STARTED** | requires `WP-RUNTIME` |
| Rewrite rules resolve after activation (one-time flush) | **NOT_STARTED** | requires `WP-RUNTIME` |

Deliberate limitation recorded here: the authoring environment has no PHP, so
`NOT_STARTED` rows were **not** silently downgraded to "probably fine". They stay
open until they run on WordPress.

---

## 0.2.0 — Architecture

| Claim | Status | Evidence |
| --- | --- | --- |
| CI enforces the six gates above | **VERIFIED** | `.github/workflows/ci.yml`, passing on `423c020` and later |
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

# the runtime gate (needs WordPress + WP-CLI)
wp plugin activate wavira-core
wp wavira verify
curl "$SITE/wp-json/wavira/v1/tracks?per_page=5"
```
