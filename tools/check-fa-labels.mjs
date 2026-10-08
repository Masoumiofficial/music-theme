#!/usr/bin/env node
/**
 * Decide whether a section heading on a rendered page is Persian.
 *
 * The theme's own strings have a gate of their own (`tools/check-render.mjs`),
 * and the plugin's catalogue has `tools/i18n.mjs check` — but neither of them
 * looks at a *served page*, and that is where the difference shows: a plugin
 * whose translation file is not in effect renders its English labels inside an
 * otherwise Persian page. The artist page said “Albums (۲)” next to «آهنگ ۵»
 * while every check was green, because the string is a plugin string and the
 * plugin's catalogue was not loaded for that request (0.15.0's render).
 *
 * The expected Persian is read from the shipped catalogue, so this gate fails
 * when the catalogue loses a translation *and* when the catalogue stops being
 * loaded — the two failures look the same on a page and are fixed in different
 * places.
 *
 * Usage: node tools/check-fa-labels.mjs --page=<file> --catalogue=<file>
 *        [--label=Albums] (repeatable; defaults to the plugin's section labels)
 * Exits 1 and prints `::error` annotations when a heading is English.
 */

import { readFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

/** The plugin's section headings, by their English source string. */
export const SECTION_LABELS = [ 'Albums', 'Singles', 'Music videos', 'Works' ];

function escapeRegExp( value ) {
	return value.replaceAll( /[.*+?^${}()|[\]\\]/g, '\\$&' );
}

/**
 * Every translation of a source string in a `.po` catalogue.
 *
 * @param {string} catalogue Contents of a `.po` file.
 * @param {string} msgid     English source string.
 * @return {string[]} Translations, one per occurrence.
 */
export function translations( catalogue, msgid ) {
	const pattern = new RegExp( `^msgid ${ escapeRegExp( JSON.stringify( msgid ) ) }$\\nmsgstr "(.*)"$`, 'gm' );

	return [ ...catalogue.matchAll( pattern ) ].map( ( match ) => match[ 1 ] );
}

const ZWNJ = '\u200c';

/** Drop the zero-width non-joiner: the same sentence with and without it matches. */
export function plain( text ) {
	return text.replaceAll( ZWNJ, '' );
}

/**
 * What is wrong with a page's section headings, and what was found.
 *
 * The rule is about what the page *prints*, not about the catalogue in the
 * abstract: a heading printed in English is a defect (the catalogue is not in
 * effect), a heading printed in Persian is the point, and a section that is not
 * on the page at all is not this gate's business — `tools/i18n.mjs check`
 * already fails a catalogue with an untranslated entry, and a demo whose artist
 * has no videos legitimately has no video section.
 *
 * @param {object}   input
 * @param {string}   input.page      Rendered HTML.
 * @param {string}   input.catalogue Contents of the plugin's `.po`.
 * @param {string[]} [input.labels]  Source strings to look for.
 * @return {{ found: string[], problems: string[], missing: string[] }} What the page printed, what is wrong, and what was not on it.
 */
export function checkLabels( { page, catalogue, labels = SECTION_LABELS } ) {
	const found = [];
	const problems = [];
	const missing = [];
	const flat = plain( page );

	for ( const label of labels ) {
		// An `msgstr ""` in the catalogue is an untranslated entry, not a translation
		// of the empty string: `page.includes( '' )` is always true.
		const known = translations( catalogue, label ).filter( ( value ) => '' !== value );
		const translation = known.length > 0 ? known[ 0 ] : '';

		if ( '' !== translation && flat.includes( plain( translation ) ) ) {
			found.push( `${ label } → ${ translation }` );
			continue;
		}

		// What an untranslated section heading looks like: the English source
		// string and either a count or the end of the tag.
		const english = new RegExp( `>\\s*${ escapeRegExp( label ) }\\s*(?:\\(|<)` ).test( page );

		if ( english ) {
			problems.push(
				'' !== translation
					? `the page prints “${ label } (…)” instead of “${ translation }” — the plugin catalogue is not in effect for this request`
					: `the page prints “${ label }” and the plugin catalogue has no Persian translation for it`
			);
			continue;
		}

		missing.push( label );
	}

	return { found, problems, missing };
}

export function usage() {
	return 'usage: node tools/check-fa-labels.mjs --page=<file> [--catalogue=wavira-core/languages/fa_IR.po] [--label=Albums]';
}

/* c8 ignore start — the CLI half is exercised by the CI job, not by node:test. */
if ( process.argv[ 1 ] && import.meta.url === pathToFileURL( process.argv[ 1 ] ).href ) {
	const args = process.argv.slice( 2 );
	const value = ( name, absent = null ) => {
		const attached = args.find( ( argument ) => argument.startsWith( `--${ name }=` ) );

		return attached ? attached.slice( name.length + 3 ) : absent;
	};
	const page = value( 'page' );
	const catalogue = value( 'catalogue', 'wavira-core/languages/fa_IR.po' );
	const labels = args
		.filter( ( argument ) => argument.startsWith( '--label=' ) )
		.map( ( argument ) => argument.slice( '--label='.length ) );

	if ( typeof page !== 'string' || typeof catalogue !== 'string' ) {
		console.error( usage() );
		process.exit( 2 );
	}

	let result;

	try {
		result = checkLabels( {
			page: readFileSync( page, 'utf8' ),
			catalogue: readFileSync( catalogue, 'utf8' ),
			labels: labels.length > 0 ? labels : SECTION_LABELS,
		} );
	} catch ( error ) {
		console.error( `::error title=Persian labels::${ error.message }` );
		process.exit( 1 );
	}

	for ( const line of result.found ) {
		console.log( `      · Persian heading on the page: ${ line }` );
	}

	for ( const label of result.missing ) {
		console.log( `      · not on this page: ${ label }` );
	}

	for ( const problem of result.problems ) {
		console.log( `::error title=Persian labels::${ problem }` );
	}

	if ( result.problems.length > 0 ) {
		process.exit( 1 );
	}

	console.log( `      OK    the plugin's section headings are Persian (${ result.found.length })` );
}
/* c8 ignore stop */
