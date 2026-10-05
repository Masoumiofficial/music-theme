# ADR 0011 — Slugs, permalinks and the legacy redirect map

- **Status:** Accepted
- **Date:** 2026-10-05
- **Phase:** 0.3.0 (implementation: post-type/taxonomy registration)
- **Related:** `MIGRATION-BLUEPRINT.md` §4, `SEO-AUDIT.md` E4/E9, `BRAND-DECISION.md`

## Context

The legacy site published music as ordinary posts (`/{postname}/`) with the artist taxonomy at
`/singer/{slug}/` and a second, competing artist URL family at `/tag/{slug}/`; styles/categories were
mixed. Slugs are hard to change later: they are baked into search results, backlinks, sitemaps and —
critically — into the migration tool that must preserve existing rankings.

The open decision ("permalinks and the 301 map") was resolved by the product owner on 2026-10-05:
keep legacy post slugs, adopt the proposed English entity slugs.

## Decision

1. **Entity permalinks (new URLs):**

| Entity | Slug | Example |
| --- | --- | --- |
| Artist CPT `wavira_artist` | `/artists/` (archive `/artists/`, single `/artists/{slug}/`) | `/artists/example-artist/` |
| Album CPT `wavira_album` | `/albums/` | `/albums/{slug}/` |
| Track CPT `wavira_track` | `/tracks/` | `/tracks/{slug}/` |
| Video CPT `wavira_video` | `/videos/` | `/videos/{slug}/` |
| Genre taxonomy `wavira_genre` | `/genres/` | `/genres/{slug}/` |
| Mood / Language / Label / Year | `/moods/`, `/languages/`, `/labels/`, `/years/` | `/{tax}/{slug}/` |

Registration rules: `with_front => false`, stable `rewrite['slug']`, `has_archive` for all four CPTs,
`show_in_rest => true` (REST base = `wavira/v1`), and slug strings defined in exactly one place
(`PostTypes`/`Taxonomies` constants) so nothing can drift.

2. **Legacy URLs are never broken.** The migration tool (phase 0.9.0) must produce a redirect map:

| Legacy | Target | Type |
| --- | --- | --- |
| `/{postname}/` (music post) | same slug on the migrated CPT | **preserve slug exactly** (highest SEO value) |
| `/singer/{slug}/` | `/artists/{slug}/` | 301 |
| `/tag/{artist-slug}/` (tag used as an artist) | `/artists/{slug}/` | 301, tag kept only when it is a real editorial tag |
| `/category/{genre}/` (music categories) | `/genres/{slug}/` | 301, pagination preserved |
| `/?p={id}` | canonical permalink of the same post ID | 301 |
| Attachment/media URLs | unchanged (same `uploads/` tree) | no redirect needed |

3. **One artist URL family.** The legacy dual model (`singer` + artist tags) collapses into
   `/artists/{slug}/`; tags remain available for editorial taxonomy only.
4. **No slug is renamed silently.** If a legacy slug collides with a new entity slug, the migration
   report lists the conflict and the tool refuses that row (it does not rename automatically).
5. **Slugs are translatable but not localised by default:** a Persian site may change the base slugs
   through a documented filter (`wavira_rewrite_slugs`) and the migration map is generated from the
   *actual* configured slugs, not from hardcoded English ones.

## Consequences

- SEO equity from the legacy catalogue is preserved, while new URLs are clean, logical and
  language-neutral for the international market.
- The redirect layer must exist **before** the first production migration (it is a migration-tool
  acceptance test: M8 in `MIGRATION-BLUEPRINT.md`).
- Localised slug bases are supported, which matters for the Persian market where
  `/خواننده/` may be preferred; the filter is the single supported way to change them.
- Any later change to these slugs requires a new ADR plus a redirect update — they are a public
  contract from 1.0.0 onward.
