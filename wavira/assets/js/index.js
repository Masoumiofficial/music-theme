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
 * 3. the photo gallery: open a photo at full size in a dialog instead of leaving
 *    the page. Without this script every photo is still a link to the file, which
 *    is what a gallery was before lightboxes existed;
 * 4. nothing that a core block already does — navigation, query loops and
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

	var VERSION = '0.12.0';
	var STORAGE_KEY = 'wavira.theme';
	var MODES = [ 'light', 'dark', 'auto' ];
	var BASE_STRINGS = {
		lightbox: 'Photo',
		close: 'Close',
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
	 * Wire the back-to-top button, when the theme printed one.
	 *
	 * The button ships `hidden` and is revealed only after the page is scrolled:
	 * with scripting off it stays out of the way instead of sitting there doing
	 * nothing. Visitors who prefer reduced motion get an instant jump — the
	 * preference decides *how* it scrolls, never whether the control works.
	 *
	 * @param {Object} doc Document.
	 * @return {number} Number of buttons wired (0 or 1).
	 */
	function initBackToTop( doc ) {
		if ( ! doc || 'function' !== typeof doc.querySelector ) {
			return 0;
		}

		var button = doc.querySelector( '[data-wavira-to-top]' );

		if ( ! button ) {
			return 0;
		}

		var reduce = false;

		try {
			reduce = !! ( global.matchMedia && global.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );
		} catch ( error ) {
			reduce = false;
		}

		button.hidden = false;

		button.addEventListener( 'click', function ( event ) {
			event.preventDefault();

			if ( 'function' === typeof global.scrollTo ) {
				global.scrollTo( { top: 0, behavior: reduce ? 'auto' : 'smooth' } );
			}

			var target = doc.querySelector( 'a.skip-link, #wp--skip-link--target, main' );

			if ( target && 'function' === typeof target.focus ) {
				target.focus( { preventScroll: true } );
			}
		} );

		return 1;
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
	 * Turn gallery links into a lightbox, when the browser has `<dialog>`.
	 *
	 * Progressive by construction: the markup already links to the full-size
	 * photo, so this only intercepts the click, shows the picture in a modal
	 * dialog and closes on click, Escape or the close button. A browser without
	 * `<dialog>` keeps the plain link — no polyfill, nothing to break (ADR 0006).
	 *
	 * @param {Object} doc Document.
	 * @return {number} Number of galleries upgraded.
	 */
	function initLightbox( doc ) {
		if ( ! doc || 'function' !== typeof doc.querySelectorAll || 'undefined' === typeof doc.createElement ) {
			return 0;
		}

		var links = doc.querySelectorAll( '[data-wavira-lightbox]' );

		if ( ! links.length ) {
			return 0;
		}

		var probe = doc.createElement( 'dialog' );

		if ( 'function' !== typeof probe.showModal ) {
			return 0;
		}

		var dialog = doc.createElement( 'dialog' );
		var image = doc.createElement( 'img' );
		var close = doc.createElement( 'button' );
		var counter = 0;

		dialog.className = 'wavira-lightbox';
		dialog.setAttribute( 'aria-label', BASE_STRINGS.lightbox || 'Photo' );
		close.type = 'button';
		close.className = 'wavira-lightbox__close';
		close.textContent = '×';
		close.setAttribute( 'aria-label', BASE_STRINGS.close || 'Close' );
		image.className = 'wavira-lightbox__image';
		image.alt = '';
		dialog.appendChild( image );
		dialog.appendChild( close );
		doc.body.appendChild( dialog );

		dialog.addEventListener( 'close', function () {
			image.removeAttribute( 'src' );
		} );

		dialog.addEventListener( 'click', function ( event ) {
			// A click on the backdrop is a click outside the picture.
			if ( event.target === dialog ) {
				dialog.close();
			}
		} );

		close.addEventListener( 'click', function () {
			dialog.close();
		} );

		Array.prototype.forEach.call( links, function ( link ) {
			link.addEventListener( 'click', function ( event ) {
				// A modified click (new tab, download, middle button) belongs to
				// the browser; only a plain left click opens the dialog.
				if ( event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || 0 !== event.button ) {
					return;
				}

				event.preventDefault();
				image.src = link.getAttribute( 'href' );
				image.alt = link.getAttribute( 'data-caption' ) || '';
				dialog.showModal();
				counter++;
			} );
		} );

		return counter > 0 ? 1 : 0;
	}

	/**
	 * Card play buttons: a link that plays in place when it can.
	 *
	 * The markup is an ordinary link to the track, so a browser with no script —
	 * or a site with the plugin switched off — opens the page, which is what a
	 * link has always done. When the engine is on the page the click is taken
	 * over, the track starts in the visitor's player, and the card says so.
	 *
	 * @param {Object} doc Document.
	 * @return {Object} Controller with `destroy()`.
	 */
	function initCardPlayback( doc ) {
		var CARD = '[data-wavira-card]';
		var TRIGGER = '[data-wavira-play], [data-wavira-play-context]';
		var bridge = [];

		/**
		 * @param {*} value Post ID.
		 * @return {number} Positive ID or zero.
		 */
		function postId( value ) {
			var id = parseInt( value, 10 );

			return id > 0 ? id : 0;
		}

		/**
		 * Find the track, album or artist card that owns the playing track.
		 *
		 * @param {Object} detail Player event detail.
		 * @return {Element|null} Matching card.
		 */
		function cardFor( detail ) {
			var track = detail && detail.track;

			if ( ! track || ! doc || 'function' !== typeof doc.querySelectorAll ) {
				return null;
			}

			var trackId = postId( track.id );
			var albumId = postId( track.album && track.album.id );
			var artistId = postId( track.artist && track.artist.id );
			var cards = doc.querySelectorAll( CARD );

			for ( var index = 0; index < cards.length; index++ ) {
				var card = cards[ index ];
				var id = postId( card.getAttribute( 'data-wavira-post' ) );
				var kind = card.getAttribute( 'data-wavira-kind' );

				if ( ( 'wavira_track' === kind && trackId === id ) || ( 'wavira_album' === kind && albumId === id ) || ( 'wavira_artist' === kind && artistId === id ) ) {
					return card;
				}
			}

			return null;
		}

		/**
		 * Clear the visual state on every card before marking the current one.
		 *
		 * @return {void}
		 */
		function clear() {
			if ( ! doc || 'function' !== typeof doc.querySelectorAll ) {
				return;
			}

			Array.prototype.forEach.call( doc.querySelectorAll( CARD ), function ( node ) {
				node.classList.remove( 'is-current' );
				node.classList.remove( 'is-playing' );
				node.classList.remove( 'is-loading' );
			} );
		}

		/**
		 * Mark the player event's track as selected (and, if appropriate, playing).
		 *
		 * @param {Object}  detail  Player event detail.
		 * @param {boolean} playing Whether the player is now playing.
		 * @return {void}
		 */
		function syncTrack( detail, playing ) {
			clear();

			var card = cardFor( detail );

			if ( ! card ) {
				return;
			}

			card.classList.add( 'is-current' );

			if ( playing ) {
				card.classList.add( 'is-playing' );
			}
		}

		/**
		 * @param {Event} event Click event.
		 * @return {void}
		 */
		function onClick( event ) {
			if ( event.defaultPrevented || event.button || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey ) {
				return;
			}

			var trigger = event.target && event.target.closest ? event.target.closest( TRIGGER ) : null;

			if ( ! trigger ) {
				return;
			}

			var api = global.Wavira && global.Wavira.player;

			if ( ! api ) {
				return;
			}

			var card = trigger.closest ? trigger.closest( CARD ) : null;
			var href = trigger.getAttribute( 'href' ) || '';

			// The same card toggles pause/play; another card selects and starts its
			// item. With no engine, every control remains a normal link.
			if ( card && card.classList.contains( 'is-playing' ) && 'function' === typeof api.toggle ) {
				event.preventDefault();
				api.toggle();
				card.classList.remove( 'is-playing' );

				return;
			}

			var context = trigger.getAttribute( 'data-wavira-play-context' );
			var id = postId( trigger.getAttribute( 'data-wavira-play' ) || trigger.getAttribute( 'data-wavira-play-id' ) );
			var started;

			if ( context ) {
				if ( 'function' !== typeof api.playContext ) {
					return;
				}

				started = api.playContext( context, id );
			} else {
				if ( 'function' !== typeof api.play ) {
					return;
				}

				started = api.play( id, trigger.getAttribute( 'data-wavira-context' ) || 'tracks' );
			}

			// No player, invalid ID or an older script: leave the anchor alone.
			if ( ! started || 'function' !== typeof started.then ) {
				return;
			}

			event.preventDefault();

			if ( card ) {
				clear();
				card.classList.add( 'is-loading' );
			}

			started.then( function ( ok ) {
				if ( card ) {
					card.classList.remove( 'is-loading' );
				}

				if ( ! ok && href && global.location && 'function' === typeof global.location.assign ) {
					// Playback could not start: keep the user's click useful by taking
					// them to the same track/album page that the link promises.
					global.location.assign( href );
				}
			} ).catch( function () {
				if ( card ) {
					card.classList.remove( 'is-loading' );
				}

				if ( href && global.location && 'function' === typeof global.location.assign ) {
					global.location.assign( href );
				}
			} );
		}

		if ( ! doc || 'function' !== typeof doc.addEventListener ) {
			return {
				destroy: function () {}
			};
		}

		doc.addEventListener( 'click', onClick );

		// Every player mount bridges its state as DOM events. Listen to the
		// selection, play and pause events so the card follows controls in the
		// sticky bar as well as the overlay button on the card itself.
		if ( 'function' === typeof doc.querySelectorAll ) {
			Array.prototype.forEach.call( doc.querySelectorAll( '[data-wavira-player]' ), function ( mount ) {
				var onTrack = function ( event ) {
					syncTrack( event.detail, false );
				};
				var onPlay = function ( event ) {
					syncTrack( event.detail, true );
				};
				var onPause = function ( event ) {
					var card = cardFor( event.detail );

					if ( card ) {
						card.classList.remove( 'is-playing' );
					}
				};

				mount.addEventListener( 'wavira:player:trackchange', onTrack );
				mount.addEventListener( 'wavira:player:play', onPlay );
				mount.addEventListener( 'wavira:player:pause', onPause );
				bridge.push( function () {
					mount.removeEventListener( 'wavira:player:trackchange', onTrack );
					mount.removeEventListener( 'wavira:player:play', onPlay );
					mount.removeEventListener( 'wavira:player:pause', onPause );
				} );
			} );
		}

		return {
			destroy: function () {
			doc.removeEventListener( 'click', onClick );

			bridge.forEach( function ( off ) {
				off();
			} );

			bridge.length = 0;
		}
		};
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

		initCardPlayback( doc );
		initBackToTop( doc );
		initLightbox( doc );

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
		initCardPlayback: initCardPlayback,
		initBackToTop: initBackToTop,
		initLightbox: initLightbox,
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
