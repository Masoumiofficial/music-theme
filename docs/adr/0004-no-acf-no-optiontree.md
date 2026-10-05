# ADR 0004 — No ACF, no OptionTree

- **Status:** Accepted
- **Date:** 2026-10-05
- **Phase:** 0.2.0 (implementation in 0.3.0)
- **Related:** `LICENSE-AUDIT.md`, `DATA-MODEL-AUDIT.md` §4, brief §14–§16

## Context

The legacy product depends on two field/settings frameworks: **Advanced Custom Fields** (not bundled,
required for ~20 keys and all term fields, plus `have_rows()` repeaters) and **OptionTree 2.6.0**
(bundled, GPLv3, "tested up to WordPress 4.4", 2.2 MB of assets, stores one opaque serialized settings
option). Both create lock-in: fields are untyped, unregistered, not REST-aware, and the settings
schema accepts raw JavaScript ad code.

## Decision

1. **No ACF dependency.** Use WordPress native registration:
   `register_post_meta()` / `register_term_meta()` (+ `register_meta` where appropriate) with full
   type/sanitization/auth/REST schema; use core meta boxes or block-editor panels for editing.
2. **No OptionTree.** Replace with:
   - **Settings API** for product settings (one typed schema, one option array, sanitize per field,
     capability checks),
   - **`theme.json`** for design tokens and visual settings,
   - **block attributes / patterns** for homepage composition that used to be repeatable option lists
     (`hty`, `siing_t`).
3. **Compatibility, not duplication:** if a site already uses ACF, the product must not break it
   (no global hooks removed, no conflicting meta keys), but the product never *requires* it.
4. OptionTree is deleted from all packages; the legacy settings option is read **once** by the
   migration tool (read-only) and never written by the new code.

## Consequences

- Music fields become typed, validated, REST-visible and queryable — the legacy's biggest data weakness
  disappears.
- Admins lose the OptionTree UI; the replacement must be at least as fast for the daily workflow
  (this becomes an acceptance criterion for phase 0.6.0, not an afterthought).
- The product ships with zero third-party runtime dependency for its core features, which simplifies
  licensing and support (see ADR 0010).
