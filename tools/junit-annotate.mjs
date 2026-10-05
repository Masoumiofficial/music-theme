#!/usr/bin/env node
/**
 * Turn a JUnit XML report into GitHub Actions annotations.
 *
 * Why this exists: PHPUnit output only lands in the job log, and job logs are not
 * always reachable from every environment (they live on a separate host). GitHub
 * annotations ARE reachable through the Checks API and show up inline in the pull
 * request, so test failures stay readable everywhere (see docs/VERIFICATION.md).
 *
 * Usage: node tools/junit-annotate.mjs <junit.xml> [maxAnnotations]
 * Exit code: 1 when the report contains failures or errors, 0 otherwise.
 */

import { readFileSync } from 'node:fs';

const file = process.argv[2];
const max = Number.parseInt(process.argv[3] ?? '40', 10);

if (!file) {
	console.error('usage: node tools/junit-annotate.mjs <junit.xml> [maxAnnotations]');
	process.exit(2);
}

let xml;
try {
	xml = readFileSync(file, 'utf8');
} catch (error) {
	console.error(`could not read ${file}: ${error.message}`);
	process.exit(2);
}

/** Attributes are always double-quoted in PHPUnit output. */
function attr(tag, name) {
	const match = tag.match(new RegExp(`${name}="([^"]*)"`));
	return match ? match[1] : '';
}

function decode(value) {
	return value
		.replace(/&lt;/g, '<')
		.replace(/&gt;/g, '>')
		.replace(/&quot;/g, '"')
		.replace(/&apos;/g, "'")
		.replace(/&amp;/g, '&');
}

function sanitize(value) {
	return decode(value).replace(/[\r\n]+/g, ' ').replace(/::/g, ':').slice(0, 900);
}

const blocks = xml.split(/<testcase\b/).slice(1);
const problems = [];

for (const raw of blocks) {
	const end = raw.indexOf('</testcase>');
	const block = end >= 0 ? raw.slice(0, end) : raw;
	const open = `<testcase ${block.split('>')[0]}>`;
	const failure = block.match(/<(failure|error)\b[^>]*>([\s\S]*?)<\/\1>/);

	if (!failure) {
		continue;
	}

	const name = attr(open, 'name') || 'unnamed test';
	const classname = attr(open, 'classname');
	const location = decode(failure[2]).match(/\/tests\/([^\s:]+):(\d+)/);

	problems.push({
		kind: failure[1],
		message: `${classname}::${name} — ${sanitize(failure[2])}`,
		file: location ? `tests/${location[1]}` : 'tests',
		line: location ? Number.parseInt(location[2], 10) : 1,
	});
}

for (const problem of problems.slice(0, max)) {
	const level = problem.kind === 'error' ? 'error' : 'error';
	console.log(`::${level} file=${problem.file},line=${problem.line}::${problem.message}`);
}

if (problems.length > max) {
	console.log(`::warning::${problems.length - max} further test failure(s) beyond the annotation limit — see the JUnit report.`);
}

const totals = xml.match(/<testsuite\b[^>]*tests="(\d+)"[^>]*failures="(\d+)"[^>]*errors="(\d+)"/);
if (totals) {
	console.log(`JUnit: ${totals[1]} test(s), ${totals[2]} failure(s), ${totals[3]} error(s).`);
}

process.exit(problems.length > 0 ? 1 : 0);
