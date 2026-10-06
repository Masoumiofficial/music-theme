# Persian localisation — what a Persian site gets, and how to change it

Wavira is built Persian-first for the Iranian market: the interface, the admin screens, the demo
content and the **calendar**. This document is the operator's and integrator's view of that; the
decisions behind it are [ADR 0015](adr/0015-persian-first-localisation.md) (catalogues) and
[ADR 0017](adr/0017-jalali-dates-and-iranian-defaults.md) (calendar and Iranian defaults).

> **Status (0.10.1, verified).** CI runs `37438117893` (push) and `37438125487` (pull request) are green:
> all 8 jobs, `OK (134 tests, 1090 assertions)` on PHP 7.4 and 8.2. The calendar's anchors, its policy and
> its re-entrancy guard are executed by `tests/test-jalali.php`; the claims and their evidence are listed
> in [VERIFICATION.md](VERIFICATION.md).

## 1. What ships translated

| Surface | Source |
| --- | --- |
| Theme interface (front end, patterns, template parts) | `wavira/languages/fa_IR.{po,mo}` |
| Admin screens, post-type/taxonomy labels, settings, CLI output, REST argument descriptions | `wavira-core/languages/fa_IR.{po,mo}` |
| Block titles/descriptions/keywords in the editor | the same catalogues, via core's i18n schema |
| Player controls (play, queue, repeat, errors, Media Session labels) | the `wavira-core` catalogue, printed into the page by PHP |

Both `.po` and `.mo` are committed, and `[FA]` in `tools/lint.sh` fails the build if a source string
has no Persian translation, if the placeholders of a translation differ from its source, if the `.pot`
is stale, or if the `.mo` does not match the `.po`. After changing a string:

```bash
npm run i18n:extract   # regenerate the .pot from the sources
npm run i18n:build     # merge into the .po and compile the .mo
npm run i18n:check     # the same gate CI runs
```

## 2. The calendar

Dates on the front end follow the site language:

| Site locale | Calendar | A date prints as |
| --- | --- | --- |
| `fa_IR`, `fa_AF`, … | **Jalali (Shamsi)** | «۱۳ مهر ۱۴۰۵» |
| anything else | Gregorian (the site's own `date_format`) | `October 5, 2026` |

The conversion is accurate for Jalali years 1178–1633 (≈1799–2254); outside that range a date is clamped
to the boundary instead of being extrapolated. The anchor set that guards it — Nowruz, the Islamic
Revolution, a leap Esfand, a forty-year round trip — lives in `tests/test-jalali.php`.

The conversion is also **re-entrancy safe**: formatting a date asks WordPress to format it, and WordPress
runs these very filters while it does that, so a nested call returns WordPress's own output instead of
converting a second time. An empty format is resolved to the option of its own hook (`date_format` for
`get_the_date()`, `time_format` for `get_the_time()`), so a time never becomes a date. Both rules are
asserted in `Test_Jalali::test_conversion_does_not_recurse()` — the missing guard is what hung the 0.10.1
integration jobs.

### What is never converted

| Surface | Why |
| --- | --- |
| `<time datetime="…">`, `datePublished`, sitemaps, feeds | machine-readable, must stay ISO 8601 |
| REST API responses, the block editor, list tables | integrations and the editor compare ISO dates |
| Formats with a time part (`F j, Y g:i a`) | a Jalali day would swallow the time |
| Admin, AJAX, cron, `robots.txt` | not the customer-facing surface |

### Changing the format or switching the calendar off

```php
// «دوشنبه ۱۳ مهر ۱۴۰۵»
add_filter( 'wavira_core_date_format', fn() => 'l j F Y' );

// A site that already runs another Jalali plugin: let that one own the calendar.
add_filter( 'wavira_core_date_style', fn() => 'gregorian' );

// Last word on the string (e.g. a Hijri Qamari label for a special page).
add_filter( 'wavira_core_date_label', fn( $label ) => $label, 10, 4 );
```

Templates never branch on the calendar. They call the public API:

```php
echo esc_html( wavira_core_date_label( get_post_timestamp() ) );  // localised, digit-converted
$style = wavira_core_date_style();                               // 'jalali' | 'gregorian'
```

Supported format tokens for the Jalali style: `Y y n m F j d l`, with `\` escaping a literal
(e.g. `'j F Y'`, `'Y/m/d'`, `'d\ی'`). Unknown characters are printed as written, so a format that was
meant for `wp_date()` never silently changes meaning.

## 3. Iranian defaults

Applied by `wp wavira seed` (not at runtime, and never to a site that already has content):

| Setting | Value | Why |
| --- | --- | --- |
| `WPLANG` | `fa_IR` | the interface, the editor and the admin are Persian on install |
| `timezone_string` | `Asia/Tehran` | Iranian publication times |
| `start_of_week` | `6` (Saturday) | the Iranian week starts on Saturday |
| `date_format` / `time_format` | `j F Y` / `H:i` | a 24-hour clock and a Jalali-shaped format |
| `blogdescription` | «انتشار موسیقی روی سایت خودتان» | only when nobody wrote one |
| `primary` menu | خانه، آهنگها، آلبومها، هنرمندان، ویدیوها، سبکها | every item points at a real archive |

```bash
wp wavira seed                  # Persian demo + Iranian site defaults
wp wavira seed --english        # the neutral English fixture, site untouched
wp wavira seed --no-site        # content only: no options, no menu
wp wavira seed --force          # seed again even though tracks exist
```

The seeded demo is one artist (آرمان راد), two releases — an album (شبهای تهران، ۲۰۰۵-۱۲-۲۰ →
۲۹ آذر ۱۴۰۴) and a single (باران بهاری) — four tracks with generated Persian lyrics, three genres
(پاپ رؤیایی، راک تجربی، امبینت) and a live video. No audio file and no image is seeded: a fabricated
media URL that 404s is worse than no URL, and the demo's artwork/playback live in `tools/preview/`.

## 4. Persian numerals

`Dates::digits()` maps `0-9` → `۰-۹` for a Persian locale and is applied to every date the product
renders. Any other numeric output a template prints (bitrates, counts, durations) goes through
`wavira_core_digits()` in the theme's helper layer, so a Persian site never mixes numeral systems.
Latin digits stay in code, slugs, IDs, admin form fields, JSON and machine output.

## 5. Working on the Persian UI

- **RTL is a build gate, not a review habit.** `tools/check-css.mjs` rejects physical direction
  properties (`left`, `right`, `margin-left`, `text-align: left`, …); use logical ones.
- **The harness is Persian.** `node tools/preview/serve.mjs` renders a `dir="rtl"`, `lang="fa-IR"` page
  with Persian demo content, Jalali dates in the cards and the Persian player strings from the shipped
  catalogue — so a layout or contrast change is judged against the interface the customer buys.
- **Contrast is measured on the dark and light palettes** in RTL and LTR (`tools/check-contrast.mjs`),
  and Persian text is taller: the token set's line-heights are set for it.
- **Fonts.** The token stack prefers Vazirmatn (OFL-1.1) with system fallbacks; no font file is bundled
  yet (see `THIRD-PARTY-NOTICES.md`).

## 6. Adding another locale

Drop `xx_XX.po` next to `fa_IR.po` in either artifact and run `npm run i18n:build`; the gate treats every
`.po` it finds identically, and the calendar follows the locale automatically (`fa*` → Jalali, anything
else → Gregorian). A locale whose language needs a different calendar gets a filter, not a fork.
