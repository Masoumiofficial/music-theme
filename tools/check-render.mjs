#!/usr/bin/env node
/**
 * Decide whether a rendered page is the Persian front page it claims to be.
 *
 * `wp-render` renders the theme on a real WordPress and `tools/screenshot.mjs`
 * refuses an image that is not the page it asked for. This is the other half: the
 * page itself has to be Persian, and it has to be the *front page* — the theme's
 * own headings around the theme's own sections — rather than the blog index that
 * shipped in place of one until 0.14.0 (ADR 0021).
 *
 * The expected strings are read from the shipped Persian catalogue instead of
 * being typed here, so the gate compares the page against the translation the
 * theme actually ships and fails when either side is missing. ZWNJ is dropped on
 * both sides: it is a typographic choice, not a different string.
 *
 * Usage: node tools/check-render.mjs --page=<file> [--catalogue=<file>]
 *        [--min-sections=4]
 * Exits 1 and prints `::error` annotations when the page is not what it claims.
 */

import { readFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

/** The heading patterns the front page uses, by their English source string. */
export const FRONT_PAGE_HEADINGS = [
	'Latest albums',
	'Listen now',
	'Latest tracks',
	'Music videos',
	'Music news',
];

const ZWNJ = '\u200c';
const PERSIAN = /[\u0600-\u06FF]/;

/** Drop the zero-width non-joiner: the same sentence with and without it matches. */
export function plain( text ) {
	return text.replaceAll( ZWNJ, '' );
}

/**
 * Every translation of a source string in a `.po` catalogue — one per context or
 * plural form, so a heading may legitimately translate more than once.
 */
export function translations( catalogue, msgid ) {
	const pattern = new RegExp(
		`^msgid ${ escapeRegExp( JSON.stringify( msgid ) ) }$\\nmsgstr "(.*)"$`,
		'mg',
	);

	return [ ...catalogue.matchAll( pattern ) ].map( ( match ) => match[ 1 ] );
}

function escapeRegExp( value ) {
	return value.replaceAll( /[.*+?^${}()|[\]\\]/g, '\\$&' );
}

/**
 * The first value of an attribute whose value contains `path`, or an empty string.
 *
 * @param {string} page Rendered HTML.
 * @param {string} name Attribute name, `href` or `src`.
 * @param {string} path Regex source the value has to contain.
 * @return {string} The URL as printed, or `''`.
 */
export function attributeValue( page, name, path ) {
	const match = page.match( new RegExp( `${ name }=['"]([^'"]*${ path }[^'"]*)['"]` ) );

	return match ? match[ 1 ] : '';
}

/**
 * Read the result of a page check.
 *
 * @param {object}   input
 * @param {string}   input.page             Rendered HTML.
 * @param {string}   input.catalogue        The `.po` file the theme ships.
 * @param {string[]} [input.headings]       Source strings to look for.
 * @param {number}   [input.minSections]    How many `wavira-section` elements a front page has.
 * @param {string}   [input.origin]         The origin the page was fetched from, e.g. `http://127.0.0.1:8080`.
 * @return {{ found: string[], problems: string[] }} What was on the page, and what was wrong with it.
 */
export function checkPage( { page, catalogue, headings = FRONT_PAGE_HEADINGS, minSections = 4, origin = '' } ) {
	const found = [];
	const problems = [];
	const flat = plain( page );

	for ( const heading of headings ) {
		const known = translations( catalogue, heading );
		const onPage = known.filter( ( translation ) => translation && flat.includes( plain( translation ) ) );

		if ( onPage.length > 0 ) {
			found.push( `${ heading } → ${ onPage[ 0 ] }` );
			continue;
		}

		problems.push(
			known.length === 0
				? `the catalogue has no Persian translation for “${ heading }”`
				: `the page does not contain “${ heading }” as “${ known[ 0 ] }”`,
		);
	}

	// The front page is the template, not the blog index: it marks its sections.
	const sections = flat.split( 'wavira-section' ).length - 1;

	// Landmarks and headings, counted rather than judged: axe reported a duplicate
	// contentinfo landmark and no h1 on the front page, and the count in an
	// annotation is what says which element is which.
	const counts = {
		h1: ( page.match( /<h1[\s>]/g ) || [] ).length,
		footer: ( page.match( /<footer[\s>]/g ) || [] ).length,
		nav: ( page.match( /<nav[\s>]/g ) || [] ).length,
		main: ( page.match( /<main[\s>]/g ) || [] ).length,
	};

	if ( sections < minSections ) {
		problems.push(
			`the page has ${ sections } wavira-section element(s), expected at least ${ minSections } — ` +
				'front-page.html did not render',
		);
	}

	if ( ! PERSIAN.test( page ) ) {
		problems.push( 'the page carries no Persian text at all' );
	}

	// The theme's own stylesheet, by the URL this theme builds: a block theme
	// renders with core's defaults when its stylesheet is not on the page, and a
	// screenshot of it looks almost right — which is how the first render's layout
	// survived a review of the HTML alone.
	// Both quote styles: WordPress prints `<link ... href='...'>` with single
	// quotes and a theme or a plugin may print double ones, and a gate that only
	// reads one of them fails a page that is fine (it did, on the first run).
	const attribute = ( name, path ) => new RegExp( `${ name }=['"][^'"]*${ path }[^'"]*['"]` );
	const stylesheet = attribute( 'href', 'assets\\/dist\\/theme\\.css' );

	if ( ! stylesheet.test( page ) ) {
		problems.push(
			'the page does not load the theme stylesheet (assets/dist/theme.css) — ' +
				'the theme is rendering with core’s defaults'
		);
	}

	if ( ! attribute( 'src', 'assets\\/dist\\/theme\\.js' ).test( page ) ) {
		problems.push( 'the page does not load the theme script (assets/dist/theme.js) — the player and the colour toggle are inert' );
	}

	// And every `wp-content` URL the page prints has to be the one this origin
	// serves. A WordPress site URL that carries a path —
	// `http://127.0.0.1:8080/site`, which is what WP-CLI guesses when
	// `--path=/tmp/site` puts the installation one directory below its own phar —
	// makes every URL the site builds point at `/site/wp-content/…`. Nothing
	// serves that path, so the stylesheet arrives as the front page's `text/html`,
	// the browser refuses it, and the theme renders with core's defaults while
	// every HTML-level check still passes (0.15.0's screenshots, four of them).
	// The URL is the thing that was wrong, so the URL is what is compared — and
	// it is compared against `--origin`, because only the caller knows where the
	// page was fetched from.
	if ( origin ) {
		const base = origin.replace( /\/+$/, '' );
		const wantedPrefix = `${ base }/wp-content/`;
		const inside = ( url ) => {
			if ( /^[a-z][a-z0-9+.-]*:\/\//i.test( url ) ) {
				return url.startsWith( wantedPrefix );
			}

			// A site-relative URL resolves against the origin the page came from,
			// so it only has to have the right path.
			try {
				return new URL( url, `${ base }/` ).href.startsWith( wantedPrefix );
			} catch {
				return false;
			}
		};

		const outside = [ ...new Set( [ ...page.matchAll( /(?:href|src)=['"]([^'"]*wp-content[^'"]*)['"]/g ) ].map( ( match ) => match[ 1 ] ) ) ]
			.filter( ( url ) => ! inside( url ) );

		if ( outside.length > 0 ) {
			problems.push(
				`${ outside.length } of the page's wp-content URL(s) are not served by ${ base } — ` +
					`the first is ${ outside[ 0 ] } (expected it to start with ${ wantedPrefix }); ` +
					'the site URL carries a path the render cannot reach, so the stylesheet and the demo images 404 silently'
			);
		}
	}

	return { found, problems, counts };
}

export function usage() {
	return 'usage: node tools/check-render.mjs --page=<file> [--catalogue=wavira/languages/fa_IR.po] [--min-sections=4] [--origin=http://127.0.0.1:8080]';
}

/**
 * Read `--name=value` (or a bare `--flag`) out of an argv-style array.
 *
 * The same rules as `tools/screenshot.mjs`: a value is always attached with `=`,
 * a bare flag reads as `true`. Two parsers in one repository is one too many, so
 * `tests/js/check-render.test.mjs` asserts the two agree.
 */
export function option( args, name, absent = null ) {
	const withValue = args.find( ( argument ) => argument.startsWith( `--${ name }=` ) );

	if ( withValue ) {
		return withValue.slice( name.length + 3 );
	}

	return args.includes( `--${ name }` ) ? true : absent;
}

/* c8 ignore start — the CLI half is exercised by the CI job, not by node:test. */
if ( process.argv[ 1 ] && import.meta.url === pathToFileURL( process.argv[ 1 ] ).href ) {
	const args = process.argv.slice( 2 );
	const page = option( args, 'page' );
	const cataloguePath = option( args, 'catalogue', 'wavira/languages/fa_IR.po' );
	const minSections = option( args, 'min-sections', '4' );
	const origin = option( args, 'origin', '' );

	// A bare `--page` (or `--page /tmp/x.html`, which is a flag and a stray word)
	// reads as `true`: say so instead of failing on a path called "true".
	if ( typeof page !== 'string' || typeof cataloguePath !== 'string' || typeof minSections !== 'string' ) {
		console.error( usage() );
		console.error( 'note: values are attached with `=`, e.g. --page=/tmp/home.html' );
		process.exit( 2 );
	}

	let result;

	try {
		result = checkPage( {
			page: readFileSync( page, 'utf8' ),
			catalogue: readFileSync( cataloguePath, 'utf8' ),
			minSections: Number( minSections ),
			origin: String( origin ),
		} );
	} catch ( error ) {
		console.error( `::error title=Rendered theme::${ error.message }` );
		process.exit( 1 );
	}

	for ( const line of result.found ) {
		console.log( `      · heading on the page: ${ line }` );
	}

	console.log(
		`      · landmarks: ${ result.counts.h1 } h1, ${ result.counts.main } main, ${ result.counts.nav } nav, ` +
			`${ result.counts.footer } footer`
	);

	for ( const problem of result.problems ) {
		console.log( `::error title=Rendered theme::front page: ${ problem }` );
	}

	if ( result.problems.length > 0 ) {
		process.exit( 1 );
	}

	console.log( `      OK    the front page is Persian, with the theme’s own headings (${ result.found.length })` );
}
/* c8 ignore stop */
