/*!
 * Wavira — Customizer live preview.
 *
 * Loaded only inside the Customizer's preview frame, with core's
 * `customize-preview` as its dependency. Its whole job is the other half of the
 * contract the schema declares (ADR 0020): a field whose transport is
 * `postMessage` is one the preview can apply without a reload, and PHP hands the
 * list over in `window.waviraCustomizeLive` — the same `live` entries the server
 * uses to print the option CSS, so the two cannot disagree about a property name.
 *
 * The work per field is small on purpose:
 *   var        — set (or remove) one CSS custom property on `:root`;
 *   bool-class — toggle one body class while the switch is off;
 *   root-font  — `html{font-size}`;
 *   weight     — the heading-weight rule, in a style element of our own;
 *   css        — the free-CSS block, appended after everything else.
 *
 * A field that changes markup (a logo, a social row, the announcement bar) stays
 * on `refresh`: only the server can render it, so promising otherwise would be a
 * lie the preview tells.
 *
 * Pure functions are exported on `window.Wavira.customizer` so they can be unit
 * tested in `node:vm` without a browser (tests/js/customizer.test.mjs).
 */
( function ( global ) {
	'use strict';

	var VERSION = '0.12.0';
	var LIVE_CSS_ID = 'wavira-live-css';
	var HEADINGS_STYLE_ID = 'wavira-live-headings';
	var HEADING_PREFIX = 'wavira-heading-';
	var HEADING_SELECTORS = [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', '.wp-block-heading' ];

	/**
	 * Whether a Customizer value means "on".
	 *
	 * A checkbox sends a real boolean; a value that arrived over `postMessage`
	 * from an older control can be a string.
	 *
	 * @param {*} value Raw value.
	 * @return {boolean} True when the switch is on.
	 */
	function isOn( value ) {
		return true === value || 1 === value || '1' === value || 'true' === value || 'on' === value;
	}

	/**
	 * The setting's value expressed the way the schema declares its default.
	 *
	 * @param {Object} entry Live entry.
	 * @param {*}      value Raw value.
	 * @return {boolean} True when the value is the declared default.
	 */
	function isDefault( entry, value ) {
		return String( entry.default ) === String( value );
	}

	/**
	 * Set or remove one CSS custom property.
	 *
	 * Removing rather than writing an empty value is what makes "back to the
	 * default" mean the theme's own design again instead of an invalid
	 * declaration.
	 *
	 * @param {CSSStyleDeclaration} style    Element style.
	 * @param {string}              property Custom property name.
	 * @param {string}              value    Value, or an empty string to remove.
	 * @return {void}
	 */
	function setProperty( style, property, value ) {
		if ( '' === value || null === value || undefined === value ) {
			style.removeProperty( property );
			return;
		}

		style.setProperty( property, value );
	}

	/**
	 * Create, update or drop one of the preview's own `<style>` elements.
	 *
	 * @param {Document} doc  Document to update.
	 * @param {string}   id   Element id.
	 * @param {string}   text CSS text; an empty string removes the element.
	 * @return {void}
	 */
	function setStyleText( doc, id, text ) {
		var style = doc.getElementById( id );

		if ( '' === text ) {
			if ( style && style.parentNode ) {
				style.parentNode.removeChild( style );
			}

			return;
		}

		if ( ! style ) {
			style = doc.createElement( 'style' );
			style.id = id;
			( doc.head || doc.documentElement ).appendChild( style );
		}

		if ( 'textContent' in style ) {
			style.textContent = text;
		} else {
			style.appendChild( doc.createTextNode( text ) );
		}
	}

	/**
	 * The heading rule for one weight.
	 *
	 * Empty for the declared default: the built stylesheet already carries it.
	 *
	 * @param {string} weight Font weight.
	 * @return {string} CSS rule, or an empty string.
	 */
	function headingRule( weight ) {
		return HEADING_SELECTORS.map( function ( selector ) {
			return 'body.' + HEADING_PREFIX + weight + ' ' + selector;
		} ).join( ',' ) + '{font-weight:' + weight + '}';
	}

	/**
	 * Move the heading weight, in the body class and in the preview's rule.
	 *
	 * Both, because the server prints them as a pair: the class is what wins on
	 * specificity, the rule is what carries the weight.
	 *
	 * @param {Object}   entry Live entry.
	 * @param {*}        value Raw value.
	 * @param {Document} doc   Document to update.
	 * @return {void}
	 */
	function applyWeight( entry, value, doc ) {
		var weight = String( value );
		var body = doc.body;

		if ( body ) {
			var kept = String( body.className || '' ).split( /\s+/ ).filter( function ( name ) {
				return '' !== name && 0 !== name.indexOf( HEADING_PREFIX );
			} );

			if ( ! isDefault( entry, weight ) ) {
				kept.push( HEADING_PREFIX + weight );
			}

			body.className = kept.join( ' ' );
		}

		setStyleText( doc, HEADINGS_STYLE_ID, isDefault( entry, weight ) ? '' : headingRule( weight ) );
	}

	/**
	 * Apply one live entry to one document.
	 *
	 * @param {Object}   entry Live entry from the server.
	 * @param {*}        value Setting value.
	 * @param {Document} doc   Document to update.
	 * @return {void}
	 */
	function apply( entry, value, doc ) {
		if ( ! entry || ! doc ) {
			return;
		}

		var root = doc.documentElement;

		switch ( entry.mode ) {
			case 'bool-class':
				if ( doc.body ) {
					doc.body.classList.toggle( String( entry.class ), ! isOn( value ) );
				}
				return;

			case 'root-font':
				setProperty( root.style, 'font-size', isDefault( entry, value ) ? '' : String( value ) + ( entry.unit || '' ) );
				return;

			case 'weight':
				applyWeight( entry, value, doc );
				return;

			case 'css':
				setStyleText( doc, LIVE_CSS_ID, String( value ) );
				return;

			case 'var':
			default:
				if ( 'bool' === entry.type ) {
					setProperty( root.style, String( entry.css ), isOn( value ) ? '' : String( entry.off || 'none' ) );
					return;
				}

				setProperty(
					root.style,
					String( entry.css ),
					isDefault( entry, value ) || '' === String( value ) ? '' : String( value ) + ( entry.unit || '' )
				);
		}
	}

	/**
	 * Subscribe every live entry to its setting.
	 *
	 * A setting that is not registered (a field another filter removed) is
	 * skipped rather than fatal: the preview keeps working for the fields that
	 * are there.
	 *
	 * @param {Function} api     `wp.customize`.
	 * @param {Document} doc     Preview document.
	 * @param {Object[]} entries Live entries.
	 * @return {number} How many settings were bound.
	 */
	function init( api, doc, entries ) {
		var bound = 0;

		entries.forEach( function ( entry ) {
			var setting = api( 'wavira_' + entry.key );

			if ( ! setting || 'function' !== typeof setting.bind ) {
				return;
			}

			setting.bind( function ( value ) {
				apply( entry, value, doc );
			} );

			bound++;
		} );

		return bound;
	}

	global.Wavira = global.Wavira || {};
	global.Wavira.customizer = {
		version: VERSION,
		liveCssId: LIVE_CSS_ID,
		headingPrefix: HEADING_PREFIX,
		isOn: isOn,
		isDefault: isDefault,
		setProperty: setProperty,
		setStyleText: setStyleText,
		headingRule: headingRule,
		apply: apply,
		init: init
	};

	if ( global.wp && global.wp.customize && global.waviraCustomizeLive ) {
		global.Wavira.customizer.bound = init( global.wp.customize, global.document, global.waviraCustomizeLive );
	}
} )( typeof window !== 'undefined' ? window : this );
