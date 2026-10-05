# ADR 0008 — Internationalisation and RTL-first direction

- **Status:** Accepted
- **Date:** 2026-10-05
- **Phase:** 0.2.0
- **Related:** `UX-AUDIT.md` U9, `SEO-AUDIT.md` E7, brief §46–§48

## Context

The legacy theme is Persian-only in practice: hardcoded `dir="rtl" lang="fa-IR"`, no text domain, no
`languages/` directory, ad-hoc translation calls with inconsistent domains, and CSS built from
`left/right` pairs. The product's primary market is Persian (Iran) and its growth market is
international, so both directions must be first-class.

## Decision

1. **Source strings in English**; translations shipped for **fa_IR** (primary), and supported for
   **ar**, **de**, **fr**, **es** (as far as contribution allows). One text domain per artifact:
   `wavira` (theme), `wavira-core` (plugin).
2. **RTL-first design, full LTR parity.** Layouts are designed in RTL and verified in LTR (and vice
   versa for international templates); a component is not "done" until both directions look correct.
3. **Logical CSS properties only** (`margin-inline`, `padding-inline`, `inset-inline`,
   `border-inline-*`, `text-align: start/end`); physical values need a written justification.
4. **No direction-specific markup.** `dir`/`lang` come from `language_attributes()`; conditional logic
   uses `is_rtl()` at most — never a hardcoded `dir="rtl"`.
5. **Content localisation:** strings are never concatenated; `sprintf` placeholders with translator
   comments for anything ambiguous; `wp_date()`/`number_format_i18n()` for dates and numbers
   (Persian/Arabic digits come from locale data, not from hardcoded glyphs).
6. **Typographic reality:** Persian text needs its own font pairing and line-height; the design system
   exposes a `--wavira-font-*` token set that switches per locale, and font files must be
   OFL-licensed (see ADR 0010) and subset for size.
7. **Multilingual plugin compatibility:** architecture must not hardcode language into data. Entities
   are translatable by WPML/Polylang through standard CPT/meta registration; no language-specific
   fields, no per-language options duplication.

## Consequences

- `.pot` generation becomes part of the release process; hardcoded strings are a lint/review failure.
- Every new component adds a two-direction visual check to its definition of done.
- Player time formats, download sizes, and pagination numerals are localised, not hardcoded.
