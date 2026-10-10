# UX-AUDIT.md — Legacy User-Experience & Accessibility Review

**Method:** template + CSS + JS reading (no live site). Findings reference source files.
Aimed at the brief's targets: WCAG 2.2 AA, mobile-first, RTL+LTR, dark/light, premium feel.

---

## 1. Journey map of the legacy product

```
HOME ─┬─ VIP slider (autoplay, 1–2 cards)          → single
      ├─ index player (playlist rows)               → inline play
      ├─ latest box                                 → single / archive
      ├─ most-viewed box                            → single / archive
      ├─ N category boxes (from settings)           → category archive
      └─ artist slider (manual links)               → /singer|/tag pages

ARCHIVE (category | tag | singer | search | page-template)
      └─ song cards (cover, "Artist – Title", type badge)  → single

SINGLE (music post)
      ├─ video (one quality)                        → playback
      ├─ player (single track OR album playlist)     → playback, volume, seek
      ├─ lyrics                                     → read
      ├─ download buttons 128/320 (+album/video)     → off-site file/direct link
      ├─ share (FB/X/WhatsApp/Telegram)              → off-site
      ├─ tags                                        → tag archive
      ├─ related tracks (artist)                     → single
      └─ sidebars (widgets)                          → single
```

Primary intents served: **listen**, **download**, **find artist**, **find latest**. All four work on
mobile, but with substantial friction described below.

---

## 2. Findings

### U1 — The player is not operable without a mouse/pointer (**Critical, a11y**)
Play/pause, next/previous and playlist toggles are `<i class="icofont-…">` glyphs with class-based JS
handlers (`js/scripts.js`). No `<button>`, no `tabindex`, no `role`, no `aria-pressed`, no keyboard
handlers, no focus styles; `screen-reader-text` spans exist inside glyphs but without ARIA semantics
they only add noise. Also `$('#cssmenu li.has-sub>a').on('click', …)` calls `removeAttr('href')`
— a keyboard user can lose the link entirely.
**Rebuild:** real buttons, visible focus rings, arrow-key seek/volume, `aria-live` for state changes,
documented keyboard map, tab order validated per template.

### U2 — Hover-only desktop navigation (**High**)
`jQuery(".menu_right ul li").hover(...)` opens submenus via `slideDown`. Touch devices and keyboard
users cannot access submenu items; there is no `focusin` path, no `aria-expanded`.
**Rebuild:** click/focus-toggle with `aria-expanded`, ESC to close, hover as an enhancement only.

### U3 — Two different headers and UA sniffing (**High**)
Desktop header is printed only when `!wp_is_mobile()`; the "mobile" header (`inc/mobiles_header.php`)
is emitted in the *footer* and is also present in desktop DOM (it is not `display:none` by markup, only
by CSS), plus an inline `<style>` adds `body{padding-top:60px}` on mobile only. Inconsistent
rendering, cache-poisoning, and a mobile menu that animates by `marginRight` (RTL-only geometry).
**Rebuild:** a single responsive header/menu, CSS-driven, no UA detection.

### U4 — No visible focus, no skip link, no landmark structure (**High, a11y**)
Templates use `<div id="centers_box">`, `<aside>`, `<main>` only in `header.php` wrapper; no skip-link,
no `<nav aria-label>`, no focus-visible styling in `style.css`. Sidebar widgets are `<aside>`s with
`<h3>` titles (fine) but duplicated across pages.
**Rebuild:** landmark skeleton (header/nav/main/aside/footer), skip link, focus-visible tokens,
heading order per view.

### U5 — Dark mode flashes and depends on jQuery cookie (**Medium**)
Body class `night` is applied server-side only when the option is on; otherwise the toggle adds the
class after DOM-ready (`js/scripts.js`) → **FOUC** on every page load for users who chose dark; the
choice lives in `$.cookie("toggle_modes")`, JS-cookie baked into a 19 KB bundle.
**Rebuild:** `prefers-color-scheme` default, tiny pre-paint inline script, localStorage (documented),
no flash, `color-scheme` CSS property, AA contrast in both themes.

### U6 — Empty states are hostile (**High**)
Missing content pages render the 404 illustration + text **and** auto-redirect home after 15 s
(`404.php`, `inc/no-content.php`). Missing thumbnails show the generic `nomusics.jpg`; missing audio
leaves a broken-looking player with no message; a track without lyrics simply omits the block.
**Rebuild:** designed empty states per case (no tracks / no results / missing audio / missing lyrics /
missing artwork), never a redirect, never a raw error, always a next action.

### U7 — Download UX lacks information and trust signals (**Medium**)
Buttons read "دانلود آهنگ با کیفیت 128" with no file size, no format, no duration, no source
(hosted vs external), no counter, no policy note; targets open in new tabs without `rel` (see
SECURITY S3). Album and video downloads are mixed into the same block.
**Rebuild:** download panel with quality/format/size/duration matrix, external-source badge, optional
gate (VIP/membership) with clear messaging, accessible button states, tracking that is privacy-safe.

### U8 — Responsive behaviour is breakpoint-driven, not content-driven (**Medium**)
Twelve `max-width` breakpoints (250–1300 px) with 70 `!important` overrides; the smallest breakpoints
(250/350 px) are dead weight; slider item counts are fixed per breakpoint
(`responsive:{200:{items:2},500:{items:3},800:{items:4},1000:{items:6},1300:{items:7}}`) rather than
derived from card width. Horizontal overflow was not verified on real devices (no staging) → must be
tested in QA at 360/375/390/414/768/1024/1280/1440/1920.
**Rebuild:** container-query-ish sizing or fluid grid, `clamp()` typography, logical properties,
zero horizontal overflow verified per breakpoint.

### U9 — RTL hardcoded, LTR unimplemented (**High**)
`dir="rtl"` in `header.php`; CSS uses `margin-left/right`, `float` and `text-align` pairs;
`mobiles_menu` animates `marginRight: -262px`. LTR visitors get mirrored-but-broken layout.
**Rebuild:** logical properties, `language_attributes()`, direction-aware components, per-locale QA in
both directions.

### U10 — Premium-feel gaps (**Medium**)
Visual system is 2015-era: one 62 KB stylesheet, icon-font controls, `.liner`/`.clear` divs, no design
tokens, no spacing scale, no radius/shadow system, no motion language, no skeleton loaders,
no typographic scale (Vazir only), no empty-state illustration system.
**Rebuild:** tokenised design system (see REBUILD-PLAN §Design), motion with `prefers-reduced-motion`
support, cohesive card/hero/player components, editorial typography (Persian + Latin pairing).

### U11 — Forms and comments (**Medium, a11y**)
Custom comment markup uses placeholders instead of labels, has no `aria-describedby` for errors, and
duplicates core (`myfunctions.php`). Search inputs rely on placeholder-only labelling
(`#lsds`, `#lsds_mobile`). Mobile search toggles are `<div>`/`<i>` elements.
**Rebuild:** labelled fields, visible or SR-only labels, inline validation, accessible search
dialog/panel with `role="search"`.

### U12 — Motion & autoplay (**Medium, a11y**)
VIP and artist sliders autoplay every 4 s and loop indefinitely with no pause control; `autoplayHoverPause`
only helps pointer users. No `prefers-reduced-motion` support.
**Rebuild:** no autoplay by default (or pause/play control + reduced-motion off-switch), WCAG 2.2.2
compliant carousels.

---

## 3. Accessibility scorecard (target: WCAG 2.2 AA)

| Requirement | Legacy | Rebuild plan |
| --- | --- | --- |
| Keyboard operability | Fail (player, menus, sliders) | all interactive elements are native controls; documented key map |
| Focus visible | Fail (no styles) | `:focus-visible` design token |
| Name/role/value | Fail (glyph icons) | ARIA on player, nav, carousel; text alternatives |
| Contrast | Unknown (no tokens, dark mode ad-hoc) | measured token pairs ≥ 4.5:1 (body) / 3:1 (large/UI) |
| Reduced motion | Fail | media query honoured by all animation |
| Forms & errors | Fail (placeholders, no errors) | labels, `aria-invalid`, error text, live regions |
| Reflow / zoom to 400 % | Unknown (72 `!important`, fixed sliders) | verified in QA matrix |
| Screen-reader test | Not performed | NVDA + VoiceOver pass per template |
| Time-based content | Fail (15 s redirect, autoplay) | removed |
| Media captions | n/a (audio) | lyrics as text alternative; video: poster + captions field |

## 4. UX backlog priorities for v1

1. Player Engine with full keyboard/ARIA/media-session support (P0)
2. Single responsive header + accessible menus, no UA sniffing (P0)
3. Dark/light with zero flash and AA contrast (P0)
4. Download panel with full information + optional gating (P0/P1)
5. Designed empty/error states for every list and player (P0)
6. Search panel with debounced live results (P0)
7. Motion system with reduced-motion support (P1)
8. RTL/LTR parity verification in CI-ish manual matrix (P0)
