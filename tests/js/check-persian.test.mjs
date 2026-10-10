/**
 * Unit tests for the fully-Persian gate (0.15.0).
 *
 * `tools/i18n.mjs check` proves the catalogues are complete and
 * `tools/check-render.mjs` proves the theme's headings reach the page. Neither
 * sees a string that was never wrapped in a translation function, an English
 * `alt`, or a template's placeholder left in the markup — a complete catalogue
 * and an English page. These tests pin the three places a word can hide: the
 * page's text, its spoken attributes, and behind a tag.
 *
 *   node --test tests/js/check-persian.test.mjs
 *
 * @package Wavira
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';

import { ALLOWED, checkPersian, elements, latinWords, visible, withoutEntities } from '../../tools/check-persian.mjs';

test( 'the reader keeps text and drops markup, scripts and comments', () => {
	assert.equal(
		visible( '<p>سلام <strong>دنیا</strong></p><!-- draft --><script>var x = "Not text";</script>' ),
		'سلام دنیا'
	);
	assert.equal( visible( '<style>.a{color:red}</style><p>خوب</p>' ), 'خوب' );

	// Entities are punctuation, not words.
	assert.equal( visible( '<p>الف&nbsp;ب &amp; پ</p>' ), 'الف ب پ' );
} );

test( 'Latin words are found, minus the proper nouns the list allows', () => {
	assert.deepEqual( latinWords( 'آلبوم Live at Wavira' ), [ 'Live', 'at' ] );
	assert.deepEqual( latinWords( 'Etehad WP و Wavira و Vazirmatn' ), [] );

	// A word that ends a sentence keeps its punctuation out of the report.
	assert.deepEqual( latinWords( 'New album.' ), [ 'New', 'album' ] );

	// A single letter is not a word — a stray `x` in a template is not English.
	assert.deepEqual( latinWords( 'x' ), [] );
} );

test( 'a Persian page passes', () => {
	const result = checkPersian( {
		page: '<h1>واویرا موزیک</h1><p>پخش و انتشار موسیقی ایرانی</p><img src="/a.png" alt="طرح جلد آلبوم باران بهاری">',
	} );

	assert.equal( result.clean, true );
	assert.deepEqual( result.text, [] );
	assert.deepEqual( result.attributes, [] );
} );

test( 'an untranslated string in the text is named', () => {
	const result = checkPersian( {
		page: '<h2>Albums (۲)</h2><p>پخش و انتشار موسیقی ایرانی</p>',
	} );

	assert.equal( result.clean, false );
	assert.deepEqual( result.text, [ 'Albums' ] );
} );

test( 'an untranslated alt or label is named with the attribute it is in', () => {
	const result = checkPersian( {
		page:
			'<img src="/a.png" alt="Demo cover art">' +
			'<button aria-label="Play the queue">پخش</button>' +
			'<input type="search" placeholder="Search the catalogue">',
	} );

	assert.equal( result.attributes.length, 3 );
	assert.deepEqual( result.attributes[ 0 ], {
		element: 'img',
		attribute: 'alt',
		value: 'Demo cover art',
		words: [ 'Demo', 'cover', 'art' ],
	} );
	assert.equal( result.attributes[ 1 ].attribute, 'aria-label' );
	assert.equal( result.attributes[ 1 ].element, 'button' );
	assert.deepEqual( result.attributes[ 2 ].words, [ 'Search', 'the', 'catalogue' ] );
	assert.deepEqual( result.text, [] );
} );

test( 'a word hidden in an attribute WordPress prints with single quotes is found', () => {
	// Core prints `<link rel='stylesheet' …>` with single quotes; a gate that
	// reads only double ones misses half the page.
	const result = checkPersian( { page: "<img src='/a.png' title='Studio session'>" } );

	assert.equal( result.attributes.length, 1 );
	assert.deepEqual( result.attributes[ 0 ].words, [ 'Studio', 'session' ] );
} );

test( 'a directive is not an attribute, and an entity is not a word', () => {
	// WordPress's Interactivity API writes `data-wp-bind--aria-label`, which
	// *contains* `aria-label`: a regex over attribute names reported core's own
	// directives as untranslated labels.
	const directive = checkPersian( {
		page: '<button data-wp-bind--aria-label="state.ariaLabel" aria-label="باز کردن جستجو">جستجو</button>',
	} );

	assert.deepEqual( directive.attributes, [] );

	// `&raquo;` is punctuation from core's feed titles, not the word “raquo”.
	assert.equal( withoutEntities( 'واویرا موزیک &raquo; خوراک' ), 'واویرا موزیک   خوراک' );
	assert.equal( checkPersian( { page: '<a title="واویرا موزیک &raquo; خوراک">خوراک</a>' } ).clean, true );

	// A machine reads `<link title>`: feed, oEmbed and RSD titles are WordPress's
	// own and are not this product's to translate.
	assert.equal(
		checkPersian( { page: '<link rel="alternate" type="application/rss+xml" title="oEmbed (XML)" href="/feed">' } ).clean,
		true
	);

	// The same words on an element a visitor sees are still a defect.
	assert.equal( checkPersian( { page: '<a title="Studio session">ضبط</a>' } ).clean, false );
} );

test( 'the element reader pairs tags with their own attributes', () => {
	const found = elements( '<img src="/a.png" alt="طرح"><a href="/b" title="صفحه">ب</a>' );

	assert.deepEqual( found[ 0 ], { name: 'img', attributes: { src: '/a.png', alt: 'طرح' } } );
	assert.deepEqual( found[ 1 ], { name: 'a', attributes: { href: '/b', title: 'صفحه' } } );
} );

test( 'the allow-list is proper nouns, and a caller can extend it', () => {
	assert.ok( ALLOWED.includes( 'wavira' ) );
	assert.equal(
		checkPersian( { page: '<p>مجله Music Life</p>', allow: [ 'music', 'life' ] } ).clean,
		true
	);
} );
