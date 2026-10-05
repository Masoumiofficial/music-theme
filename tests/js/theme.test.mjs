/**
 * Theme script unit tests.
 *
 * The colour-mode logic must be verifiable without a browser, so the file is
 * loaded in a `node:vm` context with a stub document and a stub storage. Run
 * with:
 *
 *   node --test tests/js/theme.test.mjs
 *
 * @package Wavira
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const SOURCE = readFileSync( new URL( '../../wavira/assets/js/index.js', import.meta.url ), 'utf8' );

/**
 * Load the theme script in a context with no document.
 *
 * @return {Object} `window.Wavira.theme` of the sandbox.
 */
function loadTheme() {
	const sandbox = { console };

	sandbox.window = sandbox;

	return vm.runInNewContext( SOURCE + '\nwindow.Wavira.theme;', vm.createContext( sandbox ), {
		filename: 'wavira/assets/js/index.js',
	} );
}

/**
 * A stub element that records attributes and click handlers.
 *
 * @return {Object} Element stub.
 */
function fakeElement() {
	return {
		textContent: '',
		attributes: {},
		listeners: {},
		setAttribute( key, value ) {
			this.attributes[ key ] = String( value );
		},
		getAttribute( key ) {
			return Object.prototype.hasOwnProperty.call( this.attributes, key ) ? this.attributes[ key ] : null;
		},
		addEventListener( type, handler ) {
			this.listeners[ type ] = this.listeners[ type ] || [];
			this.listeners[ type ].push( handler );
		},
		dispatch( type ) {
			( this.listeners[ type ] || [] ).forEach( ( handler ) => handler( {} ) );
		},
		querySelector() {
			return null;
		},
	};
}

/**
 * A stub document with one toggle button.
 *
 * @return {Object} Document stub.
 */
function fakeDocument() {
	const root = fakeElement();
	const button = fakeElement();

	return {
		documentElement: root,
		button,
		querySelectorAll( selector ) {
			return '[data-wavira-theme-toggle]' === selector ? [ button ] : [];
		},
	};
}

/**
 * A storage stub.
 *
 * @param {string|null} initial Value the store starts with.
 * @return {Object} Storage stub with `value`.
 */
function fakeStorage( initial = null ) {
	return {
		value: initial,
		getItem() {
			return this.value;
		},
		setItem( key, value ) {
			this.value = value;
		},
	};
}

test( 'only the three documented modes are accepted', () => {
	const theme = loadTheme();

	// Spread first: values created inside `node:vm` carry that realm's prototypes.
	assert.deepEqual( [ ...theme.modes ], [ 'light', 'dark', 'auto' ] );
	assert.equal( theme.isMode( 'dark' ), true );
	assert.equal( theme.isMode( 'sepia' ), false );
	assert.equal( theme.isMode( null ), false );
	assert.equal( theme.isMode( '' ), false );
} );

test( 'the toggle cycles light, dark, auto and wraps around', () => {
	const theme = loadTheme();

	assert.equal( theme.nextMode( 'light' ), 'dark' );
	assert.equal( theme.nextMode( 'dark' ), 'auto' );
	assert.equal( theme.nextMode( 'auto' ), 'light' );
	// An unknown value must not throw; it restarts the cycle.
	assert.equal( theme.nextMode( 'nonsense' ), 'light' );
} );

test( 'storage is read defensively and defaults to auto', () => {
	const theme = loadTheme();

	assert.equal( theme.readStored( fakeStorage( 'dark' ), 'k' ), 'dark' );
	assert.equal( theme.readStored( fakeStorage( 'sepia' ), 'k' ), 'auto' );
	assert.equal( theme.readStored( fakeStorage(), 'k' ), 'auto' );
	assert.equal(
		theme.readStored(
			{
				getItem() {
					throw new Error( 'storage blocked' );
				},
			},
			'k'
		),
		'auto',
		'a blocked storage must not break the page'
	);
} );

test( 'a failed write is reported, not thrown', () => {
	const theme = loadTheme();

	assert.equal( theme.writeStored( fakeStorage(), 'k', 'dark' ), true );
	assert.equal(
		theme.writeStored(
			{
				setItem() {
					throw new Error( 'quota' );
				},
			},
			'k',
			'dark'
		),
		false
	);
} );

test( 'applyMode writes data-theme and refuses junk', () => {
	const theme = loadTheme();
	const doc = fakeDocument();

	theme.applyMode( doc, 'dark' );
	assert.equal( doc.documentElement.getAttribute( 'data-theme' ), 'dark' );

	theme.applyMode( doc, 'sunset' );
	assert.equal( doc.documentElement.getAttribute( 'data-theme' ), 'auto' );
} );

test( 'the stored mode is applied on load and the button is labelled', () => {
	const theme = loadTheme();
	const doc = fakeDocument();

	theme.initThemeMode( doc, fakeStorage( 'dark' ) );

	assert.equal( doc.documentElement.getAttribute( 'data-theme' ), 'dark' );
	assert.equal( doc.button.getAttribute( 'aria-pressed' ), 'true' );
	assert.equal( doc.button.getAttribute( 'aria-label' ), 'Colour theme: Dark' );
	assert.equal( doc.button.textContent, 'Dark' );
} );

test( 'clicking the toggle cycles the mode and persists it', () => {
	const theme = loadTheme();
	const doc = fakeDocument();
	const storage = fakeStorage( 'light' );

	theme.initThemeMode( doc, storage );
	doc.button.dispatch( 'click' );

	assert.equal( storage.value, 'dark' );
	assert.equal( doc.documentElement.getAttribute( 'data-theme' ), 'dark' );

	doc.button.dispatch( 'click' );
	doc.button.dispatch( 'click' );

	assert.equal( storage.value, 'light', 'three clicks return to the start' );
} );

test( 'the player is only initialised when the engine is present', () => {
	const theme = loadTheme();

	assert.equal( theme.initPlayers( fakeDocument() ), 0, 'no engine, no work' );

	const sandbox = { console };
	const calls = [];

	sandbox.window = sandbox;
	sandbox.Wavira = {
		player: {
			instances: [ {}, {} ],
			init( doc ) {
				calls.push( doc );
			},
		},
	};

	const api = vm.runInNewContext( SOURCE + '\nwindow.Wavira;', vm.createContext( sandbox ), {
		filename: 'wavira/assets/js/index.js',
	} );
	const doc = fakeDocument();

	assert.equal( api.theme.initPlayers( doc ), 2 );
	assert.equal( calls.length, 1 );
} );

test( 'an engine that throws never breaks the page', () => {
	const sandbox = { console };

	sandbox.window = sandbox;
	sandbox.Wavira = {
		player: {
			instances: [],
			init() {
				throw new Error( 'engine failed' );
			},
		},
	};

	const api = vm.runInNewContext( SOURCE + '\nwindow.Wavira;', vm.createContext( sandbox ), {
		filename: 'wavira/assets/js/index.js',
	} );

	assert.equal( api.theme.initPlayers( fakeDocument() ), 0 );
} );

test( 'the theme script keeps the documented safety locks', () => {
	assert.equal( /\.innerHTML\s*=/.test( SOURCE ), false );
	assert.equal( /\beval\s*\(/.test( SOURCE ), false );
	assert.equal( /document\.cookie/.test( SOURCE ), false, 'preferences never go into cookies' );
	assert.equal( /jQuery|\$\(/.test( SOURCE ), false, 'no jQuery in the product (ADR 0006)' );
	assert.equal( /https?:\/\//.test( SOURCE ), false, 'no remote asset or endpoint is referenced' );
	assert.equal( /var STORAGE_KEY = 'wavira\.theme';/.test( SOURCE ), true );
} );

test( 'the storage key matches the one PHP prints', () => {
	const php = readFileSync( new URL( '../../wavira/inc/assets.php', import.meta.url ), 'utf8' );

	assert.equal(
		php.includes( "'storageKey' => 'wavira.theme'" ),
		true,
		'the server and the script must agree on the storage key'
	);
} );
