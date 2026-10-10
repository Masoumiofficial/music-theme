# ADR 0014 — Colour modes, design tokens and the theme's presentation contract

- **Status:** Accepted
- **Date:** 2026-10-05
- **Phase:** 0.6.0
- **Related:** ADR 0002 (theme vs. core split), ADR 0008 (RTL-first, logical properties),
  ADR 0009 (a11y and performance gates), `wavira/assets/css/tokens.css`,
  `wavira/assets/js/index.js`, `wavira-core/assets/css/player.css`, `tools/check-css.mjs`

## Context

The legacy theme hard-coded two colour schemes and shipped a settings panel (OptionTree) that
duplicated every colour in a second stylesheet; dark mode was a separate template directory with
divergent markup. The result was unmaintainable and unreviewable.

Two consumer groups need colour in this product: **blocks** and **theme CSS** (theme), and the
**player component** (plugin). The player must keep working inside any theme, so it cannot depend on
this theme's palette — but inside this theme it must follow it, including the visitor's colour mode,
without a second stylesheet.

WordPress already provides the mechanism: `theme.json` compiles the palette into
`--wp--preset--color--*` custom properties that core, blocks and the editor all consume.

## Decision

1. **One colour source.** `theme.json` owns the palette, spacing, shadows and typography; the theme
   CSS consumes *only* those custom properties for colour. No component declares a hex value.
2. **Dark mode is a variable remap, not a stylesheet.** `tokens.css` remaps the
   `--wp--preset--color--*` variables for dark mode, so theme CSS *and* every block using a palette
   preset restyle from one place.
3. **The mode lives on `<html>` as `data-theme`** with three values: `light`, `dark`, `auto`.
   `auto` is the default and resolves in CSS through `prefers-color-scheme`, so the first paint is
   correct before any script runs; a tiny pre-paint inline script (theme, `wp_head` priority 1)
   applies a stored choice instead of flashing the wrong mode.
4. **The theme script has exactly three jobs:** apply/cycle/persist the colour mode, boot player
   instances through `window.Wavira.player`, and nothing else. It is a progressive enhancement:
   without JavaScript the site renders in the system colour mode with a native `<audio>` fallback.
5. **The plugin ships neutral player tokens** (`--wavira-player-*`) with its own safe defaults. The
   theme maps those tokens to the palette (`.wavira-site-player`, `.wavira-player`), which is how
   the component follows dark mode with no duplicated selector and how it stays usable in a foreign
   theme (ADR 0002).
6. **Text on an accent fill is a token** (`--wavira-on-accent`): both palettes use a bright accent,
   so the readable foreground differs per mode and no component should guess it. `tools/check-contrast.mjs`
   verifies the pairs in both modes as a merge gate.
7. **The block editor follows the same tokens** (`editor.css`): the editor renders the light palette
   and the same component geometry, so what the author sees matches the front end. The visitor's
   colour mode is a front-end choice, not an editor setting — the editor is not themed by `data-theme`.
8. **Token references must resolve.** WordPress kebab-cases preset slugs *and* custom keys when it
   compiles them (`WP_Theme_JSON::flatten_tree()` → `strtolower( _wp_to_kebab_case( $property ) )`),
   so `settings.custom.player.barHeight` becomes `--wp--custom--player--bar-height`. A camelCase
   reference is not an error in CSS — it silently resolves to nothing and the declaration falls back,
   which hides the mistake and disables the setting. `tools/check-css.mjs` therefore fails the build
   when a `--wp--preset--*`, `--wp--custom--*` or `--wp--style--*` reference cannot be produced from
   `theme.json`.

## Consequences

- Adding a palette colour requires it to be remapped in both dark blocks (enforced), and adding a
  custom setting requires the kebab-cased reference (enforced).
- Two defects of exactly this class were found while building 0.6.0 and are fixed here:
  `--wp--custom--player--barSpace` (tokens.css) and `--wp--custom--player--barHeight` (player.css)
  never resolved, so `custom.player.barSpace` and `custom.player.barHeight` had no effect on the
  rendered page — the 6 rem / 72 px fallbacks happened to equal the intended values, which is why
  the page looked correct. **Verified by reverting the fix and observing the gate fail.**
- `custom.player.miniHeight` (56 px) is declared in `theme.json` but consumed by nothing: the only
  sticky rule uses `custom.player.barHeight`, and no compact/mini variant exists yet. Recorded as an
  open item (wire it to a compact variant or remove the setting) instead of silently leaving a
  setting that changes nothing.
- The editor shows the light palette; a site that wants a dark admin experience must use a WordPress
  admin colour scheme. Documented as a known limitation, not a bug.
