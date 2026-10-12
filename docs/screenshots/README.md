# Rendered screenshots (generated)

These files are written by the `wp-render` CI job through `tools/screenshot.mjs`, which renders the
theme on a real WordPress with the Persian demo seeded — the one kind of evidence this repository
cannot produce from source.

| File | What it shows |
| --- | --- |
| `../screenshot.png` | the front page in light mode: latest albums, the catalogue player, latest tracks, videos, the news — the same image the marketplace asks for, which is why it is not duplicated here |
| `album.png` | an album page: cover, tracklist, durations, the play button |
| `artist.png` | an artist profile: works by type, biography, social links |
| `home-dark.png` | the same front page with `prefers-color-scheme: dark` |

They exist so a review does not need a running site, and so a visual regression is visible in a diff.
Re-render with:

```bash
node tools/build.mjs
wp wavira seed --force            # on the site you are capturing
node tools/screenshot.mjs --url=http://example.test/ --out=wavira/screenshot.png \
  --extra=docs/screenshots --page=album=http://example.test/albums/example/ \
  --page=artist=http://example.test/artists/example/ --axe=/tmp/wavira-axe.json
node tools/check-axe.mjs /tmp/wavira-axe.json
```

A render that changes by any byte is not a failure — the layout is allowed to change; a render that
fails the size check, the HTTP check, the "the page has text on it" check, or the axe gate is.
