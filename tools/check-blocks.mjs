#!/usr/bin/env node
/**
 * Block gate: the theme's dynamic blocks stay consistent with themselves.
 *
 * A block lives in four places — `block.json` (metadata), `render.php` (server
 * output), `inc/blocks.php` (the registrar) and `blocks/editor.js` (the editor
 * registration) — and a mismatch is silent until somebody opens the editor:
 *
 *   - a block missing from the registrar never appears;
 *   - a block missing from the editor script renders on the front end but shows
 *     as "unsupported block" in the editor;
 *   - a `render.php` without the direct-access guard is reachable on its own;
 *   - a dynamic block that declares `save()` markup stores stale content.
 *
 * Rules:
 *   1. every `blocks/<dir>/block.json` is valid JSON with the documented name,
 *      apiVersion, category, textdomain, render file and `supports.html: false`;
 *   2. `render.php` exists and refuses direct access;
 *   3. the block list in `inc/blocks.php` and the registrations in
 *      `blocks/editor.js` are exactly the directories on disk;
 *   4. no block asset points at a remote URL (WordPress policy, ADR 0010).
 *
 * Usage:
 *   node tools/check-blocks.mjs          # check, exit 1 on a violation
 */

import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = resolve( dirname( fileURLToPath( import.meta.url ) ), '..' );
const BLOCKS = join( ROOT, 'wavira/blocks' );
const REGISTRAR = join( ROOT, 'wavira/inc/blocks.php' );
const EDITOR = join( ROOT, 'wavira/blocks/editor.js' );

const problems = [];
const notes = [];

if ( ! existsSync( BLOCKS ) ) {
	problems.push( 'wavira/blocks/ is missing' );
}

const directories = existsSync( BLOCKS )
	? readdirSync( BLOCKS ).filter( ( name ) => {
			const path = join( BLOCKS, name );

			return statSync( path ).isDirectory();
	  } )
	: [];

if ( 0 === directories.length ) {
	problems.push( 'wavira/blocks/ contains no block directory' );
}

const declared = new Map();

for ( const directory of directories ) {
	const metadataPath = join( BLOCKS, directory, 'block.json' );

	if ( ! existsSync( metadataPath ) ) {
		problems.push( `${ directory }: block.json is missing` );
		continue;
	}

	let metadata;

	try {
		metadata = JSON.parse( readFileSync( metadataPath, 'utf8' ) );
	} catch ( error ) {
		problems.push( `${ directory }: block.json is not valid JSON (${ error.message })` );
		continue;
	}

	const name = `wavira/${ directory }`;

	if ( metadata.name !== name ) {
		problems.push( `${ directory }: name is "${ metadata.name }", expected "${ name }"` );
	}

	if ( 3 !== metadata.apiVersion ) {
		problems.push( `${ directory }: apiVersion must be 3 (${ metadata.apiVersion })` );
	}

	if ( 'file:./render.php' !== metadata.render ) {
		problems.push( `${ directory }: render must be "file:./render.php" (${ metadata.render })` );
	}

	if ( 'wavira' !== metadata.textdomain ) {
		problems.push( `${ directory }: textdomain must be "wavira" (${ metadata.textdomain })` );
	}

	if ( 'wavira-music' !== metadata.category ) {
		problems.push( `${ directory }: category must be "wavira-music" (${ metadata.category })` );
	}

	if ( false !== metadata.supports?.html ) {
		problems.push( `${ directory }: a dynamic block must declare supports.html: false` );
	}

	const renderPath = join( BLOCKS, directory, 'render.php' );

	if ( ! existsSync( renderPath ) ) {
		problems.push( `${ directory }: render.php is missing` );
	} else {
		const render = readFileSync( renderPath, 'utf8' );

		if ( ! /defined\( 'ABSPATH' \) \|\| exit;/.test( render ) ) {
			problems.push( `${ directory }: render.php must refuse direct access (defined( 'ABSPATH' ) || exit;)` );
		}

		if ( /(file_get_contents|curl_|wp_remote_)\(/.test( render ) ) {
			problems.push( `${ directory }: render.php must not fetch anything at render time` );
		}
	}

	if ( /https?:\/\//.test( JSON.stringify( metadata.attributes ?? {} ) ) ) {
		problems.push( `${ directory }: block attributes must not carry remote URLs` );
	}

	declared.set( name, directory );
	notes.push( `${ directory }: apiVersion 3, server render, ${ Object.keys( metadata.attributes ?? {} ).length } attribute(s)` );
}

// ------------------------------------------------------- 2. registrar and editor
if ( existsSync( REGISTRAR ) ) {
	const registrar = readFileSync( REGISTRAR, 'utf8' );
	const listed = [ ...registrar.matchAll( /'([a-z0-9-]+)',/g ) ].map( ( match ) => match[ 1 ] );
	const names = [ ...registrar.matchAll( /wavira_block_names\(\)/g ) ];
	const list = registrar.match( /return array\(([^)]*)\);/ );

	if ( 0 === names.length ) {
		problems.push( 'inc/blocks.php: wavira_block_names() is never used' );
	}

	if ( ! list ) {
		problems.push( 'inc/blocks.php: wavira_block_names() has no list to compare' );
	} else {
		const registered = [ ...list[ 1 ].matchAll( /'([a-z0-9-]+)'/g ) ].map( ( match ) => match[ 1 ] );
		const missing = directories.filter( ( directory ) => ! registered.includes( directory ) );
		const extra = registered.filter( ( directory ) => ! directories.includes( directory ) );

		if ( missing.length > 0 ) {
			problems.push( `inc/blocks.php: ${ missing.join( ', ' ) } not registered` );
		}

		if ( extra.length > 0 ) {
			problems.push( `inc/blocks.php: ${ extra.join( ', ' ) } registered but not on disk` );
		}

		if ( listed.length > 0 && registered.length === directories.length ) {
			notes.push( `registrar: ${ registered.length } block(s) registered on init` );
		}
	}
} else {
	problems.push( 'wavira/inc/blocks.php is missing' );
}

if ( existsSync( EDITOR ) ) {
	const editor = readFileSync( EDITOR, 'utf8' );
	const registered = new Set( [ ...editor.matchAll( /register\(\s*'(wavira\/[a-z0-9-]+)'/g ) ].map( ( match ) => match[ 1 ] ) );

	for ( const name of declared.keys() ) {
		if ( ! registered.has( name ) ) {
			problems.push( `blocks/editor.js: ${ name } is not registered in the editor` );
		}
	}

	for ( const name of registered ) {
		if ( ! declared.has( name ) ) {
			problems.push( `blocks/editor.js: ${ name } has no block.json on disk` );
		}
	}

	if ( /\bfrom\s+['"]@wordpress\//.test( editor ) ) {
		problems.push( 'blocks/editor.js: must use the wp.* globals, never an import that needs a build step (ADR 0006)' );
	}

	notes.push( `editor: ${ registered.size } block(s) registered from the wp.* globals` );
} else {
	problems.push( 'wavira/blocks/editor.js is missing' );
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

console.log( `      OK    ${ declared.size } block(s) consistent across metadata, renderer, registrar and editor` );
