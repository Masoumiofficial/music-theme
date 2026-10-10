# ADR 0024 — The demo generates its own media in pure PHP

* Status: Accepted
* Date: 2026-10-08
* Phase: 0.15.0
* Relates to: [0023](0023-one-download-route-four-kinds.md) (downloads need files), [0010](0010-licensing-and-third-party-policy.md)
  (assets and third-party policy), [0017](0017-jalali-dates-and-iranian-defaults.md) (the Persian demo),
  [0006](0006-asset-strategy-and-tooling.md) (no build step required to run)

## Context

The demo importer (0.10.1, ADR 0017) creates a small Persian music site: an artist, an album with tracks, a
single, a music video, genres, lyrics, durations, Jalali dates. What it never created was a **file**: covers
were empty, the player had nothing to play, and after ADR 0023 the download buttons had nothing to hand out.
A demo that claims to be a music site and cannot play a note is a demo that lies — and it is also the state
in which every screenshot, every axe run and every manual check of the download path was worthless.

The options were:

1. **ship demo media as files in the repository** (a cover, a photo, two audio files). Licensing: ADR 0010
   allows only GPL/OFL/own assets, so every byte would have to be generated or licensed, and a JPEG of
   somebody's face is exactly the asset the audit removed from the legacy theme;
2. **download them from a third party at import time** — forbidden: the product's rule is that a page makes
   no third-party request, and an Iranian host may not reach the outside world at all;
3. **generate them at import time** on the site that runs the import, with nothing but PHP.

Option 3 costs a PNG encoder and a WAV writer and needs no GD, no Imagick and no ffmpeg — extensions that a
cheap Iranian host very often does not have, and which the authoring sandbox did not have either when this
was written.

## Decision

1. **`Demo\Placeholders` writes the media in pure PHP.**
   * **`png()`** builds a real PNG byte by byte: signature, `IHDR`, an `IDAT` holding zlib-compressed
     scanlines, `IEND`, each chunk with its CRC32. The picture is a seeded vertical gradient with a banded
     overlay, so two covers on one page do not look like the same file, and the size is small (a few KB).
   * **`wav()`** writes a PCM WAV header and 8 kHz mono 16-bit samples: a tone, deliberately, because a tone
     is honest about being a placeholder. The 320 kbps take is written **louder** than the 128 kbps take, so
     the two download qualities differ audibly — a visitor who picks one can hear that it worked.
2. **Only the importer calls them.** Generation happens during `Tools → Wavira demo content` (or
   `wp wavira demo`), never on a normal request, and nothing in the theme or the REST path can trigger it.
3. **Every file becomes a real attachment**: stored through `wp_upload_bits()`, inserted as an attachment,
   marked `_wavira_demo_media`, attached to the post it illustrates (`post_parent` — the artist gallery
   *is* the attachments of the artist post) with alt text and a caption, and used as the cover meta where a
   cover belongs. A fresh import is about **23 files**.
4. **The import says how much it made.** The report counts generated files (`report['media']`), and both
   surfaces print it — the CLI summary and the admin notice
   («محتوای نمایشی وارد شد: %1$d آلبوم، %2$d قطعه و %3$d فایل ساختهشده…»). An import that claims to have
   seeded a site states how many files it wrote.
5. **Replace mode still deletes exactly what it created** (`_wavira_demo_media`), so a second import does not
   leave orphans behind and an uninstall does not take a real site's uploads with it.
6. **The demo never references a remote URL.** No placeholder service, no CDN, no embedded font: the
   generated files are the only media, and they live on the site that generated them.

## Consequences

* A fresh demo install has something to play in every section and something to download in each: the player
  has two qualities per track, the album page has its master file and a gallery of six photos, the video page
  has a hosted file, and the artist page has a portrait and a gallery.
* The `wp-render` job (ADR 0022) finally photographs a *populated* theme, which is what makes the marketplace
  screenshot honest and what turns “the download button is missing” into a failing render rather than a
  paragraph in a document.
* The demo audio is a tone and the demo photos are gradients. That is a visible limitation, and it is
  documented in the demo screen's own copy: the fixtures are labelled *Generated demo biography / replace
  with real content* and the same rule applies to the media.
* `wp_generate_attachment_metadata()` still runs for the PNGs (core understands them), so the theme's own
  image sizes exist for the covers; a WAV has no sizes to generate and is stored without metadata.
* Cost: about 60 lines of bit-twiddling for the PNG and 30 for the WAV, with no new dependency and no
  binary in the repository — the same trade ADR 0006 made for the build step.
