#!/usr/bin/env bash
#
# Wavira — quality gate.
# Runs every check that can run without a WordPress installation:
#   1. PHP syntax            (skipped with a notice when PHP is not installed)
#   2. PHPCS                 (skipped when vendor/bin/phpcs is missing)
#   3. JS syntax             (node --check on every .js/.mjs, excluding node_modules/vendor/dist)
#   4. JSON validity         (every .json in the product folders)
#   5. PHP class references  (every Class::method() / new Class() resolves — tools/check-class-refs.py)
#   6. Legacy-echo gate      (forbidden legacy patterns — see tools/README.md)
#   7. Module boundaries     (ARCHITECTURE §2: Core → Theme coupling, layer direction,
#                             theme uses the public function API only, access guards,
#                             global function prefixes — tools/check-boundaries.mjs)
#   8. Asset-size report     (informational; budget enforced in the release checklist)
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
say "[1/8] PHP syntax"
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
say "[2/8] WordPress Coding Standards (PHPCS)"
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
say "[3/8] JavaScript syntax"
if command -v node >/dev/null 2>&1; then
  JS_FAIL=0
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
say "[4/8] JSON validity"
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
say "[5/8] PHP class references"
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

# ------------------------------------------------------------ 6. Legacy-echo gate
say "[6/8] Legacy-echo gate"
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
say "[7/8] Module boundaries (ARCHITECTURE §2)"
if node tools/check-boundaries.mjs; then
  :
else
  FAIL=1
fi

# ------------------------------------------------------------ 7. Asset-size report
say "[8/8] Asset-size report (informational)"
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
