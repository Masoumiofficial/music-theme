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

test( 'a namespaced file that names a global class without importing it is a finding', () => {
	// The defect of 0.15.0's second render: PHP resolves `WP_Post` inside a
	// namespace to `Wavira\Core\Demo\WP_Post`, `instanceof` answers false, and
	// the branch is never taken — silently, with a green syntax check.
	const source = [
		'<?php',
		'namespace Wavira\\Core\\Demo;',
		'',
		'$post = get_post( 1 );',
		'if ( ! $post instanceof WP_Post ) {',
		'\treturn 0;',
		'}',
		'',
	].join( '\n' );

	const findings = inspect( source );

	assert.equal( findings.length, 1 );
	assert.equal( findings[ 0 ].rule, 'core-class-import' );
	assert.match( findings[ 0 ].message, /WP_Post/ );
	assert.match( findings[ 0 ].message, /never imported/ );
} );

test( 'a static call and a class name given as a string are findings too', () => {
	// The same defect in the clothes it wore on the third render:
	// `class_exists( 'WP_Classic_To_Block_Menu_Converter' )` asks for the
	// *namespaced* name, so the guard answers true and the very next line fatals
	// with “class not found”. `instanceof` was never the only way to name one.
	const source = [
		'<?php',
		'namespace Wavira\\Core\\Demo;',
		'',
		'if ( ! class_exists( \'WP_Classic_To_Block_Menu_Converter\' ) ) {',
		'\treturn 0;',
		'}',
		'',
		'$blocks = WP_Classic_To_Block_Menu_Converter::convert( 1 );',
		'',
		'if ( is_a( $blocks, \'WP_Error\' ) ) {',
		'\treturn 0;',
		'}',
	].join( '\n' );

	const findings = inspect( source );

	assert.equal( findings.length, 3, 'the string, the static call and is_a()' );
	assert.equal( findings[ 0 ].rule, 'core-class-import' );
	assert.deepEqual(
		findings.map( ( finding ) => finding.line ),
		[ 4, 8, 10 ],
		'one finding per use, in source order'
	);
	assert.match( findings[ 0 ].message, /WP_Classic_To_Block_Menu_Converter/ );
	assert.match( findings[ 2 ].message, /WP_Error/ );
} );

test( 'a fully qualified name is not a finding, and neither is a name in prose', () => {
	const source = [
		'<?php',
		'namespace Wavira\\Core\\Admin;',
		'',
		'/**',
		' * Uses class_exists( \'WP_CLI\' ) to find out whether this is a CLI request.',
		' */',
		'function run(): void {',
		'\t\\WP_CLI::log( \'done\' );',
		'}',
	].join( '\n' );

	assert.deepEqual( inspect( source ), [], 'the backslash says global, and the docblock is prose' );
} );

test( 'and it is not a finding once the class is imported', () => {
	const source = [
		'<?php',
		'namespace Wavira\\Core\\Demo;',
		'',
		'use WP_Post;',
		'use WP_Term as Term;',
		'',
		'if ( $post instanceof WP_Post ) {',
		'\t$term = $term instanceof Term ? $term : null;',
		'}',
		'',
	].join( '\n' );

	assert.deepEqual( inspect( source ), [] );
} );

test( 'the global namespace is not the business of this rule', () => {
	// A theme file has no namespace: `WP_Query` there is the global class, which
	// is what it always was.
	const source = [
		'<?php',
		'$query = new WP_Query( array() );',
		'if ( $query instanceof WP_Query ) {',
		'\techo 1;',
		'}',
		'',
	].join( '\n' );

	assert.deepEqual( inspect( source ), [] );
} );
