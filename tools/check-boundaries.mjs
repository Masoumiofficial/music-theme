#!/usr/bin/env node
/**
 * Module-boundary gate (ARCHITECTURE.md §2, ADR 0002).
 *
 * The architecture states rules that nothing was checking. This script turns
 * them into a gate that runs in `tools/lint.sh` and in CI, with no dependencies
 * and no WordPress installation:
 *
 *   R1  Core never reaches into the theme (no template paths, no theme
 *       constants, no theme helper functions).
 *   R2  Layer direction: foundation < data < services < entry points. A file may
 *       use the same layer or a lower one, never a higher one, so a service can
 *       never depend on the REST layer and content can never depend on a
 *       controller.
 *   R3  The theme references no `Wavira\Core\*` class name: the plugin is reached
 *       through the public functions in `wavira-core/public-api.php`, actions and
 *       filters only (that is what keeps a theme switch and a plugin update
 *       independent).
 *   R4  Every product PHP file refuses direct access (ABSPATH, or
 *       WP_UNINSTALL_PLUGIN for uninstall.php).
 *   R5  Global functions of the plugin are prefixed `wavira_core_`, of the theme
 *       `wavira_` (see CODING-STANDARD.md; WPCS checks the prefixes it knows).
 *
 * Usage: node tools/check-boundaries.mjs
 * Exit code: 1 on any violation, 0 otherwise.
 */

import { readFileSync } from 'node:fs';
import { readdirSync, statSync } from 'node:fs';
import { join, relative, sep } from 'node:path';

const root = new URL('..', import.meta.url).pathname.replace(/\/$/, '');
const violations = [];

/** Recursively list PHP files under a directory. */
function phpFiles(dir) {
	const found = [];

	for (const entry of readdirSync(dir)) {
		const path = join(dir, entry);

		if (statSync(path).isDirectory()) {
			found.push(...phpFiles(path));
		} else if (entry.endsWith('.php')) {
			found.push(path);
		}
	}

	return found;
}

const themeFiles = phpFiles(join(root, 'wavira'));
const coreFiles = phpFiles(join(root, 'wavira-core'));

/** Layer of a plugin source file. Unknown `Support/` files are foundation. */
function layerOf(relPath) {
	const path = relPath.split(sep).join('/');

	if (path.startsWith('src/Contracts/')) {
		return 0;
	}
	if (/^src\/Support\/(Autoloader|Requirements|Cache)\.php$/.test(path)) {
		return 0;
	}
	if (path === 'public-api.php' || path === 'wavira-core.php' || path === 'uninstall.php') {
		return 3;
	}
	if (path.startsWith('src/Content/') || path.startsWith('src/Settings/')) {
		return 1;
	}
	if (/^src\/Support\/CacheInvalidator\.php$/.test(path) || /^src\/Support\/Cli\.php$/.test(path)) {
		return 2;
	}
	if (path.startsWith('src/')) {
		if (/^src\/(Player|Downloads|Search|Related|News|Import|Demo|Migration|Integrations)\//.test(path)) {
			return 2;
		}
		if (/^src\/(Rest|Admin)\//.test(path) || path === 'src/Plugin.php') {
			return 3;
		}
	}

	// Anything else in src/Support is foundation and usable from everywhere.
	return 0;
}

const LAYER_NAMES = ['foundation', 'data', 'services', 'entry point'];

/** `use Wavira\Core\X\Y;` → [ X/Y relative to src/ ]. */
function coreUses(source) {
	const uses = [];

	for (const match of source.matchAll(/^\s*use\s+Wavira\\Core\\([A-Za-z0-9_\\]+)/gm)) {
		const parts = match[1].split('\\');

		// A namespace import (`use Wavira\Core\Content;`) is a directory; a class
		// import is a file. Both are resolved by prefix, so the file is optional.
		uses.push(`src/${parts.join('/')}.php`);
	}

	return uses;
}

// ---------------------------------------------------------------- R1, R2, R4, R5
for (const file of coreFiles) {
	const rel = relative(root, file);
	const source = readFileSync(file, 'utf8');
	const isEntry = rel === 'wavira-core/wavira-core.php' || rel === 'wavira-core/uninstall.php';

	// R1 — Core must never depend on the theme.
	for (const pattern of [
		'get_template_directory',
		'get_stylesheet_directory',
		'get_template_directory_uri',
		'WAVIRA_THEME_VERSION',
		'wavira_icon(',
		'wavira_related_posts(',
	]) {
		if (source.includes(pattern)) {
			violations.push(`R1 ${rel}: references the theme (\`${pattern}\`) — Core must be theme-independent.`);
		}
	}

	// R2 — layer direction.
	const ownLayer = layerOf(rel.replace(/^wavira-core\//, ''));

	for (const target of coreUses(source)) {
		const targetRel = target;

		// Resolve `src/A/B.php` or a directory prefix such as `src/A`.
		let targetLayer = null;

		if (/\.php$/.test(targetRel) && coreFiles.some((f) => relative(root, f) === `wavira-core/${targetRel}`)) {
			targetLayer = layerOf(targetRel);
		} else {
			const dir = targetRel.replace(/\.php$/, '');
			const inside = coreFiles
				.map((f) => relative(root, f).replace(/^wavira-core\//, ''))
				.filter((f) => f.startsWith(`${dir}/`));

			if (inside.length > 0) {
				targetLayer = Math.max(...inside.map(layerOf));
			}
		}

		if (targetLayer !== null && targetLayer > ownLayer) {
			violations.push(
				`R2 ${rel}: ${LAYER_NAMES[ownLayer]} uses ${LAYER_NAMES[targetLayer]} (${targetRel}) — dependencies may only point downwards.`
			);
		}
	}

	// R4 — direct access guard.
	if (!source.includes('ABSPATH') && !source.includes('WP_UNINSTALL_PLUGIN')) {
		violations.push(`R4 ${rel}: no \`defined( 'ABSPATH' ) || exit;\` guard.`);
	}

	// R5 — function prefixes.
	for (const match of source.matchAll(/^[ \t]*function\s+([a-z0-9_]+)\s*\(/gim)) {
		if (!match[1].startsWith('wavira_core_')) {
			violations.push(`R5 ${rel}: function ${match[1]}() must be prefixed \`wavira_core_\`.`);
		}
	}

	if (isEntry) {
		continue;
	}
}

// ---------------------------------------------------------------------- R3, R4, R5
for (const file of themeFiles) {
	const rel = relative(root, file);
	const source = readFileSync(file, 'utf8');

	// R3 — the theme calls functions/actions, never plugin classes.
	for (const match of source.matchAll(/Wavira\\Core\\[A-Za-z0-9_\\]+/g)) {
		violations.push(
			`R3 ${rel}: references the Core class \`${match[0]}\` — use a public function from wavira-core/public-api.php instead.`
		);
	}

	// R4 — direct access guard.
	if (!source.includes('ABSPATH')) {
		violations.push(`R4 ${rel}: no \`defined( 'ABSPATH' ) || exit;\` guard.`);
	}

	// R5 — function prefixes.
	for (const match of source.matchAll(/^[ \t]*function\s+([a-z0-9_]+)\s*\(/gim)) {
		if (!match[1].startsWith('wavira_')) {
			violations.push(`R5 ${rel}: function ${match[1]}() must be prefixed \`wavira_\`.`);
		}
	}
}

console.log(`Wavira boundary gate — ${coreFiles.length} plugin file(s), ${themeFiles.length} theme file(s)`);

if (violations.length > 0) {
	for (const violation of violations) {
		console.log(`      FAIL  ${violation}`);
	}
	console.log(`RESULT: FAIL — ${violations.length} boundary violation(s).`);
	process.exit(1);
}

console.log('      OK    R1 no Core → Theme coupling, R2 layer direction, R3 theme uses public API only,');
console.log('      OK    R4 direct-access guards present, R5 global function prefixes correct');
console.log('RESULT: PASS');
