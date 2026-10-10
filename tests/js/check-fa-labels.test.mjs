/**
 * Unit tests for the Persian-labels gate (0.15.0).
 *
 * The theme's strings are covered by `tools/check-render.mjs` and the plugin's
 * catalogue by `tools/i18n.mjs check`, but neither looks at a served page: an
 * artist page printed “Albums (۲)” — a plugin label — next to Persian chips,
 * because the plugin's catalogue was not loaded for that request. These tests
 * pin the three outcomes: a Persian heading, an English one, and a section the
 * page does not render at all.
 *
 *   node --test tests/js/check-fa-labels.test.mjs
 *
 * @package Wavira
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';

import { SECTION_LABELS, checkLabels, plain, translations } from '../../tools/check-fa-labels.mjs';

const CATALOGUE = [
	'msgid ""',
	'msgstr "Content-Type: text/plain; charset=UTF-8\\n"',
	'',
	'msgid "Albums"',
	'msgstr "آلبوم‌ها"',
	'',
	'msgid "Singles"',
	'msgstr "تک‌آهنگ‌ها"',
	'',
	'msgid "Music videos"',
	'msgstr "موزیک‌ویدیوها"',
	'',
	'msgid "Works"',
	'msgstr "آثار"',
	'',
].join( '\n' );

test( 'the catalogue reader finds the Persian of a source string', () => {
	assert.deepEqual( translations( CATALOGUE, 'Albums' ), [ 'آلبوم‌ها' ] );
	assert.deepEqual( translations( CATALOGUE, 'Nothing here' ), [] );
} );

test( 'a page whose headings are Persian passes, and says which they are', () => {
	const result = checkLabels( {
		page: '<h2>آلبوم‌ها (۲)</h2><h2>تک‌آهنگ‌ها (۵)</h2><h2>موزیک‌ویدیوها (۱)</h2><h2>آثار (۰)</h2>',
		catalogue: CATALOGUE,
	} );

	assert.deepEqual( result.problems, [] );
	assert.deepEqual( result.missing, [] );
	assert.equal( result.found.length, 4 );
	assert.match( result.found[ 0 ], /Albums → آلبوم‌ها/ );

	// A rendered heading without the zero-width non-joiner is the same heading:
	// the catalogue spells it «موزیک‌ویدیوها» and a page may spell it with a space.
	assert.deepEqual(
		checkLabels( { page: '<h2>موزیک ویدیوها (۱)</h2>', catalogue: CATALOGUE, labels: [ 'Music videos' ] } ).problems,
		[]
	);
	assert.equal( plain( 'موزیک‌ویدیوها' ), 'موزیکویدیوها' );
} );

test( 'an English heading names the place it comes from', () => {
	const result = checkLabels( {
		page: '<h2>Albums (۲)</h2><h2>تک‌آهنگ‌ها (۵)</h2>',
		catalogue: CATALOGUE,
	} );

	assert.equal( result.problems.length, 1 );
	assert.match( result.problems[ 0 ], /the page prints “Albums \(…\)” instead of “آلبوم‌ها”/ );
	assert.match( result.problems[ 0 ], /the plugin catalogue is not in effect for this request/ );
	assert.equal( result.found.length, 1 );
} );

test( 'a section the page does not render is neither found nor a problem', () => {
	const result = checkLabels( { page: '<h2>آلبوم‌ها (۲)</h2>', catalogue: CATALOGUE } );

	assert.deepEqual( result.problems, [] );
	assert.deepEqual( result.missing, [ 'Singles', 'Music videos', 'Works' ] );
	assert.equal( result.found.length, 1 );
} );

test( 'an untranslated catalogue entry is not mistaken for a translation', () => {
	// `msgstr ""` would match every page if it were treated as a translation.
	const result = checkLabels( {
		page: '<h2>Albums (۲)</h2>',
		catalogue: 'msgid "Albums"\nmsgstr ""\n',
		labels: [ 'Albums' ],
	} );

	assert.equal( result.problems.length, 1 );
	assert.match( result.problems[ 0 ], /has no Persian translation for it/ );
	assert.equal( SECTION_LABELS.length, 4 );
} );
