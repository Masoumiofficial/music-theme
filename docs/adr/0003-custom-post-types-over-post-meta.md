# ADR 0003 — Real content entities instead of post meta

- **Status:** Accepted
- **Date:** 2026-10-05
- **Phase:** 0.2.0 (implementation in 0.3.0)
- **Related:** `DATA-MODEL-AUDIT.md` findings D1–D10, `MIGRATION-BLUEPRINT.md`

## Context

Legacy model (verified): every music item is a `post` distinguished by `musics_type` meta
(`mp3`/`mp4`/`album`); the artist exists twice (taxonomy `singer` and post tags) with duplicated term
meta; album tracklists are an ACF repeater of file URLs (`song_names`, `albumlink128/320`), so tracks
are not entities; artist credits are free-text meta (`artist`) duplicating taxonomy relations;
queries therefore combine `meta_query` + `tax_query` and sort on unindexed meta.

## Decision

1. Introduce CPTs: `wavira_artist`, `wavira_album`, `wavira_track`, `wavira_video`.
2. Introduce taxonomies: `wavira_genre` (required) and `wavira_mood`, `wavira_language`, `wavira_label`,
   `wavira_year` (optional, enabled by settings). Genres are **never hardcoded**.
3. Relations are **IDs**, not strings: track → {primary artist, featured artists, album}, album →
   {primary artist, featured artists, ordered tracklist}, video → {artist, album}.
4. Every field is a registered meta key with `type`, `single`, `sanitize_callback`, `auth_callback`
   and REST schema. Lyrics are text (rendered through an allow-list), not unescaped HTML blobs.
5. Legacy data is migrated by a dedicated, dry-runnable tool (phase 0.9.0), preserving post IDs and
   slugs so existing URLs keep working.

## Consequences

- One canonical artist entity removes the duplicate-URL/duplicate-content problem (SEO-AUDIT E4).
- Queries become taxonomy/ID based and indexable; `posts_per_page => -1` is no longer needed anywhere.
- Albums can reference real tracks; tracks are individually addressable, playable, countable and
  REST-exposed (`show_in_rest`).
- Migration is non-trivial: free-text `artist` strings need resolution (exact name/slug match, then a
  "needs review" queue) — accepted as the cost of a correct model.
- Slug strategy is decided in phase 0.3.0 (see open decisions in `DECISIONS.md`).
