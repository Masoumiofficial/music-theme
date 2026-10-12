# ADR 0001 — Product scope and non-goals

- **Status:** Accepted
- **Date:** 2026-10-05
- **Phase:** 0.2.0 (architecture)
- **Related:** `REBUILD-PLAN.md`, `FEATURE-MAP.md`, brief §8 and §54

## Context

The legacy theme is a Persian music-download site theme: a catalogue of tracks/videos/albums with
download links, lyrics, an artist taxonomy and a homepage assembled from sliders and boxes. The brief
requires a **premium WordPress music-publishing ecosystem**, and explicitly forbids drifting into
streaming/SaaS/DRM/AI territory for v1 while still leaving seams for those futures.

## Decision

1. **In scope for v1:** Publishing and discovery of music — artists, albums, tracks, music videos,
   genres; lyrics; multiple download qualities; a first-class player; SEO and performance; RTL + LTR;
   light/dark; Gutenberg + Elementor support; migration from the legacy schema.
2. **Explicit non-goals for v1 (no implementation, only seams):** streaming infrastructure, DRM or
   content protection claims, subscriptions/billing, multi-tenant SaaS, mobile apps, AI
   recommendations, artist dashboards, front-end submission, licence/update server, third-party
   telemetry.
3. **Product identity:** the theme is named **Wavira Music**, the content engine **Wavira Core**; both
   are sold/supported as one product with two installable artifacts.

## Consequences

- The player is a *feature*, not a platform: it plays files/URLs the site legitimately hosts or links.
- No schema, endpoint or table is added for accounts, payments or analytics in v1.
- REST + hooks are designed so that a future streaming/API product can be built **on top** without
  refactoring the data model.
- Any request to add a non-goal item requires a superseding ADR with a cost/benefit analysis.
