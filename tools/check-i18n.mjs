#!/usr/bin/env node
/**
 * Text gate: nothing user-visible ships untranslatable, and no pattern reference
 * points at a pattern that does not exist.
 *
 * A block template (`.html`) cannot execute PHP, so any text written inside one
 * is frozen in English forever — the core themes solve this by keeping the
 * strings in PHP patterns and referencing them (`wp:pattern`). This gate
 * enforces that practice on our own files:
 *
 *   1. no text node in `templates/*.html` or `parts/*.html` (block comments are
 *      metadata, not text, and are ignored);
 *   2. no text-bearing block attribute there either — e.g. `buttonText` or
 *      `label` store a literal string in the page, which no locale can change;
 *   3. every `wp:pattern {"slug":"…"}` reference resolves to a pattern file, and
 *      every hidden pattern (`Inserter: no`) is actually referenced by one;
 *   4. every pattern declares `Title:` and `Slug:`, and hidden ones are marked.
 *
 * A string that must not be translated (a proper noun, an attribution) is
 * exempted with an explicit marker on the same physical line, and the marker
 * comment explains why:
 *
 *   <a href="…">اتحاد وردپرس · Etehad WP</a><!-- wavira:i18n-exempt author attribution -->
 *
 * Text inside `patterns/*.php` is allowed: those files run at registration time,
 * so `esc_html_x()` there is translated before the content exists.
 *
 * Usage:
 *   node tools/check-i18n.mjs          # check, exit 1 on a violation
 */

import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = resolve( dirname( fileURLToPath( import.meta.url ) ), '..' );
const TEMPLATE_DIRS = [ 'wavira/templates', 'wavira/parts' ];
const PATTERN_DIR = 'wavira/patterns';

/** Block attributes that carry a literal sentence into the rendered page. */
const TEXT_ATTRIBUTES = [ 'buttonText', 'label', 'placeholder', 'alt', 'caption', 'text', 'content', 'moreText', 'summary' ];

const problems = [];
const notes = [];

function files( directory, extension ) {
	const absolute = join( ROOT, directory );

	if ( ! existsSync( absolute ) ) {
		return [];
	}

	return readdirSync( absolute )
		.filter( ( name ) => statSync( join( absolute, name ) ).isFile() && name.endsWith( extension ) )
		.map( ( name ) => ( { name, path: join( absolute, name ) } ) );
}

/**
 * Remove everything that is not visible text: exempted lines, block comments,
 * HTML comments and PHP blocks (whose output is escaped and translated by
 * WordPress).
 *
 * Exempt lines are dropped before the comments are stripped, because the marker
 * sits in a comment on the same line as the string it excuses.
 *
 * @param {string} source File contents.
 * @return {string} Source with the non-text regions blanked out.
 */
function readableOnly( source ) {
	return source
		.split( '\n' )
		.filter( ( line ) => ! /wavira:i18n-exempt/.test( line ) )
		.join( '\n' )
		.replace( /<\?php[\s\S]*?\?>/g, '' )
		.replace( /<!--[\s\S]*?-->/g, '' );
}

/**
 * Text nodes of a markup string: anything between `>` and the next `<`.
 *
 * @param {string} markup Markup without comments or PHP.
 * @return {string[]} Text nodes.
 */
function textNodes( markup ) {
	return [ ...markup.matchAll( />([^<>]*)</g ) ].map( ( match ) => match[ 1 ].trim() );
}

// ------------------------------------------------------- 1./2. templates & parts
let scanned = 0;

for ( const directory of TEMPLATE_DIRS ) {
	for ( const file of files( directory, '.html' ) ) {
		scanned++;

		const markup = readableOnly( readFileSync( file.path, 'utf8' ));

		for ( const text of textNodes( markup ) ) {
			if ( /[A-Za-z]{3,}/.test( text ) ) {
				problems.push( `${ directory }/${ file.name }: hard-coded text “${ text.slice( 0, 48 ) }” — move it into a pattern (see patterns/hidden-404.php)` );
			}
		}

		for ( const attribute of TEXT_ATTRIBUTES ) {
			const pattern = new RegExp( `"${ attribute }"\\s*:\\s*"[^"]+"` );

			if ( pattern.test( readFileSync( file.path, 'utf8' ) ) ) {
				problems.push( `${ directory }/${ file.name }: “${ attribute }” stores literal text in the template — omit it and let core's translated default apply` );
			}
		}
	}
}

// ------------------------------------------------------------- 3./4. the patterns
const patterns = new Map();

for ( const file of files( PATTERN_DIR, '.php' ) ) {
	const source = readFileSync( file.path, 'utf8' );
	const slug = ( source.match( /^\s*\*\s*Slug:\s*(\S+)/m ) || [] )[ 1 ];
	const title = ( source.match( /^\s*\*\s*Title:\s*(.+)$/m ) || [] )[ 1 ];
	const hidden = /^\s*\*\s*Inserter:\s*no\s*$/m.test( source );

	if ( ! slug ) {
		problems.push( `${ PATTERN_DIR }/${ file.name }: no “Slug:” header` );
		continue;
	}

	if ( ! title ) {
		problems.push( `${ PATTERN_DIR }/${ file.name }: no “Title:” header` );
	}

	if ( patterns.has( slug ) ) {
		problems.push( `${ PATTERN_DIR }/${ file.name }: slug “${ slug }” is already used by patterns/${ patterns.get( slug ).name }` );
	}

	if ( ! slug.startsWith( 'wavira/' ) ) {
		problems.push( `${ PATTERN_DIR }/${ file.name }: slug must be namespaced as wavira/<name> (${ slug })` );
	}

	patterns.set( slug, { name: file.name, hidden, title, referenced: 0 } );

	for ( const text of textNodes( readableOnly( source ) ) ) {
		if ( /[A-Za-z]{3,}/.test( text ) ) {
			problems.push( `${ PATTERN_DIR }/${ file.name }: hard-coded text “${ text.slice( 0, 48 ) }” — wrap it in esc_html_x( …, 'wavira' )` );
		}
	}
}

// ----------------------------------------------------------------- 3. references
const REFERENCES = /wp:pattern\s*\{[^}]*"slug"\s*:\s*"([^"]+)"/g;
let references = 0;

for ( const directory of [ ...TEMPLATE_DIRS, PATTERN_DIR ] ) {
	for ( const file of [ ...files( directory, '.html' ), ...files( directory, '.php' ) ] ) {
		const source = readFileSync( file.path, 'utf8' );

		for ( const match of source.matchAll( REFERENCES ) ) {
			references++;
			const slug = match[ 1 ];
			const pattern = patterns.get( slug );

			if ( ! pattern ) {
				problems.push( `${ directory }/${ file.name }: references unknown pattern “${ slug }”` );
				continue;
			}

			pattern.referenced++;
		}
	}
}

for ( const [ slug, pattern ] of patterns ) {
	if ( pattern.hidden && 0 === pattern.referenced ) {
		problems.push( `${ PATTERN_DIR }/${ pattern.name }: hidden pattern “${ slug }” is never referenced — delete it or reference it from a template` );
	}
}

notes.push( `${ scanned } template/part file(s) carry no untranslatable text` );
notes.push( `${ patterns.size } pattern(s), ${ references } reference(s) resolve` );

for ( const line of notes ) {
	console.log( `      · ${ line }` );
}

if ( problems.length > 0 ) {
	for ( const line of problems ) {
		console.log( `      FAIL ${ line }` );
	}

	process.exit( 1 );
}

console.log( '      OK    every string is translatable and every pattern reference resolves' );
