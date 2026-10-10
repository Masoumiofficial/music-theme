# ADR 0013 — Download counters, authorization and delivery

- **Status:** Accepted
- **Date:** 2026-10-05
- **Phase:** 0.4.0
- **Related:** ADR 0002 (plugin owns business logic), ADR 0003 (registered meta), ADR 0012 (relations),
  open decisions "own view counter vs. plugin integration" and "REST caching strategy" in `DECISIONS.md`,
  `FEATURE-MAP.md` F-31/F-32/F-41, `PERFORMANCE-AUDIT.md`

## Context

The legacy theme offered 128 kbps and 320 kbps downloads. Three things were unclear in the audit and
have to be decided before any code ships:

1. **Counting.** How are download totals recorded without a read-modify-write race, and without
   pretending the number is a security boundary?
2. **What a download *is*.** The legacy flow handed a file URL to the browser. The rebuild exposes a
   REST endpoint `/download/{id}`. Does that endpoint *proxy* the bytes, *gate* them behind tokens, or
   *authorize and redirect*?
3. **Where the numbers live.** Public REST payload or plugin-internal state?

Relevant constraints from earlier phases: no DRM claims and no security by obscurity
(`SECURITY-AUDIT.md`), no `posts_per_page => -1` or unbounded writes, page-cache/CDN friendliness
(`PERFORMANCE-AUDIT.md`), and "the theme is presentation; the plugin owns business logic" (ADR 0002).

## Decision

### 1. Counters are atomic, plugin-side, and never public

- Storage: registered meta on the track — `wavira_download_count` (total),
  `wavira_download_count_128`, `wavira_download_count_320`.
- Increment: **one SQL statement**, not read-modify-write:

  ```sql
  UPDATE {postmeta} SET meta_value = CAST(meta_value AS UNSIGNED) + %d WHERE post_id = %d AND meta_key = %s
  ```

  The statement runs through `$wpdb->prepare()`; the meta key is a `MetaSchema` constant, never a
  literal. A concurrent request cannot lose an increment.
- The three counter keys are registered with `show_in_rest => false`. They are **internal state**: a
  REST response never exposes a raw download number that the site owner cannot verify or correct.
  The public REST surface may expose an aggregate *only* through an explicit filter
  (`wavira_download_served` / the summary helper), and never as a claim of uniqueness or protection.
- Counting is a **plugin** responsibility. The theme renders a button and calls the endpoint; it never
  increments anything. A theme switch cannot corrupt the numbers (ADR 0002).
- A plugin bridge for an existing counter (e.g. a download-manager plugin) is allowed later behind the
  same `Counter` API — it is explicitly **not** in v1 scope.
- Every increment fires `wavira_download_counted` so integrations can mirror the number without
  reading the meta table themselves.

### 2. Delivery = authorization + HTTP redirect, never a proxy, never DRM

`GET /wavira/v1/download/{id}`:

1. resolves the requested quality through the quality matrix
   (`wavira_download_quality_matrix`, default 320 → 128 fallback) and the per-track opt-out,
2. evaluates the authorization chain in `Downloads\Access` (site setting → per-track opt-out →
   optional login requirement) and returns `403` with a machine-readable error when it fails,
3. on success returns **`302` to the stored file URL** (or the JSON envelope with that URL when the
   client asks for JSON), fires `wavira_download_served`, increments the counter, and
   `Cache-Control: no-store` on the redirect itself.

Explicitly rejected alternatives:

| Rejected | Why |
| --- | --- |
| PHP byte proxy (`readfile()`) | doubles bandwidth and memory, breaks range requests and CDNs, and buys nothing: the URL is still discoverable |
| Signed expiring tokens / hidden URLs presented as protection | security by obscurity — the audit's standing prohibition; it also breaks page cache and shared links, and the media is public marketing material by definition |
| Claiming DRM | the product is a **music publishing** product; the files are meant to be distributed. What we implement is *authorization* (who may be offered the download) and *accounting* (how often it was served) |

Recorded limitation, in the ADR itself so it cannot be forgotten: **any visitor who can play the track
can obtain the file.** The quality matrix, opt-out and login gate control the *offer*, not the
possibility. Documentation must never imply otherwise.

### 3. Caching: revisions-based generations, short TTLs for derived data

- `Support\Cache` keys are generation-scoped. Any content mutation that can change a cached answer
  bumps the generation (one option write) and the entire derived layer is invalidated atomically —
  no key-by-key bookkeeping that can miss a case.
- Derived, expensive answers get short TTLs: search 300 s, related 3600 s; both are filterable.
- REST responses contain **no per-user state on shared cache keys** (the download endpoint is the one
  exception and it is `no-store` by construction). HTTP caching stays the site's concern: we do not
  send `private`/`no-cache` on public collections, so a page cache or CDN may serve them.
- An object-cache backend is used when present (WP's own cache API); no custom cache backend, no
  additional table without a superseding ADR.

## Consequences

- Download numbers are correct under concurrency and cannot be inflated by a REST reader.
- Serving a download costs one redirect and one fast `UPDATE`; it does not turn PHP into a file server.
- The product is documented honestly: it offers *authorized downloads with accounting*, not protection
  against copying. Any future "protected downloads" feature must come with a new ADR, a threat model
  and a statement of what it does **not** stop.
- `wp wavira verify` can assert counter integrity (non-negative integers, no counter key exposed in
  REST schema) without needing a download to have happened.
