/**
 * Player engine unit tests.
 *
 * The engine is loaded into a fresh `node:vm` context with no DOM, which is the
 * whole point of ADR 0005 §2: state, queue maths and persistence must be
 * verifiable without a browser. Run with:
 *
 *   node --test tests/js/
 *
 * @package Wavira
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

/**
 * Copy a value from the vm realm into a host-realm value.
 *
 * `assert.deepEqual` compares prototypes, and objects created inside `node:vm`
 * have that context's prototypes — so every deep comparison round-trips first.
 *
 * @param {*} value Value from the sandbox.
 * @return {*} Host-realm copy.
 */
function plain( value ) {
	return JSON.parse( JSON.stringify( value ) );
}

const SOURCE = readFileSync( new URL( '../../wavira-core/assets/js/index.js', import.meta.url ), 'utf8' );

/**
 * Load the engine in a DOM-less context.
 *
 * @return {Object} `window.Wavira.player` of the sandbox.
 */
function loadPlayer() {
	const sandbox = { console };

	sandbox.window = sandbox;

	return vm.runInNewContext( SOURCE + '\nwindow.Wavira.player;', vm.createContext( sandbox ), {
		filename: 'wavira-core/assets/js/index.js',
	} );
}

/**
 * Deterministic pseudo-random source.
 *
 * @param {number} seed Starting seed.
 * @return {Function} Random function returning [0, 1).
 */
function seededRandom( seed ) {
	let value = seed;

	return function () {
		value = ( value * 1103515245 + 12345 ) % 2147483648;

		return value / 2147483648;
	};
}

/**
 * Fake audio adapter recording every call the engine makes.
 *
 * @param {Object} player Player namespace (for the emitter).
 * @return {Object} Adapter plus its call log.
 */
function fakeAudio( player ) {
	const emitter = player.core.createEmitter();
	const calls = { loaded: [], seeks: [], volumes: [], muted: [], plays: 0, pauses: 0 };

	const adapter = {
		on: emitter.on,
		emit: emitter.emit,
		load: ( src ) => calls.loaded.push( src ),
		play: () => {
			calls.plays++;
			emitter.emit( 'play', {} );

			return Promise.resolve();
		},
		pause: () => {
			calls.pauses++;
			emitter.emit( 'pause', {} );
		},
		seek: ( time ) => calls.seeks.push( time ),
		setVolume: ( value ) => calls.volumes.push( value ),
		setMuted: ( value ) => calls.muted.push( value ),
	};

	return { adapter, calls };
}

/**
 * In-memory stand-in for localStorage.
 *
 * @return {Object} Storage-like object plus its backing map.
 */
function fakeStorage() {
	const data = new Map();

	return {
		data,
		getItem: ( key ) => ( data.has( key ) ? data.get( key ) : null ),
		setItem: ( key, value ) => data.set( key, String( value ) ),
	};
}

/**
 * Track payload as the REST layer produces it.
 *
 * @param {number} id Track ID.
 * @param {Object} overrides Extra fields.
 * @return {Object} Payload.
 */
function track( id, overrides = {} ) {
	return Object.assign(
		{
			id,
			type: 'wavira_track',
			title: `Track ${ id }`,
			permalink: `https://example.test/tracks/track-${ id }/`,
			duration: 245,
			preferred: 320,
			sources: { 320: `https://example.test/audio-320-${ id }.mp3`, 128: `https://example.test/audio-128-${ id }.mp3` },
			cover: { url: `https://example.test/cover-${ id }.jpg`, alt: `Cover ${ id }` },
			artist: { id: 7, name: 'Artist Seven' },
			album: { id: 9, title: 'Album Nine' },
			media_session: { title: `Track ${ id }`, artist: 'Artist Seven', album: 'Album Nine', artwork: [] },
		},
		overrides
	);
}

const SETTINGS = {
	routes: {
		track: 'https://example.test/wp-json/wavira/v1/player/tracks/%d',
		queue: 'https://example.test/wp-json/wavira/v1/player/queue',
	},
	defaults: { volume: 0.5, repeat: 'off', shuffle: false, seekStep: 5, volumeStep: 0.1, context: 'tracks', limit: 20, advance: true },
	storage: { prefix: 'wavira.player.' },
	strings: { error: 'Playback failed', blocked: 'Tap to play' },
};

/**
 * Build an engine around the fakes.
 *
 * @param {Object} player Player namespace.
 * @param {Object} options  Overrides (`fetcher`, `storage`, `random`, `settings`).
 * @return {Object} Engine plus fakes.
 */
function buildEngine( player, options = {} ) {
	const { adapter, calls } = fakeAudio( player );
	const storage = options.storage || fakeStorage();
	const engine = player.core.createEngine( {
		settings: player.core.normaliseSettings( options.settings || SETTINGS ),
		fetcher: options.fetcher,
		storage: player.core.createStorage( storage, 'wavira.player.' ),
		random: options.random || seededRandom( 42 ),
	} );

	engine.attachAudio( adapter );

	return { engine, calls, storage, adapter };
}

test( 'formatTime renders minutes, seconds and hours', () => {
	const player = loadPlayer();
	const { formatTime } = player.core;

	assert.equal( formatTime( 0 ), '0:00' );
	assert.equal( formatTime( 65 ), '1:05' );
	assert.equal( formatTime( 245 ), '4:05' );
	assert.equal( formatTime( 3661 ), '1:01:01' );
	assert.equal( formatTime( undefined ), '--:--' );
	assert.equal( formatTime( -3 ), '--:--' );
} );

test( 'settings are normalised with safe fallbacks', () => {
	const player = loadPlayer();
	const settings = player.core.normaliseSettings( {
		routes: { track: 'a/%d' },
		defaults: { volume: 4, repeat: 'nonsense', seekStep: 'x', volumeStep: 9 },
		strings: { play: 'Start' },
	} );

	assert.equal( settings.routes.track, 'a/%d' );
	assert.equal( settings.routes.queue, '' );
	assert.equal( settings.defaults.volume, 1, 'volume above 1 must clamp' );
	assert.equal( settings.defaults.repeat, 'off', 'unknown repeat mode falls back' );
	assert.equal( settings.defaults.seekStep, 5 );
	assert.equal( settings.defaults.volumeStep, 1 );
	assert.equal( settings.storage.prefix, 'wavira.player.' );
	assert.equal( settings.strings.play, 'Start', 'server strings win' );
	assert.equal( settings.strings.pause, 'Pause', 'missing strings keep the built-in copy' );

	const bare = player.core.normaliseSettings( undefined );

	assert.equal( bare.routes.track, '' );
	assert.equal( bare.defaults.autoplayOnLoad, false );
} );

test( 'unsafe URLs are rejected', () => {
	const player = loadPlayer();
	const { isSafeUrl } = player.core;

	assert.equal( isSafeUrl( 'https://example.test/a.mp3' ), true );
	assert.equal( isSafeUrl( '/audio/a.mp3' ), true );
	assert.equal( isSafeUrl( 'audio/a.mp3' ), true );
	assert.equal( isSafeUrl( 'javascript:alert(1)' ), false );
	assert.equal( isSafeUrl( 'javascript :alert(1)' ), false );
	assert.equal( isSafeUrl( 'data:audio/mp3;base64,AAAA' ), false );
	assert.equal( isSafeUrl( '' ), false );
	assert.equal( isSafeUrl( undefined ), false );
} );

test( 'preferredSource follows the server quality order', () => {
	const player = loadPlayer();
	const { preferredSource } = player.core;

	assert.equal( preferredSource( track( 1 ) ), 'https://example.test/audio-320-1.mp3' );
	assert.equal(
		preferredSource( track( 1, { preferred: 128 } ) ),
		'https://example.test/audio-128-1.mp3',
		'the server-declared quality wins'
	);
	assert.equal(
		preferredSource( { sources: { external: 'https://example.test/ext.mp3' } } ),
		'https://example.test/ext.mp3',
		'external sources are usable when they are all that exists'
	);
	assert.equal( preferredSource( { sources: { 320: 'javascript:evil()' } } ), '', 'unsafe sources are dropped' );
	assert.equal( preferredSource( { sources: {} } ), '' );
	assert.equal( preferredSource( null ), '' );
} );

test( 'advance walks the queue and honours repeat', () => {
	const player = loadPlayer();
	const { advance } = player.core;

	assert.deepEqual( plain( advance( { length: 3, index: 0, repeat: 'off' }, 1 ) ), { index: 1, ended: false } );
	assert.deepEqual( plain( advance( { length: 3, index: 2, repeat: 'off' }, 1 ) ), { index: -1, ended: true } );
	assert.deepEqual( plain( advance( { length: 3, index: 2, repeat: 'all' }, 1 ) ), { index: 0, ended: false } );
	assert.deepEqual( plain( advance( { length: 3, index: 0, repeat: 'all' }, -1 ) ), { index: 2, ended: false } );
	assert.deepEqual( plain( advance( { length: 3, index: -1, repeat: 'off' }, 1 ) ), { index: 0, ended: false } );
	assert.deepEqual( plain( advance( { length: 0, index: -1, repeat: 'all' }, 1 ) ), { index: -1, ended: true } );
	assert.deepEqual(
		plain( advance( { length: 3, index: 2, repeat: 'off', order: [ 2, 0, 1 ] }, 1 ) ),
		{ index: 0, ended: false },
		'shuffle order decides the successor'
	);
} );

test( 'shuffleOrder keeps every index and starts at the current track', () => {
	const player = loadPlayer();
	const { shuffleOrder } = player.core;
	const order = shuffleOrder( 10, 4, seededRandom( 7 ) );

	assert.equal( order.length, 10 );
	assert.equal( order[ 0 ], 4 );
	assert.deepEqual( [ ...order ].sort( ( a, b ) => a - b ), [ 0, 1, 2, 3, 4, 5, 6, 7, 8, 9 ] );
	assert.deepEqual( plain( shuffleOrder( 0, -1, seededRandom( 1 ) ) ), [] );
} );

test( 'engine loads a queue and plays through it', async () => {
	const player = loadPlayer();
	const requested = [];
	const { engine, calls } = buildEngine( player, {
		fetcher: ( url ) => {
			requested.push( url );

			return Promise.resolve( { context: 'tracks', count: 3, items: [ track( 1 ), track( 2 ), track( 3 ) ] } );
		},
	} );

	const events = [];
	engine.subscribe( 'trackchange', ( payload ) => events.push( `track:${ payload.track.id }` ) );
	engine.subscribe( 'queueend', () => events.push( 'queueend' ) );

	const items = await engine.loadQueue( 'tracks', { limit: 3 }, 0 );

	assert.equal( items.length, 3 );
	assert.match( requested[ 0 ], /\/player\/queue\?context=tracks&limit=3$/ );
	assert.equal( engine.field( 'queue' ).length, 3 );
	assert.equal( engine.field( 'currentIndex' ), 0 );
	assert.equal( calls.loaded[ 0 ], 'https://example.test/audio-320-1.mp3' );
	assert.deepEqual( events, [ 'track:1' ] );

	await engine.play();

	assert.equal( engine.field( 'isPlaying' ), true );

	await engine.next();

	assert.equal( engine.field( 'currentIndex' ), 1 );
	assert.equal( calls.loaded[ 1 ], 'https://example.test/audio-320-2.mp3' );

	await engine.next();
	await engine.next();

	assert.equal( events[ events.length - 1 ], 'queueend', 'repeat off stops after the last track' );
	assert.equal( engine.field( 'isPlaying' ), false );
} );

test( 'repeat all wraps, repeat one replays the same track', async () => {
	const player = loadPlayer();
	const { engine, calls, adapter } = buildEngine( player );
	const items = [ track( 11 ), track( 12 ) ];

	engine.setQueue( items, 1 );
	engine.setRepeat( 'all' );

	await engine.next();

	assert.equal( engine.field( 'currentIndex' ), 0, 'repeat all wraps to the first item' );

	engine.setRepeat( 'one' );
	engine.setQueue( items, 0 );
	engine.setVolume( 1 );

	const playsBefore = calls.plays;
	const seeksBefore = calls.seeks.length;

	adapter.emit( 'ended', {} );

	assert.equal( calls.seeks.length, seeksBefore + 1, 'repeat one seeks back to the start' );
	assert.equal( calls.seeks[ calls.seeks.length - 1 ], 0 );
	assert.equal( calls.plays, playsBefore + 1, 'and plays again' );
	assert.equal( engine.field( 'currentIndex' ), 0 );
} );

test( 'engines survive a failed fetch and report a usable error', async () => {
	const player = loadPlayer();
	const { engine } = buildEngine( player, {
		fetcher: () => Promise.reject( new Error( 'HTTP 404' ) ),
	} );

	const errors = [];
	engine.subscribe( 'error', ( state ) => errors.push( state.message ) );

	await assert.rejects( () => engine.loadTrack( 99, false ) );
	assert.equal( engine.field( 'error' ), 'Playback failed' );
	assert.equal( engine.field( 'isLoading' ), false );
	assert.equal( errors.length, 1 );
} );

test( 'track payloads are fetched from the server-provided route', async () => {
	const player = loadPlayer();
	const urls = [];
	const { engine, calls } = buildEngine( player, {
		fetcher: ( url ) => {
			urls.push( url );

			return Promise.resolve( track( 42, { preferred: 128 } ) );
		},
	} );

	await engine.loadTrack( 42, false );

	assert.equal( urls[ 0 ], 'https://example.test/wp-json/wavira/v1/player/tracks/42' );
	assert.equal( engine.field( 'currentTrack' ).id, 42 );
	assert.equal( calls.loaded[ 0 ], 'https://example.test/audio-128-42.mp3' );
} );

test( 'volume and mute are clamped and persisted under the player prefix', () => {
	const player = loadPlayer();
	const storage = fakeStorage();
	const { engine, calls } = buildEngine( player, { storage } );

	engine.setVolume( 1.7 );
	engine.setMuted( true );
	engine.volumeBy( -0.5 );

	assert.equal( engine.field( 'volume' ), 0.5 );
	assert.equal( engine.field( 'muted' ), true );
	assert.equal( storage.getItem( 'wavira.player.volume' ), '0.5' );
	assert.equal( storage.getItem( 'wavira.player.muted' ), 'true' );
	assert.equal( calls.volumes.includes( 1 ), true, 'the adapter receives the clamped value' );
	assert.equal( calls.muted.includes( true ), true );

	// A second engine reuses the persisted preferences.
	const second = buildEngine( player, { storage } ).engine;

	assert.equal( second.field( 'volume' ), 0.5 );
	assert.equal( second.field( 'muted' ), true );
} );

test( 'queue edits keep the current track playing', () => {
	const player = loadPlayer();
	const { engine, calls } = buildEngine( player );

	engine.setQueue( [ track( 1 ), track( 2 ), track( 3 ) ], 1 );
	engine.removeAt( 0 );

	assert.equal( engine.field( 'currentIndex' ), 0, 'the current track follows the removal' );
	assert.equal( engine.field( 'currentTrack' ).id, 2 );
	assert.equal( calls.loaded.length, 1, 'removing another item does not reload the track' );

	const removed = engine.removeAt( 0 );

	assert.equal( removed.id, 2 );
	assert.equal( engine.field( 'currentTrack' ).id, 3, 'removing the playing item selects the successor' );

	engine.clearQueue();

	assert.equal( engine.field( 'queue' ).length, 0 );
	assert.equal( engine.field( 'currentTrack' ), null );
	assert.equal( engine.field( 'isPlaying' ), false );
} );

test( 'playback starts from the first queue item when nothing is selected', async () => {
	const player = loadPlayer();
	const { engine, calls } = buildEngine( player );

	engine.setQueue( [ track( 5 ), track( 6 ) ], -1 );

	assert.equal( engine.field( 'currentTrack' ), null, 'filling a queue never auto-selects' );

	await engine.play();

	assert.equal( engine.field( 'currentIndex' ), 0 );
	assert.equal( engine.field( 'isPlaying' ), true );
	assert.equal( calls.loaded.length, 1 );
} );

test( 'a mount point without a DOM view still runs the engine', async () => {
	const player = loadPlayer();
	const mount = {
		firstChild: null,
		attributes: { 'data-context': 'album', 'data-id': '9' },
		getAttribute( name ) {
			return this.attributes[ name ] ?? null;
		},
		setAttribute() {},
		addEventListener() {},
		removeEventListener() {},
		querySelectorAll: () => [],
	};

	const urls = [];
	const controller = player.create( mount, {
		noView: true,
		settings: player.settings( SETTINGS ),
		engine: player.core.createEngine( {
			settings: player.settings( SETTINGS ),
			fetcher: ( url ) => {
				urls.push( url );

				return Promise.resolve( { items: [ track( 1 ), track( 2 ) ] } );
			},
		} ),
	} );

	const items = await controller.load();

	assert.equal( items.length, 2 );
	assert.match( urls[ 0 ], /context=album&id=9/ );
	assert.equal( controller.engine.field( 'queue' ).length, 2 );

	controller.destroy();
} );

test( 'the shipped engine keeps the documented safety locks', () => {
	assert.equal( /\.innerHTML\s*=/.test( SOURCE ), false, 'payload text must never be written as markup' );
	assert.equal( /\.outerHTML\s*=/.test( SOURCE ), false );
	assert.equal( /\beval\s*\(/.test( SOURCE ), false );
	assert.equal( /document\.cookie/.test( SOURCE ), false, 'the player never writes cookies' );
	assert.equal( /id=["']audio["']/.test( SOURCE ), false, 'no global element ID: instances own their <audio>' );
	assert.equal( /wavira:player:/.test( SOURCE ), true, 'the documented DOM event bridge is present' );
	assert.equal( /wavira-player/.test( SOURCE ), true );
} );

test( 'the view builder mounts the queue list and wires its toggle', () => {
	// Regression lock for the 0.6.0 defect where the queue <ol> was created and
	// populated but never appended, so the panel could not appear. A DOM-free
	// suite cannot observe rendered output, so the shipped source is asserted
	// directly (ADR 0005 §2, ADR 0009 §3).
	assert.match(
		SOURCE,
		/var queueList = el\( 'ol', 'wavira-player__queue'/,
		'the queue list is an <ol> owned by the view'
	);
	assert.match( SOURCE, /root\.appendChild\( queueList \)/, 'the queue list must be appended to the player root' );
	assert.match(
		SOURCE,
		/queueToggle\.setAttribute\( 'aria-controls', queueList\.id \)/,
		'the toggle must point at the queue list it discloses'
	);
	assert.match( SOURCE, /mount\.setAttribute\( 'data-queue-open'/, 'the open state is exposed on the mount for CSS' );
	assert.match( SOURCE, /controls\.appendChild\( queueToggle \)/, 'the toggle lives in the controls group' );
} );

test( 'a card starts its track in the sticky player and leaves the queue intact', async () => {
	const player = loadPlayer();
	const calls = [];
	const items = [ track( 11 ), track( 12 ) ];
	const ordinary = {
		mount: { className: 'wavira-player' },
		engine: {
			loadQueue: ( context, args ) => {
				calls.push( [ 'loadQueue', context, args ] );

				return Promise.resolve( items );
			},
			setQueue: ( queue, index ) => calls.push( [ 'setQueue', queue, index ] ),
			play: () => {
				calls.push( [ 'play' ] );

				return Promise.resolve();
			},
		},
	};
	const sticky = {
		mount: { className: 'wavira-player wavira-player--sticky' },
		engine: {
			loadQueue: ( context, args ) => {
				calls.push( [ 'stickyQueue', context, args ] );

				return Promise.resolve( items );
			},
			setQueue: ( queue, index ) => calls.push( [ 'stickySet', queue, index ] ),
			play: () => {
				calls.push( [ 'stickyPlay' ] );

				return Promise.resolve();
			},
		},
	};

	player.instances.push( ordinary, sticky );

	assert.equal( await player.play( 12, 'tracks' ), true );
	assert.equal( player.primary(), sticky );
	assert.equal( calls[ 0 ][ 0 ], 'stickyQueue' );
	assert.equal( calls.find( ( call ) => 'stickySet' === call[ 0 ] )[ 2 ], 1 );
	assert.equal( calls.some( ( call ) => 'stickyPlay' === call[ 0 ] ), true );
} );

test( 'a card can play a track outside the current queue and still advance from it', async () => {
	const player = loadPlayer();
	const calls = [];
	const original = [ track( 21 ) ];
	const requested = track( 29 );
	player.instances.push( {
		mount: { className: 'wavira-player wavira-player--sticky' },
		engine: {
			loadQueue: () => Promise.resolve( original ),
			loadTrack: ( id, autoplay ) => {
				calls.push( [ 'loadTrack', id, autoplay ] );

				return Promise.resolve( requested );
			},
			setQueue: ( queue, index ) => calls.push( [ 'setQueue', queue, index ] ),
			play: () => Promise.resolve(),
		},
	} );

	assert.equal( await player.play( 29, 'tracks' ), true );
	const selected = calls.filter( ( call ) => 'setQueue' === call[ 0 ] ).at( -1 );

	assert.equal( selected[ 1 ].length, 2 );
	assert.equal( selected[ 1 ][ 1 ].id, 29 );
	assert.equal( selected[ 2 ], 1 );
	assert.deepEqual( calls.find( ( call ) => 'loadTrack' === call[ 0 ] ), [ 'loadTrack', 29, false ] );
} );

test( 'a card plays the selected album or artist queue', async () => {
	const player = loadPlayer();
	const calls = [];
	const items = [ track( 31 ), track( 32 ) ];
	player.instances.push( {
		mount: { className: 'wavira-player wavira-player--sticky' },
		engine: {
			loadQueue: ( context, args ) => {
				calls.push( [ 'loadQueue', context, args ] );

				return Promise.resolve( items );
			},
			setQueue: ( queue, index ) => calls.push( [ 'setQueue', index ] ),
			play: () => Promise.resolve(),
		},
	} );

	assert.equal( await player.playContext( 'album', 91 ), true );
	assert.deepEqual( plain( calls[ 0 ] ), [ 'loadQueue', 'album', { id: 91, slug: '', limit: 0 } ] );
	assert.deepEqual( plain( calls[ 1 ] ), [ 'setQueue', 0 ] );
} );

test( 'every player quietly preloads its queue before the visitor presses play', () => {
	assert.match( SOURCE, /controller\s*\.load\(\)\s*\.then\(/ );
	assert.match( SOURCE, /alreadyMounted = instances\.some/ );
	assert.match( SOURCE, /if \( mount\.getAttribute\( 'data-autoplay' \) \|\| settings\.defaults\.autoplayOnLoad \)/ );
} );

test( 'a card pauses the player that is actually playing when the page has two mounts', async () => {
	const player = loadPlayer();
	const calls = [];
	player.instances.push(
		{
			mount: { className: 'wavira-player wavira-player--sticky' },
			engine: {
				field: () => false,
				toggle: () => calls.push( 'idle sticky' ),
			},
		},
		{
			mount: { className: 'wavira-player' },
			engine: {
				field: () => true,
				toggle: () => {
					calls.push( 'active inline' );

					return Promise.resolve();
				},
			},
		}
	);

	assert.equal( await player.toggle(), true );
	assert.deepEqual( calls, [ 'active inline' ] );
} );

test( 'the view reads state after a queue event and the track from its event payload', () => {
	assert.match( SOURCE, /engine\.subscribe\( 'trackchange', function \( event \) \{\s*renderTrack\( event\.track \);/ );
	assert.match( SOURCE, /engine\.subscribe\( 'change', function \(\) \{\s*renderState\( engine\.get\(\) \);/ );
	assert.match( SOURCE, /var state = engine\.get\(\);\s*renderQueue\( state \);\s*renderState\( state \);/ );
} );
