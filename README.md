# Wavira — WordPress music-publishing ecosystem

> **Status: 0.2.0 (architecture phase).** No user-facing product code exists yet — this repository
> currently contains the completed forensic audit, the locked brand decision, the architecture
> (ADRs) and the code skeleton. Development proceeds phase by phase per `docs/REBUILD-PLAN.md`.

**Wavira** is a premium WordPress product for publishing and discovering music: artists, albums,
tracks, music videos, genres, lyrics, multi-quality downloads and a first-class player — RTL-first with
full LTR support, dark/light, Gutenberg and Elementor ready, built for performance and WCAG 2.2 AA.

## Repository layout

| Path | What it is |
| --- | --- |
| `music-theme.zip` | **Legacy artifact, read-only.** The original ionCube-protected theme that was audited. Never modified, never shipped. |
| `docs/` | Audit reports, brand decision, architecture, coding standard, ADRs. Start at `docs/EXECUTIVE-SUMMARY.md`. |
| `wavira/` | **Wavira Music** theme (presentation layer) — skeleton in place, templates arrive in 0.6.0. |
| `wavira-core/` | **Wavira Core** plugin (all music data + business logic) — skeleton in place, content model arrives in 0.3.0. |
| `tools/` | Development tooling: `lint.sh`, asset build scripts. |
| `dist/` | Release packages (generated; never committed with binaries). |

## The two-artifact model

```
Wavira Music (theme)        →  how it looks
Wavira Core  (plugin)       →  what it stores and does  (artists, albums, tracks, videos, genres,
                              settings, player engine, downloads, REST API wavira/v1)
```

Switch themes at any time: the music catalogue, settings and player configuration live in the plugin
and survive. Deactivate the plugin and the theme still renders a clean post/page site.

## Documentation map

| Document | Purpose |
| --- | --- |
| `docs/EXECUTIVE-SUMMARY.md` | The five decisions that shape the product |
| `docs/PROJECT-AUDIT.md` | What the legacy artifact contains (97 files, hashed, classified) |
| `docs/FEATURE-MAP.md` | Every legacy feature, re-specified with priority P0–P3 |
| `docs/ARCHITECTURE.md` | **Target architecture** (binding) |
| `docs/CODING-STANDARD.md` | Enforceable rules (PHP/JS/CSS/i18n/security/perf) |
| `docs/DECISIONS.md` + `docs/adr/` | Architecture decision records |
| `docs/DATA-MODEL.md` | **Authoritative music data model** (0.3.0) |
| `docs/VERIFICATION.md` | **Per-claim evidence log** (what is VERIFIED vs. still open) |
| `docs/MIGRATION-BLUEPRINT.md` | Legacy → Wavira data migration plan |
| `docs/REBUILD-PLAN.md` | Phases 0.1.0 → 1.0.0 with exit criteria |
| `docs/TECH-DEBT.md` | 35 legacy debt items and their disposition |
| `docs/BRAND-RESEARCH.md`, `docs/BRAND-DECISION.md` | Brand evidence + Brand Lock (Wavira) |

## Development quick start

```bash
# 1. Requirements: PHP 7.4+ (or none — see below), Node 18+ for asset tooling only.

# 2. Lint everything (PHP syntax, JS syntax, JSON, legacy-echo gate, asset sizes)
bash tools/lint.sh

# 3. Build front-end assets (CSS/JS) when working on the UI
node tools/build.mjs

# 4. Install for local WordPress testing
#    - copy (or symlink) wavira/      → wp-content/themes/wavira
#    - copy (or symlink) wavira-core/ → wp-content/plugins/wavira-core
```

The product **does not require a build to be installable**: when `assets/dist/` is missing the theme
enqueues nothing and the front end degrades gracefully (see ADR 0006).

## Contributing rules in one paragraph

Escape on output, sanitize on input, nonce + capability on every mutation; no front-end jQuery; no
unbounded queries; no UA sniffing; logical CSS properties only; every string translatable; WCAG 2.2 AA
and the performance budget are merge gates; new dependencies need a licence note. The full list is in
`docs/CODING-STANDARD.md`.

## Licence

GPL-2.0-or-later for the product (theme + plugin). Third-party components and their licences are listed
in `THIRD-PARTY-NOTICES.md`. The legacy artifact in this repository is **not** part of the product and
is excluded from every package for licensing reasons (`docs/LICENSE-AUDIT.md`).

## Credits

| Role | Name |
| --- | --- |
| Designer & Author (طراح و نویسنده قالب) | **Etehad WP — اتحاد وردپرس** · <https://etehadwp.com/> |
| Product & documentation | Etehad WP product team |

## Brand notice

Preliminary name screening only — professional trademark clearance is still recommended before
commercial launch. See `docs/BRAND-DECISION.md`.
