/**
 * Harness data source: a stubbed `wavira/v1` player API and the settings object
 * the plugin would print.
 *
 * Everything else on the page is the shipped product code. The stub exists
 * because the player's REST routes need WordPress, and the demo audio is served
 * as real HTTP URLs (the engine's URL lock deliberately rejects `data:` and
 * `blob:`), generated on request by serve.mjs.
 *
 * The demo catalogue and the interface strings are Persian, matching the `fa_IR`
 * demo the seeder produces (phase 0.10.1); the strings are copied from
 * `wavira-core/languages/fa_IR.po` through the same keys
 * `Wavira\Core\Player\Assets::strings()` localises.
 */
( function ( window ) {
	'use strict';

	var STUB = '/wp-json/wavira/v1/player';
	var ARTIST = 'آرمان راد';
	var DEMO = [
		{ id: 101, title: 'راه بارانی', album: 'شب‌های تهران', cover: 1, hz: 294, genre: 'پاپ رؤیایی' },
		{ id: 102, title: 'سکوت', album: 'شب‌های تهران', cover: 2, hz: 330, genre: 'پاپ رؤیایی' },
		{ id: 103, title: 'پرواز', album: 'سفر شمال', cover: 3, hz: 392, genre: 'راک تجربی' },
		{ id: 104, title: 'باران بهاری', album: 'باران بهاری', cover: 4, hz: 440, genre: 'امبینت' }
	];

	/**
	 * Build the playback payload for one demo track, in the shape
	 * `Wavira\Core\Player\Payload::for_track()` returns.
	 *
	 * @param {Object} item Demo catalogue row.
	 * @return {Object} Playback payload.
	 */
	function payload( item ) {
		var cover = '/tools/preview/cover-' + item.cover + '.svg';
		var source = '/tools/preview/tone-' + item.hz + '.wav';

		return {
			id: item.id,
			type: 'track',
			title: item.title,
			permalink: '#tracks',
			duration: 3,
			duration_label: '۰:۰۳',
			explicit: false,
			has_lyrics: true,
			artist: { id: 11, name: ARTIST, permalink: '#artists' },
			album: { id: 21, title: item.album, permalink: '#albums' },
			cover: { url: cover, alt: item.title + ' — جلد', srcset: '', sizes: '' },
			genres: [ { id: 31, name: item.genre, slug: 'persian-demo', permalink: '#genres' } ],
			sources: { 320: source },
			preferred: 320,
			media_session: {
				title: item.title,
				artist: ARTIST,
				album: item.album,
				artwork: [ { src: cover, sizes: '400x400', type: 'image/svg+xml' } ]
			}
		};
	}

	/**
	 * Find one demo track by ID.
	 *
	 * @param {number} id Track ID.
	 * @return {Object|undefined} Demo row.
	 */
	function find( id ) {
		var found;

		DEMO.forEach( function ( item ) {
			if ( item.id === id ) {
				found = item;
			}
		} );

		return found;
	}

	// The settings the plugin prints through wp_add_inline_script().
	window.waviraPlayerSettings = {
		version: '0.11.0',
		routes: {
			track: STUB + '/tracks/%d',
			queue: STUB + '/queue'
		},
		defaults: {
			volume: 0.6,
			repeat: 'off',
			shuffle: false,
			seekStep: 5,
			volumeStep: 0.05,
			context: 'tracks',
			limit: 0,
			advance: true,
			autoplayOnLoad: false,
			sticky: true
		},
		storage: { prefix: 'wavira.player.' },
		strings: {
			player: 'پخشکنندهٔ صوتی',
			play: 'پخش',
			pause: 'توقف',
			next: 'قطعهٔ بعدی',
			previous: 'قطعهٔ قبلی',
			seek: 'جابهجایی در قطعه',
			volume: 'بلندی صدا',
			mute: 'بیصدا',
			unmute: 'باصدا',
			shuffle: 'پخش تصادفی',
			repeat: 'حالت تکرار',
			repeatOff: 'بدون تکرار',
			repeatAll: 'تکرار همه',
			repeatOne: 'تکرار یک قطعه',
			queue: 'پخش صف',
			remove: 'حذف از صف: %s',
			loading: 'در حال بارگذاری قطعه…',
			buffering: 'در حال آمادهسازی…',
			error: 'این قطعه پخش نشد.',
			empty: 'اینجا چیزی برای پخش نیست.',
			nowPlaying: 'در حال پخش: %s',
			ofTotal: 'قطعهٔ %1$d از %2$d',
			openTrack: 'باز کردن صفحهٔ قطعه',
			removedTrack: 'از صف حذف شد: %s',
			blocked: 'برای پخش، نخست دکمهٔ پخش را بزنید.',
		}
	};

	// Answer the two player routes from memory; everything else stays untouched.
	var nativeFetch = window.fetch ? window.fetch.bind( window ) : null;

	window.fetch = function ( url, options ) {
		var value = String( url );

		if ( 0 !== value.indexOf( STUB ) ) {
			return nativeFetch ? nativeFetch( url, options ) : Promise.reject( new Error( 'harness: no network' ) );
		}

		var match = value.match( /tracks\/(\d+)/ );
		var item = match ? find( Number( match[ 1 ] ) ) : null;
		var ok = ! match || Boolean( item );
		var body = match
			? item
				? payload( item )
				: { code: 'wavira_not_found' }
			: { items: DEMO.map( function ( track ) {
				return payload( track );
			} ) };

		return Promise.resolve( {
			ok: ok,
			status: ok ? 200 : 404,
			headers: { get: function () {
				return null;
			} },
			json: function () {
				return Promise.resolve( body );
			}
		} );
	};
} )( window );
