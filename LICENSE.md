# Licence — Wavira

**Product licence:** GNU General Public License v2.0 or later (GPL-2.0-or-later)
**Applies to:** `wavira/` (theme) and `wavira-core/` (plugin), including their source and built assets.

```
Wavira — WordPress music-publishing ecosystem
Copyright (C) 2026 Wavira

This program is free software; you can redistribute it and/or modify it under the terms of
the GNU General Public License as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
See the GNU General Public License for more details.
```

The canonical licence text is published at:
<https://www.gnu.org/licenses/old-licenses/gpl-2.0.txt>

## Release packaging requirement

Release packages (`dist/`) must bundle the **verbatim** GPL-2.0 text as `LICENSE` plus the notices in
`THIRD-PARTY-NOTICES.md`. This is a release-checklist item (`docs/CODING-STANDARD.md` §9) — the full
licence text is added to the repository during phase 0.9.0 packaging.

## What this licence does **not** cover

| Item | Status |
| --- | --- |
| `music-theme.zip` (legacy artifact in this repository) | **Excluded.** Proprietary/encrypted third-party files; audited but never shipped. See `docs/LICENSE-AUDIT.md`. |
| Third-party libraries bundled with the product | Covered by their own licences — see `THIRD-PARTY-NOTICES.md`. |
| Fonts, icons, demo images shipped for demonstration | OFL / MIT / own / CC0 only; provenance in `docs/DEMO-ASSET-LICENCES.md` (created in phase 0.9.0). |
| The brand name and logo "Wavira" | Trademark matter, not granted by this licence. See `docs/BRAND-DECISION.md`. |
