#!/usr/bin/env node
/**
 * Performance-budget gate (docs/PERFORMANCE-AUDIT.md §3, ADR 0009 §2).
 *
 * The budgets were written down in 0.1.0 and measured by hand ever since; a
 * promise that is not checked is a promise that drifts. This gate runs the
 * checks that need no browser and no WordPress, on the *built* files — the bytes
 * a visitor actually downloads:
 *
 *   [size]      theme CSS ≤ 25 KB gzipped, theme JS ≤ 30 KB gzipped,
 *               player bundle ≤ 15 KB gzipped, Customizer preview ≤ 10 KB
 *               gzipped, settings screen ≤ 8 KB gzipped. The player bundle is `core.js` because that is the
 *               file the plugin enqueues (the engine is part of it, ADR 0005);
 *               the preview script is measured separately because it is only
 *               ever loaded inside the Customizer frame (ADR 0020).
 *   [remote]    no `http(s)://` reference in any shipped CSS/JS: zero
 *               third-party requests by default.
 *   [lazy]      `wavira_get_image()` must not hard-code `loading="lazy"` on the
 *               core path, because that cancels core's LCP optimisation
 *               (inc/performance.php).
 *   [queries]   no unbounded `posts_per_page => -1` and no `nopaging => true`
 *               anywhere in the product sources (CODING-STANDARD W3).
 *   [srcset]    no `srcset` disabling (`'srcset' => false`, …
 *               `wp_calculate_image_srcset` returning nothing, …).
 *
 * A missing built file is reported as a skip, not a failure: an un-built
 * checkout is a developer state, and `node tools/build.mjs` fixes it.
 *
 * Usage: node tools/check-perf.mjs
 * Exit code: 1 on any violation, 0 otherwise.
 */

import { readFileSync, existsSync, readdirSync, statSync } from 'node:fs';
import { gzipSync } from 'node:zlib';
import { join, extname } from 'node:path';

const root = new URL('..', import.meta.url).pathname.replace(/\/$/, '');
const problems = [];
const notes = [];

/** Budgets in bytes, gzipped. */
const BUDGETS = [
	{ file: 'wavira/assets/dist/theme.css', kbyte: 25, label: 'theme CSS' },
	{ file: 'wavira/assets/dist/theme.js', kbyte: 30, label: 'theme JS' },
	{ file: 'wavira/assets/dist/customizer.js', kbyte: 10, label: 'Customizer preview' },
	{ file: 'wavira/assets/dist/admin.js', kbyte: 8, label: 'settings screen' },
	{ file: 'wavira-core/assets/dist/core.js', kbyte: 15, label: 'player bundle' }
];

/** Recursively list files with one of the given extensions. */
function sources(dir, extensions) {
	const found = [];

	if (!existsSync(dir)) {
		return found;
	}

	for (const entry of readdirSync(dir)) {
		const path = join(dir, entry);

		if (statSync(path).isDirectory()) {
			if (['node_modules', 'vendor', 'dist'].includes(entry)) {
				continue;
			}

			found.push(...sources(path, extensions));
		} else if (extensions.includes(extname(entry))) {
			found.push(path);
		}
	}

	return found;
}

// ---------------------------------------------------------------- 1. budgets
for (const budget of BUDGETS) {
	const path = join(root, budget.file);

	if (!existsSync(path)) {
		notes.push(`SKIP  ${budget.label}: ${budget.file} is not built (run: node tools/build.mjs)`);

		continue;
	}

	const bytes = gzipSync(readFileSync(path), { level: 9 }).length;
	const limit = budget.kbyte * 1024;
	const line = `${bytes} B gzipped of ${limit} B (${((bytes / limit) * 100).toFixed(0)}%)  ${budget.label}: ${budget.file}`;

	if (bytes > limit) {
		problems.push(`[size] ${budget.label} is over budget — ${line}`);
	} else {
		notes.push(`OK    ${line}`);
	}
}

// ------------------------------------------------------- 2. no remote assets
const shipped = [
	...sources(join(root, 'wavira/assets'), ['.css', '.js', '.svg']),
	...sources(join(root, 'wavira-core/assets'), ['.css', '.js', '.svg'])
];

for (const file of shipped) {
	const source = readFileSync(file, 'utf8');
	const remote = source.match(/https?:\/\/[^\s"'()]+/g) || [];
	const reference = remote.filter((url) => !url.includes('www.w3.org/2000/svg') && !url.includes('schema.org'));

	if (reference.length > 0) {
		problems.push(`[remote] ${file.replace(`${root}/`, '')} references ${reference[0]}`);
	}
}

notes.push(`OK    ${shipped.length} shipped asset(s) carry no third-party URL`);

// --------------------------------------------- 3. LCP-friendly image markup
const markup = readFileSync(join(root, 'wavira/inc/markup.php'), 'utf8');
const coreCall = markup.slice(markup.indexOf('wp_get_attachment_image('), markup.indexOf('$url = isset( $image'));

if (/['"]loading['"]\s*=>/.test(coreCall) || /['"]decoding['"]\s*=>/.test(coreCall)) {
	problems.push('[lazy] wavira_get_image() passes a hard-coded loading/decoding attribute on the core path');
} else {
	notes.push('OK    wavira_get_image() leaves loading/decoding/fetchpriority to core');
}

// ------------------------------------------------------- 4. query discipline
const productSources = [
	...sources(join(root, 'wavira'), ['.php']),
	...sources(join(root, 'wavira-core'), ['.php'])
];

for (const file of productSources) {
	const source = readFileSync(file, 'utf8');

	if (/posts_per_page\s*['"]?\s*=>\s*-1/.test(source)) {
		problems.push(`[queries] ${file.replace(`${root}/`, '')} asks for every post (posts_per_page => -1)`);
	}

	if (/['"]nopaging['"]\s*=>\s*true/.test(source)) {
		problems.push(`[queries] ${file.replace(`${root}/`, '')} disables paging (nopaging => true)`);
	}
}

notes.push(`OK    ${productSources.length} product source file(s) stay within the bounded-query rule`);

// ----------------------------------------------------------- 5. srcset intact
for (const file of productSources) {
	const source = readFileSync(file, 'utf8');

	if (/wp_calculate_image_srcset\s*['"]?/.test(source) || /['"]srcset['"]\s*=>\s*(false|0|null)/.test(source)) {
		problems.push(`[srcset] ${file.replace(`${root}/`, '')} disables or overrides srcset`);
	}
}

notes.push('OK    no srcset is disabled or recalculated');

// ------------------------------------------------------------------- report
for (const note of notes) {
	console.log(`      ${note}`);
}

if (problems.length > 0) {
	for (const problem of problems) {
		console.log(`      FAIL  ${problem}`);
	}

	console.log(`      ${problems.length} performance-budget problem(s)`);
	process.exit(1);
}

console.log(`      OK    ${BUDGETS.length} budget(s) met, no remote asset, query and srcset rules hold`);
