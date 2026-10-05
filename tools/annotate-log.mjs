#!/usr/bin/env node
/**
 * Emit the tail of a log file as GitHub Actions annotations.
 *
 * Job logs live on a separate host and are not reachable from every environment,
 * while annotations are available through the Checks API and inline in the pull
 * request. Any CI step whose output matters pipes through `tee` and, when the job
 * fails, this script turns the interesting lines into annotations.
 *
 * Usage: node tools/annotate-log.mjs <logfile> [label] [lines] [level]
 * Always exits 0 — the failure itself must come from the step that produced the log.
 */

import { readFileSync } from 'node:fs';

const [, , file, label = 'log', lineCount = '40', level = 'error'] = process.argv;

if (!file) {
	console.error('usage: node tools/annotate-log.mjs <logfile> [label] [lines] [level]');
	process.exit(2);
}

let content;
try {
	content = readFileSync(file, 'utf8');
} catch (error) {
	console.log(`::warning::${label}: ${error.message}`);
	process.exit(0);
}

/** GitHub workflow commands need these escapes inside the message. */
function escape(value) {
	return value
		.replace(/%/g, '%25')
		.replace(/\r/g, '%0D')
		.replace(/\n/g, '%0A')
		.slice(0, 1000);
}

const lines = content
	.split('\n')
	.map((line) => line.replace(/\u001b\[[0-9;]*m/g, '').trimEnd())
	.filter((line) => line.trim() !== '');

const limit = Number.parseInt(lineCount, 10);
const interesting = /error|fail|denied|refused|cannot|can't|unable|not found|no such|fatal|exception/i;
const flagged = lines.filter((line) => interesting.test(line)).slice(-20);
const tail = lines.slice(-limit);
// Tail first: the last lines point at the actual failure, and annotation counts
// are capped per step, so the most useful lines must come first.
const chosen = [...new Set([...tail, ...flagged])];

for (const line of chosen) {
	console.log(`::${level} file=ci/${label}::${escape(line)}`);
}

console.log(`${label}: annotated ${chosen.length} of ${lines.length} line(s) (${flagged.length} error-like + tail).`);
