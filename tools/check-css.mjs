#!/usr/bin/env node
/**
 * CSS gate: the rules the product promises about its stylesheets.
 *
 *   1. no `!important` — a single declaration must never win by force;
 *   2. logical properties only — RTL ships as one stylesheet (ADR 0008);
 *   3. dark-mode parity — every preset colour in theme.json is remapped in
 *      tokens.css, so a new palette entry cannot silently stay light;
 *   4. component parity — every `.wavira-player__*` selector is a class the
 *      engine or the theme actually renders (the queue list once shipped with no
 *      markup because nothing checked this);
 *   5. size budget — the built files stay inside the ADR 0009 budget when they
 *      exist (CSS ≤ 25 KB gzip, engine ≤ 15 KB, theme script ≤ 10 KB);
 *   6. design-token resolvability — every `--wp--preset--*`, `--wp--custom--*`
 *      and `--wp--style--*` reference resolves to something theme.json actually
 *      generates. WordPress kebab-cases both preset slugs and custom keys
 *      (`WP_Theme_JSON::flatten_tree()`), so `--wp--custom--player--barSpace`
 *      silently resolves to nothing: the fallback hides the typo and the setting
 *      stops working (both instances of this shipped in 0.6.0 and are fixed).
 *
 * Usage:
 *   node tools/check-css.mjs          # check, exit 1 on a violation
 */

import { existsSync, readFileSync, readdirSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { gzipSync } from 'node:zlib';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const THEME_CSS = join(ROOT, 'wavira/assets/css');
const CORE_CSS = join(ROOT, 'wavira-core/assets/css');
const ENGINE = join(ROOT, 'wavira-core/assets/js/index.js');
const THEME_JS = join(ROOT, 'wavira/assets/js/index.js');
const THEME_DIR = join(ROOT, 'wavira');
const THEME_JSON = join(THEME_DIR, 'theme.json');
const DARK_JSON = join(THEME_DIR, 'styles/dark.json');

const problems = [];
const notes = [];

/** Read every .css file in a folder. */
function cssFiles( dir ) {
	if ( ! existsSync( dir ) ) {
		return [];
	}

	return readdirSync( dir )
		.filter( ( name ) => name.endsWith( '.css' ) )
		.map( ( name ) => ( { name, path: join( dir, name ), css: readFileSync( join( dir, name ), 'utf8' ) } ) );
}

/** Strip comments so prose never looks like code. */
function strip( css ) {
	return css.replace( /\/\*[\s\S]*?\*\//g, '' );
}

const files = [ ...cssFiles( THEME_CSS ), ...cssFiles( CORE_CSS ) ];

// ---------------------------------------------------------------- 1. !important
for ( const { name, css } of files ) {
	if ( /!important/.test( strip( css ) ) ) {
		problems.push( `${name}: uses !important` );
	}
}

// ------------------------------------------------------ 2. logical properties
const PHYSICAL = [
	/\b(?:margin|padding|border)-(?:left|right)\b/g,
	/\btext-align:\s*(?:left|right)\b/g,
	/^\s*(?:left|right)\s*:/gm,
	/\binset-(?:left|right)\b/g,
];

for ( const { name, css } of files ) {
	const body = strip( css );

	for ( const pattern of PHYSICAL ) {
		const hits = body.match( pattern );

		if ( hits ) {
			problems.push( `${name}: physical property ${hits.join( ', ' )} — use the logical equivalent` );
		}
	}
}

// --------------------------------------------------------- 3. dark-mode parity
if ( existsSync( THEME_JSON ) && existsSync( join( THEME_CSS, 'tokens.css' ) ) ) {
	const theme = JSON.parse( readFileSync( THEME_JSON, 'utf8' ) );
	const tokens = readFileSync( join( THEME_CSS, 'tokens.css' ), 'utf8' );
	const palette = ( theme.settings?.color?.palette ?? [] ).map( ( item ) => item.slug );
	const darkBlocks = [ ...tokens.matchAll( /\[data-theme="dark"\]\s*\{([\s\S]*?)\n\}/g ) ].map( ( m ) => m[1] );
	const autoBlocks = [ ...tokens.matchAll( /\[data-theme="auto"\]\s*\{([\s\S]*?)\n\t\}/g ) ].map( ( m ) => m[1] );
	const combined = darkBlocks.join( '\n' ) + '\n' + autoBlocks.join( '\n' );

	if ( 0 === darkBlocks.length ) {
		problems.push( 'tokens.css: no [data-theme="dark"] block found' );
	}

	for ( const slug of palette ) {
		if ( ! combined.includes( `--wp--preset--color--${ slug }:` ) ) {
			problems.push( `tokens.css: palette colour "${ slug }" is not remapped for dark mode` );
		}
	}

	notes.push( `dark-mode parity: ${ palette.length } palette colour(s) remapped` );
}

// ------------------------------------------------------- 4. component parity
if ( existsSync( ENGINE ) ) {
	const engine = readFileSync( ENGINE, 'utf8' );
	const themePhp = readdirSync( join( THEME_DIR, 'inc' ) )
		.filter( ( name ) => name.endsWith( '.php' ) )
		.map( ( name ) => readFileSync( join( THEME_DIR, 'inc', name ), 'utf8' ) )
		.join( '\n' );
	const sources = engine + '\n' + themePhp;

	for ( const { name, css } of files ) {
		const selectors = new Set( [ ...strip( css ).matchAll( /\.(wavira-player[a-zA-Z0-9_-]*)/g ) ].map( ( m ) => m[ 1 ] ) );

		for ( const selector of selectors ) {
			if ( ! sources.includes( selector ) ) {
				problems.push( `${name}: selector .${ selector } matches no class the product renders` );
			}
		}
	}
}

// -------------------------------------------- 6. design-token resolvability
if ( existsSync( THEME_JSON ) ) {
	const theme = JSON.parse( readFileSync( THEME_JSON, 'utf8' ) );
	const settings = theme.settings ?? {};
	const kebab = ( key ) =>
		String( key )
			.replace( /([a-z0-9])([A-Z])/g, '$1-$2' )
			.replace( /[_/]/g, '-' )
			.toLowerCase();

	// Flatten `settings.custom` the way WordPress does: kebab-case every key and
	// join the levels with `--` (WP_Theme_JSON::flatten_tree()).
	const custom = new Set();
	const walk = ( tree, prefix ) => {
		for ( const [ key, value ] of Object.entries( tree ) ) {
			const name = prefix ? `${ prefix }--${ kebab( key ) }` : kebab( key );

			if ( value && 'object' === typeof value && ! Array.isArray( value ) ) {
				walk( value, name );
			} else {
				custom.add( `--wp--custom--${ name }` );
			}
		}
	};
	walk( settings.custom ?? {}, '' );

	// Preset slugs are kebab-cased by WordPress as well (_wp_to_kebab_case()).
	// Defaults are seeded whenever the theme has not switched them off.
	const defined = {
		color: new Set( ( settings.color?.palette ?? [] ).map( ( item ) => kebab( item.slug ) ) ),
		spacing: new Set( ( settings.spacing?.spacingSizes ?? [] ).map( ( item ) => kebab( item.slug ) ) ),
		shadow: new Set( ( settings.shadow?.presets ?? [] ).map( ( item ) => kebab( item.slug ) ) ),
		'font-size': new Set( ( settings.typography?.fontSizes ?? [] ).map( ( item ) => kebab( item.slug ) ) ),
		'font-family': new Set( ( settings.typography?.fontFamilies ?? [] ).map( ( item ) => kebab( item.slug ) ) ),
	};

	if ( false !== settings.color?.defaultPalette ) {
		[ 'base', 'contrast', 'primary', 'secondary', 'tertiary' ].forEach( ( slug ) => defined.color.add( slug ) );
	}

	if ( false !== settings.spacing?.defaultSpacingSizes ) {
		[ '20', '30', '40', '50', '60', '70', '80' ].forEach( ( slug ) => defined.spacing.add( slug ) );
	}

	if ( false !== settings.typography?.defaultFontSizes ) {
		[ 'x-small', 'small', 'medium', 'large', 'x-large', 'xx-large' ].forEach( ( slug ) =>
			defined[ 'font-size' ].add( slug )
		);
	}

	const styleVars = new Set( [
		...( settings.layout?.contentSize ? [ '--wp--style--global--content-size' ] : [] ),
		...( settings.layout?.wideSize ? [ '--wp--style--global--wide-size' ] : [] ),
	] );

	const reference = /var\(\s*(--wp--[A-Za-z0-9-]+)/g;
	const checkedRefs = new Set();

	for ( const { name, css } of files ) {
		const refs = [ ...strip( css ).matchAll( reference ) ].map( ( match ) => match[ 1 ] );

		for ( const ref of new Set( refs ) ) {
			if ( styleVars.has( ref ) || custom.has( ref ) ) {
				continue;
			}

			checkedRefs.add( ref );

			const preset = ref.match( /^--wp--preset--([a-z-]+)--(.+)$/ );

			if ( preset && defined[ preset[ 1 ] ]?.has( preset[ 2 ] ) ) {
				continue;
			}

			problems.push( `${ name }: ${ ref } is not generated by theme.json — check the slug casing and the preset` );
		}
	}

	notes.push( `design tokens: ${ custom.size } custom propert${ 1 === custom.size ? 'y' : 'ies' } resolvable` );
}

// ------------------------------------------------------------- 5. size budget
const BUDGETS = [
	{ label: 'theme CSS', file: join( THEME_DIR, 'assets/dist/theme.css' ), kb: 25 },
	{ label: 'theme script', file: join( ROOT, 'wavira/assets/dist/theme.js' ), kb: 10 },
	{ label: 'player CSS', file: join( ROOT, 'wavira-core/assets/dist/player.css' ), kb: 6 },
	{ label: 'player engine', file: join( ROOT, 'wavira-core/assets/dist/core.js' ), kb: 15 },
];

for ( const { label, file, kb } of BUDGETS ) {
	if ( ! existsSync( file ) ) {
		notes.push( `${label}: not built yet (budget ${ kb } KB gzip)` );
		continue;
	}

	const raw = readFileSync( file );
	const gzipped = gzipSync( raw, { level: 9 } ).length / 1024;

	if ( gzipped > kb ) {
		problems.push( `${label}: ${ gzipped.toFixed( 1 ) } KB gzip exceeds the ${ kb } KB budget` );
	} else {
		notes.push( `${label}: ${ gzipped.toFixed( 1 ) } KB gzip (budget ${ kb } KB)` );
	}
}

// The theme script must exist (it is a product file, not a build artefact).
if ( ! existsSync( THEME_JS ) ) {
	problems.push( 'wavira/assets/js/index.js is missing' );
}

for ( const line of notes ) {
	console.log( `      · ${ line }` );
}

if ( problems.length > 0 ) {
	for ( const line of problems ) {
		console.log( `      FAIL ${ line }` );
	}
	process.exit( 1 );
}

console.log( `      OK    ${ files.length } stylesheet(s) pass the CSS rules` );
