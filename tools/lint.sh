#!/usr/bin/env bash
#
# Wavira — quality gate.
# Runs every check that can run without a WordPress installation. Gates are named,
# not numbered, so inserting one never invalidates a reference in the docs:
#
#   [PHP]       PHP syntax          (skipped with a notice when PHP is not installed)
#   [PHPCS]     WordPress standards (skipped when vendor/bin/phpcs is missing)
#   [JS]        JS syntax           (node --check on every .js/.mjs; module syntax for
#                                    wavira-core/assets/js, because it must be valid without a build)
#   [JSON]      JSON validity
#   [REFS]      PHP class references (tools/check-class-refs.py)
#   [BLOCKS]    Block metadata / renderer / registrar / editor consistency
#   [I18N]      Translatable text and pattern references (tools/check-i18n.mjs)
#   [MAPPING]   Migration blueprint targets exist (tools/check-mapping.mjs)
#   [FA]        Persian catalogues complete and compiled (tools/i18n.mjs check)
#   [CSS]       CSS rules + size budgets (tools/check-css.mjs)
#   [CONTRAST]  WCAG 2.2 AA contrast of the documented pairs (tools/check-contrast.mjs)
#   [PERF]      Performance budgets + query discipline (tools/check-perf.mjs)
#   [LEGACY]    Legacy-echo / forbidden-pattern / jQuery gate
#   [BOUNDARIES] Module boundaries (ARCHITECTURE §2, tools/check-boundaries.mjs)
#   [SIZE]      Asset-size report (informational)
#
# Exit code 0 = all gates passed. Any failure prints the offending lines.

set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT" || exit 1

FAIL=0
SOURCES=("wavira" "wavira-core")

hr() { printf '%s\n' "------------------------------------------------------------"; }
say() { printf '%s\n' "$*"; }

hr
say "Wavira lint gate — $(date -u '+%Y-%m-%d %H:%M UTC')"
hr

# ---------------------------------------------------------------- 1. PHP syntax
say "[PHP] PHP syntax"
if command -v php >/dev/null 2>&1; then
  PHP_FILES=$(find "${SOURCES[@]}" -type f -name '*.php' \
    -not -path '*/node_modules/*' -not -path '*/vendor/*' -not -path '*/assets/dist/*' 2>/dev/null)
  if [ -z "$PHP_FILES" ]; then
    say "      no PHP files found yet — nothing to check"
  else
    while IFS= read -r file; do
      if ! out=$(php -l "$file" 2>&1); then
        say "      FAIL  $file"
        say "            $out"
        FAIL=1
      fi
    done <<< "$PHP_FILES"
    [ "$FAIL" -eq 0 ] && say "      OK    $(printf '%s' "$PHP_FILES" | wc -l | tr -d ' ') file(s) parsed"
  fi
else
  say "      SKIP  php not installed in this environment (CI runs this gate)"
fi

# ------------------------------------------------------------------- 2. PHPCS
say "[PHPCS] WordPress Coding Standards (PHPCS)"
if [ -x "vendor/bin/phpcs" ]; then
  if vendor/bin/phpcs --standard=phpcs.xml.dist -q; then
    say "      OK    PHPCS clean"
  else
    say "      FAIL  PHPCS reported issues (run vendor/bin/phpcbf to auto-fix what it can)"
    FAIL=1
  fi
else
  say "      SKIP  vendor/bin/phpcs not installed (composer install --dev)"
fi

# ------------------------------------------------------------------ 3. JS syntax
say "[JS] JavaScript syntax"
if command -v node >/dev/null 2>&1; then
  JS_FAIL=0
  # The repository is `"type": "module"`, so every .js file here is parsed as an
  # ES module: `node --check` must accept the player sources as they ship, because
  # the plugin enqueues them directly and works with no build step (ADR 0006).
  while IFS= read -r file; do
    if ! out=$(node --check "$file" 2>&1); then
      say "      FAIL  $file"
      say "            $out"
      JS_FAIL=1
      FAIL=1
    fi
  done < <(find "${SOURCES[@]}" tools -type f \( -name '*.js' -o -name '*.mjs' \) \
            -not -path '*/node_modules/*' -not -path '*/vendor/*' -not -path '*/assets/dist/*' 2>/dev/null)
  [ "$JS_FAIL" -eq 0 ] && say "      OK    all modules parse"
else
  say "      SKIP  node not installed"
fi

# --------------------------------------------------------------- 4. JSON validity
say "[JSON] JSON validity"
if command -v node >/dev/null 2>&1; then
  while IFS= read -r file; do
    if ! node -e "JSON.parse(require('fs').readFileSync(process.argv[1],'utf8'))" "$file" >/dev/null 2>&1; then
      say "      FAIL  $file is not valid JSON"
      FAIL=1
    fi
  done < <(find "${SOURCES[@]}" . -maxdepth 2 -type f -name '*.json' \
            -not -path '*/node_modules/*' -not -path '*/vendor/*' -not -path '*/assets/dist/*' 2>/dev/null | sort -u)
  say "      OK    JSON files parsed (or none found)"
else
  say "      SKIP  node not installed"
fi

# ----------------------------------------------------- 5. PHP class references
# Without a local PHP the CI suite is the only oracle for a missing import or a
# method that was never written. This static pass reads class references and
# reports any that do not resolve inside the repository — the exact class of
# defect that cost a full CI round in 0.5.0.
say "[REFS] PHP class references"
if command -v python3 >/dev/null 2>&1; then
  if python3 tools/check-class-refs.py; then
    :
  else
    say "      FAIL  unresolved class references (see above)"
    FAIL=1
  fi
else
  say "      SKIP  python3 not installed"
fi

# --------------------------------------------------------------- BLOCKS
# A block lives in four places (block.json, render.php, inc/blocks.php,
# blocks/editor.js) and a mismatch only shows up when somebody opens the editor.
say "[BLOCKS] Dynamic block consistency"
if command -v node >/dev/null 2>&1; then
  if node tools/check-blocks.mjs; then
    :
  else
    say "      FAIL  the block gate reported violations (see above)"
    FAIL=1
  fi
else
  say "      SKIP  node not installed"
fi

# ------------------------------------------------------------------ I18N
# A block template cannot run PHP, so text written inside one is frozen in
# English: the strings live in patterns and this gate keeps it that way.
say "[I18N] Translatable text"
if command -v node >/dev/null 2>&1; then
  if node tools/check-i18n.mjs; then
    :
  else
    say "      FAIL  the text gate reported violations (see above)"
    FAIL=1
  fi
else
  say "      SKIP  node not installed"
fi

# --------------------------------------------------------------- MAPPING
# docs/MIGRATION-BLUEPRINT.md is what the migration tool will be written from.
# This gate resolves every wavira_* name it promises against the implemented
# schema, so a plan cannot outlive the code it describes.
say "[MAPPING] Migration blueprint targets"
if command -v node >/dev/null 2>&1; then
  if node tools/check-mapping.mjs; then
    :
  else
    say "      FAIL  the mapping gate reported violations (see above)"
    FAIL=1
  fi
else
  say "      SKIP  node not installed"
fi

# ------------------------------------------------------------------- FA
# The product ships Persian in the repository: every translatable string must
# have a Persian translation, the POT must match the sources, and the compiled
# .mo must match the .po (WordPress loads the .mo, never the .po).
say "[FA] Persian catalogues"
if command -v node >/dev/null 2>&1; then
  if node tools/i18n.mjs check; then
    :
  else
    say "      FAIL  the catalogue gate reported violations (see above)"
    FAIL=1
  fi
else
  say "      SKIP  node not installed"
fi

# ------------------------------------------------------------------ CSS
# The rules the product promises about its stylesheets: no !important, logical
# properties only (one stylesheet for both directions), dark-mode parity for every
# palette colour, component-class parity, and the ADR 0009 size budgets.
say "[CSS] CSS rules and size budgets"
if command -v node >/dev/null 2>&1; then
  if node tools/check-css.mjs; then
    :
  else
    say "      FAIL  the CSS gate reported violations (see above)"
    FAIL=1
  fi
else
  say "      SKIP  node not installed"
fi

# --------------------------------------------------------------- CONTRAST
# WCAG 2.2 AA is a launch requirement, so the documented colour pairs are
# machine-checked in both modes instead of being asserted in prose.
say "[CONTRAST] WCAG 2.2 AA colour pairs"
if command -v node >/dev/null 2>&1; then
  if node tools/check-contrast.mjs; then
    :
  else
    say "      FAIL  a colour pair misses its WCAG threshold (see above)"
    FAIL=1
  fi
else
  say "      SKIP  node not installed"
fi

# ------------------------------------------------------------------ PERF
# The 0.10.0 budget (PERFORMANCE-AUDIT §3, ADR 0009) is machine-checked on the
# built bundles: gzipped sizes, no third-party URL, bounded queries, intact srcset.
say "[PERF] Performance budget and query discipline"
if command -v node >/dev/null 2>&1; then
  if node tools/check-perf.mjs; then
    :
  else
    say "      FAIL  a performance budget is not met (see above)"
    FAIL=1
  fi
else
  say "      SKIP  node not installed"
fi

# ------------------------------------------------------------ 6. Legacy-echo gate
say "[LEGACY] Legacy-echo gate"
LEGACY_HITS=$(grep -rniE \
  "javanseda|javan seda|جوان صدا|tarlanweb|rkianoosh|rtl-theme|rezakianoosh|09158856205" \
  "${SOURCES[@]}" --include='*.php' --include='*.js' --include='*.mjs' --include='*.css' --include='*.json' \
  2>/dev/null | grep -v '/assets/dist/' || true)

PATTERN_HITS=$(grep -rnE \
  "posts_per_page[[:space:]]*=>[[:space:]]*-1|posts_per_page=-1|wp_calculate_image_srcset|wp_is_mobile|create_function|wp_title\(|id=[\"']audio[\"']|getElementById\([\"']audio" \
  "${SOURCES[@]}" --include='*.php' --include='*.js' --include='*.mjs' 2>/dev/null | grep -v '/assets/dist/' || true)

JQUERY_HITS=$(grep -rnE "jQuery|\\\$\(" \
  "${SOURCES[@]}" --include='*.js' --include='*.mjs' 2>/dev/null | grep -v '/assets/dist/' || true)

if [ -n "$LEGACY_HITS" ]; then
  say "      FAIL  legacy brand/author tokens found:"
  printf '%s\n' "$LEGACY_HITS" | sed 's/^/            /'
  FAIL=1
fi
if [ -n "$PATTERN_HITS" ]; then
  say "      FAIL  forbidden legacy patterns found:"
  printf '%s\n' "$PATTERN_HITS" | sed 's/^/            /'
  FAIL=1
fi
if [ -n "$JQUERY_HITS" ]; then
  say "      FAIL  jQuery usage found in front-end JS (see ADR 0006):"
  printf '%s\n' "$JQUERY_HITS" | sed 's/^/            /'
  FAIL=1
fi
[ -z "$LEGACY_HITS$PATTERN_HITS$JQUERY_HITS" ] && say "      OK    no legacy echoes, no forbidden patterns, no jQuery"

# -------------------------------------------------------------- 7. Module boundaries
say "[BOUNDARIES] Module boundaries (ARCHITECTURE §2)"
if node tools/check-boundaries.mjs; then
  :
else
  FAIL=1
fi

# ------------------------------------------------------------ 7. Asset-size report
say "[SIZE] Asset-size report (informational)"
for dist in wavira/assets/dist wavira-core/assets/dist; do
  if [ -d "$dist" ]; then
    SIZE=$(du -sk "$dist" 2>/dev/null | awk '{print $1}')
    say "      $dist — ${SIZE} KB on disk"
  else
    say "      $dist — not built yet (expected before phase 0.6.0)"
  fi
done

hr
if [ "$FAIL" -eq 0 ]; then
  say "RESULT: PASS"
else
  say "RESULT: FAIL — fix the items above before committing"
fi
hr
exit "$FAIL"
