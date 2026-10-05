# ADR 0005 — Player Engine design

- **Status:** Accepted (implementation in phase 0.5.0)
- **Date:** 2026-10-05
- **Phase:** 0.2.0
- **Related:** `FEATURE-MAP.md` F-40…F-47, `UX-AUDIT.md` U1, brief §21–§23

## Context

Legacy implementation (verified): every player renders `<audio id="audio">` plus `#volume-bar`; the JS
binds to `document.getElementById('audio')` and class selectors, keeps a global `nowPlaying`, and only
supports one player per page. There is no queue model, no shuffle/repeat, no Media Session integration,
no keyboard support, no error state, and controls are `<i>` glyphs.

## Decision

Build the **Wavira Player Engine** as a standalone, framework-free ES module in Wavira Core:

1. **No global element ID.** Each player instance mounts into a passed element and creates its own
   `<audio>`; multiple instances coexist (card player, sticky bar, album player).
2. **DOM-independent state machine.** State object:
   `currentTrack, queue, currentIndex, isPlaying, isLoading, isBuffering, duration, currentTime, volume,
   muted, repeatMode, shuffleMode, error`. The engine emits events; views subscribe.
3. **One shared state, many views.** A single engine instance can drive several visual components
   (e.g. sticky bar + inline card), synchronised through custom events — never through DOM scraping.
4. **Feature set:** play/pause, seek, volume/mute, next/previous, shuffle, repeat (off/all/one),
   queue reorder-lite (remove/clear), loading/buffering/error states, controlled autoplay (never
   forced), Media Session API metadata + action handlers, documented keyboard map
   (`Space`, `←/→` seek, `↑/↓` volume, `M` mute, `N/P` next/previous).
5. **Accessibility built in:** native `<button>`/`<input type="range">` controls with ARIA state,
   visible focus, live-region announcements for track changes and errors, `prefers-reduced-motion`.
6. **Data comes from Core's REST payload** (`wavira/v1/player/...`) which includes signed-or-public
   source URLs, artwork and metadata; the engine never constructs URLs itself.
7. **Persistence:** volume, last track and repeat/shuffle preferences in documented `localStorage`
   keys (`wavira.player.*`), respecting the privacy rules; never cookies for playback state.
8. **No DRM claims.** The engine plays what the server authorises; protection is access control only.

## Consequences

- Templates no longer own player markup; they render a mount point and pass a track payload.
- The sticky/mini player becomes a view over the same engine — no duplicate logic, no page-cache
  poisoning (state is client-side only).
- The engine is testable without WordPress (plain DOM + events), so it can be unit-tested in CI.
- Player JS ships as its own bundle, loaded only where a player is rendered (performance budget).
