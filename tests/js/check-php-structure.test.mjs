/**
 * Unit tests for the PHP structure gate (0.15.0).
 *
 * The gate exists because a docblock that lost its closing delimiter swallowed a
 * method, and `php -l` said the file was fine: a comment containing a function
 * declaration parses. These tests pin both findings — the swallowed declaration
 * and the docblock that documents a different number of arguments than the
 * signature takes — plus the cases that must *not* be reported, because a gate
 * that cries wolf on healthy code is a gate people turn off.
 *
 *   node --test tests/js/check-php-structure.test.mjs
 *
 * @package Wavira
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';

import { inspect, mask } from '../../tools/check-php-structure.mjs';

test( 'the masker keeps offsets and the line numbers that go with them', () => {
	const source = "<?php\n// a line comment\n$string = 'not code';\n/* block */\n";
	const { masked, comments } = mask( source );

	assert.equal( masked.length, source.length );
	assert.equal( masked.split( '\n' ).length, source.split( '\n' ).length );
	assert.equal( comments.length, 2 );

	// The masked text keeps `$string = 'not code';` but the comment bodies are gone.
	assert.match( masked, /\$string = ' {8}';/ );
	assert.doesNotMatch( masked, /line comment|block/ );
} );

test( 'a healthy file reports nothing', () => {
	const source = [
		'<?php',
		'/**',
		' * Store bytes.',
		' *',
		' * @param string $bytes File contents.',
		' * @param int    $id    Post ID.',
		' * @return int',
		' */',
		'function store( string $bytes, int $id = 0 ): int {',
		'\t// A line comment with the word function in it: function store().',
		'\treturn $id;',
		'}',
	].join( '\n' );

	assert.deepEqual( inspect( source ), [] );
} );

test( 'a declaration swallowed by an unclosed docblock is reported', () => {
	// The real defect: the docblock is missing its closing delimiter, so the
	// method it documents is a comment. `php -l` is happy.
	const source = [
		'<?php',
		'/**',
		' * Store bytes.',
		' *',
		' * @param string $bytes File contents.',
		' * @param int    $parent_id Post ID.',
		' * @param string $title Title.',
		'function store( string $bytes, int $parent_id = 0, string $title = "" ): int {',
		'\treturn 0;',
		'}',
	].join( '\n' );

	const findings = inspect( source );

	assert.equal( findings.length, 1 );
	assert.equal( findings[ 0 ].rule, 'declaration-in-comment' );
	assert.match( findings[ 0 ].message, /store\(\)” is inside a block comment that opened on line 2/ );
	assert.equal( findings[ 0 ].line, 8 );
} );

test( 'a docblock that documents the wrong number of parameters is reported', () => {
	const source = [
		'<?php',
		'/**',
		' * @param string $bytes File contents.',
		' * @param int    $parent_id Post ID.',
		' */',
		'function store( string $bytes, int $parent_id = 0, string $title = "" ): int {',
		'\treturn 0;',
		'}',
	].join( '\n' );

	const findings = inspect( source );

	assert.equal( findings.length, 1 );
	assert.equal( findings[ 0 ].rule, 'param-count' );
	assert.match( findings[ 0 ].message, /store\(\) takes 3 parameter\(s\) and its docblock documents 2/ );
} );

test( 'a docblock for a different declaration is not that declaration’s docblock', () => {
	const source = [
		'<?php',
		'/**',
		' * Does nothing.',
		' */',
		'function unrelated(): void {}',
		'',
		'function store( string $bytes, int $parent_id = 0 ): int {',
		'\treturn 0;',
		'}',
	].join( '\n' );

	// `store()` has no docblock of its own; the one above `unrelated()` belongs to
	// `unrelated()` — a declaration between them is what separates the two.
	assert.deepEqual( inspect( source ), [] );
} );
