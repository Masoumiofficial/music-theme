# ADR 0023 — One download route serves four kinds of file

* Status: Accepted
* Date: 2026-10-08
* Phase: 0.15.0
* Relates to: [0013](0013-download-counters-and-delivery.md) (counters and delivery), [0002](0002-theme-vs-core-plugin-split.md)
  (theme vs plugin), [0008](0008-i18n-and-rtl-first.md) (one text domain per artifact),
  [0024](0024-generated-demo-media.md) (the demo media this route needs)

## Context

0.3.0 built the download path for one thing: a **track's** audio, 128 and 320 kbps, behind
`wavira/v1/download/{id}`, with authorization, a `302` to the file, an atomic counter and no DRM claim
(ADR 0013). The product then grew the sections the legacy theme sold — an album page, a video page, an
artist page with a photo gallery — and the owner's request was blunt: **every section offers something to
play and something to download** (2026-10-08).

Three ways to answer that were possible:

1. a route per type (`/download/track/{id}`, `/download/album/{id}`…), each with its own access rules;
2. the theme reading the post meta directly and printing the file URL — the shortest path, and the one
   that gives away the file, skips the counter and duplicates the rules in two artifacts;
3. one route, one resolver, one authorization decision, four kinds of file.

The theme cannot read a track's file meta without breaking the boundary the architecture gate enforces
(R3: the theme uses the plugin's public API only), and rule 2 is exactly why a site owner cannot tell today
which of their files is public: the legacy theme linked `wp-content/uploads/…` directly.

## Decision

1. **One route, four kinds.** `wavira/v1/download/{id}` serves a **track** (its audio at 128 or 320 kbps),
   an **album** (its own master file), a **video** (the *hosted* file at 480/720/1080) and an **image**
   (a cover or a gallery photo). The route and the response shape are unchanged from ADR 0013; the JSON
   now also carries `type`, `quality` and a translated `label`.
2. **A quality is the number a visitor recognises** — kbps for audio, the frame height for video — and the
   label is that number plus its unit through the catalogue (`%d kbps`, `%d pixels`). The theme never
   builds a unit by concatenation; it prints the label the plugin resolved, so a Persian site reads
   «۳۲۰ کیلوبیتبرثانیه» and a video reads «۷۲۰ پیکسل».
3. **`Downloads\Sources` is the only resolver**: `type()`, `qualities()`, `meta_key()`, `available()`,
   `resolve()`, `quality_label()`, `url()`. A site that keeps its files under its own meta key plugs in
   through the `wavira_download_meta_key` filter instead of forking the resolver.
4. **`Access::allows()` is the only authorization decision**, and it answers for all four kinds: the post
   must be published, global downloads must be on (`downloads_enabled`), the file must exist for the type,
   the per-post opt-out (`wavira_download_enabled`, which covers albums and videos as well as tracks) must
   be respected, and the site's login rule (`downloads_require_login`) still applies. Two consequences are
   deliberate:
   * **an embed is never downloadable** — a YouTube or Aparat post has no file to hand out, so the button is
     not printed at all rather than printed and broken;
   * **an image is downloadable only when its parent is published** — cover art is content, a private file
     is not.
5. **Failure is honest**: a post with no file answers `404`, a forbidden post answers `401` (login required)
   or `403` (opted out), and `wavira_download_served_post` fires after a successful delivery so a counter,
   a log or a licence checker can hook it. `wavira_download_access_post` filters the decision for
   membership plugins, exactly as `wavira_download_access` always has for tracks.
6. **The theme prints a link, or nothing.** `wavira_get_download()` and the `wavira/download` block ask
   `wavira_core_can_download()` before printing anything: **no file, no link** — in the hero of a track, an
   album and a video, in every tracklist row, and under every gallery photo. A dead download link is worse
   than no download link, and on a fresh install “no file” is the normal state.
7. **The public API is the contract**: `wavira_core_download_url()`, `wavira_core_can_download()`,
   `wavira_core_download_qualities()`. The theme's `link` variant exists for a tracklist row, `button` for
   a hero, `list` for a page that offers several qualities, and a third-party theme can use all three
   without knowing a meta key.

## Consequences

* The counter stays where it was (per quality, atomic, never REST-exposed) and now counts an album master
  or a hosted video the same way it counted a track.
* Album and video pages finally *do* something with the files the plugin already stored: `wavira_album_audio_128`
  and `wavira_video_720` were editable in the admin and unreachable from the front end.
* A gallery photo has a download link of its own, which is the one download the legacy theme never offered —
  and the lightbox (ADR 0024) hands out the same full-size file.
* The demo has to *have* media for any of this to be visible, which is what ADR 0024 is about.
* What this is not: DRM. A public audio file can be saved by anyone who can play it, and the ADR 0013
  wording — *authorization, never a DRM claim* — still governs the admin copy.
