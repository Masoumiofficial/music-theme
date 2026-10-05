# Component harness (development only)

A browser page that renders the **shipped** theme layers and the **shipped** player engine, so the
visual checks in `docs/VERIFICATION.md` (colour modes, RTL/LTR, the 360 → 1920 breakpoint matrix,
keyboard and screen-reader behaviour) can be performed without a WordPress install.

`tools/` is excluded from both product packages: nothing here ships.

## Run it

```bash
node tools/preview/serve.mjs        # → http://localhost:4173/tools/preview/
node tools/preview/serve.mjs 8080   # or choose a port
```

`index.html` is the chrome: colour mode (auto/light/dark), direction (RTL/LTR) and viewport width
(360/480/768/1024/1440/1920). The stage is a real `iframe`, so media queries respond to the chosen
width instead of the window.

## What is real

- `wavira/assets/css/{tokens,base,components,utilities}.css` — the four shipping layers, in build
  order (`tools/build.mjs` concatenates exactly these files into `assets/dist/theme.css`).
- `wavira-core/assets/css/player.css` and `wavira-core/assets/js/index.js` — the player component.
- `wavira/assets/js/index.js` — the theme script (colour-mode toggle, player bootstrapping).
- The mount markup and the no-JavaScript fallback, as `wavira_player_mount()` /
  `wavira_player_fallback()` print them.

## What is stubbed, and why

| Piece | Substitute | Why |
| --- | --- | --- |
| `theme.json` → `--wp--preset--*` / `--wp--custom--*` | generated on request by `presets.mjs` | WordPress prints these at runtime; `presets.css` reproduces the naming (kebab-cased slugs and custom keys) and the values verbatim |
| `GET /wavira/v1/player/*` | `embed.js` answers from an in-memory catalogue | the REST routes need WordPress |
| Audio sources | `serve.mjs` generates short sine tones on request | the engine's URL lock rejects `data:` and `blob:`, and the harness must not fetch anything external |
| Cover art | `serve.mjs` generates palette-coloured SVGs | same reason; no binary assets in the repository |
| Templates, patterns, shortcodes | static markup mirroring what the PHP prints | the PHP needs WordPress; the CSS/JS under test is identical |

## Known difference from a live site

`theme.json` sets `typography.fluid: true`, and WordPress converts font-size presets into `clamp()`
values when it compiles them. `presets.css` emits the declared values verbatim, so the type ramp in
this harness equals the *declared* scale, not the fluid ramp of a live site. Colours, spacing,
shadows, radii and custom properties are exact.

## Not a substitute for

A rendered WordPress page. The harness proves CSS/JS behaviour, not PHP output: no template,
pattern or shortcode has been rendered by WordPress yet. Those rows stay `NOT_STARTED` in
`docs/VERIFICATION.md` until a real install runs them.
