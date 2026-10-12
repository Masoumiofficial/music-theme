#!/usr/bin/env node
/**
 * Static server for the Wavira component harness.
 *
 * The harness is a development aid only: it renders the shipped theme layers
 * and the shipped player engine in a real browser so a human can check dark and
 * light colour modes, RTL and LTR, and the 360 → 1920 breakpoint matrix without
 * a WordPress install. Nothing here is part of the theme or the plugin package
 * (`tools/` is excluded from both).
 *
 * It serves the repository root, so the harness loads exactly the files that
 * ship; the concatenated `assets/dist/` bundles are byte-identical to the
 * sources they are built from (see tools/build.mjs).
 *
 * Three things are generated on request because the product intentionally
 * refuses anything that is not a same-origin HTTP URL (the engine's URL lock
 * rejects `data:` and `blob:`):
 *
 *   /tools/preview/presets.css        the `--wp--preset--*` / `--wp--custom--*`
 *                                     variables WordPress prints from theme.json
 *   /tools/preview/tone-<hz>.wav      a short faded sine tone
 *   /tools/preview/cover-<n>.svg      placeholder artwork in the product palette
 *
 * Usage:  node tools/preview/serve.mjs [port]
 */

import { createReadStream, existsSync, statSync } from 'node:fs';
import { createServer } from 'node:http';
import { extname, join, normalize, resolve, sep } from 'node:path';
import { presetsCss } from './presets.mjs';

const ROOT = resolve( import.meta.dirname, '..', '..' );
const PORT = Number( process.argv[ 2 ] || process.env.PORT || 4173 );
const HOST = process.env.HOST || '0.0.0.0';
const PALETTE = [ '#5636d6', '#12c9a4', '#4326b0', '#0b0c10' ];

const TYPES = {
	'.css': 'text/css; charset=utf-8',
	'.html': 'text/html; charset=utf-8',
	'.js': 'text/javascript; charset=utf-8',
	'.json': 'application/json; charset=utf-8',
	'.mjs': 'text/javascript; charset=utf-8',
	'.svg': 'image/svg+xml',
	'.wav': 'audio/wav',
	'.woff2': 'font/woff2'
};

/**
 * Resolve a request path to a file inside the repository.
 *
 * @param {string} urlPath Requested path.
 * @return {string} Absolute file path, or an empty string when outside the root.
 */
function resolveTarget( urlPath ) {
	const decoded = decodeURIComponent( urlPath.split( '?' )[ 0 ] );
	const target = normalize( join( ROOT, decoded ) );

	if ( target !== ROOT && ! target.startsWith( ROOT + sep ) ) {
		return '';
	}

	if ( existsSync( target ) && statSync( target ).isDirectory() ) {
		return existsSync( join( target, 'index.html' ) ) ? join( target, 'index.html' ) : '';
	}

	return existsSync( target ) ? target : '';
}

/**
 * A 2.5 second, 8-bit, 8 kHz mono sine tone with a short fade, as a WAV buffer.
 *
 * @param {number} frequency Tone frequency in Hz.
 * @return {Buffer} Complete WAV file.
 */
function toneWav( frequency ) {
	const rate = 8000;
	const samples = rate * 2.5;
	const buffer = Buffer.alloc( 44 + samples );

	buffer.write( 'RIFF', 0 );
	buffer.writeUInt32LE( 36 + samples, 4 );
	buffer.write( 'WAVE', 8 );
	buffer.write( 'fmt ', 12 );
	buffer.writeUInt32LE( 16, 16 );
	buffer.writeUInt16LE( 1, 20 );
	buffer.writeUInt16LE( 1, 22 );
	buffer.writeUInt32LE( rate, 24 );
	buffer.writeUInt32LE( rate, 28 );
	buffer.writeUInt16LE( 1, 32 );
	buffer.writeUInt16LE( 8, 34 );
	buffer.write( 'data', 36 );
	buffer.writeUInt32LE( samples, 40 );

	for ( let i = 0; i < samples; i++ ) {
		const fade = Math.min( 1, i / 400, ( samples - i ) / 800 );
		const value = 128 + Math.round( 82 * fade * Math.sin( ( 2 * Math.PI * frequency * i ) / rate ) );

		buffer[ 44 + i ] = Math.max( 0, Math.min( 255, value ) );
	}

	return buffer;
}

/**
 * Persian numerals for the placeholder artwork.
 *
 * @param {string|number} value Text to convert.
 * @return {string} Converted text.
 */
function fa( value ) {
	return String( value ).replace( /[0-9]/g, ( digit ) => '۰۱۲۳۴۵۶۷۸۹'[ Number( digit ) ] );
}

/**
 * Placeholder artwork in the product palette.
 *
 * @param {number} index Cover number (1-based).
 * @return {string} SVG document.
 */
function coverSvg( index ) {
	const from = PALETTE[ ( index - 1 ) % PALETTE.length ];
	const to = PALETTE[ index % PALETTE.length ];

	return `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400" viewBox="0 0 400 400" role="img" aria-label="جلد نمایشی ${ fa( index ) }">
	<defs>
		<linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
			<stop offset="0" stop-color="${ from }"/>
			<stop offset="1" stop-color="${ to }"/>
		</linearGradient>
	</defs>
	<rect width="400" height="400" fill="url(#g)"/>
	<circle cx="200" cy="200" r="112" fill="none" stroke="rgba(255,255,255,.45)" stroke-width="10"/>
	<circle cx="200" cy="200" r="18" fill="rgba(255,255,255,.85)"/>
	<text x="200" y="372" text-anchor="middle" font-family="system-ui, sans-serif" font-size="42" fill="rgba(255,255,255,.9)">${ fa( index ) }</text>
</svg>`;
}

createServer( ( request, response ) => {
	const path = request.url.split( '?' )[ 0 ];

	if ( '/' === path ) {
		response.writeHead( 302, { Location: '/tools/preview/' } );

		return response.end();
	}

	// Preset variables, generated from theme.json on demand so the harness can
	// never drift from the real configuration.
	if ( '/tools/preview/presets.css' === path ) {
		response.writeHead( 200, { 'Content-Type': TYPES['.css'], 'Cache-Control': 'no-store' } );

		return response.end( presetsCss() );
	}

	const tone = path.match( /^\/tools\/preview\/tone-(\d{2,4})\.wav$/ );

	if ( tone ) {
		const body = toneWav( Math.max( 40, Math.min( 2000, Number( tone[ 1 ] ) ) ) );

		response.writeHead( 200, {
			'Content-Type': TYPES['.wav'],
			'Content-Length': body.length,
			'Cache-Control': 'no-store'
		} );

		return response.end( body );
	}

	const cover = path.match( /^\/tools\/preview\/cover-(\d{1,2})\.svg$/ );

	if ( cover ) {
		response.writeHead( 200, { 'Content-Type': TYPES['.svg'], 'Cache-Control': 'no-store' } );

		return response.end( coverSvg( Number( cover[ 1 ] ) ) );
	}

	const target = resolveTarget( request.url );

	if ( '' === target ) {
		response.writeHead( 404, { 'Content-Type': 'text/plain; charset=utf-8' } );

		return response.end( 'Not found\n' );
	}

	response.writeHead( 200, {
		'Content-Type': TYPES[ extname( target ) ] || 'application/octet-stream',
		'Cache-Control': 'no-store',
		'X-Content-Type-Options': 'nosniff'
	} );

	createReadStream( target ).pipe( response );
} ).listen( PORT, HOST, () => {
	console.log( `Wavira component harness → http://localhost:${ PORT }/tools/preview/` );
} );
