/*!
 * Wavira — player engine.
 *
 * One engine, many views (ADR 0005):
 * - the state machine and the queue logic never touch the DOM, so they are unit
 *   tested without a browser (`tests/js/player.test.mjs`);
 * - every instance creates its own `<audio>` element — there is no global
 *   element ID and several players can live on one page;
 * - the engine never builds a URL: PHP hands it route templates and settings
 *   (`window.waviraPlayerSettings`);
 * - payload strings are treated as data, never as markup, and URLs are filtered
 *   before they reach an attribute.
 *
 * Loaded as a classic script by Wavira Core; it exposes exactly one global
 * (`window.Wavira.player`) as documented in ADR 0006.
 */
( function ( global ) {
	'use strict';

	var doc = global.document;
	var STORAGE_PREFIX_FALLBACK = 'wavira.player.';
	var MOUNT_SELECTOR = '[data-wavira-player]';
	// Bridge of engine events to DOM events on the mount point (ADR 0005 §3).
	// `timeupdate` is deliberately absent: it fires several times per second and
	// theme code can read it from the progress control or the engine emitter.
	var DOM_EVENTS = [
		'trackchange',
		'loading',
		'ready',
		'buffering',
		'play',
		'pause',
		'queuechange',
		'queueend',
		'volumechange',
		'repeatchange',
		'shufflechange',
		'error'
	];
	var REPEAT_MODES = [ 'off', 'all', 'one' ];
	// Monotonic counter: `aria-controls` needs an ID that is unique per instance,
	// and a fixed ID is exactly what ADR 0005 §1 forbids.
	var VIEW_SEQ = 0;

	/** Strings used when PHP provided none (English product copy, no brand strings). */
	var BASE_STRINGS = {
		player: 'Music player',
		play: 'Play',
		pause: 'Pause',
		next: 'Next track',
		previous: 'Previous track',
		seek: 'Seek',
		volume: 'Volume',
		mute: 'Mute',
		unmute: 'Unmute',
		shuffle: 'Shuffle',
		repeat: 'Repeat mode',
		repeatOff: 'Repeat off',
		repeatAll: 'Repeat all',
		repeatOne: 'Repeat one',
		queue: 'Play queue',
		remove: 'Remove from the queue: %s',
		loading: 'Loading track…',
		buffering: 'Buffering…',
		error: 'This track could not be played.',
		empty: 'There is nothing to play here.',
		nowPlaying: 'Now playing: %s',
		ofTotal: 'Track %1$d of %2$d',
		openTrack: 'Open the track page',
		removedTrack: 'Removed from the queue: %s',
		blocked: 'Playback needs a tap on the play button first.'
	};

	/* ---------------------------------------------------------------------- *
	 * Pure helpers — no DOM, no globals: the unit-tested core.
	 * ---------------------------------------------------------------------- */

	/**
	 * Coerce a value to a finite number.
	 *
	 * @param {*}      value    Input value.
	 * @param {number} fallback Value used when the input is unusable.
	 * @return {number} A finite number.
	 */
	function toNumber( value, fallback ) {
		var number = parseFloat( value );

		return isFinite( number ) ? number : fallback;
	}

	/**
	 * Clamp a number into a range.
	 *
	 * @param {*}      value Input value.
	 * @param {number} min   Lower bound.
	 * @param {number} max   Upper bound.
	 * @return {number} Clamped value.
	 */
	function clamp( value, min, max ) {
		var number = toNumber( value, min );

		if ( number < min ) {
			return min;
		}

		return number > max ? max : number;
	}

	/**
	 * Format seconds as `m:ss` (or `h:mm:ss` for long items).
	 *
	 * @param {number} seconds Position in seconds.
	 * @return {string} Human-readable time, `--:--` when unknown.
	 */
	function formatTime( seconds ) {
		var total = Math.floor( toNumber( seconds, -1 ) );

		if ( total < 0 ) {
			return '--:--';
		}

		var hours = Math.floor( total / 3600 );
		var minutes = Math.floor( ( total % 3600 ) / 60 );
		var secs = total % 60;
		var pad = function ( part ) {
			return part < 10 ? '0' + part : String( part );
		};

		return hours > 0 ? hours + ':' + pad( minutes ) + ':' + pad( secs ) : minutes + ':' + pad( secs );
	}

	/**
	 * Freeze a shuffled play order.
	 *
	 * The current index is kept first so enabling shuffle never jumps away from
	 * the track that is playing.
	 *
	 * @param {number}   length Number of queue items.
	 * @param {number}   start  Index that must come first.
	 * @param {Function} random Random source returning [0, 1).
	 * @return {number[]} Shuffled indexes.
	 */
	function shuffleOrder( length, start, random ) {
		var order = [];
		var i;

		for ( i = 0; i < length; i++ ) {
			order.push( i );
		}

		for ( i = order.length - 1; i > 0; i-- ) {
			var j = Math.floor( clamp( random(), 0, 0.999999 ) * ( i + 1 ) );
			var swap = order[ i ];

			order[ i ] = order[ j ];
			order[ j ] = swap;
		}

		if ( start > 0 && start < order.length && order[ 0 ] !== start ) {
			var position = order.indexOf( start );

			order.splice( position, 1 );
			order.unshift( start );
		}

		return order;
	}

	/**
	 * Decide what plays next.
	 *
	 * @param {Object} state `{ length, index, repeat, order }`.
	 * @param {number} step  +1 for next, -1 for previous.
	 * @return {Object} `{ index, ended }` — `ended` true when the queue is over.
	 */
	function advance( state, step ) {
		var length = Math.max( 0, Math.floor( toNumber( state.length, 0 ) ) );
		var backwards = step < 0;

		if ( length < 1 ) {
			return { index: -1, ended: true };
		}

		var order = Array.isArray( state.order ) && state.order.length === length ? state.order : null;
		var position = toNumber( state.index, -1 );

		if ( position < 0 ) {
			return { index: order ? order[ backwards ? length - 1 : 0 ] : backwards ? length - 1 : 0, ended: false };
		}

		if ( order ) {
			position = order.indexOf( position );

			if ( position < 0 ) {
				position = 0;
			}
		}

		var target = position + ( backwards ? -1 : 1 );

		if ( target >= length || target < 0 ) {
			if ( 'all' !== state.repeat ) {
				return { index: -1, ended: true };
			}

			target = target < 0 ? length - 1 : 0;
		}

		return { index: order ? order[ target ] : target, ended: false };
	}

	/**
	 * Whether a URL may be used as a media or link target.
	 *
	 * The payload comes from this site's own REST API, but a template override or
	 * a compromised editor field must not be able to inject `javascript:` or
	 * `data:` into an element attribute.
	 *
	 * @param {string} url Candidate URL.
	 * @return {boolean} True when the URL is safe to apply.
	 */
	function isSafeUrl( url ) {
		if ( 'string' !== typeof url ) {
			return false;
		}

		var value = url.trim();

		if ( '' === value || /^\s*(javascript|data|vbscript):/i.test( value ) ) {
			return false;
		}

		return (
			/^(https?:)?\/\//i.test( value ) ||
			/^[/.]/.test( value ) ||
			/^[a-z0-9._~%/-]+$/i.test( value )
		);
	}

	/**
	 * Best available source URL of a playback payload.
	 *
	 * @param {Object} payload Playback payload.
	 * @return {string} Source URL or an empty string.
	 */
	function preferredSource( payload ) {
		var sources = payload && payload.sources ? payload.sources : {};
		var order = [];
		var preferred = payload && payload.preferred ? String( payload.preferred ) : '';

		if ( preferred && sources[ preferred ] ) {
			order.push( preferred );
		}

		order = order.concat( [ '320', '128', 'external' ] );

		for ( var i = 0; i < order.length; i++ ) {
			var candidate = sources[ order[ i ] ];

			if ( candidate && isSafeUrl( candidate ) ) {
				return candidate;
			}
		}

		return '';
	}

	/**
	 * Merge the server settings with built-in fallbacks.
	 *
	 * The engine must behave sensibly when it is loaded without PHP settings (a
	 * test, a static export), so nothing here is required.
	 *
	 * @param {Object} provided Settings injected by PHP.
	 * @return {Object} Normalised settings.
	 */
	function normaliseSettings( provided ) {
		var source = provided && 'object' === typeof provided ? provided : {};
		var defaults = source.defaults && 'object' === typeof source.defaults ? source.defaults : {};
		var storage = source.storage && 'object' === typeof source.storage ? source.storage : {};
		var routes = source.routes && 'object' === typeof source.routes ? source.routes : {};
		var repeat = REPEAT_MODES.indexOf( defaults.repeat ) > -1 ? defaults.repeat : 'off';

		return {
			version: source.version || '0.5.0',
			routes: {
				track: routes.track || '',
				queue: routes.queue || ''
			},
			storage: {
				prefix: storage.prefix || STORAGE_PREFIX_FALLBACK
			},
			defaults: {
				volume: clamp( defaults.volume, 0, 1 ),
				repeat: repeat,
				shuffle: !! defaults.shuffle,
				seekStep: Math.max( 1, toNumber( defaults.seekStep, 5 ) ),
				volumeStep: clamp( defaults.volumeStep, 0.01, 1 ),
				context: defaults.context || 'tracks',
				limit: Math.max( 0, Math.floor( toNumber( defaults.limit, 0 ) ) ),
				advance: false !== defaults.advance,
				autoplayOnLoad: !! defaults.autoplayOnLoad,
				sticky: !! defaults.sticky
			},
			strings: Object.assign( {}, BASE_STRINGS, source.strings || {} )
		};
	}

	/**
	 * Emitter — a few lines instead of an event library.
	 *
	 * @return {Object} `on`, `off`, `emit`.
	 */
	function createEmitter() {
		var listeners = {};

		return {
			/**
			 * Subscribe to an event.
			 *
			 * @param {string}   name     Event name.
			 * @param {Function} callback Handler.
			 * @return {Function} Unsubscribe function.
			 */
			on: function ( name, callback ) {
				if ( 'function' !== typeof callback ) {
					return function () {};
				}

				( listeners[ name ] = listeners[ name ] || [] ).push( callback );

				return function () {
					listeners[ name ] = ( listeners[ name ] || [] ).filter( function ( candidate ) {
						return candidate !== callback;
					} );
				};
			},

			/**
			 * Unsubscribe.
			 *
			 * @param {string}   name     Event name.
			 * @param {Function} callback Handler.
			 * @return {void}
			 */
			off: function ( name, callback ) {
				listeners[ name ] = ( listeners[ name ] || [] ).filter( function ( candidate ) {
					return candidate !== callback;
				} );
			},

			/**
			 * Notify subscribers.
			 *
			 * @param {string} name    Event name.
			 * @param {Object} payload Event data.
			 * @return {void}
			 */
			emit: function ( name, payload ) {
				( listeners[ name ] || [] ).slice().forEach( function ( callback ) {
					callback( payload );
				} );

				( listeners['*'] || [] ).slice().forEach( function ( callback ) {
					callback( name, payload );
				} );
			}
		};
	}

	/**
	 * Observable player state (ADR 0005 §2).
	 *
	 * @param {Object} initial Initial field values.
	 * @param {Object} emitter Emitter used for `change` events.
	 * @return {Object} Store API.
	 */
	function createStore( initial, emitter ) {
		var state = Object.assign(
			{
				currentTrack: null,
				queue: [],
				currentIndex: -1,
				isPlaying: false,
				isLoading: false,
				isBuffering: false,
				duration: 0,
				currentTime: 0,
				volume: 1,
				muted: false,
				repeatMode: 'off',
				shuffleMode: false,
				error: null,
				order: null
			},
			initial || {}
		);

		return {
			/**
			 * Read the whole state (a copy, so callers cannot mutate it).
			 *
			 * @return {Object} Current state.
			 */
			get: function () {
				return Object.assign( {}, state );
			},

			/**
			 * Read one field.
			 *
			 * @param {string} key Field name.
			 * @return {*} Field value.
			 */
			field: function ( key ) {
				return state[ key ];
			},

			/**
			 * Write fields and emit `change` for each modified field.
			 *
			 * @param {Object} patch Field updates.
			 * @return {string[]} Names of the fields that changed.
			 */
			set: function ( patch ) {
				var changed = [];
				var snapshot;

				Object.keys( patch ).forEach( function ( key ) {
					if ( state[ key ] !== patch[ key ] ) {
						state[ key ] = patch[ key ];
						changed.push( key );
					}
				} );

				if ( changed.length && emitter ) {
					snapshot = Object.assign( {}, state );

					changed.forEach( function ( key ) {
						emitter.emit( 'change', { key: key, value: snapshot[ key ], state: snapshot } );
						// Namespaced on purpose: a field named `error` or `play`
						// must not impersonate an engine event.
						emitter.emit( 'state:' + key, snapshot );
					} );
				}

				return changed;
			}
		};
	}

	/**
	 * Storage facade that never throws (private mode, quota, disabled storage).
	 *
	 * @param {Object} backend Storage-like object; defaults to localStorage.
	 * @param {string} prefix  Key prefix.
	 * @return {Object} `get`, `set`.
	 */
	function createStorage( backend, prefix ) {
		var resolve = function () {
			if ( backend ) {
				return backend;
			}

			try {
				return global.localStorage || null;
			} catch ( error ) {
				return null;
			}
		};

		return {
			/**
			 * Read a persisted value.
			 *
			 * @param {string} key      Key without prefix.
			 * @param {*}      fallback Value when nothing was stored.
			 * @return {*} Stored value or the fallback.
			 */
			get: function ( key, fallback ) {
				var store = resolve();

				if ( ! store ) {
					return fallback;
				}

				try {
					var raw = store.getItem( prefix + key );

					return null === raw || 'undefined' === typeof raw ? fallback : JSON.parse( raw );
				} catch ( error ) {
					return fallback;
				}
			},

			/**
			 * Persist a value.
			 *
			 * @param {string} key   Key without prefix.
			 * @param {*}      value Value to store (JSON-serialisable).
			 * @return {void}
			 */
			set: function ( key, value ) {
				var store = resolve();

				if ( ! store ) {
					return;
				}

				try {
					store.setItem( prefix + key, JSON.stringify( value ) );
				} catch ( error ) {
					// A full or disabled storage must never break playback.
				}
			}
		};
	}

	/**
	 * Default fetcher: same-origin REST requests returning JSON.
	 *
	 * @param {string} url     Absolute URL built by PHP.
	 * @param {Object} options Extra fetch options.
	 * @return {Promise<Object>} Parsed JSON.
	 */
	function defaultFetcher( url, options ) {
		var settings = Object.assign( { credentials: 'same-origin', headers: { Accept: 'application/json' } }, options || {} );

		return fetch( url, settings ).then( function ( response ) {
			if ( ! response.ok ) {
				var error = new Error( 'HTTP ' + response.status );

				error.status = response.status;

				throw error;
			}

			return response.json();
		} );
	}

	/**
	 * Build a URL from a server-provided template.
	 *
	 * @param {Object} settings Normalised settings.
	 * @param {string} name     Route name (track, queue).
	 * @param {number} id       Item ID substituted into `%d`.
	 * @param {Object} query    Query arguments for the queue route.
	 * @return {string} URL, or an empty string when the route is unknown.
	 */
	function routeUrl( settings, name, id, query ) {
		var template = settings && settings.routes ? settings.routes[ name ] : '';

		if ( ! template ) {
			return '';
		}

		var url = template;

		if ( 'number' === typeof id ) {
			url = url.replace( '%d', String( id ) );
		}

		if ( query ) {
			var parts = [];

			Object.keys( query ).forEach( function ( key ) {
				var value = query[ key ];

				if ( null === value || 'undefined' === typeof value || '' === value ) {
					return;
				}

				parts.push( encodeURIComponent( key ) + '=' + encodeURIComponent( String( value ) ) );
			} );

			if ( parts.length ) {
				url += ( url.indexOf( '?' ) > -1 ? '&' : '?' ) + parts.join( '&' );
			}
		}

		return url;
	}

	/* ---------------------------------------------------------------------- *
	 * Engine — playback, queue maths and state transitions.
	 * ---------------------------------------------------------------------- */

	/**
	 * The player engine.
	 *
	 * @param {Object} options `{ settings, fetcher, storage, emitter, random }`.
	 * @return {Object} Engine API.
	 */
	function createEngine( options ) {
		var settings = options.settings;
		var fetcher = options.fetcher || defaultFetcher;
		var storage = options.storage || createStorage( null, settings.storage.prefix );
		var emitter = options.emitter || createEmitter();
		var random = options.random || Math.random;
		var audio = null;
		var requestToken = 0;

		var store = createStore(
			{
				volume: clamp( storage.get( 'volume', settings.defaults.volume ), 0, 1 ),
				muted: !! storage.get( 'muted', false ),
				repeatMode: REPEAT_MODES.indexOf( storage.get( 'repeat', settings.defaults.repeat ) ) > -1
					? storage.get( 'repeat', settings.defaults.repeat )
					: settings.defaults.repeat,
				shuffleMode: !! storage.get( 'shuffle', settings.defaults.shuffle )
			},
			emitter
		);

		/**
		 * Record a failure and tell every view about it.
		 *
		 * @param {Error} error Failure.
		 * @return {Object} The message that was published.
		 */
		function fail( error ) {
			var message = settings.strings.error;

			store.set( { error: message, isPlaying: false, isBuffering: false, isLoading: false } );
			emitter.emit( 'error', { error: error || null, message: message } );

			return { message: message };
		}

		/**
		 * Apply the preferred source of a payload to the audio adapter.
		 *
		 * @param {Object} payload Playback payload.
		 * @return {void}
		 */
		function applySource( payload ) {
			var source = preferredSource( payload );

			if ( ! source ) {
				fail( new Error( 'Track has no playable source' ) );

				return;
			}

			store.set( { duration: toNumber( payload.duration, 0 ), currentTime: 0, error: null } );

			if ( audio ) {
				audio.load( source );
			}
		}

		/**
		 * Replace the queue and optionally select one item.
		 *
		 * A negative index keeps whatever is currently loaded (a queue can exist
		 * without a selection); it never starts playback by itself.
		 *
		 * @param {Object[]} items      Playback payloads.
		 * @param {number}   startIndex Index to select (-1 = none).
		 * @return {void}
		 */
		function setQueue( items, startIndex ) {
			var queue = Array.isArray( items ) ? items.filter( Boolean ) : [];
			var index = 'number' === typeof startIndex ? startIndex : -1;
			var patch = {
				queue: queue,
				currentIndex: index >= 0 && index < queue.length ? index : -1,
				order: store.field( 'shuffleMode' ) ? shuffleOrder( queue.length, index, random ) : null
			};

			var current = store.field( 'currentTrack' );

			if ( patch.currentIndex > -1 ) {
				patch.currentTrack = queue[ patch.currentIndex ];
			}

			var sameTrack = !!( current && patch.currentTrack && current.id === patch.currentTrack.id );

			store.set( patch );
			emitter.emit( 'queuechange', { queue: queue, index: patch.currentIndex } );

			if ( patch.currentIndex > -1 && ! sameTrack ) {
				emitter.emit( 'trackchange', { track: patch.currentTrack } );
				applySource( patch.currentTrack );
			}
		}

		/**
		 * Load a single track by ID (REST).
		 *
		 * @param {number}  trackId  Track post ID.
		 * @param {boolean} autoPlay Start playback when the payload arrives.
		 * @return {Promise<Object>} The loaded track payload.
		 */
		function loadTrack( trackId, autoPlay ) {
			var url = routeUrl( settings, 'track', trackId );

			if ( ! url ) {
				fail( new Error( 'No track route configured' ) );

				return Promise.reject( new Error( 'No track route configured' ) );
			}

			var token = ++requestToken;

			store.set( { isLoading: true, error: null } );
			emitter.emit( 'loading', { id: trackId } );

			return fetcher( url )
				.then( function ( payload ) {
					if ( token !== requestToken ) {
						return payload;
					}

					store.set( { isLoading: false, currentTrack: payload } );
					emitter.emit( 'trackchange', { track: payload } );
					applySource( payload );

					if ( autoPlay ) {
						play();
					}

					return payload;
				} )
				.catch( function ( error ) {
					if ( token === requestToken ) {
						fail( error );
					}

					throw error;
				} );
		}

		/**
		 * Load the queue of a context from the REST API.
		 *
		 * @param {string} context    Queue context.
		 * @param {Object} args       `{ id, slug, limit, orderby, order }`.
		 * @param {number} startIndex Optional index to select afterwards.
		 * @return {Promise<Object[]>} Queue items.
		 */
		function loadQueue( context, args, startIndex ) {
			var query = Object.assign( { context: context || settings.defaults.context }, args || {} );
			var url = routeUrl( settings, 'queue', null, query );

			if ( ! url ) {
				fail( new Error( 'No queue route configured' ) );

				return Promise.reject( new Error( 'No queue route configured' ) );
			}

			store.set( { isLoading: true, error: null } );

			return fetcher( url )
				.then( function ( payload ) {
					var items = payload && Array.isArray( payload.items ) ? payload.items : [];

					setQueue( items, 'number' === typeof startIndex ? startIndex : -1 );
					store.set( { isLoading: false } );

					return items;
				} )
				.catch( function ( error ) {
					fail( error );

					throw error;
				} );
		}

		/**
		 * Play the current item, starting the queue when nothing is selected.
		 *
		 * @return {Promise<void>} Resolves when playback started or was blocked.
		 */
		function play() {
			if ( ! store.field( 'currentTrack' ) ) {
				if ( store.field( 'queue' ).length ) {
					return setCurrent( 0 );
				}

				return Promise.resolve();
			}

			store.set( { error: null } );

			if ( ! audio ) {
				store.set( { isPlaying: true } );
				emitter.emit( 'play', { track: store.field( 'currentTrack' ) } );

				return Promise.resolve();
			}

			var result = audio.play();

			if ( result && 'function' === typeof result.then ) {
				return result.catch( function () {
					store.set( { isPlaying: false } );
					emitter.emit( 'blocked', { track: store.field( 'currentTrack' ), message: settings.strings.blocked } );
				} );
			}

			return Promise.resolve();
		}

		/**
		 * Pause playback.
		 *
		 * @return {void}
		 */
		function pause() {
			if ( audio ) {
				audio.pause();
			}

			store.set( { isPlaying: false } );
			emitter.emit( 'pause', { track: store.field( 'currentTrack' ) } );
		}

		/**
		 * Toggle play/pause.
		 *
		 * @return {Promise<void>} Playback promise when starting.
		 */
		function toggle() {
			if ( store.field( 'isPlaying' ) ) {
				pause();

				return Promise.resolve();
			}

			return play();
		}

		/**
		 * Play one queue index.
		 *
		 * @param {number} index Queue index.
		 * @param {boolean} autoPlay Start playback (default true).
		 * @return {Promise<void>} Playback promise.
		 */
		function setCurrent( index, autoPlay ) {
			var queue = store.field( 'queue' );

			if ( index < 0 || index >= queue.length ) {
				return Promise.resolve();
			}

			store.set( { currentIndex: index, currentTrack: queue[ index ], error: null } );
			emitter.emit( 'trackchange', { track: queue[ index ] } );
			applySource( queue[ index ] );

			return false === autoPlay ? Promise.resolve() : play();
		}

		/**
		 * Play the next or previous item, honouring repeat and shuffle.
		 *
		 * @param {number} step +1 or -1.
		 * @return {Promise<void>} Playback promise.
		 */
		function step( step ) {
			var queue = store.field( 'queue' );
			var result = advance(
				{
					length: queue.length,
					index: store.field( 'currentIndex' ),
					repeat: store.field( 'repeatMode' ),
					order: store.field( 'order' )
				},
				step
			);

			if ( result.ended ) {
				if ( audio ) {
					audio.pause();
				}

				store.set( { isPlaying: false } );
				emitter.emit( 'queueend', { queue: queue } );

				return Promise.resolve();
			}

			return setCurrent( result.index );
		}

		/**
		 * Seek to an absolute position.
		 *
		 * @param {number} time Seconds.
		 * @return {void}
		 */
		function seek( time ) {
			var duration = toNumber( store.field( 'duration' ), 0 );
			var target = clamp( time, 0, duration > 0 ? duration : Math.max( 0, toNumber( time, 0 ) ) );

			if ( audio ) {
				audio.seek( target );
			}

			store.set( { currentTime: target } );
			emitter.emit( 'timeupdate', { currentTime: target, duration: duration } );
		}

		/**
		 * Set the volume and persist it.
		 *
		 * @param {number} value 0–1.
		 * @return {void}
		 */
		function setVolume( value ) {
			var volume = clamp( value, 0, 1 );

			if ( audio ) {
				audio.setVolume( volume );
				audio.setMuted( store.field( 'muted' ) );
			}

			store.set( { volume: volume } );
			storage.set( 'volume', volume );
			emitter.emit( 'volumechange', { volume: volume, muted: store.field( 'muted' ) } );
		}

		/**
		 * Mute or unmute, persisting the choice.
		 *
		 * @param {boolean} value Muted state.
		 * @return {void}
		 */
		function setMuted( value ) {
			var muted = !! value;

			if ( audio ) {
				audio.setMuted( muted );
			}

			store.set( { muted: muted } );
			storage.set( 'muted', muted );
			emitter.emit( 'volumechange', { volume: store.field( 'volume' ), muted: muted } );
		}

		/**
		 * Set the repeat mode.
		 *
		 * @param {string} mode off, all or one.
		 * @return {string} The active mode.
		 */
		function setRepeat( mode ) {
			if ( REPEAT_MODES.indexOf( mode ) < 0 ) {
				return store.field( 'repeatMode' );
			}

			store.set( { repeatMode: mode } );
			storage.set( 'repeat', mode );
			emitter.emit( 'repeatchange', { mode: mode } );

			return mode;
		}

		/**
		 * Cycle off → all → one → off.
		 *
		 * @return {string} The new mode.
		 */
		function cycleRepeat() {
			return setRepeat( REPEAT_MODES[ ( REPEAT_MODES.indexOf( store.field( 'repeatMode' ) ) + 1 ) % REPEAT_MODES.length ] );
		}

		/**
		 * Turn shuffle on or off, rebuilding the frozen order.
		 *
		 * @param {boolean} value Shuffle state.
		 * @return {void}
		 */
		function setShuffle( value ) {
			var shuffle = !! value;
			var queue = store.field( 'queue' );

			store.set( { shuffleMode: shuffle } );
			store.set( { order: shuffle ? shuffleOrder( queue.length, store.field( 'currentIndex' ), random ) : null } );
			storage.set( 'shuffle', shuffle );
			emitter.emit( 'shufflechange', { shuffle: shuffle } );
		}

		/**
		 * Remove one item from the queue without breaking the current track.
		 *
		 * @param {number} index Queue index.
		 * @return {Object|null} The removed item.
		 */
		function removeAt( index ) {
			var queue = store.field( 'queue' ).slice();

			if ( index < 0 || index >= queue.length ) {
				return null;
			}

			var removed = queue.splice( index, 1 )[ 0 ];
			var current = store.field( 'currentIndex' );
			var nextIndex = current;

			if ( index < current ) {
				nextIndex = current - 1;
			} else if ( index === current ) {
				nextIndex = Math.min( current, queue.length - 1 );
			}

			store.set( {
				queue: queue,
				currentIndex: queue.length ? nextIndex : -1,
				order: store.field( 'shuffleMode' ) && queue.length ? shuffleOrder( queue.length, nextIndex, random ) : null
			} );

			if ( index === current ) {
				if ( queue.length ) {
					var nextTrack = queue[ nextIndex ];

					store.set( { currentTrack: nextTrack } );
					emitter.emit( 'trackchange', { track: nextTrack } );

					if ( store.field( 'isPlaying' ) ) {
						applySource( nextTrack );
					}
				} else {
					store.set( { currentTrack: null, isPlaying: false } );

					if ( audio ) {
						audio.pause();
					}
				}
			}

			emitter.emit( 'queuechange', { queue: queue, index: store.field( 'currentIndex' ) } );
			emitter.emit( 'removed', { track: removed } );

			return removed;
		}

		/**
		 * Empty the queue and stop.
		 *
		 * @return {void}
		 */
		function clearQueue() {
			if ( audio ) {
				audio.pause();
			}

			store.set( { queue: [], currentIndex: -1, currentTrack: null, isPlaying: false, order: null } );
			emitter.emit( 'queuechange', { queue: [], index: -1 } );
		}

		/**
		 * Attach an audio adapter and forward its events into the state.
		 *
		 * @param {Object} adapter Audio adapter (see createAudioAdapter).
		 * @return {void}
		 */
		function attachAudio( adapter ) {
			audio = adapter;

			adapter.on( 'play', function () {
				store.set( { isPlaying: true, isBuffering: false } );
				emitter.emit( 'play', { track: store.field( 'currentTrack' ) } );
			} );
			adapter.on( 'pause', function () {
				store.set( { isPlaying: false } );
				emitter.emit( 'pause', { track: store.field( 'currentTrack' ) } );
			} );
			adapter.on( 'ended', function () {
				if ( 'one' === store.field( 'repeatMode' ) ) {
					seek( 0 );
					play();

					return;
				}

				if ( settings.defaults.advance && store.field( 'queue' ).length > 1 ) {
					step( 1 );

					return;
				}

				store.set( { isPlaying: false } );
				emitter.emit( 'queueend', { queue: store.field( 'queue' ) } );
			} );
			adapter.on( 'timeupdate', function ( position ) {
				store.set( { currentTime: position } );
				emitter.emit( 'timeupdate', { currentTime: position, duration: store.field( 'duration' ) } );
			} );
			adapter.on( 'duration', function ( duration ) {
				if ( duration > 0 ) {
					store.set( { duration: duration } );
				}
			} );
			adapter.on( 'waiting', function () {
				store.set( { isBuffering: true } );
				emitter.emit( 'buffering', {} );
			} );
			adapter.on( 'ready', function () {
				store.set( { isBuffering: false } );
				emitter.emit( 'ready', {} );
			} );
			adapter.on( 'error', function ( error ) {
				fail( error || new Error( 'Media error' ) );
			} );

			adapter.setVolume( store.field( 'volume' ) );
			adapter.setMuted( store.field( 'muted' ) );
		}

		return {
			get: store.get,
			field: store.field,
			subscribe: function ( name, callback ) {
				return emitter.on( name, callback );
			},
			loadTrack: loadTrack,
			loadQueue: loadQueue,
			setQueue: setQueue,
			setCurrent: setCurrent,
			preferredSource: preferredSource,
			play: play,
			pause: pause,
			toggle: toggle,
			next: function () {
				return step( 1 );
			},
			previous: function () {
				return step( -1 );
			},
			seek: seek,
			seekBy: function ( delta ) {
				seek( toNumber( store.field( 'currentTime' ), 0 ) + toNumber( delta, 0 ) );
			},
			setVolume: setVolume,
			setMuted: setMuted,
			toggleMute: function () {
				setMuted( ! store.field( 'muted' ) );
			},
			volumeBy: function ( delta ) {
				setVolume( store.field( 'volume' ) + toNumber( delta, 0 ) );
			},
			setRepeat: setRepeat,
			cycleRepeat: cycleRepeat,
			setShuffle: setShuffle,
			toggleShuffle: function () {
				setShuffle( ! store.field( 'shuffleMode' ) );
			},
			removeAt: removeAt,
			clearQueue: clearQueue,
			attachAudio: attachAudio
		};
	}

	/* ---------------------------------------------------------------------- *
	 * Audio adapter — one <audio> element per player instance.
	 * ---------------------------------------------------------------------- */

	/**
	 * Wrap an `<audio>` element in a small, testable interface.
	 *
	 * @param {HTMLAudioElement} element Audio element.
	 * @return {Object} Adapter API.
	 */
	function createAudioAdapter( element ) {
		var emitter = createEmitter();
		var handlers = {};

		[
			[ 'timeupdate', function () {
				emitter.emit( 'timeupdate', element.currentTime );
			} ],
			[ 'durationchange', function () {
				emitter.emit( 'duration', element.duration );
			} ],
			[ 'loadedmetadata', function () {
				emitter.emit( 'duration', element.duration );
			} ],
			[ 'canplay', function () {
				emitter.emit( 'ready', {});
			} ],
			[ 'waiting', function () {
				emitter.emit( 'waiting', {});
			} ],
			[ 'playing', function () {
				emitter.emit( 'play', {});
			} ],
			[ 'play', function () {
				emitter.emit( 'play', {});
			} ],
			[ 'pause', function () {
				emitter.emit( 'pause', {});
			} ],
			[ 'ended', function () {
				emitter.emit( 'ended', {});
			} ],
			[ 'error', function () {
				var error = new Error( 'Media error ' + ( element.error ? element.error.code : 0 ) );

				error.code = element.error ? element.error.code : 0;
				emitter.emit( 'error', error );
			} ]
		].forEach( function ( entry ) {
			handlers[ entry[ 0 ] ] = entry[ 1 ];
			element.addEventListener( entry[ 0 ], entry[ 1 ] );
		} );

		return {
			element: element,
			on: emitter.on,
			/**
			 * Point the element at a source.
			 *
			 * @param {string} src Media URL.
			 * @return {void}
			 */
			load: function ( src ) {
				element.src = src;

				try {
					element.load();
				} catch ( error ) {
					emitter.emit( 'error', error );
				}
			},
			play: function () {
				return element.play();
			},
			pause: function () {
				element.pause();
			},
			seek: function ( time ) {
				element.currentTime = time;
			},
			setVolume: function ( value ) {
				element.volume = clamp( value, 0, 1 );
			},
			setMuted: function ( value ) {
				element.muted = !! value;
			},
			destroy: function () {
				Object.keys( handlers ).forEach( function ( name ) {
					element.removeEventListener( name, handlers[ name ] );
				} );

				element.removeAttribute( 'src' );
			}
		};
	}

	/* ---------------------------------------------------------------------- *
	 * Media Session, keyboard and view adapters.
	 * ---------------------------------------------------------------------- */

	/**
	 * Mirror the player into the operating system's media controls.
	 *
	 * @param {Object} engine   Player engine.
	 * @param {Object} settings Normalised settings.
	 * @return {Object} `destroy`.
	 */
	function createMediaSessionAdapter( engine, settings ) {
		var session = global.navigator && global.navigator.mediaSession ? global.navigator.mediaSession : null;
		var unsubscribe = [];

		if ( ! session || 'function' !== typeof global.MediaMetadata ) {
			return { destroy: function () {} };
		}

		var handle = function ( action, callback ) {
			try {
				session.setActionHandler( action, callback );
			} catch ( error ) {
				// Unsupported actions throw in some browsers; ignoring is correct.
			}
		};

		unsubscribe.push( engine.subscribe( 'trackchange', function ( state ) {
			var track = state.currentTrack || {};
			var meta = track.media_session || {};

			try {
				session.metadata = new global.MediaMetadata(
					Object.assign( { title: track.title || '', artist: '', album: '', artwork: [] }, meta )
				);
			} catch ( error ) {
				return;
			}

			handle( 'play', function () {
				engine.play();
			} );
			handle( 'pause', function () {
				engine.pause();
			} );
			handle( 'stop', function () {
				engine.pause();
			} );
			handle( 'nexttrack', function () {
				engine.next();
			} );
			handle( 'previoustrack', function () {
				engine.previous();
			} );
			handle( 'seekbackward', function ( details ) {
				engine.seekBy( -( details && details.seekOffset ? details.seekOffset : settings.defaults.seekStep ) );
			} );
			handle( 'seekforward', function ( details ) {
				engine.seekBy( details && details.seekOffset ? details.seekOffset : settings.defaults.seekStep );
			} );
			handle( 'seekto', function ( details ) {
				if ( details && 'number' === typeof details.seekTime ) {
					engine.seek( details.seekTime );
				}
			} );
		} ) );

		unsubscribe.push( engine.subscribe( 'play', function () {
			session.playbackState = 'playing';
		} ) );
		unsubscribe.push( engine.subscribe( 'pause', function () {
			session.playbackState = 'paused';
		} ) );
		unsubscribe.push( engine.subscribe( 'timeupdate', function ( state ) {
			if ( 'function' !== typeof session.setPositionState || ! state.duration ) {
				return;
			}

			try {
				session.setPositionState( {
					duration: state.duration,
					position: clamp( state.currentTime, 0, state.duration ),
					playbackRate: 1
				} );
			} catch ( error ) {
				// A stale duration ratio must never break playback.
			}
		} ) );

		return {
			destroy: function () {
				unsubscribe.forEach( function ( off ) {
					off();
				} );

				[ 'play', 'pause', 'stop', 'nexttrack', 'previoustrack', 'seekbackward', 'seekforward', 'seekto' ].forEach(
					function ( action ) {
						handle( action, null );
					}
				);
			}
		};
	}

	/**
	 * Keyboard shortcuts, scoped to the player region (ADR 0005 §4).
	 *
	 * @param {Object} root     Mount element.
	 * @param {Object} engine   Player engine.
	 * @param {Object} settings Normalised settings.
	 * @return {Object} `destroy`.
	 */
	function createKeyboardAdapter( root, engine, settings ) {
		/**
		 * Whether the event target handles its own keys.
		 *
		 * @param {Element} target Event target.
		 * @return {boolean} True when the player must not intercept the key.
		 */
		function isInteractive( target ) {
			if ( ! target || ! target.tagName ) {
				return false;
			}

			var tag = String( target.tagName ).toLowerCase();

			return (
				'input' === tag ||
				'select' === tag ||
				'textarea' === tag ||
				'button' === tag ||
				'a' === tag ||
				true === target.isContentEditable
			);
		}

		function onKeyDown( event ) {
			if ( event.defaultPrevented || event.metaKey || event.ctrlKey || event.altKey || isInteractive( event.target ) ) {
				return;
			}

			var handled = true;

			switch ( event.key ) {
				case ' ':
				case 'Spacebar':
				case 'k':
					engine.toggle();
					break;
				case 'ArrowLeft':
					engine.seekBy( -settings.defaults.seekStep );
					break;
				case 'ArrowRight':
					engine.seekBy( settings.defaults.seekStep );
					break;
				case 'ArrowUp':
					engine.volumeBy( settings.defaults.volumeStep );
					break;
				case 'ArrowDown':
					engine.volumeBy( -settings.defaults.volumeStep );
					break;
				case 'm':
				case 'M':
					engine.toggleMute();
					break;
				case 'n':
				case 'N':
					engine.next();
					break;
				case 'p':
				case 'P':
					engine.previous();
					break;
				default:
					handled = false;
			}

			if ( handled ) {
				event.preventDefault();
			}
		}

		root.addEventListener( 'keydown', onKeyDown );

		return {
			destroy: function () {
				root.removeEventListener( 'keydown', onKeyDown );
			}
		};
	}

	/* ---------------------------------------------------------------------- *
	 * View — DOM controls for one mount point.
	 * ---------------------------------------------------------------------- */

	/**
	 * Remove every child of a node without touching innerHTML.
	 *
	 * @param {Element} node Parent node.
	 * @return {void}
	 */
	function empty( node ) {
		while ( node.firstChild ) {
			node.removeChild( node.firstChild );
		}
	}

	/**
	 * Build and bind the controls of one player instance.
	 *
	 * Everything is created with `createElement`/`textContent`: payload strings
	 * are data, never markup.
	 *
	 * @param {Element} mount    Mount element (`[data-wavira-player]`).
	 * @param {Object}  engine   Player engine.
	 * @param {Object}  settings Normalised settings.
	 * @return {Object} `destroy`.
	 */
	function createView( mount, engine, settings ) {
		var strings = settings.strings;
		var uid = ++VIEW_SEQ;
		var unsubscribe = [];
		var root = doc.createElement( 'div' );
		var audio = doc.createElement( 'audio' );

		root.className = 'wavira-player__inner';
		audio.className = 'wavira-player__audio';
		audio.setAttribute( 'preload', 'metadata' );
		audio.setAttribute( 'playsinline', '' );

		/**
		 * Create an element with optional class, attributes and text.
		 *
		 * @param {string} tag   Tag name.
		 * @param {string} name  Class name.
		 * @param {Object} attrs Attributes.
		 * @param {string} text  Text content.
		 * @return {Element} The element.
		 */
		function el( tag, name, attrs, text ) {
			var node = doc.createElement( tag );

			if ( name ) {
				node.className = name;
			}

			Object.keys( attrs || {} ).forEach( function ( key ) {
				node.setAttribute( key, String( attrs[ key ] ) );
			} );

			if ( 'string' === typeof text ) {
				node.textContent = text;
			}

			return node;
		}

		/**
		 * Build a button with a visible label and an accessible name.
		 *
		 * The label is real text (not an icon font), so the controls are usable
		 * before the theme's stylesheet exists.
		 *
		 * @param {string} name  Class name.
		 * @param {string} label Label text.
		 * @return {HTMLButtonElement} Button.
		 */
		function button( name, label ) {
			var node = doc.createElement( 'button' );
			var span = el( 'span', 'wavira-player__label', {}, label );

			node.type = 'button';
			node.className = name;
			node.setAttribute( 'aria-label', label );
			node.appendChild( span );

			return node;
		}

		/**
		 * Update a button's visible label and accessible name.
		 *
		 * @param {HTMLButtonElement} node  Button.
		 * @param {string}            label New label.
		 * @return {void}
		 */
		function relabel( node, label ) {
			node.setAttribute( 'aria-label', label );

			var span = node.querySelector( '.wavira-player__label' );

			if ( span ) {
				span.textContent = label;
			}
		}

		var cover = el( 'img', 'wavira-player__cover', { alt: '' } );
		// The title is a link to the track being played — and until there is one,
		// it is not a link: an empty `<a href="#">` has no accessible name, which
		// is exactly what axe's `link-name` rule reports. It arrives hidden and
		// `renderTrack()` reveals it with the track's own title.
		var titleLink = el( 'a', 'wavira-player__title', { hidden: 'hidden' } );
		var artist = el( 'span', 'wavira-player__artist' );
		var meta = el( 'div', 'wavira-player__meta' );
		var status = el( 'p', 'wavira-player__status', { role: 'status', 'aria-live': 'polite' }, '' );
		var alert = el( 'p', 'wavira-player__alert', { role: 'alert' }, '' );
		var time = el( 'span', 'wavira-player__time', {}, '--:--' );
		var durationLabel = el( 'span', 'wavira-player__time-total', {}, '--:--' );

		var previous = button( 'wavira-player__previous', strings.previous );
		var playButton = button( 'wavira-player__play', strings.play );
		var next = button( 'wavira-player__next', strings.next );
		var shuffle = button( 'wavira-player__shuffle', strings.shuffle );
		var repeat = button( 'wavira-player__repeat', strings.repeatOff );
		var mute = button( 'wavira-player__mute', strings.mute );
		var openLink = el( 'a', 'wavira-player__open', { href: '#' }, strings.openTrack );
		var queueList = el( 'ol', 'wavira-player__queue', { 'aria-label': strings.queue } );
		var queueToggle = button( 'wavira-player__queue-toggle', strings.queue );
		var controls = el( 'div', 'wavira-player__controls' );

		queueList.id = 'wavira-player-queue-' + uid;
		queueToggle.setAttribute( 'aria-controls', queueList.id );
		queueToggle.setAttribute( 'aria-expanded', 'false' );

		var progress = doc.createElement( 'input' );
		var volume = doc.createElement( 'input' );

		progress.type = 'range';
		progress.className = 'wavira-player__progress';
		progress.min = '0';
		progress.max = '0';
		progress.step = '1';
		progress.value = '0';
		progress.setAttribute( 'aria-label', strings.seek );

		volume.type = 'range';
		volume.className = 'wavira-player__volume';
		volume.min = '0';
		volume.max = '1';
		volume.step = '0.01';
		volume.value = String( settings.defaults.volume );
		volume.setAttribute( 'aria-label', strings.volume );

		shuffle.setAttribute( 'aria-pressed', 'false' );
		repeat.setAttribute( 'aria-pressed', 'false' );
		titleLink.href = '#';

		meta.appendChild( titleLink );
		meta.appendChild( artist );

		controls.appendChild( previous );
		controls.appendChild( playButton );
		controls.appendChild( next );
		controls.appendChild( shuffle );
		controls.appendChild( repeat );
		controls.appendChild( queueToggle );
		controls.appendChild( mute );

		root.appendChild( audio );
		root.appendChild( cover );
		root.appendChild( meta );
		root.appendChild( controls );
		root.appendChild( progress );
		root.appendChild( time );
		root.appendChild( durationLabel );
		root.appendChild( volume );
		root.appendChild( openLink );
		root.appendChild( status );
		root.appendChild( alert );
		root.appendChild( queueList );

		// Taking over the mount point also removes the no-JavaScript fallback.
		empty( mount );
		mount.appendChild( root );
		mount.setAttribute( 'tabindex', mount.getAttribute( 'tabindex' ) || '0' );
		mount.setAttribute( 'role', 'group' );
		mount.setAttribute( 'aria-label', strings.player );
		mount.setAttribute( 'data-queue-open', 'false' );

		/* ---- rendering ---------------------------------------------------- */

		/**
		 * Render the current track: text, artwork, links.
		 *
		 * @param {Object} track Playback payload.
		 * @return {void}
		 */
		function renderTrack( track ) {
			var data = track || {};
			var coverData = data.cover || {};
			var artistData = data.artist || {};
			var albumData = data.album || {};
			var artistName = artistData.name || albumData.title || '';
			var link = isSafeUrl( data.permalink ) ? data.permalink : '';

			titleLink.textContent = data.title || '';
			titleLink.href = link || '#';
			titleLink.hidden = '' === link && '' === ( data.title || '' );
			artist.textContent = artistName;
			artist.hidden = '' === artistName;

			if ( coverData.url && isSafeUrl( coverData.url ) ) {
				cover.src = coverData.url;
				cover.alt = coverData.alt || data.title || '';
				cover.hidden = false;

				var srcset = String( coverData.srcset || '' )
					.split( ',' )
					.map( function ( part ) {
						return part.trim();
					} )
					.filter( function ( part ) {
						return '' !== part && isSafeUrl( part.split( ' ' )[ 0 ] );
					} )
					.join( ', ' );

				if ( srcset ) {
					cover.setAttribute( 'srcset', srcset );
				} else {
					cover.removeAttribute( 'srcset' );
				}

				if ( coverData.sizes ) {
					cover.setAttribute( 'sizes', String( coverData.sizes ) );
				}
			} else {
				cover.removeAttribute( 'src' );
				cover.removeAttribute( 'srcset' );
				cover.alt = '';
				cover.hidden = true;
			}

			openLink.href = link || '#';
			openLink.hidden = '' === link;
			status.textContent = data.title ? strings.nowPlaying.replace( '%s', data.title ) : '';
			alert.textContent = '';
			alert.hidden = true;
		}

		/**
		 * Render queue items as buttons.
		 *
		 * @param {Object} state Engine state snapshot.
		 * @return {void}
		 */
		function renderQueue( state ) {
			empty( queueList );

			state.queue.forEach( function ( track, index ) {
				var item = el( 'li', 'wavira-player__queue-item' );
				var trigger = button( 'wavira-player__queue-track', track.title || '' );
				var position = strings
					.ofTotal
					.replace( '%1$d', String( index + 1 ) )
					.replace( '%2$d', String( state.queue.length ) );

				trigger.setAttribute( 'aria-label', position + ': ' + ( track.title || '' ) );

				if ( index === state.currentIndex ) {
					trigger.setAttribute( 'aria-current', 'true' );
					item.className += ' is-current';
				}

				trigger.addEventListener( 'click', function () {
					engine.setCurrent( index );
				} );

				var remove = button( 'wavira-player__queue-remove', strings.remove.replace( '%s', track.title || '' ) );

				remove.addEventListener( 'click', function () {
					var removed = engine.removeAt( index );

					if ( removed ) {
						status.textContent = strings.removedTrack.replace( '%s', removed.title || '' );
					}
				} );

				item.appendChild( trigger );
				item.appendChild( remove );
				queueList.appendChild( item );
			} );

			queueList.hidden = 0 === state.queue.length;
			queueToggle.hidden = 0 === state.queue.length;

			// An empty queue closes the panel, so the toggle never shows or hides
			// nothing at all.
			if ( 0 === state.queue.length ) {
				mount.setAttribute( 'data-queue-open', 'false' );
				queueToggle.setAttribute( 'aria-expanded', 'false' );
			}
		}

		/**
		 * Render transport state: labels, sliders, ARIA values.
		 *
		 * @param {Object} state Engine state snapshot.
		 * @return {void}
		 */
		function renderState( state ) {
			var playLabel = state.isPlaying ? strings.pause : strings.play;
			var repeatLabel = 'one' === state.repeatMode ? strings.repeatOne : 'all' === state.repeatMode ? strings.repeatAll : strings.repeatOff;
			var stateName = state.error
				? 'error'
				: state.isLoading
					? 'loading'
					: state.isBuffering
						? 'buffering'
						: state.isPlaying
							? 'playing'
							: state.currentTrack
								? 'paused'
								: 'idle';

			relabel( playButton, playLabel );
			playButton.setAttribute( 'aria-pressed', state.isPlaying ? 'true' : 'false' );
			mount.setAttribute( 'data-state', stateName );

			progress.max = String( Math.max( 0, Math.floor( state.duration || 0 ) ) );
			progress.value = String( Math.min( toNumber( state.currentTime, 0 ), toNumber( progress.max, 0 ) ) );
			progress.setAttribute( 'aria-valuetext', formatTime( state.currentTime ) + ' / ' + formatTime( state.duration ) );
			progress.disabled = ! state.currentTrack;

			time.textContent = formatTime( state.currentTime );
			durationLabel.textContent = formatTime( state.duration );

			volume.value = String( state.volume );
			volume.setAttribute( 'aria-valuetext', Math.round( state.volume * 100 ) + '%' );

			relabel( mute, state.muted ? strings.unmute : strings.mute );
			mute.setAttribute( 'aria-pressed', state.muted ? 'true' : 'false' );

			shuffle.setAttribute( 'aria-pressed', state.shuffleMode ? 'true' : 'false' );
			relabel( repeat, repeatLabel );
			repeat.setAttribute( 'aria-pressed', 'off' === state.repeatMode ? 'false' : 'true' );

			previous.disabled = 0 === state.queue.length;
			next.disabled = 0 === state.queue.length;

			alert.textContent = state.error || '';
			alert.hidden = ! state.error;
		}

		/* ---- interaction -------------------------------------------------- */

		playButton.addEventListener( 'click', function () {
			engine.toggle();
		} );
		previous.addEventListener( 'click', function () {
			engine.previous();
		} );
		next.addEventListener( 'click', function () {
			engine.next();
		} );
		shuffle.addEventListener( 'click', function () {
			engine.toggleShuffle();
		} );
		repeat.addEventListener( 'click', function () {
			engine.cycleRepeat();
		} );
		queueToggle.addEventListener( 'click', function () {
			var open = 'true' !== mount.getAttribute( 'data-queue-open' );

			mount.setAttribute( 'data-queue-open', open ? 'true' : 'false' );
			queueToggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		} );
		mute.addEventListener( 'click', function () {
			engine.toggleMute();
		} );
		progress.addEventListener( 'input', function () {
			engine.seek( toNumber( progress.value, 0 ) );
		} );
		volume.addEventListener( 'input', function () {
			engine.setVolume( toNumber( volume.value, settings.defaults.volume ) );
		} );

		unsubscribe.push( engine.subscribe( 'change', function () {
			renderState( engine.get() );
		} ) );
		unsubscribe.push( engine.subscribe( 'trackchange', function ( event ) {
			renderTrack( event.track );
		} ) );
		unsubscribe.push( engine.subscribe( 'queuechange', function () {
			var state = engine.get();

			renderQueue( state );
			renderState( state );
		} ) );

		renderState( engine.get() );
		renderQueue( engine.get() );

		return {
			audio: audio,
			destroy: function () {
				unsubscribe.forEach( function ( off ) {
					off();
				} );

				empty( mount );
			}
		};
	}

	/* ---------------------------------------------------------------------- *
	 * Public API.
	 * ---------------------------------------------------------------------- */

	/**
	 * Create a player bound to a mount element.
	 *
	 * @param {Element} mount   Mount element.
	 * @param {Object}  options Optional overrides (`settings`, `engine`, `noView`, `noMediaSession`, `noKeyboard`).
	 * @return {Object} Player controller.
	 */
	function createPlayer( mount, options ) {
		options = options || {};

		var settings = options.settings || normaliseSettings( global.waviraPlayerSettings );
		var engine = options.engine || createEngine( { settings: settings } );
		var view = options.noView ? null : createView( mount, engine, settings );
		var adapter = null;
		var mediaSession;
		var keyboard;

		if ( view ) {
			adapter = createAudioAdapter( view.audio );
			engine.attachAudio( adapter );
		} else {
			empty( mount );
		}

		mediaSession = options.noMediaSession ? { destroy: function () {} } : createMediaSessionAdapter( engine, settings );
		keyboard = options.noKeyboard ? { destroy: function () {} } : createKeyboardAdapter( mount, engine, settings );

		// Re-apply persisted preferences so the adapter and the controls agree
		// with the stored state from the first paint.
		engine.setVolume( engine.field( 'volume' ) );
		engine.setMuted( engine.field( 'muted' ) );
		engine.setRepeat( engine.field( 'repeatMode' ) );
		engine.setShuffle( engine.field( 'shuffleMode' ) );

		var bridge = [];

		if ( 'function' === typeof global.CustomEvent && 'function' === typeof mount.dispatchEvent ) {
			DOM_EVENTS.forEach( function ( name ) {
				bridge.push(
					engine.subscribe( name, function ( payload ) {
						mount.dispatchEvent(
							new global.CustomEvent( 'wavira:player:' + name, { detail: payload, bubbles: false } )
						);
					} )
				);
			} );
		}

		var context = mount.getAttribute( 'data-context' ) || settings.defaults.context;
		var id = toNumber( mount.getAttribute( 'data-id' ), 0 );
		var slug = mount.getAttribute( 'data-slug' ) || '';
		var limit = toNumber( mount.getAttribute( 'data-limit' ), 0 );
		var start = toNumber( mount.getAttribute( 'data-track' ), 0 );

		var controller = {
			mount: mount,
			engine: engine,
			/**
			 * Load the mount's context: the requested track, then its queue.
			 *
			 * @return {Promise<Object[]>} Queue items.
			 */
			load: function () {
				var requested = start > 0 ? engine.loadTrack( start, false ) : Promise.resolve( null );

				return engine
					.loadQueue( context, { id: id, slug: slug, limit: limit } )
					.then( function ( items ) {
						return requested.then( function ( track ) {
							if ( track ) {
								var index = -1;

								items.forEach( function ( item, position ) {
									if ( item.id === track.id ) {
										index = position;
									}
								} );

								// Selecting the index keeps queue and player in sync;
								// an item outside the queue keeps playing from the queue's
								// first position on `next`.
								if ( index > -1 ) {
									engine.setQueue( items, index );
								} else {
									var queue = items.filter( function ( item ) {
										return item && item.id !== track.id;
									} );

									queue.push( track );
									engine.setQueue( queue, queue.length - 1 );
								}
							}

							return items;
						} );
					} );
			},
			destroy: function () {
				bridge.forEach( function ( off ) {
					off();
				} );
				keyboard.destroy();
				mediaSession.destroy();

				if ( view ) {
					view.destroy();
				}

				if ( adapter ) {
					adapter.destroy();
				}

				var index = instances.indexOf( controller );

				if ( index > -1 ) {
					instances.splice( index, 1 );
				}
			}
		};

		controller
			.load()
			.then( function () {
				if ( mount.getAttribute( 'data-autoplay' ) || settings.defaults.autoplayOnLoad ) {
					engine.play();
				}
			} )
			.catch( function () {} );

		return controller;
	}

	/** Instances created on this page. */
	var instances = [];

	/**
	 * Initialise every mount point in a scope.
	 *
	 * @param {Element} scope Optional scope element.
	 * @return {Object[]} Created controllers.
	 */
	function init( scope ) {
		if ( ! doc ) {
			return [];
		}

		var root = scope || doc;
		var settings = normaliseSettings( global.waviraPlayerSettings );

		Array.prototype.slice.call( root.querySelectorAll( MOUNT_SELECTOR ) ).forEach( function ( mount ) {
			var alreadyMounted = instances.some( function ( instance ) {
				return instance && instance.mount === mount;
			} );

			if ( alreadyMounted ) {
				return;
			}

			instances.push( createPlayer( mount, { settings: settings } ) );
		} );

		return instances;
	}

	/** Return the sticky player, or the first one. */
	function primary() {
		var first = null;

		for ( var i = 0; i < instances.length; i++ ) {
			var instance = instances[ i ];
			var mount = instance && instance.mount;

			if ( ! first ) {
				first = instance;
			}

			if ( mount && -1 !== ( ' ' + mount.className + ' ' ).indexOf( ' wavira-player--sticky ' ) ) {
				return instance;
			}
		}

		return first;
	}

	/** @param {Object} engine Player engine. @return {Promise<boolean>} */
	function confirmPlaying( engine ) {
		return Promise.resolve( engine.play() ).then( function () {
			return 'function' !== typeof engine.field || !! engine.field( 'isPlaying' );
		} );
	}

	/** Play a track in the primary player. */
	function playTrack( trackId, context ) {
		var instance = primary();
		var id = toNumber( trackId, 0 );

		if ( ! instance || id < 1 ) {
			return Promise.resolve( false );
		}

		var engine = instance.engine;

		return engine
			.loadQueue( context || 'tracks', { id: 0, slug: '', limit: 0 } )
			.then( function ( items ) {
				var index = -1;

				( items || [] ).forEach( function ( item, position ) {
					if ( item && item.id === id ) {
						index = position;
					}
				} );

				if ( index > -1 ) {
					engine.setQueue( items, index );

					return confirmPlaying( engine );
				}

				// Outside the queue — a limit, or another context: keep the queue
				// for «next» and load the track the visitor actually asked for.
				engine.setQueue( items, -1 );

				return engine.loadTrack( id, false ).then(
					function ( track ) {
						items.push( track );
						engine.setQueue( items, items.length - 1 );

						return confirmPlaying( engine );
					},
					function () {
						return false;
					}
				);
			} )
			.catch( function () {
				return false;
			} );
	}

	/** Play an album, artist or genre queue. */
	function playContext( context, id ) {
		var instance = primary();

		if ( ! instance ) {
			return Promise.resolve( false );
		}

		return instance.engine
			.loadQueue( context || 'tracks', { id: toNumber( id, 0 ), slug: '', limit: 0 } )
			.then( function ( items ) {
				if ( ! items || ! items.length ) {
					return false;
				}

				instance.engine.setQueue( items, 0 );

				return confirmPlaying( instance.engine );
			} )
			.catch( function () {
				return false;
			} );
	}

	/**
	 * Toggle the visitor's primary player from a card whose track is current.
	 *
	 * @return {Promise<boolean>} Resolves false when no player is mounted.
	 */
	function togglePrimary() {
		var instance = primary();

		for ( var i = 0; i < instances.length; i++ ) {
			var active = instances[ i ];

			if ( active.engine && 'function' === typeof active.engine.field && active.engine.field( 'isPlaying' ) ) {
				instance = active;
				break;
			}
		}

		return instance ? instance.engine.toggle().then( function () { return true; } ) : Promise.resolve( false );
	}

	global.Wavira = global.Wavira || {};
	global.Wavira.player = {
		version: '0.6.0',
		create: createPlayer,
		init: init,
		instances: instances,
		settings: normaliseSettings,
		primary: primary,
		play: playTrack,
		playContext: playContext,
		toggle: togglePrimary,
		core: {
			createEngine: createEngine,
			createStore: createStore,
			createStorage: createStorage,
			createEmitter: createEmitter,
			normaliseSettings: normaliseSettings,
			preferredSource: preferredSource,
			routeUrl: routeUrl,
			formatTime: formatTime,
			shuffleOrder: shuffleOrder,
			advance: advance,
			isSafeUrl: isSafeUrl,
			clamp: clamp
		}
	};

	if ( doc ) {
		if ( 'loading' === doc.readyState ) {
			doc.addEventListener( 'DOMContentLoaded', function () {
				init();
			} );
		} else {
			init();
		}
	}
} )( 'undefined' !== typeof window ? window : typeof globalThis !== 'undefined' ? globalThis : this );
