#!/usr/bin/env node
/**
 * Wavira asset build.
 *
 * Deliberately dependency-free (no bundler required): concatenates the layered
 * CSS sources and the ES-module JS sources into deterministic dist files.
 * Phase 0.2.0 ships the pipeline with no source files yet — the script reports
 * what it would do and exits 0, which keeps CI useful and the repository
 * runnable without any build step (see ADR 0006).
 *
 * Usage:
 *   node tools/build.mjs           # build
 *   node tools/build.mjs --check   # report only, write nothing
 */

import { existsSync, mkdirSync, readdirSync, readFileSync, statSync, writeFileSync } from 'node:fs';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const CHECK_ONLY = process.argv.includes('--check');

/** Build targets: ordered source layers → single output file. */
const TARGETS = [
	{
		name: 'theme CSS',
		sourceDir: join(ROOT, 'wavira/assets/css'),
		outFile: join(ROOT, 'wavira/assets/dist/theme.css'),
		layerOrder: ['tokens', 'base', 'components', 'utilities'],
		pattern: /\.css$/,
	},
	{
		name: 'theme JS',
		sourceDir: join(ROOT, 'wavira/assets/js'),
		outFile: join(ROOT, 'wavira/assets/dist/theme.js'),
		layerOrder: ['index'],
		pattern: /\.js$/,
	},
	{
		name: 'core JS',
		sourceDir: join(ROOT, 'wavira-core/assets/js'),
		outFile: join(ROOT, 'wavira-core/assets/dist/core.js'),
		layerOrder: ['index'],
		pattern: /\.js$/,
	},
];

/**
 * Deterministic banner: reproducible builds are a review requirement, so the
 * timestamp is taken from SOURCE_DATE_EPOCH when the environment provides it
 * (as release tooling does) and omitted otherwise.
 */
const banner = (label, files) => {
	const epoch = process.env.SOURCE_DATE_EPOCH;
	const stamp = epoch ? ` * Built ${new Date(Number(epoch) * 1000).toISOString()}` : null;

	return [
		'/*!',
		` * Wavira — ${label}`,
		...(stamp ? [stamp] : []),
		` * Sources: ${files.map((f) => relative(ROOT, f)).join(', ')}`,
		' * License: GPL-2.0-or-later',
		' */',
		'',
	].join('\n');
};

let built = 0;
let skipped = 0;

for (const target of TARGETS) {
	if (!existsSync(target.sourceDir)) {
		console.log(`- ${target.name}: source dir missing, skipped (${relative(ROOT, target.sourceDir)})`);
		skipped++;
		continue;
	}

	const all = readdirSync(target.sourceDir).filter((f) => target.pattern.test(f)).sort();
	const ordered = [
		...target.layerOrder.flatMap((layer) => all.filter((f) => f.startsWith(layer))),
		...all.filter((f) => !target.layerOrder.some((layer) => f.startsWith(layer))),
	];

	if (ordered.length === 0) {
		console.log(`- ${target.name}: no sources yet, skipped (expected before phase 0.6.0)`);
		skipped++;
		continue;
	}

	const files = ordered.map((f) => join(target.sourceDir, f));
	const body = files.map((f) => readFileSync(f, 'utf8')).join('\n');

	if (CHECK_ONLY) {
		console.log(`- ${target.name}: would build ${ordered.length} source(s) → ${relative(ROOT, target.outFile)}`);
		continue;
	}

	mkdirSync(dirname(target.outFile), { recursive: true });
	writeFileSync(target.outFile, banner(target.name, files) + body, 'utf8');

	const size = statSync(target.outFile).size;
	console.log(`- ${target.name}: built ${relative(ROOT, target.outFile)} (${ordered.length} source(s), ${(size / 1024).toFixed(1)} KB)`);
	built++;
}

console.log(
	`\nWavira build ${CHECK_ONLY ? '(check only) ' : ''}complete — ${built} target(s) built, ${skipped} skipped.`
);
