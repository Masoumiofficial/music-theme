# Wavira Music — WordPress theme

Presentation layer of the **Wavira** music ecosystem. All persistent music data (artists, albums,
tracks, videos, genres), settings, playback logic and REST endpoints belong to the
**[Wavira Core](../wavira-core/)** plugin — so content survives a theme switch.

| | |
| --- | --- |
| Version | 0.8.0 (Persian-first: fa_IR catalogue, block layer) |
| Requires | WordPress 6.6+ · PHP 7.4+ |
| Author | **Etehad WP — اتحاد وردپرس** · <https://etehadwp.com/> |
| Text domain | `wavira` |
| Languages | Persian (`fa_IR`) ships in `languages/` as `.po` **and** compiled `.mo` — the theme is usable in Persian on install, and `npm run i18n:check` fails when a string is added without its translation |
| Namespace | none (theme uses prefixed functions: `wavira_*`) |
| Licence | GPL-2.0-or-later |

## Structure

```
wavira/
├── functions.php        thin bootstrap (constants + requires only)
├── style.css            theme header only (built CSS lives in assets/dist/)
├── theme.json           design tokens for the block editor and front end
├── inc/
│   ├── setup.php        theme supports, menus, image sizes, i18n
│   ├── helpers.php      small template helpers (core-aware, degrade gracefully)
│   ├── hooks.php        body classes, small core adjustments
│   ├── assets.php       conditional, build-aware enqueueing
│   ├── markup.php       shared markup (tracklist, video, chips) — one implementation
│   ├── player.php       the player mount point + no-JavaScript fallback
│   ├── shortcodes.php   classic-editor surfaces, delegating to markup.php
│   ├── blocks.php       block registration from each blocks/<name>/block.json
│   └── integrations/    rank-math.php, yoast.php, elementor.php (later phases)
├── templates/           block templates (14: index, page, single, search, 404, music singles/archives)
├── parts/               block template parts (header, footer, player bar)
├── patterns/            insertable patterns + hidden-* patterns holding every
│                        user-visible template string (.html templates run no PHP)
├── blocks/              dynamic block sources: <name>/block.json + render.php + shared editor.js
├── assets/
│   ├── css/             token/base/component sources
│   ├── js/              ES modules (no jQuery)
│   ├── icons/           SVG icon files used by wavira_icon()
│   ├── fonts/           OFL-licensed fonts only
│   ├── images/          owned/CC0 demo art only
│   └── dist/            build output (generated — not committed)
└── languages/           wavira.pot (generated) + fa_IR.po (translation) + fa_IR.mo (compiled, loaded by WordPress)
```

## Theme rules (from the audit)

1. **No music logic in the theme.** If a user switches the theme, music data must survive → Core plugin.
2. **No front-end jQuery.** Vanilla ES modules only.
3. **No UA sniffing**, no duplicated mobile/desktop headers.
4. **Assets are conditional** and only enqueue when the built file exists.
5. **Everything escaped** on output; nothing is echoed raw from options or meta.
6. **RTL and LTR are equal citizens** — logical CSS properties only.
7. **Accessibility is a merge gate** (WCAG 2.2 AA), not a follow-up task.

See `docs/ARCHITECTURE.md` (repo root) and `docs/CODING-STANDARD.md` for the enforceable version.
