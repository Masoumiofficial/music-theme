# DECISIONS.md — Architecture Decision Records (index)

ADRs are immutable once accepted: to change a decision, add a new ADR that supersedes the old one and
update this index. Format: `docs/adr/NNNN-title.md`.

| ADR | Decision | Status | Date |
| --- | --- | --- | --- |
| [0001](adr/0001-product-scope-and-non-goals.md) | Product scope: music **publishing** ecosystem, not streaming/SaaS; explicit non-goals for v1 | Accepted | 2026-10-05 |
| [0002](adr/0002-theme-vs-core-plugin-split.md) | Persistent music data + business logic live in the **Wavira Core** plugin; the theme is presentation only | Accepted | 2026-10-05 |
| [0003](adr/0003-custom-post-types-over-post-meta.md) | Replace legacy `post` + `musics_type` meta with real CPTs (`artist`, `album`, `track`, `video`) + taxonomies | Accepted | 2026-10-05 |
| [0004](adr/0004-no-acf-no-optiontree.md) | No ACF and no OptionTree dependency; use registered meta + Settings API + `theme.json` | Accepted | 2026-10-05 |
| [0005](adr/0005-player-engine-design.md) | Instance-based, DOM-independent Player Engine; no global `#audio`; Media Session + full keyboard support | Accepted (implementation phase 0.5.0) | 2026-10-05 |
| [0006](adr/0006-asset-strategy-and-tooling.md) | Vanilla ES modules, no front-end jQuery, build-aware conditional assets; no Node build required to run the plugin | Accepted | 2026-10-05 |
| [0007](adr/0007-php-and-wordpress-baseline.md) | PHP 7.4 floor (tested on 8.2/8.3), WordPress 6.6+ floor, autoloader without a Composer runtime requirement | Accepted | 2026-10-05 |
| [0008](adr/0008-i18n-and-rtl-first.md) | English source strings, RTL-first with full LTR parity, logical CSS properties, one text domain per artifact | Accepted | 2026-10-05 |
| [0009](adr/0009-accessibility-and-performance-gates.md) | WCAG 2.2 AA and the performance budget are merge gates, not follow-up work | Accepted | 2026-10-05 |
| [0010](adr/0010-licensing-and-third-party-policy.md) | Ship only GPL-compatible/OFL/own assets; exclude all legacy encrypted files and unclear-provenance media | Accepted | 2026-10-05 |
| [0011](adr/0011-slugs-and-permalinks.md) | Permalinks `/artists/ /albums/ /tracks/ /videos/ /genres/`, legacy post slugs preserved, 301 map for `/singer/*` and artist tags | Accepted (owner-approved 2026-10-05) | 2026-10-05 |
| [0012](adr/0012-relation-storage.md) | Relations are post IDs in registered meta (artist CPT + role-aware meta), **not** a shared taxonomy; no free-text credits | Accepted | 2026-10-05 |

**Resolved open decisions** (were listed as "scheduled" in 0.2.0)

| Topic | Resolution | Where |
| --- | --- | --- |
| Permalink slugs and the 301 map | `/artists/ /albums/ /tracks/ /videos/ /genres/`; legacy slugs preserved; redirects specified | ADR 0011 |
| Relation storage (meta IDs vs. shared taxonomy) | Post IDs in registered meta, role-aware; internal index allowed later behind the service API | ADR 0012 |

**Still open (scheduled)**

| Topic | Phase | Note |
| --- | --- | --- |
| Own view counter vs. integration with popular plugins | 0.4.0 | default: own lightweight counter, plugin bridge optional |
| REST caching strategy (transient vs. object cache vs. HTTP cache headers) | 0.4.0 | must respect page cache and CDN |
| Elementor: widgets vs. dynamic tags only | 0.7.0 | depends on marketplace demand |
| Update server (self-hosted vs. marketplace-native) | 0.9.0 | affects licence/update ADR |
| Localised slug bases for fa_IR (`/خواننده/` …) | 0.6.0 | supported via `wavira_rewrite_slugs` filter (ADR 0011 §5); decision = ship English default, document the filter |

**Attribution decision (product owner, 2026-10-05)**

| Topic | Decision |
| --- | --- |
| Designer & author of the theme/plugin | **Etehad WP — اتحاد وردپرس** · <https://etehadwp.com/> — applied to theme `style.css`, plugin header, `composer.json`, `package.json`, `LICENSE.md`, README credits and `BRAND-DECISION.md` |
