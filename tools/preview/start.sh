#!/usr/bin/env bash
#
# Start the live preview: WordPress 6.7.2 + the theme and the plugin from this
# working tree, seeded with the Persian demo, on 0.0.0.0:8080.
#
# Why a script and not a documented set of flags: the preview is the only place a
# change to a template, a stylesheet or a REST route is seen as a visitor sees it,
# and the sandbox that hosts it is wiped between sessions — a preview that takes
# six remembered flags to come back is a preview nobody brings back. Everything it
# needs lives in this directory, and the WordPress core it mounts is downloaded
# once into `$WP_DIR` (outside the repository, so it is never packaged).
#
# Usage:  bash tools/preview/start.sh [--port=8080] [--wp-dir=/home/user/wp-core]
#
set -euo pipefail

PORT=8080
WP_DIR=/home/user/wp-core
WP_VERSION=6.7.2

for arg in "$@"; do
	case "$arg" in
		--port=*) PORT="${arg#*=}" ;;
		--wp-dir=*) WP_DIR="${arg#*=}" ;;
		*) echo "unknown argument: $arg" >&2; exit 2 ;;
	esac
done

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

if [ ! -f "$WP_DIR/wp-load.php" ]; then
	echo "· WordPress ${WP_VERSION} is not in ${WP_DIR} — cloning it (once)"
	rm -rf "$WP_DIR"
	git clone --depth 1 --branch "$WP_VERSION" https://github.com/WordPress/WordPress.git "$WP_DIR" >/dev/null 2>&1
	rm -rf "$WP_DIR/.git"
fi

# The one file Playground needs and a real host does not: see its own docblock.
mkdir -p "$WP_DIR/wp-content/mu-plugins"
cp "$ROOT/tools/preview/wavira-preview.php" "$WP_DIR/wp-content/mu-plugins/wavira-preview.php"

# A cached copy of the CLI makes a restart a five-second job instead of a
# network round trip (and survives the sandbox's flakier moments):
#   npm install --no-save --prefix /home/user/.wp-cli @wp-playground/cli@latest
PLAYGROUND_BIN="${WP_PLAYGROUND_BIN:-/home/user/.wp-cli/node_modules/.bin/wp-playground-cli}"

if [ ! -x "$PLAYGROUND_BIN" ]; then
	echo "· no cached CLI at ${PLAYGROUND_BIN} — falling back to npx"
	PLAYGROUND_BIN="npx --yes @wp-playground/cli@latest"
fi

echo "· theme:   $ROOT/wavira"
echo "· plugin:  $ROOT/wavira-core"
echo "· core:    $WP_DIR"
echo "· cli:     $PLAYGROUND_BIN"
echo "· url:     http://localhost:${PORT}/"

exec $PLAYGROUND_BIN server \
	--port="$PORT" \
	--php=8.2 \
	--login \
	--wordpress-install-mode=install-from-existing-files \
	--mount-before-install="$WP_DIR:/wordpress" \
	--mount="$ROOT/wavira:/wordpress/wp-content/themes/wavira" \
	--mount="$ROOT/wavira-core:/wordpress/wp-content/plugins/wavira-core" \
	--blueprint="$ROOT/tools/preview/blueprint.json"
