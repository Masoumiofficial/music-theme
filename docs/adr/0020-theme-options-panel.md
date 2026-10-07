# ADR 0020 — Theme options: one schema, CSS variables, and no page-builder switches

* Status: Accepted
* Date: 2026-10-07
* Phase: 0.12.0
* Supersedes: nothing. Relates to: [0014](0014-dark-mode-and-token-mapping.md) (token remapping),
  [0009](0009-accessibility-and-performance-gates.md) (no `!important`, budgets), [0002](0002-theme-vs-core-plugin-split.md)
  (theme vs plugin), [0015](0015-persian-first-localisation.md) (Persian defaults)

## Context

A commercial theme is expected to ship a settings panel: a buyer who installs it wants to set a logo,
a colour, a font and a social link without editing code. The failure modes of that expectation are
well known — panels that grow into page builders, options that fight the block editor, a second
permission system, and settings whose values nothing on the page ever reads.

The product also has two constraints that decide the shape of the answer:

1. **The theme is presentation only** (ADR 0002). Music data, player settings and site defaults belong
   to the core plugin, and the theme may only call the plugin's public functions.
2. **The block editor owns styling.** `theme.json` declares the palette, type scale and radii, and the
   Site Editor lets a site owner change them. A theme option that duplicates a `theme.json` control
   would be a second, silently diverging source of truth.

## Decision

### 1. The panel is the WordPress Customizer, reached from Appearance

No hand-built settings page, no third-party options framework, no bundled `TGMPA`-style dependency.
The Customizer already provides the capability model (`edit_theme_options`), nonces, the preview, the
"unsaved changes" guard, and the screen a WordPress user looks at first. A panel that re-implements any
of those is a bug generator with a licence header.

### 2. One schema drives everything

`wavira_options_schema()` in `inc/options.php` is the single declaration of every setting: its key, its
default, its `type` (`int`, `bool`, `color`, `url`, `text`, `multiline`, `choice`, `stack`,
`attachment`, `css`), the control that renders it, the section it lives in, its label, and its bounds.
The Customizer builds its controls from that array, the sanitizer dispatches on the type, and the front
end reads values through `wavira_option()`. A field cannot exist in the panel and not in the output (or
the reverse) — the failure mode of every hand-written panel — because there is only one list.

### 3. Values reach the page as CSS variables or body classes

* Colours, widths, radii, shadows, font stacks and sizes are printed as **CSS custom properties** in one
  `<style id="wavira-options-css">` element (ADR 0014's mechanism). A block that consumes
  `var(--wp--preset--color--primary)` follows the accent setting without a second stylesheet, without
  touching `theme.json`, and without `!important`.
* Switches the *editor* also styles (heading weight, sticky header, hidden tagline) are **body classes**:
  `body.wavira-heading-800 h2` beats a core `h2` rule on specificity, in both directions, whatever order
  the stylesheets land in.
* Markup-shaped settings (logo, social row, announcement bar, back-to-top button) are printed by
  **patterns**, which are PHP: a block template cannot decide whether a URL was filled in.

### 4. The preview applies what the server would print

Every field declares a **transport**. A field that changes a CSS variable or a body class is
`postMessage`: `assets/js/customizer.js` receives the list from PHP and applies it in the preview frame
without a reload. A field that changes *markup* — the logo, the social row, the announcement bar, the
fonts (which also decide what gets preloaded) — stays `refresh`, because only the server can render it, and
a preview that pretends otherwise is a preview that lies.

The list is derived from the schema's own `live` entries, and the same entries are what
`wavira_option_css()` prints, so the property name a setting drives exists in exactly one place.
`tests/test-theme-options.php` asserts the two directions of that agreement (every `postMessage` field is
in the preview list, no `refresh` field is), and `tests/js/customizer.test.mjs` asserts what the preview
does with each mode.

### 5. A default site pays nothing

`wavira_option_css()` emits a declaration only when a value differs from its declared default, and
returns an empty string when nothing does. The default site therefore ships zero extra bytes, and the
built stylesheet stays the single source of the design.

### 6. The panel's boundary is stated, not implied

In the panel: identity and logo, header, appearance, fonts, social links, footer text, additional CSS.
Not in the panel: a switch to disable Gutenberg, to disable the REST API, to "optimise SEO", to
deactivate another plugin, or to bundle a page builder. Each of those fights another component, breaks
when that component changes, or hides standard WordPress behaviour a customer expects to keep.

### 7. Persian setup is an action, not a theme setting

The site *language* is a WordPress option, not a theme option, so it is not a `theme_mod`. The theme
offers it as one nonced, capability-checked action (`wavira_persian_setup`): site language, timezone,
week start and date format in one step, plus the core language pack when the host can reach
wordpress.org. When the core plugin is active the theme calls
`wavira_core_apply_persian_defaults()` — the same code the demo installer uses — so the list does not
drift; without the plugin the theme applies the same four options itself.

### 8. Additional CSS is printed as it is typed, minus what would break the element

The field requires `edit_theme_options`, the same capability as the Customizer's own Additional CSS, and
is printed inside a `<style>` element. `wavira_sanitize_custom_css()` removes `<?`, `<` and the closing
tag sequences, so the value cannot end the element it lives in. It is not a CSS parser and does not
pretend to be: the panel says so, and the theme does not claim to validate what the site owner writes.

## Consequences

* A child theme or a site-specific plugin adds a field by filtering `wavira_options_schema` — no template
  override, no fork.
* The panel exposes 32 settings; each one has a test in `tests/test-theme-options.php` asserting it is
  registered with the shared sanitizer, sits in its declared section, and reaches the page only when set.
* The preview script is the theme's second entry point, built and budgeted like the first (≤ 10 KB gzipped,
  2.7 KB actual) and loaded only inside the Customizer frame — never on the front end.
* The panel cannot express everything `theme.json` can (fluid type, per-block styles). That is deliberate:
  the Site Editor remains the place for design-system work, and the panel covers what a buyer changes on
  day one.
* A user-chosen accent colour is not machine-checked for WCAG contrast — the theme's own palette is
  (tools/check-contrast.mjs), and the field's help text states the requirement. This limitation is
  recorded in `docs/VERIFICATION.md` rather than hidden.
* `wavira/inc/options.php` is loaded before `performance.php`, because the font preload asks the options
  layer whether the bundled font is in use.
