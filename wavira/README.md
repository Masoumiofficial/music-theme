# Wavira Music — WordPress theme

Presentation layer of the **Wavira** music ecosystem. All persistent music data (artists, albums,
tracks, videos, genres), settings, playback logic and REST endpoints belong to the
**[Wavira Core](../wavira-core/)** plugin — so content survives a theme switch.

| | |
| --- | --- |
| Version | 0.2.0 (architecture phase — no user-facing UI yet) |
| Requires | WordPress 6.6+ · PHP 7.4+ |
| Author | **Etehad WP — اتحاد وردپرس** · <https://etehadwp.com/> |
| Text domain | `wavira` |
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
│   └── integrations/    rank-math.php, yoast.php, elementor.php (later phases)
├── templates/           block templates (phases 0.6.0+)
├── parts/               block template parts (header, footer, player bar)
├── patterns/            editor patterns for homepage sections (phase 0.6.0)
├── blocks/              block sources (phase 0.4.0+)
├── assets/
│   ├── css/             token/base/component sources
│   ├── js/              ES modules (no jQuery)
│   ├── icons/           SVG icon files used by wavira_icon()
│   ├── fonts/           OFL-licensed fonts only
│   ├── images/          owned/CC0 demo art only
│   └── dist/            build output (generated — not committed)
└── languages/           wavira.pot + translations
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
