# ADR 0002 — Theme vs. Core plugin split

- **Status:** Accepted
- **Date:** 2026-10-05
- **Phase:** 0.2.0
- **Related:** `ARCHITECTURE-LEGACY.md`, `DATA-MODEL-AUDIT.md`, brief §9–§10

## Context

In the legacy theme every music concept exists only as a `post` plus meta rendered by theme templates.
Switching themes destroys the site's music presentation, and no data survives in a usable, typed form.
The brief's rule: *"If the user switches the theme, must this survive? → plugin."*

## Decision

1. **All persistent music data and business logic live in the `Wavira Core` plugin:**
   CPTs, taxonomies, registered meta, settings, download logic, search, related-content resolution,
   player payloads, REST API (`wavira/v1`), admin UX, import/export, demo content, migration tool.
2. **The theme owns presentation only:** templates/parts/patterns, block styles, design tokens,
   component styles, front-end assets that are purely visual, and optional integration shims
   (Rank Math breadcrumbs, Elementor widget registration).
3. **Dependency direction is one-way:** theme → Core (through documented helpers). Core must never call
   theme code. Core must work with **any** theme (headless/REST included).
4. **Graceful absence:** the theme renders a coherent site (posts/pages, navigation, empty states) with
   Core deactivated, showing administrators a notice explaining that music features need the plugin.

## Consequences

- A user may switch themes freely: artists/albums/tracks/videos/genres, download configuration and
  player settings remain intact as data (re-rendered by the new theme or exposed over REST).
- The theme cannot ship a "quick fix" that bypasses Core — this is enforced in review (CODING-STANDARD W2).
- Marketplace packaging must ship both artifacts and the installer must explain the relationship.
- Deactivating Core is never destructive: no uninstall-time data deletion without explicit user opt-in
  (`uninstall.php` with a setting).
