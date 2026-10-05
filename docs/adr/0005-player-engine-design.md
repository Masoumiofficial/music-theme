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

## Implementation notes (0.5.0)

Where each decision above became code, and the small decisions taken while building it.

| Decision | Implementation |
| --- | --- |
| §1 no global element ID | `wavira-core/assets/js/index.js` creates one `<audio>` per instance; the theme prints a mount point with `wavira_player_mount()` (`wavira/inc/player.php`). |
| §2 DOM-independent state | `createStore()` + `createEngine()`; no DOM reference outside the view adapter. 15 unit tests in `tests/js/player.test.mjs` run in `node:vm` with no DOM. |
| §3 one state, many views | Engine events (`trackchange`, `play`, `pause`, `queuechange`, `volumechange`, …) plus a DOM bridge that re-dispatches them as `wavira:player:<event>` on the mount point for theme JS. |
| §4 features | play/pause, seek, volume/mute, next/previous, shuffle, repeat off/all/one, queue remove/clear, loading/buffering/error, controlled autoplay, Media Session, keyboard map (`Space`/`k`, `←/→`, `↑/↓`, `m`, `n`, `p`). |
| §5 accessibility | Native `<button>` and `<input type="range">` with visible labels, `aria-pressed`, `aria-valuetext`, `role="status"` for track changes and `role="alert"` for errors; no icon font is required to operate the controls. |
| §6 REST data | `GET wavira/v1/player/tracks/{id}` and `GET wavira/v1/player/queue?context=…`; PHP also hands the engine route *templates* (`…/tracks/%d`, plus the queue URL) in `waviraPlayerSettings`, so the engine substitutes an ID and never composes a path. |
| §7 persistence | `wavira.player.volume`, `.muted`, `.repeat`, `.shuffle` through a facade that never throws (private mode, quota); no cookies, and playback position is intentionally **not** persisted. |
| §8 conditional bundle | `Wavira\Core\Player\Assets` registers the handle `wavira-player` on `wp_enqueue_scripts` only when `assets/dist/core.js` exists; the theme asks for it from the mount helper. Un-built checkouts degrade to the native `<audio>` fallback. |

Decisions recorded during implementation:

1. **A published track without audio answers `200` with empty `sources`**, not `404`: the track exists and its page must work. Only unknown IDs, drafts and non-track entities return `404 wavira_not_found`. Queue builders drop source-less tracks, so a queue never contains something that cannot play.
2. **Store field events are namespaced** (`state:volume`): a field literally named `error` or `play` must not impersonate an engine event. The unit tests caught the collision.
3. **The engine never persists a playback position.** Resuming mid-track after a page load is a privacy/UX decision with no evidence of user demand; volume, mute, repeat and shuffle are the only stored values.
4. **`wavira_core_track_playback()` and `wavira_core_enqueue_player()`** were added to the plugin's public function API so the theme renders the fallback and requests the bundle without naming a plugin class (ARCHITECTURE §2, gate R3).
5. **Bundle size:** the built `wavira-core/assets/dist/core.js` is **13.6 KB gzipped** (53 KB raw, source comments included), inside the ≤ 15 KB player budget (PERFORMANCE-AUDIT §3), measured with `gzip -9`. The source stays readable on purpose: the budget is measured on the shipped bytes and minification is a packaging-time decision (0.12.0).
6. **Deferred to 0.6.0:** the sticky/mini player is a second *view* over this engine, and the inline card player is a third; no engine change is expected. Waveform rendering is not in v1 (ADR 0001 non-goals).

