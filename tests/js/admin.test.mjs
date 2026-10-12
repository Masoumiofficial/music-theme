/**
 * Settings screen unit tests (0.13.0).
 *
 * The screen's script has two jobs that would otherwise need a browser — the
 * colour swatch and the media picker — so the file is loaded in a `node:vm`
 * context with a stub document and a stub `wp.media`, the way
 * `tests/js/customizer.test.mjs` does it. Run with:
 *
 *   node --test tests/js/admin.test.mjs
 *
 * @package Wavira
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const SOURCE = readFileSync( new URL( '../../wavira/assets/js/admin.js', import.meta.url ), 'utf8' );

/**
 * A minimal element stub: attributes, listeners, a value and children.
 *
 * @param {string} id - Element id.
 * @return {Object} Element stub.
 */
function fakeElement( id = '' ) {
	const listeners = {};

	const element = {
		id,
		value: '',
		textContent: '',
		children: [],
		attributes: {},
		parentNode: null,
		addEventListener( type, handler ) {
			listeners[ type ] = listeners[ type ] || [];
			listeners[ type ].push( handler );
		},
		dispatch( type ) {
			( listeners[ type ] || [] ).forEach( ( handler ) => handler( { currentTarget: this, preventDefault() {} } ) );
		},
		querySelector() {
			return null;
		},
		querySelectorAll() {
			return [];
		},
		getAttribute( name ) {
			return Object.prototype.hasOwnProperty.call( this.attributes, name ) ? this.attributes[ name ] : null;
		},
		setAttribute( name, value ) {
			this.attributes[ name ] = value;
		},
		appendChild( child ) {
			this.children.push( child );
		}
	};

	// In a browser, setting `innerHTML` replaces the children; the preview is
	// cleared that way, so the stub has to behave like the real thing.
	Object.defineProperty( element, 'innerHTML', {
		get() {
			return element.serialised || '';
		},
		set( value ) {
			element.serialised = value;
			element.children = [];
		}
	} );

	return element;
}

/**
 * Load the script in a context with a given document and `wp` stub.
 *
 * @param {Object} document - Document stub.
 * @param {Object} wp       - `wp` stub.
 * @return {Object} The sandbox window.
 */
function load( document = undefined, wp = undefined ) {
	const sandbox = { console };

	sandbox.window = sandbox;

	if ( document ) {
		sandbox.document = document;
	}

	if ( wp ) {
		sandbox.wp = wp;
	}

	vm.runInNewContext( SOURCE, vm.createContext( sandbox ), {
		filename: 'wavira/assets/js/admin.js'
	} );

	return sandbox;
}

/**
 * A document stub with no controls at all.
 *
 * @return {Object} Document stub.
 */
function emptyDocument() {
	return {
		// `loading` keeps the module from wiring itself, so each test decides
		// when `start()` runs and events are never bound twice.
		readyState: 'loading',
		getElementById: () => null,
		querySelectorAll: () => [],
		createElement: () => fakeElement()
	};
}

test( 'the module is exported, and loading without a document is safe', () => {
	const sandbox = load();

	assert.equal( typeof sandbox.Wavira.admin.applyColour, 'function' );
	assert.equal( typeof sandbox.Wavira.admin.start, 'function' );
	assert.match( sandbox.Wavira.admin.VERSION, /^\d+\.\d+\.\d+$/ );
} );

test( 'a colour picker writes into its text field, which is what the form posts', () => {
	const input = fakeElement( 'wavira-field-accent' );
	const sandbox = load( {
		...emptyDocument(),
		getElementById: ( id ) => ( id === 'wavira-field-accent' ? input : null )
	} );
	const picker = fakeElement();

	picker.setAttribute( 'data-target', 'wavira-field-accent' );

	sandbox.Wavira.admin.applyColour( picker, '#ff8800' );

	assert.equal( input.value, '#ff8800', 'the text input is the value the sanitizer reads' );
	assert.equal( picker.value, '#ff8800', 'and the swatch follows' );
} );

test( 'clearing a colour empties the text field instead of storing a colour nobody chose', () => {
	const input = fakeElement( 'wavira-field-accent' );
	const sandbox = load( { ...emptyDocument(), getElementById: () => input } );
	const picker = fakeElement();

	picker.setAttribute( 'data-target', 'wavira-field-accent' );
	picker.value = '#123456';
	input.value = '#123456';

	sandbox.Wavira.admin.applyColour( picker, '' );

	assert.equal( input.value, '', 'empty means: use the theme default' );
	assert.equal( picker.value, '#000000', 'the swatch still needs a colour to show' );
} );

test( 'typing in the text field moves the swatch, and clearing it clears the swatch', () => {
	const text = fakeElement( 'wavira-field-accent' );
	const picker = fakeElement();

	text.setAttribute( 'data-wavira-colour-text', '' );
	picker.setAttribute( 'data-wavira-colour-picker', '' );
	picker.value = '#111111';
	text.parentNode = { querySelector: ( selector ) => ( selector === '[data-wavira-colour-picker]' ? picker : null ) };

	const document = {
		...emptyDocument(),
		querySelectorAll: ( selector ) => ( selector === '[data-wavira-colour-text]' ? [ text ] : [] )
	};
	const sandbox = load( document );

	sandbox.Wavira.admin.start();

	text.value = '#ff8800';
	text.dispatch( 'change' );

	assert.equal( picker.value, '#ff8800', 'a valid colour moves the swatch' );

	text.value = '#ff8';
	text.dispatch( 'change' );

	assert.equal( picker.value, '#ff8', 'and so does the short form WordPress accepts' );

	text.value = 'not a colour';
	text.dispatch( 'change' );

	assert.equal( picker.value, '#ff8', 'a typo leaves the swatch where it was' );

	text.value = '';
	text.dispatch( 'change' );

	assert.equal( picker.value, '#000000', 'an empty field clears the swatch' );
} );

test( 'a colour control whose field is missing is a no-op, not a crash', () => {
	const sandbox = load( emptyDocument() );

	assert.doesNotThrow( () => sandbox.Wavira.admin.applyColour( fakeElement(), '#fff' ) );
} );

test( 'the attachment id is read from a selection, a model or a plain object', () => {
	const { attachmentId } = load( emptyDocument() ).Wavira.admin;

	assert.equal( attachmentId( { first: () => ( { toJSON: () => ( { id: 12 } ) } ) } ), '12' );
	assert.equal( attachmentId( { first: () => ( { id: 7 } ) } ), '7' );
	assert.equal( attachmentId( { first: () => null } ), '' );
	assert.equal( attachmentId( null ), '' );
} );

test( 'the preview shows the medium size, and clears when nothing is chosen', () => {
	const box = fakeElement();
	const wrapper = { querySelector: ( selector ) => ( selector === '[data-wavira-media-preview]' ? box : null ) };
	const created = [];
	const sandbox = load( {
		...emptyDocument(),
		createElement: ( tag ) => {
			const element = fakeElement();

			element.tag = tag;
			created.push( element );

			return element;
		}
	} );

	sandbox.Wavira.admin.renderPreview( wrapper, {
		id: 3,
		url: 'https://example.test/full.png',
		alt: 'cover',
		sizes: { medium: { url: 'https://example.test/medium.png' } }
	} );

	assert.equal( created.length, 1 );
	assert.equal( created[ 0 ].tag, 'img' );
	assert.equal( created[ 0 ].src, 'https://example.test/medium.png', 'the medium size, not the full one' );
	assert.equal( created[ 0 ].alt, 'cover' );
	assert.equal( box.children.length, 1 );

	assert.doesNotThrow(
		() => sandbox.Wavira.admin.renderPreview( wrapper, null ),
		'a cleared field is not an error'
	);

	assert.equal( box.children.length, 0, 'removing the image empties the preview' );
} );

test( 'the media library posts the attachment id and nothing else', () => {
	const input = fakeElement( 'wavira-field-dark_logo' );
	const box = fakeElement();
	const wrapper = { querySelector: ( selector ) => ( selector === '[data-wavira-media-preview]' ? box : null ) };
	const button = fakeElement();

	button.setAttribute( 'data-target', 'wavira-field-dark_logo' );
	button.textContent = 'Choose image';
	input.parentNode = { querySelector: ( selector ) => ( selector === '[data-wavira-media]' ? wrapper : null ) };

	const frames = [];
	let selected = null;
	const wp = {
		media: ( options ) => {
			const frame = {
				options,
				on: ( event, handler ) => {
					if ( event === 'select' ) {
						selected = handler;
					}
				},
				state: () => ( {
					get: () => ( {
						first: () => ( { toJSON: () => ( { id: 42, url: 'https://example.test/logo.png' } ) } )
					} )
				} ),
				open: () => frames.push( frame )
			};

			return frame;
		}
	};

	const document = {
		...emptyDocument(),
		getElementById: () => input,
		querySelectorAll: ( selector ) => ( selector === '[data-wavira-media-select]' ? [ button ] : [] )
	};
	const sandbox = load( document, wp );

	assert.equal( frames.length, 0, 'nothing is opened before load' );

	sandbox.Wavira.admin.start();

	assert.equal( frames.length, 0, 'and nothing before a click' );

	button.dispatch( 'click' );

	assert.equal( frames.length, 1, 'the click opens the library' );
	assert.equal( frames[ 0 ].options.library.type, 'image', 'images only' );
	assert.equal( frames[ 0 ].options.multiple, false, 'one image' );

	assert.equal( typeof selected, 'function', 'the frame reports a selection' );

	selected();

	assert.equal( input.value, '42', 'the hidden field carries the attachment id' );
	assert.equal( box.children.length, 1, 'and the preview shows the image' );
} );

test( 'without the media library the screen keeps working', () => {
	const button = fakeElement();
	const document = {
		...emptyDocument(),
		querySelectorAll: ( selector ) => ( selector === '[data-wavira-media-select]' ? [ button ] : [] )
	};
	const sandbox = load( document );

	sandbox.Wavira.admin.start();

	assert.doesNotThrow( () => button.dispatch( 'click' ), 'a missing wp.media is not a fatal' );
} );
