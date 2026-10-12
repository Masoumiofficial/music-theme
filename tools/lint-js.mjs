#!/usr/bin/env node
/**
 * JS-only syntax gate (used by `npm run lint:js`).
 *
 * Uses `node --check` per file, which resolves the module type the same way the
 * runtime does (nearest package.json "type", or the .mjs extension), so ES
 * modules are checked as modules and CommonJS as CommonJS.
 */

import { execFileSync } from 'node:child_process';
import { readdirSync, statSync } from 'node:fs';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const SOURCES = ['wavira', 'wavira-core', 'tools'];
const IGNORE = ['node_modules', 'vendor', 'assets/dist'];

function walk(dir, out = []) {
	let entries = [];
	try {
		entries = readdirSync(dir);
	} catch {
		return out;
	}
	for (const entry of entries) {
		if (IGNORE.includes(entry)) continue;
		const full = join(dir, entry);
		const info = statSync(full);
		if (info.isDirectory()) walk(full, out);
		else if (/\.(js|mjs)$/.test(entry)) out.push(full);
	}
	return out;
}

const files = SOURCES.flatMap((s) => walk(join(ROOT, s)));
let failures = 0;

for (const file of files) {
	try {
		execFileSync(process.execPath, ['--check', file], { stdio: 'pipe' });
	} catch (error) {
		const message = (error.stderr || error.stdout || '').toString().trim();
		console.error(`FAIL ${relative(ROOT, file)}\n     ${message}`);
		failures++;
	}
}

console.log(
	failures === 0
		? `OK   ${files.length} JS file(s) parsed`
		: `FAIL ${failures} of ${files.length} JS file(s) failed to parse`
);

process.exit(failures === 0 ? 0 : 1);
