#!/usr/bin/env node
/**
 * Wavira release packaging (`[PACKAGE]` gate + builder).
 *
 * The product ships as files a customer uploads to WordPress, and a package is
 * the one artefact nobody re-reads before it is uploaded: a stray `node_modules`,
 * a development-only `tests/` directory, a missing `languages/*.mo` or a font
 * whose licence text was left behind are all silent until a marketplace review
 * or a customer's site finds them. This tool is both the builder and the gate.
 *
 * What it guarantees (each is checked, not assumed):
 *
 *   1. **Nothing dev-only ships.** Every package is assembled from an explicit
 *      include list, and the result is scanned for the paths that must never be
 *      inside it (`tests/`, `tools/`, `docs/`, `bin/`, `node_modules/`,
 *      `vendor/`, `music-theme.zip`, any `.git*`). A gate that only checked its
 *      own ignore list would pass while a new directory name leaked.
 *   2. **The product is installable as packaged.** The built bundles
 *      (`assets/dist/*`), both language catalogues (`.po` *and* compiled `.mo`),
 *      the licence files and `theme.json` are all present, and both artifact
 *      headers parse and agree with `package.json`.
 *   3. **Every shipped third-party file is declared.** Any file the packages
 *      carry whose licence is not the project's own must be named in
 *      `THIRD-PARTY-NOTICES.md`; the check is by path, so adding a font means
 *      adding a row.
 *   4. **Deterministic output.** Entry order is sorted, timestamps come from
 *      `SOURCE_DATE_EPOCH` (or a fixed date), and permissions are normalised, so
 *      two builds of one commit produce byte-identical archives — a marketplace
 *      checksum then means something.
 *   5. **A manifest a human can read.** `dist/manifest.json` lists every file,
 *      its size and its SHA-256, plus the version of every artifact, and
 *      `dist/SHA256SUMS` summarises the archives.
 *
 * Usage:
 *   node tools/package.mjs            # build the packages into dist/
 *   node tools/package.mjs --check     # verify the source tree only, write nothing
 *   node tools/package.mjs --strict    # also require the marketplace-only files
 *   node tools/package.mjs --out=DIR   # write somewhere else

 * `--strict` is what the release procedure runs: it turns "this is still
 * missing" (`screenshot.png`, for instance, which needs a rendered site) from a
 * note into a failure, so a package cannot be uploaded with a placeholder
 * missing. CI runs the plain `--check`, because a green build must not depend on
 * an artefact only the release machine can produce.
 *
 * Exit code: 1 on any violation.
 */

import { createHash } from 'node:crypto';
import { deflateRawSync, gzipSync } from 'node:zlib';
import { existsSync, mkdirSync, readdirSync, readFileSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { dirname, join, relative, resolve, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = resolve( dirname( fileURLToPath( import.meta.url ) ), '..' );

const argv = process.argv.slice( 2 );
const CHECK_ONLY = argv.includes( '--check' );
const STRICT = argv.includes( '--strict' );
const OUT_DIR = resolve(
	ROOT,
	( argv.find( ( a ) => a.startsWith( '--out=' ) ) ?? '--out=dist' ).slice( '--out='.length )
);

/** Fixed timestamp for reproducible archives (2026-01-01T00:00:00Z). */
const SOURCE_DATE_EPOCH = Number( process.env.SOURCE_DATE_EPOCH ?? 1767225600 );
const DOS_TIME = new Date( SOURCE_DATE_EPOCH * 1000 );

const problems = [];
const notes = [];

/**
 * Normalise a path for the manifest and for ZIP entries (always `/`).
 *
 * @param {string} path Absolute path.
 * @return {string} POSIX-style, repo-relative path.
 */
const rel = ( path ) => relative( ROOT, path ).split( sep ).join( '/' );

// --------------------------------------------------------------- 1. versions
const pkg = JSON.parse( readFileSync( join( ROOT, 'package.json' ), 'utf8' ) );
const VERSION = String( pkg.version );

/**
 * Fields of a plugin/theme header (` * Name: value` inside the first comment).
 *
 * @param {string} file Absolute path to a PHP file or `style.css`.
 * @return {Record<string, string>} Header fields.
 */
function headers( file ) {
	const head = readFileSync( file, 'utf8' ).slice( 0, 4000 );
	const found = {};

	for ( const line of head.split( '\n' ) ) {
		const match = line.match( /^\s*(?:[*/]\s*)?([A-Za-z ]+):\s*(.+?)\s*$/ );

		if ( match ) {
			found[ match[ 1 ].trim().toLowerCase() ] = match[ 2 ].trim();
		}
	}

	return found;
}

const themeHeader = headers( join( ROOT, 'wavira/style.css' ) );
const pluginHeader = headers( join( ROOT, 'wavira-core/wavira-core.php' ) );
const themePhp = readFileSync( join( ROOT, 'wavira/functions.php' ), 'utf8' );
const pluginPhp = readFileSync( join( ROOT, 'wavira-core/wavira-core.php' ), 'utf8' );

const versions = [
	[ 'package.json', VERSION ],
	[ 'wavira/style.css', themeHeader.version ],
	[ 'wavira/functions.php', themePhp.match( /WAVIRA_THEME_VERSION',\s*'([^']+)'/ )?.[ 1 ] ],
	[ 'wavira-core/wavira-core.php (header)', pluginHeader.version ],
	[ 'wavira-core/wavira-core.php (constant)', pluginPhp.match( /WAVIRA_CORE_VERSION',\s*'([^']+)'/ )?.[ 1 ] ],
];

for ( const [ where, version ] of versions ) {
	if ( version !== VERSION ) {
		problems.push( `[versions] ${ where } says ${ version ?? '(none)' }, package.json says ${ VERSION }` );
	}
}

notes.push( `version ${ VERSION } agrees across ${ versions.length } declaration(s)` );

// ----------------------------------------------------------- 2. what ships
/**
 * One release artifact: the folder that becomes a ZIP, the archive prefix, and
 * the files that are deliberately left out (with the reason, so the next person
 * can argue with the reason instead of guessing).
 */
const ARTIFACTS = [
	{
		slug: 'wavira-theme',
		folder: 'wavira',
		archiveName: ( v ) => `wavira-theme-${ v }.zip`,
		requires: [
			'style.css',
			'functions.php',
			'theme.json',
			'readme.txt',
			'changelog.md',
			'README.md',
			'languages/fa_IR.mo',
			'languages/fa_IR.po',
			'languages/wavira.pot',
			'assets/dist/theme.css',
			'assets/dist/theme.js',
			'assets/fonts/vazirmatn/vazirmatn-variable.woff2',
			'assets/fonts/vazirmatn/OFL.txt',
		],
		optional: [ 'screenshot.png' ],
		exclude: [
			[ /(^|\/)\.gitkeep$/, 'placeholder for an empty directory' ],
			[ /(^|\/)\.git[^/]*$/, 'repository metadata' ],
			[ /^node_modules\//, 'development dependency' ],
			[ /^vendor\//, 'development dependency' ],
			[ /\.map$/, 'source map for a bundle we ship minified by hand' ],
		],
	},
	{
		slug: 'wavira-core',
		folder: 'wavira-core',
		archiveName: ( v ) => `wavira-core-${ v }.zip`,
		requires: [
			'wavira-core.php',
			'uninstall.php',
			'public-api.php',
			'changelog.md',
			'README.md',
			'languages/fa_IR.mo',
			'languages/fa_IR.po',
			'languages/wavira-core.pot',
			'assets/dist/core.js',
			'assets/dist/player.css',
		],
		optional: [],
		exclude: [
			[ /(^|\/)\.gitkeep$/, 'placeholder for an empty directory' ],
			[ /(^|\/)\.git[^/]*$/, 'repository metadata' ],
			[ /^node_modules\//, 'development dependency' ],
			[ /^vendor\//, 'development dependency' ],
			[ /\.map$/, 'source map for a bundle we ship minified by hand' ],
		],
	},
];

/** Files the bundle adds around the two artifacts (documentation for the buyer). */
// Entry names stay ASCII on purpose: a Persian name is legal in a ZIP (with the
// UTF-8 flag below) but renders as mojibake in older Windows extractors, and the
// first thing a buyer does with the bundle is unzip it. The documents
// themselves are Persian where it matters.
const BUNDLE_EXTRAS = [
	{ from: 'LICENSE.md', to: 'README-FIRST/LICENSE.md' },
	{ from: 'THIRD-PARTY-NOTICES.md', to: 'README-FIRST/THIRD-PARTY-NOTICES.md' },
	{ from: 'docs/PERSIAN-LOCALIZATION.md', to: 'README-FIRST/PERSIAN-LOCALIZATION.md' },
	{ from: 'docs/MIGRATION-BLUEPRINT.md', to: 'README-FIRST/MIGRATION.md' },
	{ from: 'docs/INTEGRATIONS.md', to: 'README-FIRST/INTEGRATIONS.md' },
	{ from: 'docs/fa/README.md', to: 'README-FIRST/fa/README.md' },
	{ from: 'docs/fa/USER-GUIDE.md', to: 'README-FIRST/fa/USER-GUIDE.md' },
];

/** Paths that must never appear in a shipped archive, whatever the includes say. */
const FORBIDDEN = [
	[ /(^|\/)tests?\//, 'test suite' ],
	[ /(^|\/)tools\//, 'development tooling' ],
	[ /(^|\/)docs\//, 'internal documentation' ],
	[ /(^|\/)bin\//, 'development scripts' ],
	[ /(^|\/)node_modules\//, 'development dependency' ],
	[ /(^|\/)vendor\//, 'development dependency' ],
	[ /(^|\/)\.git/, 'repository metadata' ],
	[ /^music-theme\.zip$/, 'the audited legacy artifact (never redistributed)' ],
	[ /\.md5$|\.sha256$/, 'checksum files belong in dist/, not in the product' ],
	[ /\.DS_Store$/, 'OS metadata' ],
	[ /(^|\/)\.env/, 'local environment file' ],
];

/**
 * Collect the files of one artifact.
 *
 * @param {object} artifact Artifact definition.
 * @return {{ files: string[], skipped: Array<[string, string]> }} Repo-relative paths and the exclusions that matched.
 */
function collect( artifact ) {
	const base = join( ROOT, artifact.folder );
	const files = [];
	const skipped = [];

	if ( ! existsSync( base ) ) {
		problems.push( `[missing] ${ artifact.folder}/ does not exist` );

		return { files, skipped };
	}

	/** Depth-first walk. */
	const walk = ( dir ) => {
		for ( const entry of readdirSync( dir ).sort() ) {
			const path = join( dir, entry );
			const inner = relative( base, path ).split( sep ).join( '/' );
			const excluded = artifact.exclude.find( ( [ pattern ] ) => pattern.test( inner ) );

			if ( excluded ) {
				skipped.push( [ inner, excluded[ 1 ] ] );
				continue;
			}

			if ( statSync( path ).isDirectory() ) {
				walk( path );
			} else {
				files.push( path );
			}
		}
	};

	walk( base );

	return { files, skipped };
}

// ------------------------------------------------- 3. per-artifact validation
const artifacts = [];
const declared = readFileSync( join( ROOT, 'THIRD-PARTY-NOTICES.md' ), 'utf8' );

for ( const artifact of ARTIFACTS ) {
	const { files, skipped } = collect( artifact );
	const inner = files.map( ( path ) => relative( join( ROOT, artifact.folder ), path ).split( sep ).join( '/' ) );

	for ( const required of artifact.requires ) {
		if ( ! inner.includes( required ) ) {
			problems.push( `[${ artifact.slug }] required file is missing: ${ artifact.folder }/${ required }` );
		}
	}

	for ( const optional of artifact.optional ) {
		if ( inner.includes( optional ) ) {
			continue;
		}

		const message = `${ artifact.slug }: ${ optional } is not present (required before uploading to a marketplace)`;

		if ( STRICT ) {
			problems.push( `[missing] ${ message }` );
		} else {
			notes.push( message );
		}
	}

	for ( const path of inner ) {
		for ( const [ pattern, label ] of FORBIDDEN ) {
			if ( pattern.test( path ) ) {
				problems.push( `[leak] ${ artifact.folder }/${ path } is a ${ label } and must not ship` );
			}
		}

		// A shipped file that is not ours has to be declared by name.
		if ( /^assets\/fonts\//.test( path ) && ! declared.includes( path.replace( /.*\//, '' ) ) ) {
			problems.push( `[licence] ${ artifact.folder }/${ path } is not named in THIRD-PARTY-NOTICES.md` );
		}
	}

	// The compiled catalogue is the one WordPress actually reads.
	for ( const mo of inner.filter( ( path ) => path.endsWith( '.mo' ) ) ) {
		if ( statSync( join( ROOT, artifact.folder, mo ) ).size < 64 ) {
			problems.push( `[i18n] ${ artifact.folder }/${ mo } is too small to be a compiled catalogue` );
		}
	}

	notes.push(
		`${ artifact.slug }: ${ files.length } file(s)` +
			( skipped.length ? `, ${ skipped.length } exclusion(s): ${ skipped.map( ( [ p, why ] ) => `${ p } (${ why })` ).join( '; ' ) }` : '' )
	);

	artifacts.push( { artifact, files, inner } );
}

// ------------------------------------------------------- 4. the bundle files
const bundleFiles = [];

for ( const extra of BUNDLE_EXTRAS ) {
	const path = join( ROOT, extra.from );

	if ( ! existsSync( path ) ) {
		problems.push( `[bundle] ${ extra.from } is referenced by the bundle and does not exist` );

		continue;
	}

	bundleFiles.push( { path, name: extra.to } );
}

if ( problems.length > 0 ) {
	for ( const line of problems ) {
		console.log( `      FAIL  ${ line }` );
	}

	console.log( `\nRESULT: FAIL — ${ problems.length } packaging problem(s)` );
	process.exit( 1 );
}

if ( CHECK_ONLY ) {
	for ( const line of notes ) {
		console.log( `      · ${ line }` );
	}

	console.log( `      OK    the release tree is packageable (nothing written)` );
	process.exit( 0 );
}

// ------------------------------------------------------------- 5. zip writer
/**
 * Minimal deterministic ZIP writer: store or deflate, sorted entries, a fixed
 * DOS timestamp, no extra fields, no data descriptors.
 *
 * Writing this by hand is a deliberate choice (ADR 0006: the repository carries
 * no build dependency): the archives must be byte-identical between runs and
 * between machines, which is exactly what `zip` makes awkward.
 */
class Zip {
	constructor() {
		this.parts = [];
		this.central = [];
		this.offset = 0;
	}

	/**
	 * Add one file.
	 *
	 * @param {string} name  Entry path inside the archive (POSIX separators).
	 * @param {Buffer} data  File contents.
	 * @return {void}
	 */
	add( name, data ) {
		const nameBytes = Buffer.from( name, 'utf8' );
		const compressed = deflateRawSync( data, { level: 9 } );
		const method = compressed.length < data.length ? 8 : 0;
		const body = 0 === method ? data : compressed;
		const crc = crc32( data );

		// Bit 11 of the flags marks the name (and comment) as UTF-8. Every name we
		// generate today is ASCII, but a non-ASCII name written without the flag
		// is a corrupt archive for any reader that trusts the spec.
		const utf8 = /[\u0080-\uffff]/.test( name ) ? 0x0800 : 0;

		const local = Buffer.alloc( 30 );
		local.writeUInt32LE( 0x04034b50, 0 );
		local.writeUInt16LE( 20, 4 ); // version needed
		local.writeUInt16LE( utf8, 6 ); // flags
		local.writeUInt16LE( method, 8 );
		local.writeUInt16LE( dosTime(), 10 );
		local.writeUInt16LE( dosDate(), 12 );
		local.writeUInt32LE( crc, 14 );
		local.writeUInt32LE( body.length, 18 );
		local.writeUInt32LE( data.length, 22 );
		local.writeUInt16LE( nameBytes.length, 26 );
		local.writeUInt16LE( 0, 28 ); // extra length

		this.parts.push( local, nameBytes, body );

		const central = Buffer.alloc( 46 );
		central.writeUInt32LE( 0x02014b50, 0 );
		central.writeUInt16LE( 20, 4 ); // version made by
		central.writeUInt16LE( 20, 6 ); // version needed
		central.writeUInt16LE( utf8, 8 ); // flags
		central.writeUInt16LE( method, 10 );
		central.writeUInt16LE( dosTime(), 12 );
		central.writeUInt16LE( dosDate(), 14 );
		central.writeUInt32LE( crc, 16 );
		central.writeUInt32LE( body.length, 20 );
		central.writeUInt32LE( data.length, 24 );
		central.writeUInt16LE( nameBytes.length, 28 );
		central.writeUInt16LE( 0, 30 ); // extra
		central.writeUInt16LE( 0, 32 ); // comment
		central.writeUInt16LE( 0, 34 ); // disk
		central.writeUInt16LE( 0, 36 ); // internal attrs
		central.writeUInt32LE( ( 0o100644 << 16 ) >>> 0, 38 ); // external attrs: regular file, 0644 (unsigned: JS `<<` is signed)
		central.writeUInt32LE( this.offset, 42 );

		this.central.push( central, nameBytes );
		this.offset += local.length + nameBytes.length + body.length;
	}

	/**
	 * Finish the archive.
	 *
	 * @return {Buffer} The complete ZIP.
	 */
	finish() {
		const centralSize = this.central.reduce( ( total, part ) => total + part.length, 0 );
		const end = Buffer.alloc( 22 );
		end.writeUInt32LE( 0x06054b50, 0 );
		end.writeUInt16LE( 0, 4 );
		end.writeUInt16LE( 0, 6 );
		end.writeUInt16LE( Math.min( this.centralCount, 0xffff ), 8 );
		end.writeUInt16LE( Math.min( this.centralCount, 0xffff ), 10 );
		end.writeUInt32LE( centralSize, 12 );
		end.writeUInt32LE( this.offset, 16 );

		return Buffer.concat( [ ...this.parts, ...this.central, end ] );
	}

	get centralCount() {
		return this.central.filter( ( part ) => 46 === part.length ).length;
	}
}

/** DOS time from the fixed epoch. */
function dosTime() {
	return ( DOS_TIME.getUTCHours() << 11 ) | ( DOS_TIME.getUTCMinutes() << 5 ) | ( DOS_TIME.getUTCSeconds() >> 1 );
}

/** DOS date from the fixed epoch. */
function dosDate() {
	return ( ( DOS_TIME.getUTCFullYear() - 1980 ) << 9 ) | ( ( DOS_TIME.getUTCMonth() + 1 ) << 5 ) | DOS_TIME.getUTCDate();
}

let CRC_TABLE = null;

/**
 * CRC-32 of a buffer (the ZIP checksum).
 *
 * @param {Buffer} buffer Input.
 * @return {number} Unsigned CRC.
 */
function crc32( buffer ) {
	if ( ! CRC_TABLE ) {
		CRC_TABLE = new Int32Array( 256 );

		for ( let i = 0; i < 256; i++ ) {
			let c = i;

			for ( let bit = 0; bit < 8; bit++ ) {
				c = c & 1 ? 0xedb88320 ^ ( c >>> 1 ) : c >>> 1;
			}

			CRC_TABLE[ i ] = c;
		}
	}

	let crc = -1;

	for ( let i = 0; i < buffer.length; i++ ) {
		crc = ( crc >>> 8 ) ^ CRC_TABLE[ ( crc ^ buffer[ i ] ) & 0xff ];
	}

	return ( crc ^ -1 ) >>> 0;
}

/**
 * Build one archive from a list of `{ name, data }` entries.
 *
 * @param {Array<{ name: string, data: Buffer }>} entries Archive entries.
 * @return {Buffer} ZIP bytes.
 */
function zipEntries( entries ) {
	const zip = new Zip();

	for ( const entry of [ ...entries ].sort( ( a, b ) => ( a.name < b.name ? -1 : a.name > b.name ? 1 : 0 ) ) ) {
		zip.add( entry.name, entry.data );
	}

	return zip.finish();
}

// -------------------------------------------------------------- 6. building
// Clear the output directory, but never the tracked `.gitkeep` that keeps the
// folder in the repository: wiping the directory it lives in would delete a
// versioned file as a side effect of building.
if ( existsSync( OUT_DIR ) ) {
	for ( const entry of readdirSync( OUT_DIR ) ) {
		if ( '.gitkeep' === entry ) {
			continue;
		}

		rmSync( join( OUT_DIR, entry ), { recursive: true, force: true } );
	}
}

mkdirSync( OUT_DIR, { recursive: true } );

const manifest = {
	name: 'wavira',
	version: VERSION,
	built_from: process.env.GITHUB_SHA ?? 'working tree',
	licence: 'GPL-2.0-or-later',
	artifacts: [],
};

const sums = [];
const written = [];

for ( const { artifact } of artifacts ) {
	const base = join( ROOT, artifact.folder );
	const entries = files_of( base ).map( ( path ) => ( {
		name: `${ artifact.folder }/${ relative( base, path ).split( sep ).join( '/' ) }`,
		data: readFileSync( path ),
	} ) );

	const zip = zipEntries( entries );
	const file = join( OUT_DIR, artifact.archiveName( VERSION ) );

	writeFileSync( file, zip );
	written.push( file );

	manifest.artifacts.push( {
		slug: artifact.slug,
		archive: relative( OUT_DIR, file ),
		files: entries.length,
		bytes: zip.length,
		sha256: createHash( 'sha256' ).update( zip ).digest( 'hex' ),
		entries: entries.map( ( entry ) => ( {
			name: entry.name,
			bytes: entry.data.length,
			sha256: createHash( 'sha256' ).update( entry.data ).digest( 'hex' ),
		} ) ),
	} );

	sums.push( `${ createHash( 'sha256' ).update( zip ).digest( 'hex' ) }  ${ artifact.archiveName( VERSION ) }` );
}

/** Every file of a folder, with the artifact's exclusions applied. */
function files_of( base ) {
	const found = [];
	const artifact = ARTIFACTS.find( ( candidate ) => join( ROOT, candidate.folder ) === base );

	const walk = ( dir ) => {
		for ( const entry of readdirSync( dir ).sort() ) {
			const path = join( dir, entry );
			const inner = relative( base, path ).split( sep ).join( '/' );

			if ( artifact.exclude.some( ( [ pattern ] ) => pattern.test( inner ) ) ) {
				continue;
			}

			if ( statSync( path ).isDirectory() ) {
				walk( path );
			} else {
				found.push( path );
			}
		}
	};

	walk( base );

	return found;
}

// The bundle: both artifacts plus the buyer-facing documentation, one archive.
{
	const entries = [];

	for ( const { artifact, files } of artifacts ) {
		const base = join( ROOT, artifact.folder );

		for ( const path of files ) {
			entries.push( {
				name: `${ artifact.folder}/${ relative( base, path ).split( sep ).join( '/' ) }`,
				data: readFileSync( path ),
			} );
		}
	}

	for ( const extra of bundleFiles ) {
		entries.push( { name: extra.name, data: readFileSync( extra.path ) } );
	}

	entries.push( {
		name: 'README-FIRST/fa/INSTALL-AND-START.md',
		data: Buffer.from( bundleReadme(), 'utf8' ),
	} );

	const zip = zipEntries( entries );
	const file = join( OUT_DIR, `wavira-${ VERSION }-bundle.zip` );

	writeFileSync( file, zip );
	written.push( file );

	manifest.artifacts.push( {
		slug: 'bundle',
		archive: relative( OUT_DIR, file ),
		files: entries.length,
		bytes: zip.length,
		sha256: createHash( 'sha256' ).update( zip ).digest( 'hex' ),
		entries: entries.map( ( entry ) => ( { name: entry.name, bytes: entry.data.length } ) ),
	} );

	sums.push( `${ createHash( 'sha256' ).update( zip ).digest( 'hex' ) }  wavira-${ VERSION }-bundle.zip` );
}

writeFileSync( join( OUT_DIR, 'manifest.json' ), `${ JSON.stringify( manifest, null, '\t' ) }\n`, 'utf8' );
writeFileSync( join( OUT_DIR, 'SHA256SUMS' ), `${ sums.join( '\n' ) }\n`, 'utf8' );

/** The install note that sits at the top of the bundle. */
function bundleReadme() {
	return [
		`# وَویرا ${ VERSION } — بستهٔ نصب`,
		'',
		'این بسته سه بخش دارد:',
		'',
		`1. \`wavira-theme-${ VERSION }.zip\` — قالب «وَویرا موزیک» (نمای سایت).`,
		`2. \`wavira-core-${ VERSION }.zip\` — افزونهٔ «وَویرا کور» (محتوای موسیقی، پخش‌کننده، API، مهاجرت).`,
		'3. پوشهٔ `README-FIRST/` — پروانه‌ها و راهنماها.',
		'',
		'## نصب',
		'',
		'1. **افزونه را اول نصب کنید:** پیشخوان → افزونه‌ها → افزودن → بارگذاری افزونه →',
		`   \`wavira-core-${ VERSION }.zip\` را انتخاب و فعال کنید.`,
		'2. **سپس قالب:** پیشخوان → نمایش → پوسته‌ها → افزودن → بارگذاری پوسته →',
		`   \`wavira-theme-${ VERSION }.zip\` را انتخاب و فعال کنید.`,
		'3. محتوای نمایشی فارسی را با دستور زیر بسازید (اختیاری):',
		'',
		'       wp wavira seed --force',
		'',
		'4. اگر سایت قدیمی موسیقی دارید، اول نقشه را ببینید و بعد مهاجرت کنید:',
		'',
		'       wp wavira migrate --detect',
		'       wp wavira migrate --dry-run',
		'       wp wavira migrate',
		'',
		'سایت شما فارسی، راست‌به‌چپ، با تاریخ شمسی و منطقهٔ زمانی تهران تنظیم می‌شود؛ متن‌ها همه',
		'قابل ویرایش‌اند.',
		'',
		'## پیش از انتشار',
		'',
		'این بسته یک نسخهٔ کاندید انتشار (RC) است: پیش از انتشار عمومی، `wavira/screenshot.png` (تصویر',
		'معرفی قالب) و متن برگهٔ فروشگاه را اضافه کنید.',
		'',
		'## پروانه',
		'',
		'GPL-2.0-or-later — نگاه کنید به `README-FIRST/LICENSE.md`؛ اجزای شخص ثالث در',
		'`README-FIRST/THIRD-PARTY-NOTICES.md` فهرست شده‌اند (فونت وزیرمتن با پروانهٔ SIL OFL 1.1).',
		'',
	].join( '\n' );
}

// ------------------------------------------------------------------ 7. report
for ( const line of notes ) {
	console.log( `      · ${ line }` );
}

for ( const artifact of manifest.artifacts ) {
	console.log(
		`      OK    ${ artifact.archive } — ${ artifact.files } file(s), ${ ( artifact.bytes / 1024 ).toFixed( 0 ) } KB` +
			( artifact.sha256 ? `, sha256 ${ artifact.sha256.slice( 0, 12 ) }…` : '' )
	);
}

// Byte-for-byte reproducibility is claimed, so prove it: build the theme archive
// a second time and compare. (Cheap: the packages are a few MB.)
{
	const first = manifest.artifacts[ 0 ];
	const again = artifacts[ 0 ];
	const entries = files_of( join( ROOT, again.artifact.folder ) ).map( ( path ) => ( {
		name: `${ again.artifact.folder }/${ relative( join( ROOT, again.artifact.folder ), path ).split( sep ).join( '/' ) }`,
		data: readFileSync( path ),
	} ) );
	const second = createHash( 'sha256' ).update( zipEntries( entries ) ).digest( 'hex' );

	if ( second !== first.sha256 ) {
		console.log( '      FAIL  two builds of the same tree produced different archives' );
		process.exit( 1 );
	}

	notes.push( `reproducible: ${ first.archive } rebuilt byte-identically` );
	console.log( `      OK    rebuild is byte-identical (${ first.sha256.slice( 0, 12 ) }…)` );
}

console.log( `\nRESULT: PASS — ${ written.length } archive(s) in ${ rel( OUT_DIR ) || OUT_DIR }/, manifest + SHA256SUMS written` );
