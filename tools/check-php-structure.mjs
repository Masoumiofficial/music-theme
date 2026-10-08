#!/usr/bin/env node
/**
 * Structure gate for PHP files: the two defects a syntax checker cannot see.
 *
 * `php -l` (and this repository's sandbox stand-in, `token_get_all(TOKEN_PARSE)`)
 * answers one question — "does this file parse?" — and a comment that swallows a
 * method parses perfectly. That is not hypothetical: an edit that inserted a
 * `@param` line above `Placeholders::store()` dropped the docblock's closing
 * delimiter, so the method became part of the comment above it, `self::store()`
 * became a call to a method that does not exist, and the only thing that noticed
 * was CI — three jobs, one of them the render — after the change had been pushed.
 * A syntax gate said PASS on the same file.
 *
 * So this reads every PHP file and reports:
 *
 *   1. a `function` declaration inside a comment — a docblock, a block comment or
 *      a line comment; this file's own header had to be written carefully for the
 *      same reason, since a documentary example of a closing delimiter ends the
 *      comment it is written in;
 *   2. a docblock immediately above a declaration whose `@param` count does not
 *      match the declaration's parameters — the other half of the same mistake,
 *      and the one that quietly documents an argument that is not there;
 *   3. a namespaced file that names a global class without importing it. PHP
 *      resolves an unqualified class name against the current namespace and does
 *      **not** fall back to the global one — but only `instanceof` and `catch`
 *      stay quiet about it: `$post instanceof WP_Post` inside
 *      `namespace Wavira\Core\Demo` asks for `Wavira\Core\Demo\WP_Post`, gets
 *      `false`, and the branch is never taken. That is how the demo import
 *      "translated" WordPress's sample content to a return value of zero with no
 *      error anywhere: `php -l` parses, the integration suite passes, and the
 *      front page keeps showing “Hello world!”.
 *
 * The scanner masks comments and strings in place (same offsets, spaces where the
 * content was), so positions in the masked text and the real text agree.
 *
 * Usage: node tools/check-php-structure.mjs [path …]
 * Exits 1 and prints the file and line of every finding. Without arguments it
 * walks the repository's PHP trees.
 */

import { readFileSync, readdirSync, statSync } from 'node:fs';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const ROOT = resolve( dirname( fileURLToPath( import.meta.url ) ), '..' );

/**
 * Mask comments and strings, keeping every offset in place.
 *
 * @param {string} source PHP source.
 * @return {{masked: string, comments: Array<{start: number, end: number, text: string}>}}
 *         The masked text and every comment it masked.
 */
export function mask( source ) {
	const characters = source.split( '' );
	const comments = [];
	let i = 0;
	const length = source.length;

	// A character is masked when it is a space where the source had something else;
	// newlines survive so line numbers can be read off either string.
	const maskRange = ( from, to ) => {
		for ( let k = from; k < to && k < length; k++ ) {
			// Keep newlines: line numbers are read off the masked text.
			if ( '\n' !== source[ k ] ) {
				characters[ k ] = ' ';
			}
		}
	};

	while ( i < length ) {
		const character = source[ i ];

		// `// …`, `# …` (but not `#[`, which is an attribute).
		if ( ( '/' === character && '/' === source[ i + 1 ] ) || ( '#' === character && '[' !== source[ i + 1 ] ) ) {
			let end = source.indexOf( '\n', i );
			end = -1 === end ? length : end;
			comments.push( { start: i, end, text: source.slice( i, end ), line: lineAt( source, i ) } );
			maskRange( i, end );
			i = end;
			continue;
		}

		// `/* … */`, which is also a docblock when it starts with `/**`.
		if ( '/' === character && '*' === source[ i + 1 ] ) {
			const close = source.indexOf( '*/', i + 2 );
			const end = -1 === close ? length : close + 2;
			comments.push( { start: i, end, text: source.slice( i, end ), line: lineAt( source, i ) } );
			maskRange( i, end );
			i = end;
			continue;
		}

		// `'…'` and `"…"`, with backslash escapes.
		if ( "'" === character || '"' === character ) {
			let k = i + 1;
			while ( k < length ) {
				if ( '\\' === source[ k ] ) {
					k += 2;
					continue;
				}
				if ( source[ k ] === character ) {
					k++;
					break;
				}
				// A single-quoted string does not span lines; an unterminated one is
				// the syntax gate's business, not this one's.
				if ( '\n' === source[ k ] ) {
					break;
				}
				k++;
			}
			maskRange( i + 1, k - 1 );
			i = k;
			continue;
		}

		i++;
	}

	return { masked: characters.join( '' ), comments };
}

/** 1-based line number of an offset. */
function lineAt( source, offset ) {
	return source.slice( 0, offset ).split( '\n' ).length;
}

/**
 * Findings for one file.
 *
 * @param {string} source PHP source.
 * @return {Array<{line: number, rule: string, message: string}>} What is wrong.
 */
export function inspect( source ) {
	const { masked, comments } = mask( source );
	const findings = [];

	// 1. a declaration the masker swallowed: it is inside a comment.
	//
	// Only a *block* comment can swallow one — a line comment ends at its newline,
	// so a sentence that mentions `function store()` in prose is not a defect
	// (this file's own header does exactly that).
	const declaration = /\bfunction\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/g;
	const blockComments = comments.filter( ( comment ) => comment.text.startsWith( '/*' ) );

	for ( const match of source.matchAll( declaration ) ) {
		const functionOffset = match.index;
		const container = blockComments.find( ( comment ) => comment.start < functionOffset && functionOffset < comment.end );

		if ( container ) {
			findings.push( {
				line: lineAt( source, functionOffset ),
				rule: 'declaration-in-comment',
				message:
					`“function ${ match[ 1 ] }()” is inside a block comment that opened on line ${ container.line } — ` +
					'an unclosed docblock above it made the method disappear, and a syntax check cannot see it',
			} );
		}
	}

	// 2. a docblock whose `@param` count does not match the declaration.
	const functions = [];
	const maskedDeclarations = masked.matchAll( declaration );

	for ( const match of maskedDeclarations ) {
		const name = match[ 1 ];
		const open = masked.indexOf( '(', match.index );
		let depth = 0;
		let close = open;

		for ( let k = open; k < masked.length; k++ ) {
			if ( '(' === masked[ k ] ) {
				depth++;
			} else if ( ')' === masked[ k ] ) {
				depth--;
				if ( 0 === depth ) {
					close = k;
					break;
				}
			}
		}

		const signature = source.slice( open, close + 1 );
		const parameters = ( signature.match( /\$[A-Za-z_][A-Za-z0-9_]*/g ) || [] ).length;
		functions.push( { name, offset: match.index, parameters } );
	}

	// The comment immediately above a declaration, when only whitespace separates
	// it from the declaration, is that declaration's docblock.
	for ( const { name, offset, parameters } of functions ) {
		const before = comments.filter( ( comment ) => comment.end <= offset ).pop();

		if ( ! before || ! before.text.startsWith( '/**' ) ) {
			continue;
		}

		if ( masked.slice( before.end, offset ).trim() !== '' ) {
			continue;
		}

		const documented = ( before.text.match( /@param\b/g ) || [] ).length;

		if ( documented !== parameters ) {
			findings.push( {
				line: lineAt( source, before.start ),
				rule: 'param-count',
				message: `${ name }() takes ${ parameters } parameter(s) and its docblock documents ${ documented } — the docblock and the signature disagree`,
			} );
		}
	}

	// 3. a global class named without a `use` in a namespaced file.
	//
	// Only classes that are WordPress's own (`WP_*`, `wpdb`) and only the places
	// where a wrong answer is silent — `instanceof`, `catch`, `new`, static calls
	// and inheritance all resolve the same way, but the first two fail quietly.
	const namespace = masked.match( /^\s*namespace\s+([A-Za-z_][A-Za-z0-9_\\]*)\s*;/m );

	if ( namespace ) {
		const imported = new Set();

		for ( const match of masked.matchAll( /^\s*use\s+([A-Za-z_][A-Za-z0-9_\\]*)(?:\s+as\s+([A-Za-z_][A-Za-z0-9_]*))?\s*;/gm ) ) {
			imported.add( match[ 2 ] || match[ 1 ].split( '\\' ).pop() );
		}

		const globalClass = /(?:\binstanceof\s+|\bcatch\s*\(\s*|\bnew\s+)((?:WP|wpdb)[A-Za-z0-9_]*)/g;

		for ( const match of masked.matchAll( globalClass ) ) {
			const name = match[ 1 ];

			if ( imported.has( name ) ) {
				continue;
			}

			findings.push( {
				line: lineAt( source, match.index ),
				rule: 'core-class-import',
				message:
					`“${ name }” is used unqualified in namespace ${ namespace[ 1 ] } and never imported — ` +
					'PHP does not fall back to the global class, so this branch is never taken (add `use ' +
					name +
					';`)',
			} );
		}
	}

	return findings;
}

/** Every PHP file under a path. */
function phpFiles( path ) {
	const stat = statSync( path );

	if ( stat.isFile() ) {
		return path.endsWith( '.php' ) ? [ path ] : [];
	}

	return readdirSync( path ).flatMap( ( name ) => {
		const child = join( path, name );

		return /node_modules|vendor|\.git/.test( child ) ? [] : phpFiles( child );
	} );
}

/* c8 ignore start — the walk is exercised by the CI job, not by node:test. */
if ( process.argv[ 1 ] && import.meta.url === pathToFileURL( process.argv[ 1 ] ).href ) {
	const paths = process.argv.slice( 2 );
	const targets = ( paths.length > 0 ? paths : [ 'wavira', 'wavira-core', 'tests', 'bin' ] )
		.map( ( path ) => resolve( ROOT, path ) )
		.filter( ( path ) => {
			try {
				return statSync( path );
			} catch {
				return false;
			}
		} )
		.flatMap( phpFiles );

	let broken = 0;

	for ( const file of targets ) {
		for ( const finding of inspect( readFileSync( file, 'utf8' ) ) ) {
			broken++;
			console.log( `${ relative( ROOT, file ) }:${ finding.line }: ${ finding.message }` );
		}
	}

	if ( broken > 0 ) {
		console.log( `      FAIL ${ broken } structural problem(s) in PHP files` );
		process.exit( 1 );
	}

	console.log(
		`      OK    ${ targets.length } PHP file(s): no declaration inside a comment, no docblock mismatch, no missing core-class import`
	);
}
/* c8 ignore stop */
