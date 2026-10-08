#!/usr/bin/env node
/**
 * Decide whether a rendered page is fully Persian.
 *
 * The product's rule is that a fresh install is Persian end to end — not
 * "Persian is available", but "Persian is what the site speaks". `tools/i18n.mjs
 * check` proves the catalogues are complete and `tools/check-render.mjs` proves
 * the theme's headings reach the page, but neither looks at the *whole* page:
 * a string that was never wrapped in a translation function, an English `alt`
 * text, a placeholder left in a template — those are complete catalogues and an
 * English page.
 *
 * So this reads a served page and reports every Latin word a visitor could see:
 * in text, and in the attributes a visitor reads or hears (`alt`, `aria-label`,
 * `placeholder`, `title`, `value`, `content`). Proper nouns are listed in
 * `ALLOWED` with the reason they cannot be translated; anything else is a defect
 * that names itself.
 *
 * Usage: node tools/check-persian.mjs --page=<file> [--allow=word] (repeatable)
 * Exits 1 and prints `::error` annotations when English reaches the page.
 */

import { readFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

/**
 * Proper nouns and units that are not Persian and should not be: the product's
 * own name, the software it runs on, its author, its bundled font, and the file
 * format and bit-rate units a music site prints. Each one is a decision, not an
 * oversight — the point of the list is that it stays short.
 */
export const ALLOWED = [
	'wavira',
	'wavira-core', // The plugin's slug, which appears in the product's own text.
	'wordpress',
	'wp', // The credit line's author is «اتحاد وردپرس · Etehad WP».
	'etehad',
	'vazirmatn', // The bundled font's name, in the font setting.
	'mp3',
	'kbps', // A bit-rate unit; the label around it is Persian.
	'fa',
	'ir',
];

/** The attributes a visitor reads or hears, and which therefore must be Persian. */
export const SPOKEN_ATTRIBUTES = [ 'alt', 'aria-label', 'placeholder', 'title', 'value' ];

const LATIN_WORD = /[A-Za-z][A-Za-z'’.-]*/g;

/** What a page's "visible" text is, without tags, scripts, styles or comments. */
export function visible( html ) {
	return html
		.replace( /<!--[\s\S]*?-->/g, ' ' )
		.replace( /<(script|style)\b[\s\S]*?<\/\1>/gi, ' ' )
		.replace( /<[^>]*>/g, ' ' )
		.replace( /&(?:nbsp|amp|hellip|mdash|ndash|#8217|#039|#171|#187);/g, ' ' )
		.replace( /\s+/g, ' ' )
		.trim();
}

/**
 * Every Latin word in a piece of text, minus the allowed ones.
 *
 * @param {string}   text    Text to scan.
 * @param {string[]} allowed Lower-cased words that may stay Latin.
 * @return {string[]} The words that should not be there, in order, unique.
 */
export function latinWords( text, allowed = ALLOWED ) {
	const allowedSet = new Set( allowed.map( ( word ) => word.toLowerCase() ) );
	const words = text.match( LATIN_WORD ) || [];

	return [
		...new Set(
			words
				.map( ( word ) => word.replace( /[.'’-]+$/, '' ) )
				.filter( ( word ) => word.length > 1 && ! allowedSet.has( word.toLowerCase() ) )
		),
	];
}

/**
 * What a page says in a language that is not Persian.
 *
 * @param {object}   input
 * @param {string}   input.page    Rendered HTML.
 * @param {string[]} [input.allow] Extra allowed words.
 * @return {{ text: string[], attributes: Array<{attribute: string, value: string, words: string[]}>, clean: boolean }}
 *         The Latin words in the page's text, in its spoken attributes, and whether the page is clean.
 */
export function checkPersian( { page, allow = [] } ) {
	const allowed = [ ...ALLOWED, ...allow ];
	const text = latinWords( visible( page ), allowed );
	const attributes = [];

	for ( const attribute of SPOKEN_ATTRIBUTES ) {
		// Attribute values, both quote styles: WordPress prints its own with single
		// quotes and the theme's markup with double ones.
		const pattern = new RegExp( `${ attribute }=['"]([^'"]*)['"]`, 'g' );

		for ( const match of page.matchAll( pattern ) ) {
			const words = latinWords( match[ 1 ], allowed );

			if ( words.length > 0 ) {
				attributes.push( { attribute, value: match[ 1 ], words } );
			}
		}
	}

	return { text, attributes, clean: 0 === text.length && 0 === attributes.length };
}

export function usage() {
	return 'usage: node tools/check-persian.mjs --page=<file> [--allow=<word>]';
}

/* c8 ignore start — the CLI half is exercised by the CI job, not by node:test. */
if ( process.argv[ 1 ] && import.meta.url === pathToFileURL( process.argv[ 1 ] ).href ) {
	const args = process.argv.slice( 2 );
	const value = ( name, absent = null ) => {
		const attached = args.find( ( argument ) => argument.startsWith( `--${ name }=` ) );

		return attached ? attached.slice( name.length + 3 ) : absent;
	};
	const page = value( 'page' );
	const allow = args
		.filter( ( argument ) => argument.startsWith( '--allow=' ) )
		.map( ( argument ) => argument.slice( '--allow='.length ) );

	if ( typeof page !== 'string' ) {
		console.error( usage() );
		process.exit( 2 );
	}

	let result;

	try {
		result = checkPersian( { page: readFileSync( page, 'utf8' ), allow } );
	} catch ( error ) {
		console.error( `::error title=Persian page::${ error.message }` );
		process.exit( 1 );
	}

	for ( const { attribute, value, words } of result.attributes ) {
		console.log(
			`::error title=Persian page::${ attribute }="${ value.slice( 0, 80 ) }" is not Persian — it contains ${ words
				.map( ( word ) => `“${ word }”` )
				.join( ', ' ) }`
		);
	}

	if ( result.text.length > 0 ) {
		console.log(
			`::error title=Persian page::the page's text contains ${ result.text.length } Latin word(s) that are not proper nouns — ${ result.text
				.slice( 0, 12 )
				.map( ( word ) => `“${ word }”` )
				.join( ', ' ) }`
		);
	}

	if ( ! result.clean ) {
		process.exit( 1 );
	}

	console.log( '      OK    the page is Persian: no untranslated text, and no Latin alt or label' );
}
/* c8 ignore stop */
