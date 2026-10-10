#!/usr/bin/env node
/**
 * The accessibility gate: read an axe-core report and decide.
 *
 * `tools/screenshot.mjs --axe=FILE` measures — it renders the pages in a real
 * browser and writes what axe found. Measuring and deciding are separate on
 * purpose: the screenshot has to be committed even when the audit finds
 * something, so that the image under review and the report about it come from
 * the same render. This is the file that fails the build.
 *
 * Usage:
 *   node tools/check-axe.mjs /tmp/wavira-axe.json [--allow=id,id]
 *
 * Exit status is 0 when no page has a `serious` or `critical` violation.
 * `moderate` and `minor` are printed and allowed: they are real findings, but a
 * gate that fires on every advisory turns into a gate people skip. Anything
 * allowed by `--allow=` has to be named in the output, so an allowance cannot
 * become invisible.
 *
 * @package Wavira
 */

import { readFileSync } from 'node:fs';

const argv = process.argv.slice( 2 );
const file = argv.find( ( a ) => ! a.startsWith( '--' ) );

if ( ! file ) {
	process.stderr.write( 'usage: node tools/check-axe.mjs /tmp/wavira-axe.json [--allow=id,id]\n' );
	process.exit( 1 );
}

const allowed = new Set(
	( argv
		.filter( ( a ) => a.startsWith( '--allow=' ) )
		.map( ( a ) => a.slice( '--allow='.length ) )
		.join( ',' )
		.split( ',' )
		.map( ( id ) => id.trim() )
		.filter( Boolean ) )
);

let report;

try {
	report = JSON.parse( readFileSync( file, 'utf8' ) );
} catch ( error ) {
	process.stderr.write( `check-axe: cannot read ${ file }: ${ error.message }\n` );
	process.exit( 1 );
}

const pages = Array.isArray( report.pages ) ? report.pages : [];
const blocking = [];
const advisory = [];

for ( const page of pages ) {
	for ( const violation of page.violations ?? [] ) {
		const row = { page: page.name, ...violation };

		if ( 'serious' === violation.impact || 'critical' === violation.impact ) {
			if ( allowed.has( violation.id ) ) {
				advisory.push( { ...row, allowed: true } );
			} else {
				blocking.push( row );
			}
		} else {
			advisory.push( row );
		}
	}
}

for ( const row of advisory ) {
	const note = row.allowed ? ' (allowed)' : '';

	process.stdout.write( `  · ${ row.page }: ${ row.id } — ${ row.impact }${ note }: ${ row.help } (${ row.nodes } node(s))\n` );
}

process.stdout.write(
	`check-axe: ${ pages.length } page(s), ${ blocking.length } blocking violation(s), ` +
		`${ advisory.length } advisory/allowed\n`
);

if ( blocking.length ) {
	for ( const row of blocking ) {
		process.stdout.write( `::error file=docs/VERIFICATION.md::axe ${ row.impact} on ${ row.page }: ${ row.id } — ${ row.help } (${ row.nodes } node(s))\n` );
	}

	process.exit( 1 );
}
