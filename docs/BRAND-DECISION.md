# BRAND-DECISION.md — Final Brand Lock

**Status:** APPROVED FOR PRODUCT DEVELOPMENT (Brand Lock active from 2026-10-05)
**Companion document:** `docs/BRAND-RESEARCH.md` (candidates, evidence, scoring, disclaimers)

---

## FINAL BRAND NAME
**Wavira**

## BRAND PRONUNCIATION
- English: `wuh-VEER-ah` / `wa-VEE-ra` (primary), `WAH-vee-ra` acceptable
- Persian: **واویرا** (vāvirā — written `واویرا`, read naturally as "واویرا")
- Arabic: **وافيرا**
- Syllables: 3 · Letters: 6 · No diacritics, no double letters, no ambiguous digraphs

## BRAND MEANING / CONCEPT
A coined name built from **wave** (sound wave, waveform, the physical carrier of audio) plus the
open, brandable suffix **-ira**. It reads as a modern product/company name rather than a dictionary
word: "the place where sound waves are published". Short enough for a logo mark (a waveform stroke
that folds into a `W`), neutral enough to host a theme, a plugin family, a REST namespace and — later —
a mobile app or SaaS.

## WHY THIS NAME
| Criterion | Wavira result |
| --- | --- |
| Commercial signal | No significant direct conflict found in WordPress.org themes/plugins, Envato/ThemeForest/CodeCanyon, GitHub, or music brands (see research doc §5) |
| Vertical fit | Contains the audio root "wave" without being the banned bare word `Wave`/`Audio`/`Music` |
| Pronounceability | Clean in English, Persian and Arabic; no `th`, no `q`, no letter that breaks RTL rendering |
| Memorability | 3 syllables, 6 letters, one obvious short form (`WAV`) |
| Domain band | `.com`, `.net`, `.org`, `.io` all returned *no DNS record* on 2026-10-05 (strongest band in the study; still requires registrar/WHOIS verification) |
| Extensibility | Works as `Wavira Music`, `Wavira Core`, `Wavira Player`, `Wavira Studio`, `Wavira/v1` |
| Risk profile | Lowest worst-case risk score among all finalists (1/5, see research doc §7) |

**Not selected, and why:** Cadenza (commercial ThemeForest theme of the same name), Melodyx (live
music-brand conflicts), Eufona (`.com` is a premium for-sale listing + Eufonia clothing brand), Soniva
(≥4 companies including an audio-hearing brand), Hertzva (Hertz® adjacency), Lyrisma (strong
runner-up, but the `Lyr-` prefix is crowded in the exact vertical: Google DeepMind *Lyria*, *Lyrion
Music Server*, *Lyrist*, *Lyrica*), Ostinata (near-identical *Ostinato* music software), Klangra
(music-vertical phonetic crowding: Klang/Klanga/Klingra), Timpania (lower brandability/logo potential).

## BRAND SCORE
**91 / 100** (model in `BRAND-RESEARCH.md` §6)

## DOMAIN STATUS
- `wavira.com`, `wavira.net`, `wavira.org`, `wavira.io` → **no DNS record returned** on 2026-10-05.
  This is a positive *signal*, **not proof of availability** — DNS absence ≠ unregistered, and
  wildcard-free verification was done but registrar check was not.
- `wavira.shop` is in use by a small unrelated Shopify store (2024) → watch item only.
- **Action before purchase/launch:** registrar + WHOIS verification, then acquire `.com` first,
  `.io`/`.net` defensively if budget allows.
- **Never state "the domain is available" until the registrar says so.**

## WORDPRESS CONFLICT
None found. `wordpress.org/themes` search returned no `Wavira`; no plugin with this slug was found.
Post-lock action: check `wordpress.org/plugins/search/wavira` again immediately before submitting the
theme, because theme/plugin slugs are first-come-first-served.

## GITHUB CONFLICT
No repositories, packages or orgs named `wavira` were found. Post-lock action: reserve the GitHub org/
repo namespace (`wavira-music`) before the first public push.

## MARKETPLACE CONFLICT
No ThemeForest / Envato / CodeCanyon / TemplateMonster item named `Wavira` was found.

## SOCIAL CONFLICT
No active brand-level accounts found on Instagram/X/YouTube/Facebook/LinkedIn/TikTok. Post-lock
action: register handles (at minimum `@wavira`, `@waviramusic`) in the launch week.

## TRADEMARK SCREENING
No direct exact match was surfaced in the searches performed. Adjacent-lookout list (monitor, do not
copy): Wave/Wavely-type marks in audio, Wavira surname usage (personal, not commercial-class).
> **Preliminary screening only — professional trademark clearance is still recommended.** Commission
> clearance in classes 9 (software) and 42 (SaaS/dev services), plus 41 if licensing/education
> services are added, in IR/EU/US as markets require.

## RISK LEVEL
**LOW**

## DECISION
**APPROVED FOR PRODUCT DEVELOPMENT**

---

# Brand Architecture (locked)

```
Brand:              Wavira
Theme:              Wavira Music
Theme folder slug:  wavira
Core plugin:        Wavira Core            (slug: wavira-core)
Player module:      Wavira Player          (inside Core)
Text domain:        wavira                 (theme)
                    wavira-core            (plugin)
PHP namespace:      Wavira\Theme\…         (theme)
                    Wavira\Core\…          (plugin)
PHP prefix:         wavira_               (functions/vars)
Constants:          WAVIRA_*
CSS prefix:         .wavira-*
CSS variables:      --wavira-*
JS namespace:       window.Wavira          (no more global jQuery soup)
REST namespace:     wavira/v1
Option keys:        wavira_settings, wavira_player_settings
Meta prefix:        wavira_                (new meta only — never rewrites legacy meta)
GitHub repository:  wavira-music           (new repo; this legacy repo stays as-is)
Docs product name:  Wavira Documentation
Support/product:    wavira.dev / wavira.com (pending registrar verification)
Author / vendor:    Etehad WP — اتحاد وردپرس · https://etehadwp.com/
                    (theme + plugin headers, composer/package metadata, docs credits)
```

## Vocabulary rules after Brand Lock
1. The legacy names are **never** public brand copy again. Any use is classified first:
   `PUBLIC BRAND` (must be removed from all shipped UI/copy), `INTERNAL IDENTIFIER` (rename in new
   code), `DATABASE KEY` (never rename blindly — migration only), `HOOK`/`CSS CLASS`/`FUNCTION`
   (replace during reimplementation), `URL` (replace), `LICENSE`/`THIRD-PARTY` (keep verbatim, never
   rebrand someone else's notice).
2. Tokens to neutralize in the new product (no blind global replace — each classified per the rule above):
   `Javan Seda`, `جوان صدا`, `javanseda`, `tarlanweb`, `tarlanweb.ir`, `rkianoosh`, `rkianoosh.ir`,
   `Reza Kianoosh`, `rezakianoosh@gmail.com`, `09158856205`, `rtl-theme.com`, `Rtl-Theme.com`,
   `RTL_License_41d6c5f704e3a746`, `option_tree`* (settings option — migration-only rewrite),
   `tarlanweb_ir_*` widget IDs, `tarlanweb_center`/`tarlanweb_side_box`/`tarlanweb_post_ft` CSS classes,
   `rkianoosh_*` CSS classes, `myfunctions.php`, `shapeSpace_script_loader_tag`, `meks_disable_*`.
3. `*` = database keys are **read-only** in the new product; migrating tools may read them, nothing new
   may write them.

## Naming gate — closed
The following are now first-come-first-served and must be verified at purchase time, in this order:
1. Registrar check + acquire `wavira.com` → 2. `wordpress.org` theme/plugin slug check →
3. GitHub org/repo → 4. social handles → 5. professional trademark clearance filing.
No logo, screenshot, text-domain or marketplace branding was produced before this decision — the gate
was respected.
