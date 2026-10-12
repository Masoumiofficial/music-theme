# ADR 0012 — Where relations live (and why not a shared taxonomy)

- **Status:** Accepted
- **Date:** 2026-10-05
- **Phase:** 0.3.0
- **Related:** ADR 0003, `DATA-MODEL-AUDIT.md` D1/D4, open decision "relation storage" in `DECISIONS.md`

## Context

Legacy data modelled the artist↔track relation twice: a term relationship (`singer` taxonomy) and a
free-text meta field (`artist`). The rebuild must pick one mechanism. The realistic options were:

| Option | Pro | Con |
| --- | --- | --- |
| A. Shared taxonomy (`wavira_artist` as taxonomy applied to tracks) | free archives, term queries, WP-native listings | cannot express *roles* (primary vs. featured), no artist-level fields without term meta, artist data is a term (limited admin UX, hard WPML handling) |
| B. Post IDs in registered meta (artist CPT + `wavira_artist` meta on tracks) | roles, ordering, featured artists, rich artist entity, REST-friendly schemas | archive listings need meta queries; performance must be managed deliberately |

## Decision

**Option B — relations are post IDs in registered meta.** Concretely:

1. Artist is a **CPT** (`wavira_artist`) with its own fields (bio, images, verified, socials, related).
2. Relations:
   - Track → `wavira_artist` (primary, single ID), `wavira_featured_artists` (array of IDs),
     `wavira_album` (single ID).
   - Album → `wavira_artist` (primary), `wavira_featured_artists`, `wavira_tracklist` (ordered array of IDs).
   - Video → `wavira_artist`, `wavira_album`.
3. **No free-text artist credits** in the data model. A display-only string may exist
   (`wavira_credit_label`) for cases where the name printed on artwork differs from the entity — it is
   never used to resolve relations.
4. Every relation meta is registered with `type`, `single`, `sanitize_callback`, `auth_callback` and
   `show_in_rest` schema; arrays are stored as arrays of integers, never serialized strings.
5. **Performance is not assumed, it is designed:**
   - listings by relation use `meta_query` on indexed `meta_key` with pagination (no `-1`),
   - hot reads go through services implementing `Cacheable` (phase 0.4.0),
   - if a benchmark shows meta queries are the bottleneck at scale (>10k tracks), a denormalised
     lookup table or term-based index is added **behind the same service API** — the data model and
     REST payloads do not change. This is explicitly allowed without a superseding ADR because it is
     an internal index, not a model change.
6. Term-based taxonomies remain for **classification only**: genre, mood, language, label, year.

## Consequences

- Artists get a real admin experience (biography, images, verification, socials) instead of a term box.
- Themed archive pages (`/artists/{slug}/`) are CPT archives; per-artist listings are paginated
  queries over relation meta with cache.
- WPML/Polylang handle CPT relations predictably (IDs are translated per language by the plugin).
- No query can silently depend on a name string — the primary defect of the legacy model (D4) is
  structurally impossible.
