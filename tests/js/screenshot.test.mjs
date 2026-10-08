/**
 * Unit tests for the renderer's own guard rails (0.14.0).
 *
 * `tools/screenshot.mjs` drives a browser, and this environment has none — so
 * the parts that decide whether a screenshot is trustworthy are tested here,
 * without a browser: the PNG size reader that catches a blank or mis-sized
 * image, the option parsing, and the refusals a caller can act on. The capture
 * itself is exercised by the `wp-render` CI job, which has a browser.
 *
 *   node --test tests/js/screenshot.test.mjs
 *
 * @package Wavira
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { deflateSync } from 'node:zlib';

import { budgets, findChrome, option, options, parsePages, pngSize, stylesheetVerdict } from '../../tools/screenshot.mjs';

/**
 * CRC-32, the checksum every PNG chunk carries.
 *
 * Written out rather than imported: `zlib.crc32` exists in Node 20 only from
 * 20.15, and a test that needs a particular minor version is a test that fails
 * on the runner for the wrong reason.
 *
 * @param {Buffer} buffer Chunk type plus data.
 * @return {number} The checksum.
 */
function crc32( buffer ) {
	let crc = 0xffffffff;

	for ( const byte of buffer ) {
		crc ^= byte;

		for ( let bit = 0; bit < 8; bit++ ) {
			crc = crc & 1 ? ( crc >>> 1 ) ^ 0xedb88320 : crc >>> 1;
		}
	}

	return ( crc ^ 0xffffffff ) >>> 0;
}

/**
 * A real, valid PNG of the given size — one flat colour, no dependencies.
 *
 * @param {number} width  Pixels.
 * @param {number} height Pixels.
 * @return {Buffer} The file bytes.
 */
function png( width, height ) {
	const chunk = ( type, data ) => {
		const length = Buffer.alloc( 4 );

		length.writeUInt32BE( data.length );

		const body = Buffer.concat( [ Buffer.from( type, 'ascii' ), data ] );
		const crc = Buffer.alloc( 4 );

		crc.writeUInt32BE( crc32( body ) );

		return Buffer.concat( [ length, body, crc ] );
	};

	const ihdr = Buffer.alloc( 13 );

	ihdr.writeUInt32BE( width, 0 );
	ihdr.writeUInt32BE( height, 4 );
	ihdr[ 8 ] = 8; // bit depth
	ihdr[ 9 ] = 2; // truecolour

	const raw = Buffer.alloc( height * ( 1 + width * 3 ) );

	for ( let y = 0; y < height; y++ ) {
		raw[ y * ( 1 + width * 3 ) ] = 0; // filter: none
	}

	return Buffer.concat( [
		Buffer.from( [ 0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a ] ),
		chunk( 'IHDR', ihdr ),
		chunk( 'IDAT', deflateSync( raw ) ),
		chunk( 'IEND', Buffer.alloc( 0 ) ),
	] );
}

test( 'a PNG reports the size the theme screenshot has to have', () => {
	assert.deepEqual( pngSize( png( 1200, 900 ) ), { width: 1200, height: 900 } );
	assert.deepEqual( pngSize( png( 1, 1 ) ), { width: 1, height: 1 } );
} );

test( 'anything that is not a PNG is refused instead of reported as an image', () => {
	// A GIF header, a JPEG, a text file and an empty buffer: each of these has
	// been "successfully written" by some tool at some point.
	assert.equal( pngSize( Buffer.from( 'GIF89a\u0001\u0000\u0001\u0000', 'binary' ) ), null );
	assert.equal( pngSize( Buffer.from( [ 0xff, 0xd8, 0xff, 0xe0, 0, 0x10, 0x4a, 0x46 ] ) ), null );
	assert.equal( pngSize( Buffer.from( 'not an image at all, just words' ) ), null );
	assert.equal( pngSize( Buffer.alloc( 0 ) ), null );
} );

test( 'a truncated PNG stops rather than reading past the end', () => {
	const full = png( 8, 4 );

	// Signature only, signature + garbage, and a file cut in the middle of the
	// IHDR: all shorter than the 24 bytes the reader needs.
	assert.equal( pngSize( full.subarray( 0, 8 ) ), null );
	assert.equal( pngSize( full.subarray( 0, 12 ) ), null );
	assert.equal( pngSize( full.subarray( 0, 20 ) ), null );
	assert.deepEqual( pngSize( full ), { width: 8, height: 4 } );
} );

test( 'a PNG whose IHDR chunk is not where IHDR belongs is refused', () => {
	const real = png( 3, 5 );
	const fake = Buffer.from( real );

	// Same signature, same offset, but the chunk type is something else.
	fake.write( 'IHDX', 12, 'ascii' );

	assert.equal( pngSize( fake ), null );
} );

test( 'an option reads its value, its bare form, and its absence', () => {
	const args = [ '--url=http://site.test/', '--axe', '--width=1000' ];

	assert.equal( option( args, 'url' ), 'http://site.test/' );
	assert.equal( option( args, 'axe' ), true );
	assert.equal( option( args, 'width' ), '1000' );
	assert.equal( option( args, 'height' ), null );
	assert.equal( option( args, 'height', 900 ), 900 );
} );

test( 'the same option can be given more than once, in order', () => {
	const args = [
		'--page=album=http://site.test/album/',
		'--url=http://site.test/',
		'--page=artist=http://site.test/artist/',
	];

	assert.deepEqual( options( args, 'page' ), [ 'album=http://site.test/album/', 'artist=http://site.test/artist/' ] );
	assert.deepEqual( options( args, 'nothing' ), [] );
} );

test( 'an extra page is parsed into a name and a URL, and a malformed one is refused', () => {
	assert.deepEqual( parsePages( [ 'album=http://site.test/album/' ] ), [ { name: 'album', url: 'http://site.test/album/' } ] );
	assert.deepEqual( parsePages( [] ), [] );

	// A URL contains `=` in the query string; only the first one separates.
	assert.deepEqual( parsePages( [ 'search=http://site.test/?s=a&p=1' ] ), [ { name: 'search', url: 'http://site.test/?s=a&p=1' } ] );

	assert.throws( () => parsePages( [ 'album' ] ), /--page=album must be --page=name=URL/ );
	assert.throws( () => parsePages( [ '=http://site.test/' ] ), /must be --page=name=URL/ );
} );

test( 'a Chrome that was asked for by name is either there or reported missing', () => {
	// `--chrome=` points at a file that does not exist: the tool must say so
	// rather than fall back to another browser and render something else.
	assert.equal( findChrome( '/definitely/not/a/browser' ), null );

	// `process.execPath` exists, so the lookup itself is exercised.
	assert.equal( findChrome( process.execPath ), process.execPath );
} );

test( 'every wait has a budget, and a bad number falls back to the default', () => {
	assert.deepEqual( budgets( [] ), { protocol: 120000, settle: 15000, axe: 90000 } );

	assert.deepEqual(
		budgets( [ '--protocol-timeout=30000', '--settle=5000', '--axe-timeout=1000' ] ),
		{ protocol: 30000, settle: 5000, axe: 1000 }
	);

	// A budget of zero, a negative one, a word and a bare flag are all "not a
	// budget": an unbounded or instantly-expired wait is worse than the default.
	assert.deepEqual( budgets( [ '--settle=0' ] ).settle, 15000 );
	assert.deepEqual( budgets( [ '--protocol-timeout=-1' ] ).protocol, 120000 );
	assert.deepEqual( budgets( [ '--axe-timeout=soon' ] ).axe, 90000 );
	assert.deepEqual( budgets( [ '--settle' ] ).settle, 15000 );

	// A fractional number of milliseconds is not something a timer can use.
	assert.equal( budgets( [ '--settle=1500.7' ] ).settle, 1500 );
} );

test( 'an unstyled page is refused, with the reason, instead of photographed', () => {
	// The good case: the theme's sheet is linked and its rules are in effect.
	assert.equal(
		stylesheetVerdict( {
			stylesheets: [ 'http://site.test/wp-content/themes/wavira/assets/dist/theme.css?ver=1' ],
			rules: 812,
		} ),
		''
	);

	// A page with no stylesheet at all renders legibly and is still worthless as
	// a screenshot of the theme — this is what 0.14.0 shipped.
	const missing = stylesheetVerdict( { stylesheets: [], rules: 0 } );

	assert.match( missing, /does not link the theme stylesheet/ );
	assert.match( missing, /no stylesheet at all/ );

	// A page that links core's styles but not the theme's says which ones it has.
	const core = stylesheetVerdict( {
		stylesheets: [ 'http://site.test/wp-includes/css/dist/block-library/style.min.css' ],
		rules: 90,
	} );

	assert.match( core, /block-library\/style\.min\.css/ );

	// The nastiest case: the link is there, the file is not — a WordPress router
	// answers a missing file with the front page as `text/html`, so the sheet
	// parses to nothing and the page is black text on white.
	const empty = stylesheetVerdict( {
		stylesheets: [ 'http://site.test/wp-content/themes/wavira/assets/dist/theme.css' ],
		rules: 0,
	} );

	assert.match( empty, /only 0 CSS rule\(s\) are in effect/ );
	assert.match( empty, /text\/html/ );
} );

test( 'the command line refuses to run without a URL and an output file', () => {
	const cli = fileURLToPath( new URL( '../../tools/screenshot.mjs', import.meta.url ) );
	const run = ( args ) => spawnSync( process.execPath, [ cli, ...args ], { encoding: 'utf8' } );

	const bare = run( [] );

	assert.equal( bare.status, 1 );
	assert.match( bare.stderr, /usage: node tools\/screenshot\.mjs/ );

	// A malformed `--page` is refused before any browser is looked for, so this
	// behaves the same on a machine with a browser and on one without.
	const malformed = run( [ '--url=http://site.test/', '--out=/tmp/x.png', '--page=broken' ] );

	assert.equal( malformed.status, 1 );
	assert.match( malformed.stderr, /--page=broken must be --page=name=URL/ );

	// A relative path is a usage mistake, not a Chrome protocol error: the message
	// has to say which argument was wrong (this is how `wp-render` failed).
	const relative = run( [ '--url=http://site.test/', '--out=/tmp/x.png', '--page=album=/albums/example/' ] );

	assert.equal( relative.status, 1 );
	assert.match( relative.stderr, /is not an absolute URL/ );
	assert.match( relative.stderr, /\/albums\/example\// );

	const noBrowser = run( [ '--url=http://site.test/', '--out=/tmp/x.png', '--chrome=/definitely/not/a/browser' ] );

	assert.equal( noBrowser.status, 1 );
	assert.match( noBrowser.stderr, /no Chrome or Chromium found/ );
} );
