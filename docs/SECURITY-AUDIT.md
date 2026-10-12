# SECURITY-AUDIT.md — Legacy Theme Security Review

**Scope:** all readable files of the legacy theme + the bundled OptionTree copy.
**Out of scope (explicitly):** the two ionCube-encrypted files — no decryption, no bypass, no
behavioural testing. Their security impact is recorded as an un-auditable risk.
**Method:** static review of every input path, output path, capability check, nonce usage, DB access,
file access and third-party code.

---

## 1. Executive summary

| Dimension | Result |
| --- | --- |
| SQL injection risk | **None found in readable code** — the theme never touches `$wpdb` or raw SQL `[VERIFIED]` |
| CSRF risk | **None in readable code** — no forms/AJAX handlers are defined there; OptionTree's own handlers do verify nonces `[VERIFIED]` |
| Authentication/authorization | No privileged actions in readable code; **all unknown privileged behaviour inside the encrypted core is unauditable** `[BLACK_BOX_FUNCTIONALITY]` |
| Output escaping (XSS) | **Systemically weak** — dozens of unescaped echoes of options, ACF values, term meta and URLs |
| File upload/download handling | No upload handling in readable code; downloads are raw links from ACF fields |
| Secrets / phone-home | Unknown inside encrypted files `[BLACK_BOX_FUNCTIONALITY]` |
| Worst-case verdict | The theme **cannot be certified secure** as long as 158 KB of its logic is opaque; the rebuild replaces it entirely |

---

## 2. Concrete findings

### S1 — Unescaped output of admin-controlled option values (High, stored XSS surface)
`header.php` echoes option values straight into attributes and text nodes:
```php
<link rel="shortcut icon" href="<?php echo ot_get_option('favicon'); ?>">
<img src="<?php echo ot_get_option('logo'); ?>" ...>
…
<?php echo ot_get_option('ads_bt'); ?><?php echo ot_get_option('adsjs_bt'); ?>   // single.php
```
`ads_*` options are explicitly typed `javascript`/`textarea` in the schema
(`inc/theme-options.php`), i.e. **raw JS is stored and echoed**. Any account able to write theme
options (or any bug in that chain) gets arbitrary script execution on every page,
and theme-option data is also read in the front end without context-aware escaping.

**Rebuild rule:** `esc_url()` for URLs, `esc_attr()` for attributes, `wp_kses()` with an explicit
allow-list (or nothing) for ad slots; JS ad code only for `unfiltered_html`-capable roles.

### S2 — Unescaped ACF and term-meta output (High, stored XSS surface)
```php
// tag.php / taxonomy-singer.php / artist-template.php
<img src="<?php echo $image; ?>" …>                       // term meta aimg2
<a href="<?php echo get_field('afacebook', $term); ?>" …> // term meta socials
// single.php
<a class="dl_link_128" href="<?php the_field('music128'); ?>">   // post meta URL
<source src="<?php the_field('music128'); ?>" />
<?php the_field('music_text'); ?>                                // lyrics as raw HTML
```
ACF sanitises on save to some extent, but the theme performs **no** context escaping on output.
`the_field('music_text')` prints arbitrary HTML into the page body.

**Rebuild rule:** every output escaped at render time; lyrics rendered through
`wp_kses_post()` (or a stricter allow-list); media URLs through `esc_url()` and validated against
`wp_check_filetype()`/`attachment` meta where possible.

### S3 — Term/option round-trip into `target="_blank"` links without `rel` consistency (Low/Medium)
Footer social links add `rel="noreferrer noopener nofollow"`, but the same URLs in `tag.php` /
`taxonomy-singer.php` do **not** add `rel` (reverse-tabnabbing + SEO leak). Inconsistent policy.

### S4 — Whole-response output buffering (`ob_start` on `template_redirect`) (Medium)
```php
add_action('template_redirect', function(){ ob_start(function($buffer){ …str_replace… }); });
```
Rewrites every response body (fragile, can corrupt non-HTML responses such as feeds/JSON, breaks
streaming, hides PHP warnings from detection tools, and is a maintenance trap).

### S5 — Hidden H1 / keyword content injected from an option (Low/Medium, SEO + content injection)
`header.php` prints `<h1 class="h1_hidden_home">` (CSS `display:none;visibility:hidden;opacity:0`)
with the option value `head_h1` («دانلود آهنگ جدید»). Search engines treat hidden keyword blocks as
manipulation; the same value is reused as logo `alt`/`title`, i.e. a single option drives three
different outputs without escaping.

### S6 — Custom comment form/list replacing core (Low)
`myfunctions.php` ships `comment_form_cd()`/`comment_loop_cd()` (duplicates of core behaviour,
also referenced from the encrypted core). Risks: divergence from core security fixes, missing
core filters, output escaping performed ad-hoc, and inconsistent i18n. Core `comment_form()` already
carries the right nonces; a hand-rolled copy invites future breakage.

### S7 — 404 / empty-content auto-redirect via inline JS (Low)
`404.php` and `inc/no-content.php` inject `<script>` that redirects to the homepage after 15 seconds.
Besides the UX/SEO problems (soft-404 semantics), inline JS injection from a template is exactly the
shape of code that looks like malicious redirects in malware scans.

### S8 — Third-party JS with a historical `eval()` pattern (Medium, third-party)
`inc/option-tree/assets/js/vendor/jquery/jquery-ui-timepicker.js` (Trent Richardson timepicker 1.4.3)
contains `inlineSettings[attrName] = eval(attrValue)` reading `time:*` data attributes.
It is library-standard behaviour, **not** an injected backdoor, and it is admin-side only — but it is
the only `eval` in the whole tree and must not survive into the new product.

### S9 — The encrypted core (`functions.php`, `RTL_License_*.php`) (Unknown / High impact)
`[BLACK_BOX_FUNCTIONALITY]` — 158 KB of unreadable PHP that runs on every request, plus a licence
file whose behaviour (local checks, remote calls, obfuscated licensing) cannot be verified without
decryption, which is out of scope and refused by policy. **No claim of safety or unsafety is being
made about this code**; it is simply un-auditable, therefore unacceptable for a shipped product.

### S10 — No AJAX/REST/nonce/capability surface exposed by readable theme code (Positive)
Grep results: no `check_ajax_referer`, `wp_verify_nonce`, `current_user_can`, `wp_ajax_*`,
`register_rest_route`, `$wpdb`, `sanitize_*`, `esc_*`-less privileged handlers in readable files.
The only live-search feature is delegated to SearchWP (`data-swplive`) and the only admin data paths
belong to OptionTree, which does verify its nonces (see e.g. `ot-settings-api.php:996`,
`ot-meta-box-api.php:204`).

---

## 3. Requirement checklist for the rebuild

| Control | Legacy state | Required in v1 |
| --- | --- | --- |
| `validate → sanitize → escape` at every boundary | partial (ACF only) | enforced by coding standard + review checklist |
| Context-aware escaping (`esc_html/esc_attr/esc_url/wp_kses`) | mostly absent | mandatory, lint-verified where possible |
| Nonces on any state-changing request | n/a (no such code) | `wp_nonce_field`/`check_admin_referer`/REST nonce |
| Capability checks (`current_user_can`) | unknown/none visible | mandatory for settings, import/export, bulk, ad JS |
| REST hardening | none exists | permission callbacks, args schema, `sanitize_callback`, rate-aware search |
| No raw SQL without `$wpdb->prepare` | none | rule + code review |
| No direct file access | some templates lack the guard | `defined('ABSPATH') \|\| exit;` everywhere |
| No stored JS/HTML for non-privileged users | violated by `adsjs_*` | ad slots capability-gated, allow-list sanitised |
| Audio/download authorization | none | optional capability gate + non-public paths + signed URLs **without claiming DRM** |
| Privacy (cookies/localStorage) | jQuery cookie for dark mode only | documented storage keys; no tracking without consent |
| Supply chain | unauditable binary + vendored libs | no encrypted/vendored binaries; dependency list + notices |
| Error handling | raw PHP notices possible | graceful user-facing states, no raw errors ever printed |

**Audio/DRM honesty rule (from the brief):** a WordPress theme cannot absolutely prevent audio
downloading. The new product will implement *access control* (capabilities, signed URLs, server-side
authorization) and will document the limit — **no DRM claims**.
