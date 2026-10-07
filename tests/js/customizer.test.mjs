/**
 * Customizer preview unit tests.
 *
 * The live preview has to be verifiable without a WordPress install and without
 * a browser, so the file is loaded in a `node:vm` context with a stub document
 * and a stub `wp.customize`. Run with:
 *
 *   node --test tests/js/customizer.test.mjs
 *
 * @package Wavira
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const SOURCE = readFileSync( new URL( '../../wavira/assets/js/customizer.js', import.meta.url ), 'utf8' );

/**
 * Load the preview script in a context with no `wp.customize`.
 *
 * @return {Object} `window.Wavira.customizer` of the sandbox.
 */
function loadCustomizer() {
	const sandbox = { console };

	sandbox.window = sandbox;

	return vm.runInNewContext( SOURCE + '\nwindow.Wavira.customizer;', vm.createContext( sandbox ), {
		filename: 'wavira/assets/js/customizer.js',
	} );
}

/**
 * A stub CSSStyleDeclaration that records custom properties.
 *
 * @return {Object} Style stub.
 */
function fakeStyle() {
	const props = {};

	return {
		props,
		setProperty( name, value ) {
			props[ name ] = String( value );
		},
		removeProperty( name ) {
			delete props[ name ];
		},
		getPropertyValue( name ) {
			return Object.prototype.hasOwnProperty.call( props, name ) ? props[ name ] : '';
		},
	};
}

/**
 * A stub element with a class list, a style and children.
 *
 * @param {string} id Element id.
 * @return {Object} Element stub.
 */
function fakeElement( id = '' ) {
	const classes = new Set();

	return {
		id,
		className: '',
		textContent: '',
		style: fakeStyle(),
		children: [],
		parentNode: null,
		classList: {
			toggle( name, force ) {
				const on = force === undefined ? ! classes.has( name ) : Boolean( force );

				if ( on ) {
					classes.add( name );
				} else {
					classes.delete( name );
				}

				return on;
			},
			contains( name ) {
				return classes.has( name );
			},
			add( name ) {
				classes.add( name );
			},
		},
		appendChild( child ) {
			child.parentNode = this;
			this.children.push( child );
			this.textContent += child.textContent || '';

			return child;
		},
		removeChild( child ) {
			this.children = this.children.filter( ( entry ) => entry !== child );
			child.parentNode = null;
		},
	};
}

/**
 * A stub document with a head, a body and `getElementById()` over both.
 *
 * @return {Object} Document stub.
 */
function fakeDocument() {
	const head = fakeElement( 'head' );
	const body = fakeElement( 'body' );
	const root = fakeElement( 'html' );
	const doc = {
		head,
		body,
		documentElement: root,
		createElement: ( tag ) => fakeElement( `created:${ tag }` ),
		createTextNode: ( text ) => ( { textContent: text } ),
		getElementById( id ) {
			return [ ...head.children, ...root.children ].find( ( child ) => child.id === id ) || null;
		},
	};

	return doc;
}

/**
 * A stub `wp.customize`: only what the preview uses.
 *
 * @param {Object} values Initial values keyed by setting id.
 * @return {Object} `{ api, values, bind }`.
 */
function fakeApi( values = {} ) {
	const callbacks = {};

	const api = ( id ) => ( {
		bind( callback ) {
			callbacks[ id ] = callback;
		},
	} );

	return {
		api,
		callbacks,
		set( id, value ) {
			callbacks[ id ]( value );
		},
		values,
	};
}

test( 'a switch value is read the way a checkbox sends it', () => {
	const customizer = loadCustomizer();

	assert.equal( customizer.isOn( true ), true );
	assert.equal( customizer.isOn( false ), false );
	assert.equal( customizer.isOn( '1' ), true );
	assert.equal( customizer.isOn( 'true' ), true );
	assert.equal( customizer.isOn( 'on' ), true );
	assert.equal( customizer.isOn( '' ), false );
	assert.equal( customizer.isOn( 0 ), false );
	assert.equal( customizer.isOn( 'maybe' ), false );
} );

test( 'a CSS variable is set, and removed when the value is the declared default', () => {
	const customizer = loadCustomizer();
	const doc = fakeDocument();
	const entry = { key: 'container_width', mode: 'var', type: 'int', css: '--wavira-container', unit: 'px', default: 1200 };

	customizer.apply( entry, 1080, doc );

	assert.equal( doc.documentElement.style.getPropertyValue( '--wavira-container' ), '1080px' );

	customizer.apply( entry, 1200, doc );

	assert.equal( doc.documentElement.style.getPropertyValue( '--wavira-container' ), '', 'the default is the stylesheet, not a declaration' );
} );

test( 'a colour with an empty default is only written when it is filled in', () => {
	const customizer = loadCustomizer();
	const doc = fakeDocument();
	const entry = { key: 'accent', mode: 'var', type: 'color', css: '--wp--preset--color--primary', default: '' };

	customizer.apply( entry, '#123456', doc );

	assert.equal( doc.documentElement.style.getPropertyValue( '--wp--preset--color--primary' ), '#123456' );

	customizer.apply( entry, '', doc );

	assert.equal( doc.documentElement.style.getPropertyValue( '--wp--preset--color--primary' ), '' );
} );

test( 'a switch that drives a variable uses its off value', () => {
	const customizer = loadCustomizer();
	const doc = fakeDocument();
	const entry = { key: 'card_shadow', mode: 'var', type: 'bool', css: '--wavira-cover-shadow', off: 'none', default: true };

	customizer.apply( entry, false, doc );

	assert.equal( doc.documentElement.style.getPropertyValue( '--wavira-cover-shadow' ), 'none' );

	customizer.apply( entry, true, doc );

	assert.equal( doc.documentElement.style.getPropertyValue( '--wavira-cover-shadow' ), '' );
} );

test( 'a body-class switch toggles the class it declares', () => {
	const customizer = loadCustomizer();
	const doc = fakeDocument();
	const entry = { key: 'sticky_header', mode: 'bool-class', type: 'bool', class: 'wavira-not-sticky', default: true };

	customizer.apply( entry, false, doc );

	assert.equal( doc.body.classList.contains( 'wavira-not-sticky' ), true );

	customizer.apply( entry, true, doc );

	assert.equal( doc.body.classList.contains( 'wavira-not-sticky' ), false );
} );

test( 'the base text size is applied to the html element', () => {
	const customizer = loadCustomizer();
	const doc = fakeDocument();
	const entry = { key: 'font_base_size', mode: 'root-font', type: 'int', unit: 'px', default: 16 };

	customizer.apply( entry, 18, doc );

	assert.equal( doc.documentElement.style.getPropertyValue( 'font-size' ), '18px' );

	customizer.apply( entry, 16, doc );

	assert.equal( doc.documentElement.style.getPropertyValue( 'font-size' ), '' );
} );

test( 'the heading weight moves in the class and in the preview rule', () => {
	const customizer = loadCustomizer();
	const doc = fakeDocument();
	const entry = { key: 'heading_weight', mode: 'weight', type: 'choice', default: '700' };

	customizer.apply( entry, '800', doc );

	assert.equal( doc.body.className, 'wavira-heading-800' );

	const style = doc.getElementById( 'wavira-live-headings' );

	assert.ok( style, 'the rule is printed in the preview' );
	assert.equal(
		style.textContent,
		'body.wavira-heading-800 h1,body.wavira-heading-800 h2,body.wavira-heading-800 h3,'
			+ 'body.wavira-heading-800 h4,body.wavira-heading-800 h5,body.wavira-heading-800 h6,'
			+ 'body.wavira-heading-800 .wp-block-heading{font-weight:800}',
		'the preview prints the same rule the server prints'
	);

	customizer.apply( entry, '600', doc );

	assert.equal( doc.body.className, 'wavira-heading-600', 'the previous weight is not left behind' );
	assert.equal( doc.getElementById( 'wavira-live-headings' ).textContent, customizer.headingRule( '600' ) );

	customizer.apply( entry, '700', doc );

	assert.equal( doc.body.className, '', 'the default needs no class' );
	assert.equal( doc.getElementById( 'wavira-live-headings' ), null, 'and no rule' );
} );

test( 'a heading class added by something else is left alone', () => {
	const customizer = loadCustomizer();
	const doc = fakeDocument();

	doc.body.className = 'home page wavira-heading-800';

	customizer.apply( { key: 'heading_weight', mode: 'weight', default: '700' }, '600', doc );

	assert.equal( doc.body.className, 'home page wavira-heading-600' );
} );

test( 'the free-CSS field is written into the preview, and removed when emptied', () => {
	const customizer = loadCustomizer();
	const doc = fakeDocument();
	const entry = { key: 'custom_css', mode: 'css', type: 'css', default: '' };

	customizer.apply( entry, 'body{outline:1px solid red}', doc );

	const style = doc.getElementById( 'wavira-live-css' );

	assert.ok( style );
	assert.equal( style.textContent, 'body{outline:1px solid red}' );

	customizer.apply( entry, '', doc );

	assert.equal( doc.getElementById( 'wavira-live-css' ), null );
} );

test( 'only registered settings are bound, and the count is reported', () => {
	const customizer = loadCustomizer();
	const doc = fakeDocument();
	const fake = fakeApi();
	const entries = [
		{ key: 'accent', mode: 'var', type: 'color', css: '--wp--preset--color--primary', default: '' },
		{ key: 'removed_by_a_filter', mode: 'var', type: 'color', css: '--nope', default: '' },
	];

	const bound = customizer.init(
		( id ) => ( id.endsWith( 'removed_by_a_filter' ) ? null : fake.api( id ) ),
		doc,
		entries
	);

	assert.equal( bound, 1, 'a field that is not registered is skipped, not fatal' );

	fake.set( 'wavira_accent', '#abcdef' );

	assert.equal( doc.documentElement.style.getPropertyValue( '--wp--preset--color--primary' ), '#abcdef' );
} );

test( 'the script does nothing without wp.customize', () => {
	const sandbox = { console, document: fakeDocument() };

	sandbox.window = sandbox;
	sandbox.waviraCustomizeLive = [ { key: 'accent', mode: 'var', css: '--wp--preset--color--primary', default: '' } ];

	vm.runInNewContext( SOURCE, vm.createContext( sandbox ), { filename: 'wavira/assets/js/customizer.js' } );

	assert.equal( sandbox.Wavira.customizer.bound, undefined, 'no api, no binding' );
	assert.equal(
		sandbox.document.documentElement.style.getPropertyValue( '--wp--preset--color--primary' ),
		'',
		'and nothing is applied'
	);
} );

test( 'the server list is what the script reads', () => {
	const customizer = loadCustomizer();
	const sandbox = { console, document: fakeDocument() };

	sandbox.window = sandbox;
	sandbox.wp = { customize: ( id ) => ( { bind( callback ) { sandbox.bound = { id, callback }; } } ) };
	sandbox.waviraCustomizeLive = [ { key: 'accent', mode: 'var', type: 'color', css: '--wp--preset--color--primary', default: '' } ];

	vm.runInNewContext( SOURCE, vm.createContext( sandbox ), { filename: 'wavira/assets/js/customizer.js' } );

	assert.equal( sandbox.Wavira.customizer.bound, 1 );
	assert.equal( sandbox.bound.id, 'wavira_accent', 'the setting id is the schema key with the theme_mod prefix' );

	assert.equal( typeof customizer.apply, 'function' );
} );
