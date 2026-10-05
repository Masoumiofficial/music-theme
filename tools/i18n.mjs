#!/usr/bin/env node
/**
 * Translation tooling: extract, compile, and gate the shipped catalogues.
 *
 * The product ships Persian (`fa_IR`) translations *in the repository*, because
 * the audience is Iranian music sites and a theme that has to be translated
 * before it is usable is a theme nobody installs. Keeping that promise honest
 * needs three things this tool provides:
 *
 *   extract   scan the PHP sources and (re)write `languages/<domain>.pot`
 *   build     compile `languages/<locale>.po` into the `.mo` WordPress loads
 *   check     fail the build when the catalogues drift from the sources
 *
 * Two rules make the gate meaningful rather than decorative:
 *
 * - the committed `.pot` must match the sources byte for byte, so a new string
 *   cannot be added without the catalogue being regenerated;
 * - every translatable string must have a Persian translation that actually
 *   contains Persian script, unless the entry carries the `keep-latin` flag
 *   (brand names, format-only strings such as `%1$s (%2$d)`, `MP3`, `320`).
 *
 * There is no gettext dependency: the extractor and the MO writer are here, in
 * this file, so the pipeline runs anywhere Node runs — the same reason the
 * product has no build step at runtime (ADR 0006).
 *
 * Usage:
 *   node tools/i18n.mjs extract [--domain wavira]
 *   node tools/i18n.mjs build   [--locale fa_IR]
 *   node tools/i18n.mjs check
 */

import { existsSync, readFileSync, readdirSync, statSync, writeFileSync } from 'node:fs';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = resolve( dirname( fileURLToPath( import.meta.url ) ), '..' );

/** The two artifacts and the text domain each one owns. */
const DOMAINS = [
	{ domain: 'wavira', source: 'wavira', pot: 'wavira/languages/wavira.pot', languages: 'wavira/languages' },
	{ domain: 'wavira-core', source: 'wavira-core', pot: 'wavira-core/languages/wavira-core.pot', languages: 'wavira-core/languages' },
];

const SKIP_DIRS = new Set( [ 'node_modules', 'vendor', 'dist', 'languages' ] );

/**
 * Translatable functions and the argument layout of each one.
 *
 * `strings` is how many literal arguments come before the text domain:
 * `__( $text, $domain )` has one, `_x( $text, $context, $domain )` has two.
 */
const FUNCTIONS = {
	__: { strings: 1 },
	_e: { strings: 1 },
	esc_html__: { strings: 1 },
	esc_html_e: { strings: 1 },
	esc_attr__: { strings: 1 },
	esc_attr_e: { strings: 1 },
	_x: { strings: 2, context: true },
	esc_html_x: { strings: 2, context: true },
	_n: { strings: 2, plural: true },
	_nx: { strings: 2, plural: true, context: true },
};

const PERSIAN = /[\u0600-\u06FF]/;
const LATIN = /[A-Za-z]/;

const problems = [];
const notes = [];

// --------------------------------------------------------------------- helpers

/**
 * Remove PHP comments while keeping offsets stable (comments become spaces).
 *
 * A string such as "… see `__( 'x', 'y' )` …" inside a comment must not become
 * a translatable string, and string literals must survive untouched, escapes
 * included, because the extractor reads them next.
 *
 * @param {string} source PHP source.
 * @return {{code: string, comments: {end: number, text: string}[]}} Source with
 *   comments blanked out, plus the comments that mention translators.
 */
function stripComments( source ) {
	const comments = [];
	const out = source.split( '' );
	let i = 0;
	const length = source.length;

	while ( i < length ) {
		const char = source[ i ];
		const next = source[ i + 1 ];

		// A single- or double-quoted string: copy it verbatim, honouring escapes.
		if ( char === "'" || char === '"' ) {
			out[ i ] = char;
			i++;

			while ( i < length ) {
				out[ i ] = source[ i ];
				if ( source[ i ] === '\\' ) {
					out[ i + 1 ] = source[ i + 1 ];
					i += 2;
					continue;
				}
				if ( source[ i ] === char ) {
					i++;
					break;
				}
				i++;
			}
			continue;
		}

		if ( char === '/' && next === '/' ) {
			const start = i;
			while ( i < length && source[ i ] !== '\n' ) {
				out[ i ] = ' ';
				i++;
			}
			const text = source.slice( start, i );
			if ( /translators:/i.test( text ) ) {
				comments.push( { end: i, text: text.replace( /^\s*\/\/\s*/, '' ).trim() } );
			}
			continue;
		}

		if ( char === '#' ) {
			const start = i;
			while ( i < length && source[ i ] !== '\n' ) {
				out[ i ] = ' ';
				i++;
			}
			const text = source.slice( start, i );
			if ( /translators:/i.test( text ) ) {
				comments.push( { end: i, text: text.replace( /^\s*#\s*/, '' ).trim() } );
			}
			continue;
		}

		if ( char === '/' && next === '*' ) {
			const start = i;
			const end = source.indexOf( '*/', i + 2 );
			const stop = end === -1 ? length : end + 2;

			for ( let j = i; j < stop; j++ ) {
				out[ j ] = source[ j ] === '\n' ? '\n' : ' ';
			}

			const text = source.slice( start, stop );
			if ( /translators:/i.test( text ) ) {
				comments.push( { end: stop, text: text.replace( /^\s*\/\*\s*/, '' ).replace( /\s*\*\/\s*$/, '' ).trim() } );
			}

			i = stop;
			continue;
		}

		out[ i ] = char;
		i++;
	}

	return { code: out.join( '' ), comments };
}

/**
 * Read a PHP string literal that starts at `start` (a quote character).
 *
 * @param {string} code     Comment-free source.
 * @param {number} start    Index of the opening quote.
 * @return {{value: string, end: number}|null} Literal and the index after it.
 */
function readLiteral( code, start ) {
	const quote = code[ start ];

	if ( quote !== "'" && quote !== '"' ) {
		return null;
	}

	let i = start + 1;
	let value = '';

	while ( i < code.length ) {
		const char = code[ i ];

		if ( char === '\\' ) {
			const escaped = code[ i + 1 ];

			// In a single-quoted string PHP only unescapes \' and \\.
			if ( quote === "'" ) {
				value += escaped === "'" || escaped === '\\' ? escaped : '\\' + escaped;
			} else {
				const map = { n: '\n', t: '\t', r: '\r', '"': '"', '\\': '\\', $: '$', 0: '\0' };
				value += escaped in map ? map[ escaped ] : escaped;
			}

			i += 2;
			continue;
		}

		if ( char === quote ) {
			return { value, end: i + 1 };
		}

		value += char;
		i++;
	}

	return null;
}

/**
 * Read the literal arguments of a call whose opening parenthesis is at `start`.
 *
 * @param {string} code  Comment-free source.
 * @param {number} start Index of the `(`.
 * @return {{args: (string|null)[], end: number}} One entry per argument: the
 *   literal value, or null when the argument is not a plain string.
 */
function readArguments( code, start ) {
	const args = [];
	let i = start + 1;
	let depth = 1;
	let current = null;
	let started = false;

	while ( i < code.length ) {
		const char = code[ i ];

		if ( char === '(' || char === '[' ) {
			depth++;
			i++;
			continue;
		}

		if ( char === ')' || char === ']' ) {
			depth--;

			if ( depth === 0 ) {
				args.push( current !== null ? current : ( started ? '' : null ) );
				return { args, end: i + 1 };
			}

			i++;
			continue;
		}

		if ( char === ',' && depth === 1 ) {
			args.push( current );
			current = null;
			started = false;
			i++;
			continue;
		}

		if ( char === "'" || char === '"' ) {
			const literal = readLiteral( code, i );

			if ( literal && depth === 1 && current === null ) {
				current = literal.value;
			}

			i = literal ? literal.end : i + 1;
			started = true;
			continue;
		}

		if ( depth === 1 && ! /\s/.test( char ) ) {
			started = true;
		}

		i++;
	}

	return { args, end: i };
}

// ------------------------------------------------------------------- extraction

/**
 * Collect every translatable string from one file.
 *
 * @param {string} file   Absolute path.
 * @param {string} domain Expected text domain.
 * @return {{key: string, msgid: string, msgidPlural: string, context: string, comment: string, reference: string}[]}
 */
function extractFile( file, domain ) {
	const source = readFileSync( file, 'utf8' );
	const { code, comments } = stripComments( source );
	const found = [];
	const matcher = new RegExp( `\\b(${ Object.keys( FUNCTIONS ).join( '|' ) })\\s*\\(`, 'g' );
	let match;

	while ( ( match = matcher.exec( code ) ) !== null ) {
		// Skip a call that is really a method ( `$this->__(` ) or a definition.
		const before = code.slice( Math.max( 0, match.index - 40 ), match.index );
		if ( /(->|::|function\s+)$/.test( before ) ) {
			continue;
		}

		const name = match[ 1 ];
		const spec = FUNCTIONS[ name ];
		const { args } = readArguments( code, match.index + match[ 0 ].length - 1 );
		const literals = args.filter( ( arg ) => typeof arg === 'string' );

		if ( literals.length < spec.strings + 1 || literals[ literals.length - 1 ] !== domain ) {
			problems.push( `${ relative( ROOT, file ) }:${ code.slice( 0, match.index ).split( '\n' ).length }: ${ name }() is not called with the “${ domain }” text domain` );
			continue;
		}

		const msgid = args[ 0 ];
		let context = '';
		let plural = '';

		if ( spec.context ) {
			context = args[ 1 ] ?? '';
		}

		if ( spec.plural ) {
			plural = args[ 1 ] ?? '';
		}

		if ( typeof msgid !== 'string' || '' === msgid ) {
			continue;
		}

		const line = code.slice( 0, match.index ).split( '\n' ).length;
		const translators = comments
			.filter( ( comment ) => comment.end <= match.index && match.index - comment.end < 240 )
			.pop();

		found.push( {
			key: `${ context }\u0004${ msgid }\u0004${ plural }`,
			msgid,
			msgidPlural: plural,
			context,
			comment: translators ? translators.text : '',
			reference: `${ relative( ROOT, file ) }:${ line }`,
		} );
	}

	return found;
}

/**
 * Recursive file list.
 *
 * @param {string} directory Absolute directory.
 * @param {string} extension Extension to keep.
 * @return {string[]} Absolute paths, sorted.
 */
function walk( directory, extension ) {
	const found = [];

	for ( const name of readdirSync( directory ).sort() ) {
		const path = join( directory, name );

		if ( statSync( path ).isDirectory() ) {
			if ( ! SKIP_DIRS.has( name ) ) {
				found.push( ...walk( path, extension ) );
			}
			continue;
		}

		if ( name.endsWith( extension ) ) {
			found.push( path );
		}
	}

	return found;
}

/**
 * Collect the translatable fields of a block.json file.
 *
 * Core translates `title`, `description` and `keywords` from block metadata with
 * `translate_settings_using_i18n_schema()` (wp-includes/blocks.php), using the
 * contexts in wp-includes/block-i18n.json: “block title”, “block description”
 * and “block keyword”. Those contexts are what the catalogue must carry, or the
 * block stays English in the inserter even though the theme is translated.
 *
 * @param {string} file Absolute path to block.json.
 * @param {string} domain Text domain declared by the block.
 * @return {object[]} Entries in the same shape as extractFile().
 */
function extractBlockJson( file, domain ) {
	const metadata = JSON.parse( readFileSync( file, 'utf8' ) );
	const found = [];
	const relativePath = relative( ROOT, file );

	if ( metadata.textdomain && metadata.textdomain !== domain ) {
		problems.push( `${ relativePath }: textdomain is “${ metadata.textdomain }”, expected “${ domain }”` );
	}

	const add = ( value, context ) => {
		if ( typeof value !== 'string' || '' === value ) {
			return;
		}

		found.push( {
			key: `${ context }\u0004${ value }\u0004`,
			msgid: value,
			msgidPlural: '',
			context,
			comment: '',
			reference: relativePath,
		} );
	};

	add( metadata.title, 'block title' );
	add( metadata.description, 'block description' );

	for ( const keyword of metadata.keywords ?? [] ) {
		add( keyword, 'block keyword' );
	}

	return found;
}

/**
 * Extract a whole text domain.
 *
 * @param {{domain: string, source: string}} artifact Domain descriptor.
 * @return {{entries: Map<string, object>, files: number}} Entries keyed by msgid.
 */
function extractDomain( artifact ) {
	const files = walk( join( ROOT, artifact.source ), '.php' );
	const blocksDirectory = join( ROOT, artifact.source, 'blocks' );
	const blockFiles = existsSync( blocksDirectory )
		? readdirSync( blocksDirectory ).map( ( name ) => join( blocksDirectory, name, 'block.json' ) ).filter( existsSync )
		: [];
	const entries = new Map();

	for ( const file of [ ...files, ...blockFiles ] ) {
		const extracted = file.endsWith( '.json' ) ? extractBlockJson( file, artifact.domain ) : extractFile( file, artifact.domain );

		for ( const entry of extracted ) {
			const existing = entries.get( entry.key );

			if ( existing ) {
				if ( ! existing.references.includes( entry.reference ) ) {
					existing.references.push( entry.reference );
				}
				if ( ! existing.comment && entry.comment ) {
					existing.comment = entry.comment;
				}
				continue;
			}

			entries.set( entry.key, { ...entry, references: [ entry.reference ] } );
		}
	}

	return { entries, files: files.length + blockFiles.length };
}

/**
 * Render a POT file.
 *
 * @param {{domain: string, source: string}} artifact Domain descriptor.
 * @param {Map<string, object>} entries Extracted entries.
 * @return {string} POT content.
 */
function renderPot( artifact, entries ) {
	const stamp = process.env.WAVIRA_POT_DATE || '2026-10-05';
	const lines = [
		'# Copyright (C) 2026 Etehad WP — اتحاد وردپرس',
		'# This file is distributed under the GPL-2.0-or-later licence.',
		`msgid ""`,
		`msgstr ""`,
		`"Project-Id-Version: ${ artifact.domain } 0.10.0\\n"`,
		`"Report-Msgid-Bugs-To: https://etehadwp.com/\\n"`,
		`"POT-Creation-Date: ${ stamp }\\n"`,
		`"MIME-Version: 1.0\\n"`,
		`"Content-Type: text/plain; charset=UTF-8\\n"`,
		`"Content-Transfer-Encoding: 8bit\\n"`,
		`"X-Domain: ${ artifact.domain }\\n"`,
	];

	const sorted = [ ...entries.values() ].sort( ( a, b ) => a.key.localeCompare( b.key ) );

	for ( const entry of sorted ) {
		lines.push( '' );

		for ( const reference of entry.references ) {
			lines.push( `#: ${ reference }` );
		}

		if ( entry.comment ) {
			lines.push( `#. ${ entry.comment }` );
		}

		if ( entry.context ) {
			lines.push( `msgctxt "${ escapePo( entry.context ) }"` );
		}

		lines.push( `msgid "${ escapePo( entry.msgid ) }"` );

		if ( entry.msgidPlural ) {
			lines.push( `msgid_plural "${ escapePo( entry.msgidPlural ) }"` );
			lines.push( 'msgstr[0] ""' );
			lines.push( 'msgstr[1] ""' );
		} else {
			lines.push( 'msgstr ""' );
		}
	}

	return lines.join( '\n' ) + '\n';
}

/**
 * Escape a string for a PO file.
 *
 * @param {string} value Raw value.
 * @return {string} Escaped value.
 */
function escapePo( value ) {
	return value.replace( /\\/g, '\\\\' ).replace( /"/g, '\\"' ).replace( /\n/g, '\\n' ).replace( /\t/g, '\\t' );
}

/**
 * Unescape a PO string body.
 *
 * @param {string} value Escaped value.
 * @return {string} Raw value.
 */
function unescapePo( value ) {
	return value
		.replace( /\\n/g, '\n' )
		.replace( /\\t/g, '\t' )
		.replace( /\\"/g, '"' )
		.replace( /\\\\/g, '\\' );
}

/**
 * Parse a PO file into entries.
 *
 * @param {string} source PO content.
 * @return {{entries: object[], header: Record<string, string>, flags: Map<string, string[]>}} Parsed data.
 */
function parsePo( source ) {
	const entries = [];
	const flags = new Map();
	let current = null;
	let lastField = '';

	const commit = () => {
		if ( current && ( current.msgid !== null || current.msgctxt !== null ) ) {
			entries.push( current );
		}
		current = null;
	};

	for ( const rawLine of source.split( '\n' ) ) {
		const line = rawLine.trim();

		if ( '' === line ) {
			commit();
			lastField = '';
			continue;
		}

		if ( line.startsWith( '#' ) ) {
			if ( ! current ) {
				current = { msgctxt: null, msgid: null, msgidPlural: null, msgstr: [], flagList: [] };
			}

			if ( line.startsWith( '#,' ) ) {
				current.flagList.push( ...line.slice( 2 ).split( ',' ).map( ( flag ) => flag.trim() ) );
			}
			continue;
		}

		if ( ! current ) {
			current = { msgctxt: null, msgid: null, msgidPlural: null, msgstr: [], flagList: [] };
		}

		if ( line.startsWith( 'msgctxt ' ) ) {
			current.msgctxt = unescapePo( line.slice( 8 ).replace( /^"|"$/g, '' ) );
			lastField = 'msgctxt';
			continue;
		}

		if ( line.startsWith( 'msgid_plural ' ) ) {
			current.msgidPlural = ( current.msgidPlural || '' ) + unescapePo( line.slice( 13 ).replace( /^"|"$/g, '' ) );
			lastField = 'msgid_plural';
			continue;
		}

		if ( line.startsWith( 'msgid ' ) ) {
			current.msgid = ( current.msgid || '' ) + unescapePo( line.slice( 6 ).replace( /^"|"$/g, '' ) );
			current.msgid = current.msgid === '' ? '' : current.msgid;
			lastField = 'msgid';
			continue;
		}

		if ( line.startsWith( 'msgstr[' ) ) {
			const index = Number( line.slice( 7, line.indexOf( ']' ) ) );
			const value = unescapePo( line.slice( line.indexOf( '"' ) + 1, line.lastIndexOf( '"' ) ) );
			current.msgstr[ index ] = ( current.msgstr[ index ] || '' ) + value;
			lastField = 'msgstr';
			continue;
		}

		if ( line.startsWith( 'msgstr ' ) ) {
			const value = unescapePo( line.slice( 7 ).replace( /^"|"$/g, '' ) );
			current.msgstr[ 0 ] = ( current.msgstr[ 0 ] || '' ) + value;
			lastField = 'msgstr';
			continue;
		}

		if ( line.startsWith( '"' ) && current ) {
			const value = unescapePo( line.slice( 1, line.lastIndexOf( '"' ) ) );

			if ( 'msgid' === lastField ) {
				current.msgid += value;
			} else if ( 'msgctxt' === lastField ) {
				current.msgctxt += value;
			} else if ( 'msgid_plural' === lastField ) {
				current.msgidPlural += value;
			} else if ( 'msgstr' === lastField ) {
				const index = current.msgstr.length - 1;
				current.msgstr[ index ] = ( current.msgstr[ index ] || '' ) + value;
			}
		}
	}

	commit();

	const header = {};
	const headerEntry = entries.find( ( entry ) => entry.msgid === '' && entry.msgstr[ 0 ] );

	if ( headerEntry ) {
		for ( const line of headerEntry.msgstr[ 0 ].split( '\n' ) ) {
			const [ key, ...rest ] = line.split( ':' );

			if ( rest.length > 0 ) {
				header[ key.trim() ] = rest.join( ':' ).trim();
			}
		}
	}

	for ( const entry of entries ) {
		flags.set( `${ entry.msgctxt ?? '' }\u0004${ entry.msgid }\u0004${ entry.msgidPlural ?? '' }`, entry.flagList ?? [] );
	}

	return { entries, header, flags };
}

// ------------------------------------------------------------------- MO writing

/**
 * Compile PO entries into a GNU MO file.
 *
 * @param {object[]} entries PO entries (header first).
 * @return {Buffer} MO file contents.
 */
function compileMo( entries ) {
	const pairs = entries
		.filter( ( entry ) => entry.msgid !== null )
		.map( ( entry ) => {
			const context = entry.msgctxt ? `${ entry.msgctxt }\u0004` : '';
			const id = entry.msgidPlural ? `${ context }${ entry.msgid }\0${ entry.msgidPlural }` : `${ context }${ entry.msgid }`;
			const value = ( entry.msgstr.length > 1 ? entry.msgstr : [ entry.msgstr[ 0 ] ?? '' ] ).join( '\0' );

			return { id, value };
		} )
		.sort( ( a, b ) => a.id.localeCompare( b.id ) );

	const count = pairs.length;
	const headerSize = 28;
	const tableSize = count * 8;
	const original = Buffer.alloc( tableSize );
	const translation = Buffer.alloc( tableSize );
	const ids = pairs.map( ( pair ) => Buffer.from( pair.id + '\0', 'utf8' ) );
	const values = pairs.map( ( pair ) => Buffer.from( pair.value + '\0', 'utf8' ) );

	// The GNU layout is strict: the string table holds every id first, then
	// every value — the offsets cannot be assigned while walking one pair.
	let offset = headerSize + tableSize * 2;

	for ( let index = 0; index < count; index++ ) {
		original.writeUInt32LE( ids[ index ].length, index * 8 );
		original.writeUInt32LE( offset, index * 8 + 4 );
		offset += ids[ index ].length;
	}

	for ( let index = 0; index < count; index++ ) {
		translation.writeUInt32LE( values[ index ].length, index * 8 );
		translation.writeUInt32LE( offset, index * 8 + 4 );
		offset += values[ index ].length;
	}

	const header = Buffer.alloc( headerSize );
	header.writeUInt32LE( 0x950412de, 0 );
	header.writeUInt32LE( 0, 4 );
	header.writeUInt32LE( count, 8 );
	header.writeUInt32LE( headerSize, 12 );
	header.writeUInt32LE( headerSize + tableSize, 16 );
	header.writeUInt32LE( 0, 20 );
	header.writeUInt32LE( offset, 24 );

	return Buffer.concat( [ header, original, translation, ...ids, ...values ] );
}

/**
 * Read a GNU MO file back into entries.
 *
 * @param {Buffer} buffer MO contents.
 * @return {Map<string, string>} msgid (with context prefix) to msgstr.
 */
function readMo( buffer ) {
	if ( buffer.readUInt32LE( 0 ) !== 0x950412de ) {
		throw new Error( 'not a GNU MO file' );
	}

	const count = buffer.readUInt32LE( 8 );
	const originalOffset = buffer.readUInt32LE( 12 );
	const translationOffset = buffer.readUInt32LE( 16 );
	const table = new Map();

	for ( let index = 0; index < count; index++ ) {
		const idLength = buffer.readUInt32LE( originalOffset + index * 8 );
		const idOffset = buffer.readUInt32LE( originalOffset + index * 8 + 4 );
		const valueLength = buffer.readUInt32LE( translationOffset + index * 8 );
		const valueOffset = buffer.readUInt32LE( translationOffset + index * 8 + 4 );

		const id = buffer.toString( 'utf8', idOffset, idOffset + idLength - 1 );
		const value = buffer.toString( 'utf8', valueOffset, valueOffset + valueLength - 1 );
		table.set( id, value );
	}

	return table;
}

// ----------------------------------------------------------------------- modes

/**
 * `extract`: write the POT files and report the count.
 *
 * @param {string[]} argv Command line arguments.
 * @return {number} Exit code.
 */
function modeExtract( argv ) {
	const domainFilter = argv.includes( '--domain' ) ? argv[ argv.indexOf( '--domain' ) + 1 ] : null;

	for ( const artifact of DOMAINS ) {
		if ( domainFilter && artifact.domain !== domainFilter ) {
			continue;
		}

		const { entries, files } = extractDomain( artifact );
		const pot = renderPot( artifact, entries );

		writeFileSync( join( ROOT, artifact.pot ), pot, 'utf8' );

		console.log( `      · ${ artifact.domain }: ${ entries.size } string(s) from ${ files } PHP file(s) → ${ artifact.pot }` );
	}

	return problems.length > 0 ? 1 : 0;
}

/**
 * `build`: compile every PO file into its MO.
 *
 * @param {string[]} argv Command line arguments.
 * @return {number} Exit code.
 */
function modeBuild( argv ) {
	const localeFilter = argv.includes( '--locale' ) ? argv[ argv.indexOf( '--locale' ) + 1 ] : null;

	for ( const artifact of DOMAINS ) {
		const directory = join( ROOT, artifact.languages );

		if ( ! existsSync( directory ) ) {
			continue;
		}

		for ( const name of readdirSync( directory ).sort() ) {
			if ( ! name.endsWith( '.po' ) ) {
				continue;
			}

			const locale = name.slice( 0, -3 );

			if ( localeFilter && locale !== localeFilter ) {
				continue;
			}

			const { entries } = parsePo( readFileSync( join( directory, name ), 'utf8' ) );
			const mo = compileMo( entries );

			writeFileSync( join( directory, `${ locale }.mo` ), mo );

			console.log( `      · ${ artifact.domain}: ${ name } → ${ locale }.mo (${ entries.length - 1 } string(s), ${ mo.length } bytes)` );
		}
	}

	return 0;
}

/**
 * `check`: the gate. Sources, POT, PO and MO must agree, and the Persian
 * catalogue must actually be Persian.
 *
 * @return {number} Exit code.
 */
function modeCheck() {
	let totalStrings = 0;
	let totalTranslated = 0;

	for ( const artifact of DOMAINS ) {
		const { entries, files } = extractDomain( artifact );
		const potPath = join( ROOT, artifact.pot );

		if ( ! existsSync( potPath ) ) {
			problems.push( `${ artifact.pot }: missing — run “node tools/i18n.mjs extract”` );
			continue;
		}

		const expectedPot = renderPot( artifact, entries );

		if ( readFileSync( potPath, 'utf8' ) !== expectedPot ) {
			problems.push( `${ artifact.pot }: out of date with ${ files } source file(s) — run “node tools/i18n.mjs extract”` );
		}

		notes.push( `${ artifact.domain }: ${ entries.size } translatable string(s) in ${ files } file(s)` );

		const directory = join( ROOT, artifact.languages );
		const poFiles = existsSync( directory ) ? readdirSync( directory ).filter( ( name ) => name.endsWith( '.po' ) ) : [];

		if ( poFiles.length === 0 ) {
			problems.push( `${ artifact.languages }: no translation catalogue — Persian ships with the product` );
			continue;
		}

		for ( const name of poFiles.sort() ) {
			const locale = name.slice( 0, -3 );
			const { entries: poEntries, header } = parsePo( readFileSync( join( directory, name ), 'utf8' ) );
			const byKey = new Map();

			for ( const entry of poEntries ) {
				byKey.set( `${ entry.msgctxt ?? '' }\u0004${ entry.msgid }\u0004${ entry.msgidPlural ?? '' }`, entry );
			}

			if ( header.Language !== locale ) {
				problems.push( `${ artifact.languages }/${ name }: header “Language:” is ${ header.Language ?? '(missing)' }, expected ${ locale }` );
			}

			if ( ! header[ 'Plural-Forms' ] ) {
				problems.push( `${ artifact.languages }/${ name }: header is missing “Plural-Forms:”` );
			}

			let translated = 0;

			for ( const entry of entries.values() ) {
				const poEntry = byKey.get( entry.key );

				if ( ! poEntry ) {
					problems.push( `${ artifact.languages }/${ name }: “${ entry.msgid.slice( 0, 48 ) }” is not translated (referenced by ${ entry.references[ 0 ] })` );
					continue;
				}

				const value = poEntry.msgstr[ 0 ] ?? '';

				if ( '' === value ) {
					problems.push( `${ artifact.languages }/${ name }: “${ entry.msgid.slice( 0, 48 ) }” has an empty translation` );
					continue;
				}

				if ( entry.msgidPlural && ! ( poEntry.msgstr.length >= 2 && poEntry.msgstr[ 1 ] ) ) {
					problems.push( `${ artifact.languages }/${ name }: “${ entry.msgid.slice( 0, 48 ) }” is missing the plural form` );
					continue;
				}

				// A translation that drops or invents a placeholder breaks sprintf.
				const placeholders = ( text ) => ( text.match( /%(?:\d+\$)?[a-zA-Z]/g ) || [] ).sort().join( ',' );
				const source = `${ entry.msgid } ${ entry.msgidPlural || '' }`;
				const target = poEntry.msgstr.join( ' ' );

				if ( placeholders( source ) !== placeholders( target ) ) {
					problems.push( `${ artifact.languages }/${ name }: “${ entry.msgid.slice( 0, 48 ) }” changes the placeholders (source: ${ placeholders( source ) || 'none' }, translation: ${ placeholders( target ) || 'none' })` );
					continue;
				}

				const keepLatin = ( poEntry.flagList ?? [] ).includes( 'keep-latin' );

				if ( LATIN.test( entry.msgid ) && ! PERSIAN.test( value ) && ! keepLatin ) {
					problems.push( `${ artifact.languages }/${ name }: “${ entry.msgid.slice( 0, 48 ) }” is still Latin — translate it, or flag the entry “#, keep-latin” with a reason` );
					continue;
				}

				if ( keepLatin && value === entry.msgid && LATIN.test( entry.msgid ) && PERSIAN.test( entry.msgid ) === false && ! /[%$©]|\d/.test( entry.msgid ) ) {
					problems.push( `${ artifact.languages }/${ name }: “${ entry.msgid.slice( 0, 48 ) }” is flagged keep-latin but is an ordinary sentence` );
					continue;
				}

				translated++;
			}

			// The compiled file is what WordPress actually loads.
			const moPath = join( directory, `${ locale }.mo` );

			if ( ! existsSync( moPath ) ) {
				problems.push( `${ artifact.languages }/${ locale }.mo: missing — run “node tools/i18n.mjs build”` );
			} else {
				const table = readMo( readFileSync( moPath ) );
				const compiled = compileMo( poEntries );

				if ( ! readFileSync( moPath ).equals( compiled ) ) {
					problems.push( `${ artifact.languages }/${ locale }.mo: out of date with ${ name } — run “node tools/i18n.mjs build”` );
				}

				const header = table.get( '' ) ?? '';

				if ( ! header.includes( `Language: ${ locale }` ) ) {
					problems.push( `${ artifact.languages }/${ locale }.mo: header does not declare Language: ${ locale }` );
				}
			}

			notes.push( `${ artifact.domain}/${ locale }: ${ translated }/${ entries.size } string(s) translated` );
			totalStrings += entries.size;
			totalTranslated += translated;

			for ( const key of byKey.keys() ) {
				if ( ! [ ...entries.keys() ].includes( key ) && key !== '\u0004\u0004' ) {
					const entry = byKey.get( key );
					problems.push( `${ artifact.languages }/${ name }: “${ String( entry.msgid ).slice( 0, 48 ) }” is no longer in the sources — remove it` );
				}
			}
		}
	}

	for ( const note of notes ) {
		console.log( `      · ${ note }` );
	}

	if ( problems.length > 0 ) {
		for ( const problem of problems ) {
			console.log( `      FAIL ${ problem }` );
		}

		return 1;
	}

	console.log( `      OK    ${ totalTranslated }/${ totalStrings } string(s) translated, POT/PO/MO in sync` );

	return 0;
}

// ------------------------------------------------------------------------ main

// The pure pieces are exported so `tests/js/i18n.test.mjs` can pin the file
// formats down; the command line interface only runs when this file is the
// program that was started.
export { compileMo, extractDomain, parsePo, readMo, renderPot, stripComments, readArguments, readLiteral };

const invokedDirectly = process.argv[ 1 ] && resolve( process.argv[ 1 ] ) === fileURLToPath( import.meta.url );

if ( invokedDirectly ) {
	const [ mode, ...argv ] = process.argv.slice( 2 );

	if ( mode === 'extract' ) {
		process.exit( modeExtract( argv ) );
	} else if ( mode === 'build' ) {
		process.exit( modeBuild( argv ) );
	} else if ( mode === 'check' ) {
		process.exit( modeCheck() );
	} else {
		console.log( 'usage: node tools/i18n.mjs extract|build|check [--domain <domain>] [--locale <locale>]' );
		process.exit( 2 );
	}
}
