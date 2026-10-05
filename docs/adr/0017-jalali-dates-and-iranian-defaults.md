# ADR 0017 — Jalali dates and Iranian defaults

- **Status:** Accepted
- **Date:** 2026-10-05
- **Phase:** 0.10.1
- **Related:** ADR 0008 (i18n and RTL-first), ADR 0009 (accessibility and performance gates), ADR 0015
  (§6 and §8 are amended by this ADR), ADR 0016 (SEO cooperation — structured data keeps Gregorian),
  `wavira-core/src/Content/Jalali.php`, `wavira-core/src/Content/Dates.php`,
  `wavira-core/src/Admin/Cli.php`, `tests/test-jalali.php`, `tools/preview/`, `THIRD-PARTY-NOTICES.md`

## Context

The product is sold to Persian-language sites, and the demo it ships is a Persian music site. Persian
sites read dates in the **Jalali (Shamsi)** calendar: a news card that says "January 5, 2026" is a
foreign artifact, and `fa_IR` locale data does not help — WordPress's own `WP_Locale` and `wp_date()`
are Gregorian with translated month names, nothing more. Persian numerals and Persian month names on a
Gregorian grid are still the wrong grid: the year is off by 621 and the month numbering is different.

ADR 0015 §8 deferred this on purpose: an unverified conversion puts a wrong date on every page, and a
wrong date is worse than a Gregorian one. That deferral named its own exit condition — a tested change
with verified anchors. This is that change.

Two product surfaces are affected, and they pull in opposite directions:

1. **Presentation.** Cards, archives, artist pages, the lyrics page header — every human-readable date
   should be Jalali with Persian digits.
2. **Data.** `<time datetime>` attributes, JSON-LD `datePublished`, REST payloads, feeds, sitemaps and
   the editor must stay ISO Gregorian: they are read by machines, and a Jalali date there is a bug
   (ADR 0016).

The core Query Loop's `core/post-date` block is the seam that makes a single rule possible: core
renders it through `wp_date( $format, $post_timestamp )`
(`wp-includes/blocks/post-date.php` → `render_block_core_post_date()`), so a `wp_date` filter
localises both the product's own markup and the news grid a site builds itself.

## Decision

1. **Own converter, no runtime dependency.** `Wavira\Core\Content\Jalali` is a PHP port of the
   **Borkowski** algorithm as published in [`jalaali/jalaali-js`](https://github.com/jalaali/jalaali-js)
   (MIT). The algorithm is arithmetic, not a dependency: no Composer package, no JavaScript, no
   network. The MIT notice travels in `THIRD-PARTY-NOTICES.md` (ADR 0010).
2. **Accuracy is bounded and honest.** The conversion is accurate for Jalali years **1178–1633**
   (≈1799–2254 Gregorian). Input outside the table is *clamped* to the boundary rather than extrapolated,
   so a bad timestamp degrades to a date that exists instead of inventing one.
3. **The anchor set is the contract.** `tests/test-jalali.php` pins the conversions against dates that
   are matters of public record — Nowruz 1400–1405 (2021-03-21 … 2026-03-21), 2025-03-20 → 1403/12/30
   (1403 is leap), 1979-02-11 → 1357/11/22, 2000-01-01 → 1378/10/11 — plus a **forty-year day-by-day
   round trip**, the leap-year rule, month lengths, and the Saturday-first weekday order.
   *Why so strict:* the first version of this class had `mod( div( $i, 153 ), 4 )` where the algorithm
   requires `12`; the anchors caught it (9 800 round-trip failures over 1979–2019). A calendar without
   anchors is a guess.
4. **One policy class decides, and it decides from the locale.** `Dates::style()` returns `jalali` when
   `get_locale()` starts with `fa`, and `gregorian` for anything else. A Persian site that already runs
   another Jalali plugin sets the `wavira_core_date_style` filter to `gregorian` and the product steps
   aside — two converters must never both touch `wp_date`.
5. **The seam is `wp_date()` plus the four post-date functions.** `Dates::register()` filters `wp_date`,
   `get_the_time`, `get_the_modified_time`, `get_the_date` and `get_the_modified_date`. It is wired from
   `ContentModule::register()`, so the calendar is switched on by the plugin rather than by a template.
6. **Never convert a machine surface.** Formats that carry data (`c`, `U`, `r`, `Y-m-d`, `Ymd`,
   `Y-m-d H:i:s`, `Y-m-d\TH:i:sP`, `d/m/Y`), formats that contain a time token (a Jalali day would swallow
   the time), and any request in the admin, REST, AJAX, cron, a feed or `robots.txt` are returned
   untouched. Persian `fa_IR` output in the admin stays Gregorian on purpose: the editor, the REST API and
   a site's exports are compared against ISO dates by every integration a site owner uses.
7. **Conversion is idempotent.** The label is recomputed from the timestamp (`wp_date( 'Y-n-j' )`, or the
   post's own local date via `get_post_time()`), never parsed from the incoming string, so a value that
   passes through two core filters lands on the same label twice.
8. **Persian numerals are part of the style, not a separate switch.** `Dates::digits()` maps `0-9` to
   `۰-۹` for a Persian locale and is applied to the label it produces; the product's own numeric output
   (`240 kbps`, durations, counts) is translated through the same helper where a template needs it.
9. **Format and label are filterable, with defaults that taste right.** `wavira_core_date_format`
   (default `j F Y` → «۱۳ مهر ۱۴۰۵») and `wavira_core_date_label` for a last word on the string. Persian
   month and weekday names live in the class as **calendar data** (constants), not as interface strings:
   a Persian date is written the same way in every language, and the `.pot` must not grow twelve msgids
   that no translator would ever change.
10. **Iranian defaults are applied where a default belongs — the seeder, not the front end.** A Persian
    demo (`wp wavira seed`) sets `fa_IR`, `Asia/Tehran`, `start_of_week = 6` (Saturday), `date_format
    = j F Y`, `time_format = H:i`, a Persian `blogdescription` and a `primary` menu with Persian labels
    (خانه، آهنگها، آلبومها، هنرمندان، ویدیوها، سبکها). `--english` seeds the neutral fixture and leaves
    the site alone; `--no-site` seeds content only. **Nothing is changed on the front end at runtime** —
    no option writes, no locale switching, no timezone mutation.
11. **Demo content is Persian content.** The seeder's default catalogue is written in Persian literals
    (content is data, not interface copy, so it is not qualified for translation and never lands in the
    catalogue of interface strings), and its lyrics and biography are generated for the demo (ADR 0010).
    The English fixture keeps its translatable strings, so `--english` remains the neutral developer
    fixture and the existing `fa_IR` translations still cover it.
12. **The developer harness is Persian too.** `tools/preview/` renders the Persian demo (`dir="rtl"`,
    `lang="fa-IR"`, Persian titles, Jalali dates on the cards and Persian player strings read from the
    shipped `wavira-core` catalogue), because a harness that judges an RTL/Persian product in English
    only proves the Latin half.

## Consequences

- **Persian sites are Persian.** News cards, artist pages, archives, album release dates and the lyrics
  page print Shamsi dates with Persian numerals; the same templates print Gregorian on any other locale,
  and no template contains a calendar branch.
- **The demo is the product.** `wp wavira seed` produces a Persian music site out of the box, and
  `tools/preview/` shows it without a WordPress install.
- **The cost is a second calendar in the test suite.** `tests/test-jalali.php` is the only place allowed
  to hard-code Jalali values; every other test uses whatever the policy returns, so a future format
  change fails in one file.
- **Structured data stays Gregorian** (`datePublished`, `<time datetime>`, sitemaps, feeds): SEO
  correctness does not depend on the display calendar. A Jalali `datetime` would be invalid HTML.
- **Two Jalali converters in one site is a misconfiguration the product detects.** The style filter is
  documented as the escape hatch for it.
- **`fa_IR` locale files are no longer load-bearing for dates.** Month names come from the class, so a
  site missing its `.mo` still prints a correct Persian date.
- **Open:** the Hijri Qamari (lunar) calendar is out of scope; Persian sites that need it run a dedicated
  plugin together with `wavira_core_date_style` = `gregorian`. Week numbers (`W`) and ISO week output are
  not localised — no product surface prints them.
