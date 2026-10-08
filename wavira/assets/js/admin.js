/*!
 * Wavira — the settings screen's script.
 *
 * Two jobs, both about a field a plain input cannot express, and nothing else
 * (ADR 0006: no dependency, no framework, no jQuery):
 *
 * 1. **the colour field** — an `<input type="color">` swatch next to the text
 *    value, and a *Clear* button. The text input stays the real value: it is
 *    what the form posts and what the sanitizer reads, so the screen works with
 *    this script absent, and the swatch is an affordance rather than a
 *    requirement. Empty means "the theme's own colour", which is why clearing
 *    has to be possible at all.
 * 2. **the image field** — core's media library, opened through `wp.media`
 *    (enqueued with `wp_enqueue_media()` on this screen only). The hidden input
 *    carries the attachment id, exactly like the Customizer's own media control,
 *    so the server-side handling is the same in both doors.
 *
 * Pure helpers are exported on `window.Wavira.admin` so they can be unit tested
 * in `node:vm` without a browser (tests/js/admin.test.mjs).
 */
( function ( global ) {
	'use strict';

	var VERSION = '0.13.0';

	/**
	 * A `#rgb` or `#rrggbb` colour, the two shapes the picker accepts.
	 */
	var COLOUR = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i;

	/**
	 * The text input a colour control writes to.
	 *
	 * @param {Object} element - The `[data-wavira-colour-picker]` element.
	 * @return {Object|null} The matching input, or null.
	 */
	function colourTarget( element ) {
		var id = element && element.getAttribute ? element.getAttribute( 'data-target' ) : '';

		if ( ! id || ! global.document || ! global.document.getElementById ) {
			return null;
		}

		return global.document.getElementById( id );
	}

	/**
	 * Write a colour into the text field and the swatch together.
	 *
	 * @param {Object} element - The picker element.
	 * @param {string} value   - A `#rrggbb` colour, or '' to clear.
	 * @return {void}
	 */
	function applyColour( element, value ) {
		var input = colourTarget( element );

		if ( ! input ) {
			return;
		}

		input.value = value;

		if ( element.value !== undefined ) {
			element.value = value || '#000000';
		}
	}

	/**
	 * The value a media control should post.
	 *
	 * @param {Object} selection - A media library selection.
	 * @return {string} Attachment id as a string, or '' when there is none.
	 */
	function attachmentId( selection ) {
		if ( ! selection || ! selection.first ) {
			return '';
		}

		var model = selection.first();

		if ( ! model ) {
			return '';
		}

		if ( typeof model.toJSON === 'function' ) {
			model = model.toJSON();
		}

		return model && model.id ? String( model.id ) : '';
	}

	/**
	 * Render the chosen image into its preview box.
	 *
	 * @param {Object} element - The `[data-wavira-media]` wrapper.
	 * @param {Object} model   - Attachment model or plain object.
	 * @return {void}
	 */
	function renderPreview( element, model ) {
		if ( ! element || ! element.querySelector ) {
			return;
		}

		var box = element.querySelector( '[data-wavira-media-preview]' );

		if ( ! box ) {
			return;
		}

		if ( ! model ) {
			box.innerHTML = '';

			return;
		}

		if ( typeof model.toJSON === 'function' ) {
			model = model.toJSON();
		}

		if ( ! model || ! model.id ) {
			box.innerHTML = '';
			return;
		}

		var url = model.sizes && model.sizes.medium ? model.sizes.medium.url : model.url;

		if ( ! url || ! global.document || ! global.document.createElement ) {
			return;
		}

		var image = global.document.createElement( 'img' );

		image.src = url;
		image.alt = model.alt || '';
		box.innerHTML = '';
		box.appendChild( image );
	}

	/**
	 * Wire one control to one field.
	 *
	 * @param {Object} document - The document to look in.
	 * @return {void}
	 */
	function wire( document ) {
		if ( ! document || ! document.querySelectorAll ) {
			return;
		}

		var pickers = document.querySelectorAll( '[data-wavira-colour-picker]' );
		var index;

		for ( index = 0; index < pickers.length; index++ ) {
			pickers[ index ].addEventListener( 'input', function ( event ) {
				applyColour( event.currentTarget, event.currentTarget.value );
			} );
		}

		var texts = document.querySelectorAll( '[data-wavira-colour-text]' );

		for ( index = 0; index < texts.length; index++ ) {
			texts[ index ].addEventListener( 'change', function ( event ) {
				var input = event.currentTarget;
				var picker = input.parentNode ? input.parentNode.querySelector( '[data-wavira-colour-picker]' ) : null;

				if ( ! picker ) {
					return;
				}

				// A half-typed or invalid value leaves the swatch alone; an
				// empty field is the one value that clears it.
				if ( COLOUR.test( input.value ) ) {
					picker.value = input.value;
				} else if ( ! input.value ) {
					picker.value = '#000000';
				}
			} );
		}

		var clears = document.querySelectorAll( '[data-wavira-colour-clear]' );

		for ( index = 0; index < clears.length; index++ ) {
			clears[ index ].addEventListener( 'click', function ( event ) {
				var button = event.currentTarget;
				var id = button.getAttribute( 'data-target' );
				var input = id && document.getElementById ? document.getElementById( id ) : null;
				var picker = input && input.parentNode ? input.parentNode.querySelector( '[data-wavira-colour-picker]' ) : null;

				event.preventDefault();

				if ( input ) {
					input.value = '';
				}

				if ( picker ) {
					picker.value = '#000000';
				}
			} );
		}
	}

	/**
	 * Open the media library for one button.
	 *
	 * `wp.media` is only present when `wp_enqueue_media()` ran, so its absence is
	 * handled as "the picker is not available" — the hidden field keeps working.
	 *
	 * @param {Object} button - The `[data-wavira-media-select]` button.
	 * @return {void}
	 */
	function openLibrary( button ) {
		var media = global.wp && global.wp.media;

		if ( ! media ) {
			return;
		}

		var id = button.getAttribute( 'data-target' );
		var input = id && global.document.getElementById ? global.document.getElementById( id ) : null;
		var wrapper = input && input.parentNode ? input.parentNode.querySelector( '[data-wavira-media]' ) : null;
		var text = global.waviraAdminText || {};

		var frame = media( {
			title: button.textContent || text.choose || '',
			library: { type: 'image' },
			multiple: false,
			button: { text: text.use || '' }
		} );

		frame.on( 'select', function () {
			var selection = frame.state().get( 'selection' );
			var value = attachmentId( selection );

			if ( input ) {
				input.value = value;
			}

			renderPreview( wrapper, selection.first() );
		} );

		frame.open();
	}

	/**
	 * Start.
	 *
	 * @return {void}
	 */
	function start() {
		var document = global.document;

		if ( ! document ) {
			return;
		}

		wire( document );

		var buttons = document.querySelectorAll( '[data-wavira-media-select]' );
		var index;

		for ( index = 0; index < buttons.length; index++ ) {
			buttons[ index ].addEventListener( 'click', function ( event ) {
				event.preventDefault();
				openLibrary( event.currentTarget );
			} );
		}

		var removers = document.querySelectorAll( '[data-wavira-media-remove]' );

		for ( index = 0; index < removers.length; index++ ) {
			removers[ index ].addEventListener( 'click', function ( event ) {
				var button = event.currentTarget;
				var id = button.getAttribute( 'data-target' );
				var input = id && document.getElementById ? document.getElementById( id ) : null;
				var wrapper = input && input.parentNode ? input.parentNode.querySelector( '[data-wavira-media]' ) : null;

				event.preventDefault();

				if ( input ) {
					input.value = '';
				}

				renderPreview( wrapper, null );
			} );
		}
	}

	global.Wavira = global.Wavira || {};
	global.Wavira.admin = {
		VERSION: VERSION,
		applyColour: applyColour,
		attachmentId: attachmentId,
		renderPreview: renderPreview,
		wire: wire,
		start: start
	};

	if ( global.document && global.document.readyState !== 'loading' ) {
		start();
	} else if ( global.document && global.document.addEventListener ) {
		global.document.addEventListener( 'DOMContentLoaded', start );
	}
}( typeof window !== 'undefined' ? window : this ) );
