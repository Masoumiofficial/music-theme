/*!
 * Wavira — theme behaviour.
 *
 * Three jobs, nothing else (ADR 0014):
 * 1. colour mode: read the stored preference, apply it to <html>, and let the
 *    toggle cycle light → dark → auto. The first paint is handled by a small
 *    inline script in <head>, so this file never causes a flash of the wrong
 *    theme;
 * 2. player instances: ask Wavira Core to take over every mount point on the
 *    page. Without the plugin (or without its bundle) the server-rendered
 *    <audio> fallback simply stays;
 * 3. nothing that a core block already does — navigation, query loops and
 *    comments are core behaviour, not theme script.
 *
 * The navigation drawer is the core Navigation block's own disclosure, so this
 * file ships no menu code, no polyfill and no dependency (ADR 0006).
 *
 * Pure helpers are exported on `window.Wavira.theme` so they can be unit tested
 * without a browser (tests/js/theme.test.mjs).
 */
( function ( global ) {
	'use strict';

	var VERSION = '0.8.0';
	var STORAGE_KEY = 'wavira.theme';
	var MODES = [ 'light', 'dark', 'auto' ];
	var BASE_STRINGS = {
		label: 'Colour theme',
		light: 'Light',
		dark: 'Dark',
		auto: 'Auto'
	};

	/**
	 * Settings the server printed, merged over the English fallbacks.
	 *
	 * @return {Object} Settings with a complete `strings` map.
	 */
	function settings() {
		var provided = global.waviraThemeSettings || {};
		var strings = provided.strings || {};
		var merged = {};
		var key;

		for ( key in BASE_STRINGS ) {
			if ( Object.prototype.hasOwnProperty.call( BASE_STRINGS, key ) ) {
				merged[ key ] = 'string' === typeof strings[ key ] && '' !== strings[ key ] ? strings[ key ] : BASE_STRINGS[ key ];
			}
		}

		return {
			storageKey: 'string' === typeof provided.storageKey && '' !== provided.storageKey ? provided.storageKey : STORAGE_KEY,
			strings: merged
		};
	}

	/**
	 * Whether a value is one of the three documented modes.
	 *
	 * @param {*} value Candidate value.
	 * @return {boolean} True for light, dark or auto.
	 */
	function isMode( value ) {
		return MODES.indexOf( value ) !== -1;
	}

	/**
	 * The next mode in the cycle: light → dark → auto → light.
	 *
	 * @param {string} mode Current mode.
	 * @return {string} Next mode.
	 */
	function nextMode( mode ) {
		return MODES[ ( MODES.indexOf( mode ) + 1 + MODES.length ) % MODES.length ];
	}

	/**
	 * Read the stored preference, defaulting to `auto`.
	 *
	 * @param {Object} storage Storage implementation (localStorage or a stub).
	 * @param {string} key     Storage key.
	 * @return {string} A valid mode.
	 */
	function readStored( storage, key ) {
		try {
			var value = storage && 'function' === typeof storage.getItem ? storage.getItem( key ) : null;

			return isMode( value ) ? value : 'auto';
		} catch ( error ) {
			return 'auto';
		}
	}

	/**
	 * Store the preference, ignoring storage that is unavailable or full.
	 *
	 * @param {Object} storage Storage implementation.
	 * @param {string} key     Storage key.
	 * @param {string} mode    Mode to store.
	 * @return {boolean} Whether the value reached storage.
	 */
	function writeStored( storage, key, mode ) {
		try {
			if ( storage && 'function' === typeof storage.setItem ) {
				storage.setItem( key, mode );

				return true;
			}
		} catch ( error ) {
			return false;
		}

		return false;
	}

	/**
	 * Apply a mode to the document element.
	 *
	 * @param {Object} doc  Document.
	 * @param {string} mode Mode to apply.
	 * @return {void}
	 */
	function applyMode( doc, mode ) {
		if ( doc && doc.documentElement && 'function' === typeof doc.documentElement.setAttribute ) {
			doc.documentElement.setAttribute( 'data-theme', isMode( mode ) ? mode : 'auto' );
		}
	}

	/**
	 * Update one toggle button: visible label, accessible name, pressed state.
	 *
	 * The visible text is the current mode; the accessible name says what the
	 * button does, which is what a screen reader user needs (WCAG 2.5.3).
	 *
	 * @param {Element} button Toggle button.
	 * @param {string}  mode   Current mode.
	 * @param {Object}  text   Merged strings.
	 * @return {void}
	 */
	function paintToggle( button, mode, text ) {
		var label = button.querySelector ? button.querySelector( '[data-wavira-theme-label]' ) : null;

		if ( label ) {
			label.textContent = text[ mode ];
		} else {
			button.textContent = text[ mode ];
		}

		button.setAttribute( 'aria-label', text.label + ': ' + text[ mode ] );
		button.setAttribute( 'aria-pressed', 'true' );
		button.setAttribute( 'title', text.label );
	}

	/**
	 * Wire the colour-mode toggle and keep every button in sync.
	 *
	 * @param {Object} doc     Document.
	 * @param {Object} storage Storage implementation.
	 * @return {{mode: Function, setMode: Function}} Controller for hooks and tests.
	 */
	function initThemeMode( doc, storage ) {
		var config = settings();
		var mode = readStored( storage, config.storageKey );
		var buttons = doc && 'function' === typeof doc.querySelectorAll ? doc.querySelectorAll( '[data-wavira-theme-toggle]' ) : [];
		var list = [];

		Array.prototype.forEach.call( buttons, function ( button ) {
			list.push( button );
		} );

		/**
		 * Paint every toggle with the current mode.
		 *
		 * @return {void}
		 */
		function paint() {
			list.forEach( function ( button ) {
				paintToggle( button, mode, config.strings );
			} );
		}

		/**
		 * Apply and store a mode.
		 *
		 * @param {string} next Mode to apply.
		 * @return {string} The mode that is now active.
		 */
		function setMode( next ) {
			mode = isMode( next ) ? next : 'auto';
			applyMode( doc, mode );
			writeStored( storage, config.storageKey, mode );
			paint();

			return mode;
		}

		list.forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				setMode( nextMode( mode ) );
			} );
		} );

		applyMode( doc, mode );
		paint();

		return {
			mode: function () {
				return mode;
			},
			setMode: setMode
		};
	}

	/**
	 * Hand every mount point to the player engine.
	 *
	 * @param {Object} doc Document.
	 * @return {number} Number of instances the engine reported.
	 */
	function initPlayers( doc ) {
		var api = global.Wavira && global.Wavira.player;

		if ( ! api || 'function' !== typeof api.init ) {
			return 0;
		}

		try {
			api.init( doc );
		} catch ( error ) {
			return 0;
		}

		return api.instances ? api.instances.length : 0;
	}

	/**
	 * Start everything that needs a document.
	 *
	 * @param {Object} doc     Document.
	 * @param {Object} storage Storage implementation.
	 * @return {Object} Controller.
	 */
	function init( doc, storage ) {
		var controller = initThemeMode( doc, storage );

		if ( doc && 'function' === typeof doc.querySelectorAll && doc.querySelectorAll( '[data-wavira-player]' ).length ) {
			initPlayers( doc );
		}

		return controller;
	}

	global.Wavira = global.Wavira || {};
	global.Wavira.theme = {
		version: VERSION,
		modes: MODES.slice(),
		isMode: isMode,
		nextMode: nextMode,
		readStored: readStored,
		writeStored: writeStored,
		applyMode: applyMode,
		initThemeMode: initThemeMode,
		initPlayers: initPlayers,
		init: init,
		storageKey: STORAGE_KEY
	};

	if ( global.document ) {
		if ( 'loading' === global.document.readyState ) {
			global.document.addEventListener( 'DOMContentLoaded', function () {
				init( global.document, global.localStorage );
			} );
		} else {
			init( global.document, global.localStorage );
		}
	}
} )( typeof window !== 'undefined' ? window : this );
