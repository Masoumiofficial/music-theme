# ADR 0009 — Accessibility and performance as merge gates

- **Status:** Accepted
- **Date:** 2026-10-05
- **Phase:** 0.2.0
- **Related:** `UX-AUDIT.md` (scorecard), `PERFORMANCE-AUDIT.md` §3, brief §35, §49

## Context

The legacy theme fails WCAG on its core interaction (mouse-only player and hover-only menus), relies
on UA sniffing and `srcset` disabling, and has no performance budget. Accessibility and speed are
explicit commercial requirements for this product, and they are cheap to enforce early and expensive
to retrofit.

## Decision

1. **WCAG 2.2 AA is a merge gate.** A component/template is not complete until: keyboard-operable
   (tab order, focus visible, ESC/Enter semantics), ARIA-correct (names, roles, states, live regions
   where needed), contrast-verified in both light and dark themes, usable at 400 % zoom / 320 px width,
   and free of motion for users with `prefers-reduced-motion`.
2. **Performance budget is a merge gate** (from `PERFORMANCE-AUDIT.md` §3): CSS ≤ 25 KB gzipped
   critical, non-player JS ≤ 30 KB gzipped, player bundle ≤ 15 KB gzipped and loaded only where needed,
   LCP ≤ 2.5 s on 4G mid-tier Android, CLS ≤ 0.05, INP ≤ 200 ms, ≤ 20 queries per music page with a
   warm object cache, zero `posts_per_page => -1`, zero `srcset` disabling.
3. **Verification is part of the phase**, not a separate QA stage: each phase's exit criteria include
   a documented a11y pass (keyboard-only walkthrough + screen-reader smoke test on the changed views)
   and a performance check (Query Monitor + Lighthouse on the changed templates; CI bundle-size report
   on every change).
4. **Regression protection:** once a template passes, its checks are recorded in `docs/QA.md` with the
   date and tool versions; later changes must re-run the same checks for that view.
5. **No "we'll fix it before release" exceptions.** If a feature cannot meet the gates yet, it ships
   behind a flag or not at all — accessibility and speed are product features, not polish.

## Consequences

- Some legacy-inspired UI patterns (autoplay carousels, hover menus, icon-only controls) are
  redesigned rather than reproduced.
- Third-party libraries must justify their size and must not break keyboard navigation; anything
  larger than 10 KB gzipped requires an ADR-level note in the PR.
- The Definition of Done in `CODING-STANDARD.md` T4 becomes objectively checkable per phase.
