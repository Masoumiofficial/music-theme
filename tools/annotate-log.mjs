#!/usr/bin/env node
/**
 * Emit the tail of a log file as GitHub Actions annotations.
 *
 * Job logs live on a separate host and are not reachable from every environment,
 * while annotations are available through the Checks API and inline in the pull
 * request. Any CI step whose output matters pipes through `tee` and, when the job
 * fails, this script turns the interesting lines into annotations.
 *
 * Usage: node tools/annotate-log.mjs <logfile> [label] [lines] [level] [--one]
 *
 * `--one` emits the tail as a single annotation instead of one per line. A runner
 * caps how many annotations a run can carry and the Checks API returns only part
 * of them, so when a crash dump is the thing that matters it has to arrive as one
 * message: the tail is kept and the head is dropped, because the last line of a
 * crash is the least interesting one and the message is at the top of it.
 *
 * Always exits 0 — the failure itself must come from the step that produced the log.
 */

import { readFileSync } from 'node:fs';

const argv = process.argv.slice(2);
const single = argv.includes('--one');
const [file, label = 'log', lineCount = '40', level = 'error'] = argv.filter((argument) => '--one' !== argument);

if (!file) {
	console.error('usage: node tools/annotate-log.mjs <logfile> [label] [lines] [level] [--one]');
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

if (single) {
	const tail = lines.slice(-limit);
	let body = tail.join('\n');

	// Keep the end of the message: the failure is the last thing that happened.
	if (body.length > 1000) {
		body = `…${body.slice(-999)}`;
	}

	console.log(`::${level} title=${label}::${escape(body)}`);
	console.log(`${label}: ${tail.length} line(s) in one annotation of ${lines.length}.`);
	process.exit(0);
}

// First annotation is always a log summary: how long the log is and where it
// ends. Without job logs this is the quickest way to see whether a step was cut
// short or never started.
console.log(
	`::${level} file=ci/${label}::LOG STATS — ${lines.length} line(s); last line: ${escape(lines[lines.length - 1] ?? '(empty log)')}`
);
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
