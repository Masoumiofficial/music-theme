/**
 * Tests for the translation pipeline (`tools/i18n.mjs`) and for the catalogues
 * that ship with the product.
 *
 * The pipeline writes a binary format WordPress loads at runtime, and a mistake
 * in it is invisible until a Persian site shows English text: the MO writer
 * once interleaved the id and value offsets, which produced a file that looked
 * plausible on disk and translated nothing. These tests pin the format down
 * (round-trip through the reader, sorted ids, header first) and then check the
 * shipped Persian catalogues themselves.
 *
 * Run with: npm run test:js
 */

import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { test } from 'node:test';
import { fileURLToPath } from 'node:url';

import { compileMo, extractDomain, parsePo, readMo, stripComments, stuckPlurals } from '../../tools/i18n.mjs';

const ROOT = resolve( dirname( fileURLToPath( import.meta.url ) ), '../..' );
const PERSIAN = /[\u0600-\u06FF]/;

const PO = `msgid ""
msgstr ""
"Project-Id-Version: demo\\n"
"Language: fa_IR\\n"
"Plural-Forms: nplurals=2; plural=(n > 1);\\n"

#: demo.php:1
msgid "Play"
msgstr "پخش"

#: demo.php:2
msgctxt "block title"
msgid "Music player"
msgstr "پخشکنندهٔ موسیقی"

#: demo.php:3
msgid "%d track"
msgid_plural "%d tracks"
msgstr[0] "%d قطعه"
msgstr[1] "%d قطعه"
`;

test( 'the extractor ignores i18n calls that only appear in a comment', () => {
	const source = `<?php
// Old note: __( 'Never translate this', 'wavira' ) was removed.
/**
 * Docblock mentioning esc_html_x( 'Nor this', 'context', 'wavira' ).
 */
echo esc_html__( 'Translate this', 'wavira' );
`;

	const { code } = stripComments( source );

	assert.ok( ! code.includes( 'Never translate this' ), 'a line comment must not become a source string' );
	assert.ok( ! code.includes( 'Nor this' ), 'a block comment must not become a source string' );
	assert.ok( code.includes( 'Translate this' ), 'a real call must survive comment stripping' );
} );

test( 'the PO reader keeps contexts and plural forms apart', () => {
	const { entries, header } = parsePo( PO );
	const plain = entries.find( ( entry ) => entry.msgid === 'Play' );
	const contextual = entries.find( ( entry ) => entry.msgctxt === 'block title' );
	const plural = entries.find( ( entry ) => entry.msgidPlural === '%d tracks' );

	assert.equal( header.Language, 'fa_IR' );
	assert.equal( plain.msgstr[ 0 ], 'پخش' );
	assert.equal( contextual.msgstr[ 0 ], 'پخشکنندهٔ موسیقی' );
	assert.deepEqual( plural.msgstr.slice( 0, 2 ), [ '%d قطعه', '%d قطعه' ] );
} );

test( 'a compiled catalogue round-trips through the MO reader', () => {
	const { entries } = parsePo( PO );
	const table = readMo( compileMo( entries ) );

	assert.equal( table.get( '' ).includes( 'Language: fa_IR' ), true, 'the header entry carries the metadata' );
	assert.equal( table.get( 'Play' ), 'پخش' );
	assert.equal( table.get( 'block title\u0004Music player' ), 'پخشکنندهٔ موسیقی' );
	assert.equal( table.get( '%d track\u0000%d tracks' ), '%d قطعه\u0000%d قطعه' );
	assert.equal( table.size, entries.length );
} );

test( 'the MO string table has the layout WordPress relies on', () => {
	// Every value must sit after every id: the offsets are read, not guessed,
	// and a wrong layout yields a file whose lookups all miss.
	const { entries } = parsePo( PO );
	const buffer = compileMo( entries );
	const count = buffer.readUInt32LE( 8 );
	const originalOffset = buffer.readUInt32LE( 12 );
	const translationOffset = buffer.readUInt32LE( 16 );
	const ids = [];
	const values = [];

	for ( let index = 0; index < count; index++ ) {
		const idLength = buffer.readUInt32LE( originalOffset + index * 8 );
		const idOffset = buffer.readUInt32LE( originalOffset + index * 8 + 4 );
		const valueLength = buffer.readUInt32LE( translationOffset + index * 8 );
		const valueOffset = buffer.readUInt32LE( translationOffset + index * 8 + 4 );

		ids.push( idOffset + idLength );
		values.push( valueOffset );
	}

	assert.ok(
		Math.max( ...ids ) <= Math.min( ...values ),
		'the id table must end before the value table begins'
	);

	const sorted = [ ...ids ].sort( ( a, b ) => a - b );
	assert.deepEqual( ids, sorted, 'ids must be sorted for a binary-search lookup' );
} );

test( 'the shipped catalogues are complete Persian', () => {
	for ( const artifact of [
		{ domain: 'wavira', languages: 'wavira/languages' },
		{ domain: 'wavira-core', languages: 'wavira-core/languages' },
	] ) {
		const { entries } = extractDomain( { domain: artifact.domain, source: artifact.domain } );
		const { entries: po } = parsePo( readFileSync( join( ROOT, artifact.languages, 'fa_IR.po' ), 'utf8' ) );
		const table = readMo( readFileSync( join( ROOT, artifact.languages, 'fa_IR.mo' ) ) );

		assert.ok( entries.size > 0, `${ artifact.domain } must have translatable strings` );

		for ( const entry of entries.values() ) {
			const translation = po.find(
				( candidate ) =>
					candidate.msgid === entry.msgid &&
					( candidate.msgctxt ?? '' ) === entry.context &&
					( candidate.msgidPlural ?? '' ) === entry.msgidPlural
			);

			assert.ok( translation, `${ artifact.domain }: “${ entry.msgid }” is missing from fa_IR.po` );
			assert.ok( translation.msgstr[ 0 ] !== '', `${ artifact.domain }: “${ entry.msgid }” has an empty translation` );

			if ( /[A-Za-z]/.test( entry.msgid ) && ! translation.flagList.includes( 'keep-latin' ) ) {
				assert.ok(
					PERSIAN.test( translation.msgstr[ 0 ] ),
					`${ artifact.domain }: “${ entry.msgid }” is not translated to Persian`
				);
			}
		}

		// And the compiled file holds the same translations, not just the PO.
		for ( const entry of po ) {
			if ( '' === entry.msgid ) {
				continue;
			}

			const key = `${ entry.msgctxt ? `${ entry.msgctxt }\u0004` : '' }${ entry.msgid }${ entry.msgidPlural ? `\u0000${ entry.msgidPlural }` : '' }`;

			assert.equal(
				table.get( key ),
				entry.msgstr.join( '\u0000' ),
				`${ artifact.domain }: the MO must carry the PO translation of “${ entry.msgid }”`
			);
		}
	}
} );

test( 'block metadata is translated in the contexts core looks up', () => {
	// Core translates block.json fields with translate_settings_using_i18n_schema()
	// using the contexts of wp-includes/block-i18n.json. A catalogue that uses
	// different contexts leaves the inserter in English.
	const { entries } = extractDomain( { domain: 'wavira', source: 'wavira' } );
	const contexts = new Set( [ ...entries.values() ].map( ( entry ) => entry.context ) );

	assert.ok( contexts.has( 'block title' ) );
	assert.ok( contexts.has( 'block description' ) );
	assert.ok( contexts.has( 'block keyword' ) );
} );

test( 'a Persian plural keeps its نیم‌فاصله', () => {
	// A catalogue can be complete — every string translated, the POT and the MO
	// in sync — and still print «قطعهها» where Persian reads «قطعه‌ها». It is
	// the archive title of a post type, so it was on /tracks/ and /albums/ in
	// 0.15.0. The rule: the suffix is separated from a stem that joins forward,
	// and left attached after the seven letters that never join (ا، د، ذ، ر،
	// ز، ژ، و), where there is nothing to separate.
	for ( const wrong of [ 'قطعهها', 'آلبومها', 'زبانها', 'سبکها', 'نسخهها', 'قطعههای', 'برچسبهای', 'شبکههای' ] ) {
		assert.deepEqual(
			stuckPlurals( wrong ),
			[ wrong ],
			`${ wrong } must be reported`
		);
	}

	for ( const right of [ 'قطعه‌ها', 'آلبوم‌ها', 'کتاب‌هایشان', 'پیوندها', 'تصویرها', 'ابزارها', 'ویدیوها', 'خبرها', 'تازه‌ترین خبرها' ] ) {
		assert.deepEqual(
			stuckPlurals( right ),
			[],
			`${ right } is written correctly and must not be reported`
		);
	}

	// One report per word, however many times the sentence says it.
	assert.deepEqual( stuckPlurals( 'فهرست قطعهها و قطعهها' ), [ 'قطعهها' ] );
} );

test( 'the shipped catalogues have no stuck plural', () => {
	// The same rule the `check` mode enforces, asserted against the files that
	// ship, so a regression fails here as well as in CI.
	for ( const artifact of [ 'wavira/languages', 'wavira-core/languages' ] ) {
		const { entries } = parsePo( readFileSync( join( ROOT, artifact, 'fa_IR.po' ), 'utf8' ) );

		for ( const entry of entries ) {
			for ( const form of entry.msgstr ) {
				assert.deepEqual(
					stuckPlurals( form ),
					[],
					`${ artifact }: “${ entry.msgid }” must separate its plural suffix`
				);
			}
		}
	}
} );
