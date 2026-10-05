#!/usr/bin/env node
/**
 * Documentation-truth gate: the migration blueprint only names things that exist.
 *
 * `docs/MIGRATION-BLUEPRINT.md` is what the migration tool will be written from,
 * and it was authored before the schema was implemented. A row that promises a
 * `wavira_*` key which no constant declares would be discovered only when the
 * migration runs — on a real site, on real data. This gate reads the document
 * and resolves every `wavira_*` token it mentions against the code:
 *
 *   - meta keys      -> `public const … = 'wavira_…'` in Content/MetaSchema.php
 *   - post types     -> Content/PostTypes.php
 *   - taxonomies     -> Content/Taxonomies.php
 *   - settings keys  -> Settings/SettingsSchema.php
 *
 * Lines marked `[DEFERRED]` are exempt by design: they record a legacy field the
 * model deliberately does not implement yet, together with the decision that
 * holds it back.
 *
 * Usage:
 *   node tools/check-mapping.mjs        # check, exit 1 on an unknown name
 */

import { existsSync, readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = resolve( dirname( fileURLToPath( import.meta.url ) ), '..' );
const DOC = join( ROOT, 'docs/MIGRATION-BLUEPRINT.md' );
const SCHEMA = join( ROOT, 'wavira-core/src/Content/MetaSchema.php' );
const POST_TYPES = join( ROOT, 'wavira-core/src/Content/PostTypes.php' );
const TAXONOMIES = join( ROOT, 'wavira-core/src/Content/Taxonomies.php' );
const SETTINGS = join( ROOT, 'wavira-core/src/Settings/SettingsSchema.php' );

const problems = [];

for ( const path of [ DOC, SCHEMA, POST_TYPES, TAXONOMIES, SETTINGS ] ) {
	if ( ! existsSync( path ) ) {
		console.log( `      FAIL  missing file: ${ path.replace( ROOT + '/', '' ) }` );
		process.exit( 1 );
	}
}

const read = ( path ) => readFileSync( path, 'utf8' );

/** Constant values of a class that declares `const NAME = 'wavira_…';`. */
function constants( source ) {
	return new Set( [ ...source.matchAll( /const\s+[A-Z0-9_]+\s*=\s*'(wavira_[a-z0-9_]+)'/g ) ].map( ( match ) => match[ 1 ] ) );
}

const known = new Set( [
	...constants( read( SCHEMA ) ),
	...constants( read( POST_TYPES ) ),
	...constants( read( TAXONOMIES ) ),
] );

// Settings is an array schema: the keys are the array keys of all().
for ( const match of read( SETTINGS ).matchAll( /^\t\t\t'([a-z0-9_]+)'\s*=>\s*array\(/gm ) ) {
	known.add( `wavira_settings['${ match[ 1 ] }']` );
}

const lines = read( DOC ).split( '\n' );
let checked = 0;
let deferred = 0;

lines.forEach( ( line, index ) => {
	const isDeferred = line.includes( '[DEFERRED]' );
	const settingsKeys = new Set();

	// A settings pair is one token, not the sum of its parts.
	const rest = line.replace( /wavira_settings\['([a-z0-9_]+)'\]/g, ( match, key ) => {
		settingsKeys.add( `wavira_settings['${ key }']` );

		return ' ';
	} );

	const tokens = new Set( [ ...rest.matchAll( /wavira_[a-z0-9_]+/g ) ].map( ( match ) => match[ 0 ] ) );

	for ( const token of settingsKeys ) {
		tokens.add( token );
	}

	if ( 0 === tokens.size ) {
		return;
	}

	if ( isDeferred ) {
		deferred += tokens.size;

		return;
	}

	for ( const token of tokens ) {
		checked++;

		if ( ! known.has( token ) ) {
			problems.push( `docs/MIGRATION-BLUEPRINT.md:${ index + 1 }: “${ token }” exists nowhere in the code (nor is the line marked [DEFERRED])` );
		}
	}
} );

if ( problems.length > 0 ) {
	for ( const line of problems ) {
		console.log( `      FAIL ${ line }` );
	}

	process.exit( 1 );
}

console.log( `      · ${ checked } mapping token(s) resolve against the schema, ${ deferred } deferred` );
console.log( '      OK    every migration target exists in the code (or is explicitly deferred)' );
