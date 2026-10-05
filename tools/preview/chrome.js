/**
 * Harness chrome: drives the stage frame's colour mode, direction and width.
 *
 * The colour mode is written to the same localStorage key the product uses
 * (`wavira.theme`) and applied to the frame's `data-theme` attribute, so the
 * shipped theme script and the shipped pre-paint logic are exercised, not a
 * harness copy of them.
 */
( function ( doc ) {
	'use strict';

	var stage = doc.getElementById( 'stage' );
	var readout = doc.getElementById( 'readout' );
	var state = { mode: 'auto', dir: 'rtl', width: 1440 };
	var STORAGE_KEY = 'wavira.theme';

	/**
	 * Mark the active button in one group.
	 *
	 * @param {string} attribute Group attribute (`data-mode`, `data-dir`, `data-width`).
	 * @param {string} value     Active value.
	 * @return {void}
	 */
	function mark( attribute, value ) {
		var buttons = doc.querySelectorAll( '[' + attribute + ']' );

		Array.prototype.forEach.call( buttons, function ( button ) {
			button.setAttribute( 'aria-pressed', String( button.getAttribute( attribute ) === String( value ) ) );
		} );
	}

	/**
	 * Persian numerals, so the readout matches the product interface.
	 *
	 * @param {string|number} value Text to convert.
	 * @return {string} Converted text.
	 */
	function digits( value ) {
		return String( value ).replace( /[0-9]/g, function ( digit ) {
			return '۰۱۲۳۴۵۶۷۸۹'[ Number( digit ) ];
		} );
	}

	/**
	 * Print the current state.
	 *
	 * @return {void}
	 */
	function report() {
		var directions = { rtl: 'راست‌به‌چپ', ltr: 'چپ‌به‌راست' };
		var modes = { auto: 'خودکار', light: 'روشن', dark: 'تاریک' };

		if ( readout ) {
			readout.textContent = [ directions[ state.dir ], modes[ state.mode ], digits( state.width ) + ' پیکسل' ].join( ' · ' );
		}
	}

	/**
	 * Apply the whole state to the frame document.
	 *
	 * @return {void}
	 */
	function apply() {
		var frame = stage && stage.contentDocument;

		if ( ! frame || ! frame.documentElement ) {
			return;
		}

		frame.documentElement.setAttribute( 'data-theme', state.mode );
		frame.documentElement.setAttribute( 'dir', state.dir );
	}

	doc.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( 'button' );

		if ( ! button ) {
			return;
		}

		var mode = button.getAttribute( 'data-mode' );
		var dir = button.getAttribute( 'data-dir' );
		var width = button.getAttribute( 'data-width' );

		if ( mode ) {
			state.mode = mode;
			mark( 'data-mode', mode );

			try {
				window.localStorage.setItem( STORAGE_KEY, mode );
			} catch ( error ) {
				// Storage may be unavailable; the attribute still applies.
			}
		}

		if ( dir ) {
			state.dir = dir;
			mark( 'data-dir', dir );
		}

		if ( width ) {
			state.width = Number( width );
			mark( 'data-width', width );

			if ( stage ) {
				stage.style.width = state.width + 'px';
				stage.style.maxWidth = 'none';
			}
		}

		apply();
		report();
	} );

	if ( stage ) {
		stage.addEventListener( 'load', function () {
			apply();
			report();
		} );
	}

	mark( 'data-mode', state.mode );
	mark( 'data-dir', state.dir );
	mark( 'data-width', state.width );
	report();
} )( document );
