# ADR 0018 — Migration tool: copy what is known, report what is not, and never guess

- **Status:** Accepted (0.11.0, implemented — **verified**: CI runs `37440743623`/`37440749435`, 8/8 jobs, 146 tests / 1198 assertions on PHP 7.4 and 8.2)
- **Date:** 2026-10-06
- **Deciders:** product owner (brief: "a separate tool, never part of the theme's runtime"), engineering
- **Supersedes:** —
- **Related:** ADR 0001 (scope), 0003 (CPTs), 0010 (licensing), 0012 (relations), 0013 (bounded queries),
  `docs/MIGRATION-BLUEPRINT.md`, `docs/DATA-MODEL-AUDIT.md`

## Context

A customer who runs the legacy theme has years of content in a shape the new model does not use: one
`post` type discriminated by a `musics_type` meta value, artists modelled as a black-box taxonomy and as
tags, audio as raw URLs in post meta, an album tracklist as an ACF repeater, and images as URLs with no
attachment behind them. The migration either happens deliberately or the product cannot be sold to the
existing install base at all.

Two failure modes dominate real migrations: a tool that *guesses* (an artist string becomes a new artist
entity that never existed; a remote image is silently hot-linked; a value is reinterpreted under a new
name) and a tool that *cannot be undone* (a type change with no backup, a deleted post, an idempotency
bug that duplicates a catalogue on the second run).

## Decision

1. **The tool is a service, not a runtime.** `Migration\LegacySchema` and `Migration\Migrator` live in
   Wavira Core's `src/Migration/` (boundary layer 2) but nothing on the boot path constructs them: the
   only entry point is the WP-CLI command `wp wavira migrate`. A normal request pays zero for it, and no
   migration code can run by accident.
2. **Copy first, then write.** Before the first write, the original post type and every legacy value the
   run can touch are copied into `_migration_backup` (once — a second run never overwrites it with the
   migrated state) and `_migration_raw` (the audit trail, including fields the model deliberately does
   not implement). The post keeps its ID, slug, dates and `post_status`; the tool never publishes or
   unpublishes anything, and it never deletes legacy data.
3. **Report, never guess.** An ambiguous value is not invented: an artist string that matches no entity
   keeps its credit label and enters the `needs_review` queue; a slider image that is not a local
   attachment stays in the raw meta (downloading third-party media is forbidden, ADR 0010) and is queued;
   a `musics_type` value outside the map is reported and left alone.
4. **Idempotency is structural.** The tool's own selection defines it: a legacy post *is* `post` with a
   known `musics_type`, and a migrated post *is* `wavira_track`/`wavira_video`/`wavira_album`, so a second
   run cannot see the same post twice; `_migration_version` is a second guard, and the created album
   children carry `_migration_source_album`/`_migration_source_index` so an expanded album is never
   expanded again.
5. **Bounded and resumable.** Every query is bounded (`--batch`, default 200) and ordered by ID;
   `--offset` resumes; counting pages instead of loading a table (ADR 0013 applies to the tool too).
6. **Reversible, and honest about what it cannot restore.** `--rollback` restores the type and meta from
   the backup, deletes exactly the child tracks this tool created (recorded in `_migration_created`) and
   *reports* anything a human changed afterwards instead of overwriting it. Rollback of a deleted legacy
   file is impossible by definition — the tool never moves files, so there is nothing to restore.
7. **The CLI is the shipped surface.** The blueprint's §6 named an admin page as well. The CLI ships
   first because it is scriptable, dry-runnable, reviewable in a diff and usable before a site is
   switched over; an admin screen needs its own capability model and UI decision and is deferred
   explicitly rather than half-built.
8. **The map is checked against the schema by a test.** `Test_Migration::test_every_target_key_is_a_schema_constant()`
   reflects over `MetaSchema::all()` for every target the map promises, so a typo in a key fails in the
   suite rather than on a customer's site mid-run.

## Consequences

- **Good:** the existing install base can be moved with a command that is safe to run twice, and every
  uncertain row is a list an operator works through instead of a data corruption nobody notices. The
  fixtures in `tests/test-migration.php` are the legacy *shape*, never the legacy code, so the black box
  stays black (ADR 0001).
- **Cost:** the migration needs a human for the review queue (unresolved artists, non-local images) — that
  is the point, but it means a large catalogue is a project, not a button. The album repeater is expanded
  into real child tracks, which multiplies the post count of a legacy site; the tool reports how many it
  created, and `--rollback` removes them.
- **Not covered:** dead external links need a live crawl (`M8`, `WP-RUNTIME`), a 10 000-post dry run is a
  staging exercise rather than a unit test, and a legacy site that used plugins the audit never saw is out
  of scope: the tool reports an unknown `musics_type` instead of inventing a mapping for it.
