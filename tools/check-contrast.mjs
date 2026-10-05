#!/usr/bin/env node
/**
 * Contrast gate: WCAG 2.2 AA contrast for the documented colour pairs.
 *
 * Reads the palettes from `wavira/theme.json` (light) and
 * `wavira/styles/dark.json` (dark), then checks the pairs the product actually
 * uses. A pair that is not listed here is deliberately not claimed: decorative
 * hairline borders, for instance, are documentation, not a WCAG requirement.
 *
 * Thresholds (WCAG 2.2):
 *   - 4.5:1 for body text and small text (1.4.3);
 *   - 3:1 for large text, and for non-text indicators such as the focus ring
 *     and control boundaries the user must perceive (1.4.11).
 *
 * Usage:
 *   node tools/check-contrast.mjs     # exits 1 when a pair fails
 */

import { readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = resolve( dirname( fileURLToPath( import.meta.url ) ), '..' );

/** Relative luminance of a hex colour, per WCAG 2.x. */
function luminance( hex ) {
	const value = hex.replace( '#', '' );
	const channels = [ 0, 2, 4 ].map( ( offset ) => {
		const channel = parseInt( value.slice( offset, offset + 2 ), 16 ) / 255;

		return channel <= 0.03928 ? channel / 12.92 : ( ( channel + 0.055 ) / 1.055 ) ** 2.4;
	} );

	return 0.2126 * channels[ 0 ] + 0.7152 * channels[ 1 ] + 0.0722 * channels[ 2 ];
}

/** Contrast ratio between two hex colours. */
function ratio( foreground, background ) {
	const a = luminance( foreground );
	const b = luminance( background );

	return ( Math.max( a, b ) + 0.05 ) / ( Math.min( a, b ) + 0.05 );
}

/** Palette slug → colour for one theme. */
function palette( path ) {
	const theme = JSON.parse( readFileSync( path, 'utf8' ) );
	const entries = theme.settings?.color?.palette ?? [];
	const map = {};

	for ( const entry of entries ) {
		map[ entry.slug ] = entry.color;
	}

	return map;
}

const light = palette( join( ROOT, 'wavira/theme.json' ) );
const dark = palette( join( ROOT, 'wavira/styles/dark.json' ) );

if ( ! light.base || ! dark.base ) {
	console.log( '      FAIL could not read the light or dark palette' );
	process.exit( 1 );
}

/**
 * The pairs the product relies on.
 *
 * Some pairs differ per mode on purpose: on an accent-filled badge the light
 * palette uses `contrast` while the dark palette uses `base`, because both
 * accents are bright (tokens.css exposes this as `--wavira-on-accent`).
 */
const PAIRS = [
	[ 'light', 'badge label on the accent fill', 'contrast', 'accent', 4.5 ],
	[ 'dark', 'badge label on the accent fill', 'base', 'accent', 4.5 ],
	[ 'light', 'body text on the page', 'contrast', 'base', 4.5 ],
	[ 'light', 'body text on a surface', 'contrast', 'surface', 4.5 ],
	[ 'dark', 'body text on a surface', 'contrast', 'surface', 4.5 ],
	[ 'light', 'muted text on the page', 'muted', 'base', 4.5 ],
	[ 'dark', 'muted text on the page', 'muted', 'base', 4.5 ],
	[ 'light', 'muted text on a surface', 'muted', 'surface', 4.5 ],
	[ 'dark', 'muted text on a surface', 'muted', 'surface', 4.5 ],
	[ 'light', 'link text on the page', 'primary', 'base', 4.5 ],
	[ 'dark', 'link text on the page', 'primary', 'base', 4.5 ],
	[ 'light', 'hovered link on the page', 'primary-dark', 'base', 4.5 ],
	[ 'dark', 'hovered link on the page', 'primary-dark', 'base', 4.5 ],
	[ 'light', 'button label on the primary fill', 'base', 'primary', 4.5 ],
	[ 'dark', 'button label on the primary fill', 'base', 'primary', 4.5 ],
	[ 'light', 'error text on the page', 'danger', 'base', 4.5 ],
	[ 'dark', 'error text on the page', 'danger', 'base', 4.5 ],
	[ 'light', 'focus ring against the page', 'primary-dark', 'base', 3 ],
	[ 'dark', 'focus ring against the page', 'primary-dark', 'base', 3 ],
	[ 'light', 'player control border against the page', 'primary', 'base', 3 ],
	[ 'dark', 'player control border against the page', 'primary', 'base', 3 ],
];

let failed = 0;

for ( const [ mode, map ] of [ [ 'light', light ], [ 'dark', dark ] ] ) {
	for ( const [ pairMode, label, foreground, background, minimum ] of PAIRS ) {
		if ( pairMode !== mode ) {
			continue;
		}

		if ( ! map[ foreground ] || ! map[ background ] ) {
			console.log( `      FAIL ${ mode }: palette is missing ${ foreground } or ${ background }` );
			failed++;
			continue;
		}

		const value = ratio( map[ foreground ], map[ background ] );
		const pass = value >= minimum;
		const line = `${ mode.padEnd( 5 ) } ${ value.toFixed( 2 ).padStart( 5 ) }:1 (min ${ minimum }) — ${ label }`;

		if ( pass ) {
			console.log( `      · ${ line }` );
		} else {
			console.log( `      FAIL ${ line }` );
			failed++;
		}
	}
}

if ( failed > 0 ) {
	process.exit( 1 );
}

console.log( `      OK    ${ PAIRS.length } contrast pair(s) meet WCAG 2.2 AA` );
