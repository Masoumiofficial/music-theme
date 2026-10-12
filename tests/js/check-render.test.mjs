/**
 * Unit tests for the rendered-page gate (0.14.0).
 *
 * `tools/check-render.mjs` decides whether a page the `wp-render` job fetched is
 * the Persian front page it claims to be. The tool reads the expected strings
 * from the shipped catalogue rather than from a list typed into CI, so these
 * tests pin both halves: the catalogue reader and the page comparison — including
 * the case that made this gate exist, a root page whose headings are English.
 *
 * The last test runs the gate against the repository's own `fa_IR.po`, so a
 * heading that loses its translation fails here rather than on a runner.
 *
 *   node --test tests/js/check-render.test.mjs
 *
 * @package Wavira
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

import {
	FRONT_PAGE_HEADINGS,
	attributeValue,
	checkPage,
	option,
	plain,
	translations,
} from '../../tools/check-render.mjs';
import { option as screenshotOption } from '../../tools/screenshot.mjs';

const CLI = fileURLToPath( new URL( '../../tools/check-render.mjs', import.meta.url ) );
const CATALOGUE_PATH = fileURLToPath( new URL( '../../wavira/languages/fa_IR.po', import.meta.url ) );
const ZWNJ = '\u200c';

/** A catalogue with two headings, one of them translated twice. */
const CATALOGUE = [
	'msgid ""',
	'msgstr "Content-Type: text/plain; charset=UTF-8\\n"',
	'',
	'msgid "Latest albums"',
	'msgstr "جدیدترین آلبوم‌ها"',
	'',
	'#: another template',
	'msgid "Latest albums"',
	'msgstr "آلبوم‌های تازه"',
	'',
	'msgid "Listen now"',
	'msgstr "همین حالا بشنوید"',
	'',
].join( '\n' );

/** The two assets every real page carries: the theme's own stylesheet and script. */
const DRESSED = ( body ) =>
	'<link rel="stylesheet" href="/wp-content/themes/wavira/assets/dist/theme.css?ver=1">' +
	'<script src="/wp-content/themes/wavira/assets/dist/theme.js?ver=1"></script>' +
	body;

/** A page that contains the first translation of the first heading. */
const PAGE = `<main class="wavira-section">جدیدترین آلبوم${ ZWNJ }ها</main>`;

test( 'the catalogue reader finds every translation of a source string', () => {
	assert.deepEqual( translations( CATALOGUE, 'Latest albums' ), [ 'جدیدترین آلبوم‌ها', 'آلبوم‌های تازه' ] );
	assert.deepEqual( translations( CATALOGUE, 'Listen now' ), [ 'همین حالا بشنوید' ] );
} );

test( 'the catalogue reader does not confuse one source string with another', () => {
	// "Listen" is a prefix of "Listen now": a naive `includes` would match it.
	assert.deepEqual( translations( CATALOGUE, 'Listen' ), [] );
	assert.deepEqual( translations( CATALOGUE, 'Latest' ), [] );
	assert.deepEqual( translations( CATALOGUE, 'Missing entirely' ), [] );
} );

test( 'the catalogue reader treats a source string as a literal, not a pattern', () => {
	const catalogue = 'msgid "Play (all)"\nmsgstr "پخش (همه)"\n';

	assert.deepEqual( translations( catalogue, 'Play (all)' ), [ 'پخش (همه)' ] );
	assert.deepEqual( translations( catalogue, 'Play all' ), [] );
} );

test( 'the zero-width non-joiner is a typographic choice, not a difference', () => {
	assert.equal( plain( `آلبوم${ ZWNJ }ها` ), 'آلبومها' );
	assert.equal( plain( 'بدون' ), 'بدون' );
} );

test( 'a page with the translations on it passes, and says what it found', () => {
	const result = checkPage( {
		page: DRESSED( `<div class="wavira-section">جدیدترین آلبومها همین حالا بشنوید</div>` ),
		catalogue: CATALOGUE,
		headings: [ 'Latest albums', 'Listen now' ],
		minSections: 1,
	} );

	assert.equal( result.problems.length, 0 );
	assert.equal( result.found.length, 2 );
	assert.match( result.found[ 0 ], /^Latest albums → / );
} );

test( 'a translation on the page without the ZWNJ still counts', () => {
	// The catalogue writes «جدیدترین آلبومها»; the page may render «جدیدترین آلبومها».
	const result = checkPage( {
		page: DRESSED( PAGE ),
		catalogue: CATALOGUE.replace( 'جدیدترین آلبوم‌ها', 'جدیدترین آلبومها' ),
		headings: [ 'Latest albums' ],
		minSections: 0,
	} );

	assert.deepEqual( result.problems, [] );
} );

test( 'a heading the catalogue does not translate is a distinct problem', () => {
	const result = checkPage( {
		page: DRESSED( PAGE ),
		catalogue: CATALOGUE,
		headings: [ 'Latest albums', 'Nowhere to be found' ],
		minSections: 0,
	} );

	assert.equal( result.problems.length, 1 );
	assert.match( result.problems[ 0 ], /the catalogue has no Persian translation for “Nowhere to be found”/ );
} );

test( 'an English page is refused, with the translation it should have had', () => {
	// This is the defect the gate exists for: a root page that renders the theme's
	// source strings because the locale never loaded.
	const result = checkPage( {
		page: DRESSED( '<div class="wavira-section"><h2>Latest albums</h2><h2>Listen now</h2></div>' ),
		catalogue: CATALOGUE,
		headings: [ 'Latest albums', 'Listen now' ],
		minSections: 1,
	} );

	assert.equal( result.found.length, 0 );
	assert.equal( result.problems.length, 3 );
	assert.equal(
		result.problems[ 0 ],
		`the page does not contain “Latest albums” as “${ translations( CATALOGUE, 'Latest albums' )[ 0 ] }”`,
	);
	assert.match( result.problems[ 2 ], /no Persian text at all/ );
} );

test( 'a page with too few sections, or no Persian at all, is refused', () => {
	const empty = checkPage( {
		page: DRESSED( '<html><body>Nothing here</body></html>' ),
		catalogue: CATALOGUE,
		headings: [],
	} );

	assert.equal( empty.problems.length, 2 );
	assert.match( empty.problems[ 0 ], /the page has 0 wavira-section element\(s\), expected at least 4/ );
	assert.match( empty.problems[ 1 ], /no Persian text at all/ );

	// Three sections is the news-only home page the theme shipped until 0.14.0.
	const blogIndex = checkPage( {
		page: DRESSED( '<div class="wavira-section">جدیدترین آلبومها</div>'.repeat( 3 ) ),
		catalogue: CATALOGUE,
		headings: [],
	} );

	assert.equal( blogIndex.problems.length, 1 );
	assert.match( blogIndex.problems[ 0 ], /the page has 3 wavira-section element\(s\)/ );
} );

test( 'the option reader takes values, flags and repeats', () => {
	const args = [ '--page=/tmp/a.html', '--catalogue=x.po', '--verbose', '--page=/tmp/b.html' ];

	assert.equal( option( args, 'page' ), '/tmp/a.html' );
	assert.equal( option( args, 'catalogue' ), 'x.po' );
	assert.equal( option( args, 'verbose' ), true );
	assert.equal( option( args, 'missing', 'fallback' ), 'fallback' );

	// The space form is not a value: a bare flag reads as `true` in both tools.
	assert.equal( option( [ '--catalogue', 'x.po' ], 'catalogue' ), true );
} );

test( 'both command-line tools read their options the same way', () => {
	// Two parsers in one repository is one too many: the page gate and the
	// renderer have to answer identically, or CI fails on a formatting difference.
	const samples = [
		[ '--page=/tmp/x.html' ],
		[ '--page', '/tmp/x.html' ],
		[ '--verbose' ],
		[ '--page=/tmp/x.html', '--page=/tmp/y.html' ],
		[],
	];

	for ( const sample of samples ) {
		for ( const name of [ 'page', 'verbose', 'catalogue' ] ) {
			assert.deepEqual(
				option( sample, name ),
				screenshotOption( sample, name ),
				`option( ${ JSON.stringify( sample ) }, '${ name }' ) disagrees with tools/screenshot.mjs`,
			);
		}
	}
} );

test( 'the command line asks for `=` when a value is written as a separate word', () => {
	const run = spawnSync( process.execPath, [ CLI, '--page', '/tmp/x.html' ], { encoding: 'utf8' } );

	assert.equal( run.status, 2 );
	assert.match( run.stderr, /values are attached with `=`/ );
} );

test( 'the landmarks a page carries are counted, not guessed', () => {
	const result = checkPage( {
		page: DRESSED( '<main class=\"wavira-section\"><h1>واویرا</h1><nav></nav><nav></nav><footer></footer><footer></footer></main>' ),
		catalogue: CATALOGUE,
		headings: [],
		minSections: 1,
	} );

	assert.deepEqual( result.counts, { h1: 1, footer: 2, nav: 2, main: 1 } );
} );

test( 'a page without the theme stylesheet is refused, not photographed', () => {
	// The first render looked almost right with core's defaults: the search button
	// is styled by core, the headings by theme.json. The stylesheet is the thing
	// that says the theme rendered rather than merely the blocks.
	const bare = checkPage( { page: PAGE, catalogue: CATALOGUE, headings: [], minSections: 0 } );

	assert.equal( bare.problems.length, 2 );
	assert.match( bare.problems[ 0 ], /does not load the theme stylesheet \(assets\/dist\/theme\.css\)/ );
	assert.match( bare.problems[ 1 ], /does not load the theme script \(assets\/dist\/theme\.js\)/ );

	const dressed = checkPage( {
		page: DRESSED( PAGE ),
		catalogue: CATALOGUE,
		headings: [],
		minSections: 0,
	} );

	assert.deepEqual( dressed.problems, [] );
} );

test( 'the front-page banner is its visible h1, not a hidden heading', () => {
	// The site title is the visitor-facing banner, not a visually hidden heading
	// added only to silence axe. The page has one clear h1 and an obvious action.
	const template = readFileSync( fileURLToPath( new URL( '../../wavira/templates/front-page.html', import.meta.url ) ), 'utf8' );
	const banner = readFileSync( fileURLToPath( new URL( '../../wavira/patterns/hero-banner.php', import.meta.url ) ), 'utf8' );

	assert.match( template, /wp:pattern \{"slug":"wavira\/hero-banner"\} \/\-->/ );
	assert.doesNotMatch( template, /wavira-visually-hidden/ );
	assert.match( banner, /wp:site-title \{"level":1,"className":"wavira-banner__title"\} \/\-->/ );
	assert.match( banner, /href="#listen-now"/ );
} );

test( 'a wp-content URL the render cannot reach is named, with the URL to fix', () => {
	const page = DRESSED( '<div class="wavira-section"></div>' );
	const bent = ( result ) => result.problems.filter( ( problem ) => /wp-content URL/.test( problem ) );

	// The suffix of `--path=/tmp/site`: the site URL carries `/site`, so every URL
	// the site builds points at a path nothing serves. The stylesheet arrives as
	// HTML, the browser refuses it, and the page renders unstyled while every
	// HTML-level check still passes — which is what four screenshots shipped as.
	const shifted = page
		.replace( 'href="/wp-content', 'href="http://127.0.0.1:8080/site/wp-content' )
		.replace( 'src="/wp-content', 'src="http://127.0.0.1:8080/site/wp-content' );
	const named = bent( checkPage( { page: shifted, catalogue: CATALOGUE, origin: 'http://127.0.0.1:8080' } ) );

	assert.equal( named.length, 1 );
	assert.match( named[ 0 ], /\/site\/wp-content/ );
	assert.match( named[ 0 ], /expected it to start with http:\/\/127\.0\.0\.1:8080\/wp-content\// );

	// The same URLs on the origin the page came from: nothing to say.
	assert.equal(
		bent(
			checkPage( {
				page: page
					.replace( 'href="/wp-content', 'href="http://127.0.0.1:8080/wp-content' )
					.replace( 'src="/wp-content', 'src="http://127.0.0.1:8080/wp-content' ),
				catalogue: CATALOGUE,
				origin: 'http://127.0.0.1:8080',
			} )
		).length,
		0
	);

	// No origin to compare against: the check stays quiet rather than guessing.
	assert.equal( bent( checkPage( { page: shifted, catalogue: CATALOGUE } ) ).length, 0 );

	// An external link is not a defect — the theme's footer thanks its author.
	assert.equal(
		bent(
			checkPage( {
				page: DRESSED( '<a href="https://example.org/credits">طراحی و توسعه</a>' ),
				catalogue: CATALOGUE,
				origin: 'http://127.0.0.1:8080',
			} )
		).length,
		0
	);
} );

test( 'the demo images are checked against the origin as well', () => {
	const shifted = checkPage( {
		page: DRESSED( '<img src="http://127.0.0.1:8080/site/wp-content/uploads/2026/10/cover.png" alt="طرح جلد">' ),
		catalogue: CATALOGUE,
		origin: 'http://127.0.0.1:8080',
	} ).problems.filter( ( problem ) => /wp-content URL/.test( problem ) );

	assert.equal( shifted.length, 1 );
	assert.match( shifted[ 0 ], /127\.0\.0\.1:8080\/site\/wp-content\/uploads/ );

	// A relative one resolves against the origin and is fine.
	assert.equal(
		checkPage( {
			page: DRESSED( '<img src="/wp-content/uploads/2026/10/cover.png" alt="طرح جلد">' ),
			catalogue: CATALOGUE,
			origin: 'http://127.0.0.1:8080',
		} ).problems.filter( ( problem ) => /wp-content URL/.test( problem ) ).length,
		0
	);
} );

test( 'the command line refuses to run without a page', () => {
	const run = spawnSync( process.execPath, [ CLI ], { encoding: 'utf8' } );

	assert.equal( run.status, 2 );
	assert.match( run.stderr, /usage: node tools\/check-render\.mjs --page=/ );
} );

test( 'the command line fails loudly on a page it cannot read', () => {
	const run = spawnSync( process.execPath, [ CLI, '--page=/definitely/not/here.html' ], { encoding: 'utf8' } );

	assert.equal( run.status, 1 );
	assert.match( run.stdout + run.stderr, /::error title=Rendered theme::/ );
} );

test( 'the command line passes a Persian page and fails an English one', () => {
	const directory = mkdtempSync( join( tmpdir(), 'wavira-render-' ) );
	const persian = join( directory, 'fa.html' );
	const english = join( directory, 'en.html' );
	const sections = ( heading ) => `<div class="wavira-section"><h2>${ heading }</h2></div>`.repeat( 4 );

	writeFileSync( persian, DRESSED( sections( 'جدیدترین آلبومها همین حالا بشنوید جدیدترین قطعهها موزیکویدیوها اخبار موسیقی' ) ) );
	writeFileSync( english, DRESSED( sections( 'Latest albums' ) ) );

	const good = spawnSync(
		process.execPath,
		[ CLI, `--page=${ persian }`, `--catalogue=${ CATALOGUE_PATH }` ],
		{ encoding: 'utf8' },
	);

	assert.equal( good.status, 0, good.stderr );
	assert.match( good.stdout, /the front page is Persian, with the theme’s own headings \(5\)/ );

	const bad = spawnSync(
		process.execPath,
		[ CLI, `--page=${ english }`, `--catalogue=${ CATALOGUE_PATH }` ],
		{ encoding: 'utf8' },
	);

	assert.equal( bad.status, 1 );
	assert.match( bad.stdout, /::error title=Rendered theme::front page: the page does not contain/ );
} );

test( 'the repository translates every heading the front page uses', () => {
	const catalogue = readFileSync( CATALOGUE_PATH, 'utf8' );

	for ( const heading of FRONT_PAGE_HEADINGS ) {
		const known = translations( catalogue, heading );

		assert.ok( known.length > 0, `wavira/languages/fa_IR.po has no translation for “${ heading }”` );
		assert.ok( known[ 0 ].length > 0, `the translation of “${ heading }” is empty` );
	}
} );

test( 'the front page uses exactly the headings the gate checks', () => {
	const template = readFileSync( fileURLToPath( new URL( '../../wavira/templates/front-page.html', import.meta.url ) ), 'utf8' );
	const patterns = [ ...template.matchAll( /"slug":"wavira\/hidden-heading-([a-z-]+)"/g ) ].map( ( m ) => m[ 1 ] );

	// The gate is only as good as its list: a new section whose heading is not in
	// FRONT_PAGE_HEADINGS would render untranslated and no test would notice.
	const patternHeadings = {
		'latest-albums': 'Latest albums',
		'listen-now': 'Listen now',
		'latest-tracks': 'Latest tracks',
		'music-videos': 'Music videos',
		'music-news': 'Music news',
	};

	assert.deepEqual( patterns.map( ( name ) => patternHeadings[ name ] ).sort(), [ ...FRONT_PAGE_HEADINGS ].sort() );
} );

test( 'home page gives tracks an in-place play button and the section a real archive link', () => {
	const template = readFileSync( fileURLToPath( new URL( '../../wavira/templates/front-page.html', import.meta.url ) ), 'utf8' );
	const trackHeading = readFileSync( fileURLToPath( new URL( '../../wavira/patterns/hidden-heading-latest-tracks.php', import.meta.url ) ), 'utf8' );
	const tracklist = readFileSync( fileURLToPath( new URL( '../../wavira/inc/markup.php', import.meta.url ) ), 'utf8' );

	assert.match( template, /wp:wavira\/tracklist \{\"source\":\"latest\",\"limit\":6/ );
	assert.match( trackHeading, /get_post_type_archive_link\( 'wavira_track' \)/ );
	assert.match( tracklist, /data-wavira-play=/ );
	assert.match( tracklist, /data-wavira-context=\"tracks\"/ );
} );
