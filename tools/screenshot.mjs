#!/usr/bin/env node
/**
 * Render the theme in a real browser and write the images the marketplace (and
 * the WordPress theme screen) asks for.
 *
 * The authoring sandbox has no browser, so `wavira/screenshot.png` was missing
 * for three releases and `node tools/package.mjs --strict` failed on purpose
 * while it was (docs/RELEASE-CANDIDATE.md §6, item 1). CI has a browser and a
 * real WordPress install, which is where this runs: the `wp-render` job seeds
 * the Persian demo, serves the site and calls this file. A developer with Chrome
 * installed can run the same command against their own site.
 *
 * Nothing here is part of either package (`tools/` is excluded from both); this
 * is the measuring instrument, not the product.
 *
 * Usage:
 *   node tools/screenshot.mjs --url=http://127.0.0.1:8080/ --out=wavira/screenshot.png
 *
 * Options:
 *   --url=URL        the page to capture (required)
 *   --out=FILE       where the main screenshot goes (required; 1200×900 by default)
 *   --width=N        viewport width (default 1200 — WordPress's theme screenshot)
 *   --height=N       viewport height (default 900)
 *   --extra=DIR      also write `<dir>/<name>.png` per `--page`, plus `<dir>/home-dark.png`
 *   --page=name=URL  an extra page to capture (repeatable)
 *   --colour=SCHEME  `prefers-color-scheme` for the main shot: light | dark | auto (default light)
 *   --axe[=FILE]     run axe-core and write the report (default /tmp/wavira-axe.json)
 *   --chrome=PATH    the browser binary (default: CHROME_PATH, then the usual names)
 *   --timeout=MS     per-page timeout (default 30000)
 *   --settle=MS      how long a page may take to load its fonts and images (default 15000)
 *   --axe-timeout=MS how long one axe run may take (default 90000)
 *   --protocol-timeout=MS the browser's own call timeout (default 120000)
 *
 * Every wait is bounded and every phase is announced before it starts, because
 * the alternative was measured: 0.15.0's first render died with
 * `Runtime.callFunctionOn timed out` — one unbounded in-page wait, no line in
 * the log saying which page it was on, and nothing in the image set to read
 * afterwards (the artifact download is not always possible either).
 *
 * Exit status is 0 only when every image was written, every image has the size
 * it was asked for, and every page answered 200 with text on it. A screenshot
 * that is silently 1×1 or blank is worse than no screenshot: the marketplace
 * shows it and nobody looks at the bytes.
 *
 * `--axe` *measures* and never fails: the decision belongs to
 * `tools/check-axe.mjs`, so the screenshot and the report about it always come
 * from the same render even when the audit finds something.
 */

import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { dirname, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const ROOT = resolve( dirname( fileURLToPath( import.meta.url ) ), '..' );

/**
 * The PNG's pixel size, read from the IHDR chunk.
 *
 * The check exists so that "the file was written" is never mistaken for "the
 * file is an image of the right size": a browser that refused to load, a page
 * that rendered empty, or a clipped viewport all still produce a file.
 *
 * @param {Buffer|Uint8Array} bytes File contents.
 * @return {{width: number, height: number}|null} Size, or null when it is not a PNG.
 */
export function pngSize( bytes ) {
	const png = Buffer.from( bytes );

	if ( png.length < 24 ) {
		return null;
	}

	// The 8-byte signature, then `IHDR` (4), length (4), `IHDR` again (4), so
	// the width sits at 16 and the height at 20.
	if ( png.readUInt32BE( 0 ) !== 0x89504e47 || png.readUInt32BE( 4 ) !== 0x0d0a1a0a || png.toString( 'ascii', 12, 16 ) !== 'IHDR' ) {
		return null;
	}

	return { width: png.readUInt32BE( 16 ), height: png.readUInt32BE( 20 ) };
}

/**
 * Read `--name=value`, or `--name` as a bare flag.
 *
 * @param {string[]} args   Arguments, without `node script`.
 * @param {string}   name   Option name.
 * @param {*}        absent What to return when the option is not there.
 * @return {string|true|*} The value, `true`, or `absent`.
 */
export function option( args, name, absent = null ) {
	const withValue = args.find( ( a ) => a.startsWith( `--${ name }=` ) );

	if ( withValue ) {
		return withValue.slice( name.length + 3 );
	}

	return args.includes( `--${ name }` ) ? true : absent;
}

/**
 * Every occurrence of `--name=value`, in order.
 *
 * @param {string[]} args Arguments.
 * @param {string}   name Option name.
 * @return {string[]} Values.
 */
export function options( args, name ) {
	return args
		.filter( ( a ) => a.startsWith( `--${ name }=` ) )
		.map( ( a ) => a.slice( name.length + 3 ) );
}

/**
 * The three budgets this script runs inside.
 *
 * A browser call that never returns is the one failure that cannot be read from
 * the log afterwards — it stops the render mid-flight with a protocol error and
 * no picture — so each budget is a number here, validated, and covered by a test
 * instead of being a literal somewhere in the middle of `main()`.
 *
 * @param {string[]} args Arguments, without `node script`.
 * @return {{protocol: number, settle: number, axe: number}} Milliseconds.
 */
export function budgets( args ) {
	const read = ( name, fallback ) => {
		const raw = option( args, name, null );

		if ( null === raw || true === raw ) {
			return fallback;
		}

		const value = Number( raw );

		return Number.isFinite( value ) && value > 0 ? Math.floor( value ) : fallback;
	};

	return {
		protocol: read( 'protocol-timeout', 120000 ),
		settle: read( 'settle', 15000 ),
		axe: read( 'axe-timeout', 90000 ),
	};
}

/**
 * Turn the `--page=name=URL` values into shots, refusing a malformed one.
 *
 * @param {string[]} values Raw option values.
 * @return {{name: string, url: string}[]} Shots.
 */
export function parsePages( values ) {
	return values.map( ( value ) => {
		const split = value.indexOf( '=' );

		if ( -1 === split || 0 === split ) {
			throw new Error( `--page=${ value } must be --page=name=URL` );
		}

		const url = value.slice( split + 1 );

		// A relative path reaches Chrome as "Protocol error (Page.navigate):
		// Cannot navigate to invalid URL", which says nothing about what to fix
		// (`wp-render` passed `/albums/…` and failed exactly that way, 0.14.0).
		try {
			new URL( url );
		} catch {
			throw new Error( `--page=${ value }: “${ url }” is not an absolute URL — a page must start with http:// or https://` );
		}

		return { name: value.slice( 0, split ), url };
	} );
}

/**
 * Find a Chrome/Chromium binary.
 *
 * @param {string|null} explicit A `--chrome=` value, if any.
 * @return {string|null} An executable path, or null when there is none.
 */
export function findChrome( explicit = null ) {
	const set = explicit || process.env.CHROME_PATH;

	if ( set ) {
		return existsSync( set ) ? set : null;
	}

	const candidates = [
		'google-chrome',
		'google-chrome-stable',
		'chromium',
		'chromium-browser',
		'/usr/bin/google-chrome',
		'/opt/google/chrome/chrome',
		'/usr/bin/chromium',
		'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
	];

	for ( const candidate of candidates ) {
		try {
			const found = candidate.startsWith( '/' )
				? existsSync( candidate )
					? candidate
					: null
				: execFileSync( 'which', [ candidate ], { encoding: 'utf8' } ).trim();

			if ( found ) {
				return found;
			}
		} catch {
			// Not this one.
		}
	}

	return null;
}

/**
 * Decide whether a page's stylesheet actually reached the browser.
 *
 * The two facts are different: a page can link the theme stylesheet and still be
 * unstyled (the file 404s, or a WordPress router answers with the front page's
 * HTML), and a page without a single stylesheet can render perfectly legibly —
 * which is exactly how ten unstyled screenshots shipped in 0.14.0. Both failures
 * produce a plausible-looking image, so this returns a *reason*, not a boolean,
 * and the caller refuses the picture.
 *
 * @param {{stylesheets: string[], rules: number}} meta What the page reported.
 * @return {string} An empty string when the theme's CSS is in effect, else why not.
 */
export function stylesheetVerdict( meta ) {
	const sheets = Array.isArray( meta.stylesheets ) ? meta.stylesheets : [];
	const themeSheet = sheets.find( ( href ) => String( href ).includes( 'assets/dist/theme.css' ) );

	if ( ! themeSheet ) {
		return (
			'the page does not link the theme stylesheet (assets/dist/theme.css); it links ' +
			( sheets.length ? sheets.join( ', ' ) : 'no stylesheet at all' ) +
			' — an unstyled page is not a screenshot of the theme'
		);
	}

	if ( Number( meta.rules ) < 50 ) {
		return (
			`the page links ${ themeSheet } but only ${ meta.rules } CSS rule(s) are in effect — ` +
			'the stylesheet did not load (a WordPress router answers a request for a missing file with the front page as text/html)'
		);
	}

	return '';
}

/**
 * Decide whether the player a page mounts has anything in it to play.
 *
 * The theme prints a mount point and Wavira Core's engine fills it with its own
 * `<audio>` and buttons. Between those two facts a page can look finished and
 * have nothing to play: the markup is there, the section has a height, and the
 * engine never ran — a thrown exception, a script that did not load, a payload
 * that did not arrive. A screenshot of that page is a screenshot of an empty
 * section, and for a music theme that is the one thing it must not be.
 *
 * A page without a mount is not this check's business: not every template has a
 * player.
 *
 * @param {{players?: Array<{controls: number}>}} meta What the page reported.
 * @return {string} An empty string when every mount has controls, else why not.
 */
export function playerVerdict( meta ) {
	const mounts = Array.isArray( meta.players ) ? meta.players : [];

	if ( mounts.length === 0 ) {
		return '';
	}

	if ( mounts.some( ( mount ) => Number( mount.controls ) > 0 ) ) {
		return '';
	}

	return (
		`the page mounts ${ mounts.length } player(s) and none of them contains a control — ` +
		'the engine did not run (a script that did not load, or an exception), so the section shows nothing to play'
	);
}

/**
 * Run the capture with the arguments given.
 *
 * @param {string[]} args Arguments, without `node script`.
 * @return {Promise<number>} Exit status.
 */
export async function main( args ) {
	/**
	 * Fail with a message a human can act on.
	 *
	 * @param {string} message What went wrong.
	 * @return {never} Never returns.
	 */
	const fail = ( message ) => {
		process.stderr.write( `screenshot: ${ message }\n` );
		process.exit( 1 );
	};

	const url = option( args, 'url' );
	const out = option( args, 'out' );

	if ( ! url || ! out ) {
		fail( 'usage: node tools/screenshot.mjs --url=http://site.test/ --out=wavira/screenshot.png' );
	}

	const width = Number( option( args, 'width', 1200 ) );
	const height = Number( option( args, 'height', 900 ) );
	const colour = String( option( args, 'colour', 'light' ) );
	const timeout = Number( option( args, 'timeout', 30000 ) );
	const extraDir = option( args, 'extra' );
	const axeOption = option( args, 'axe', false );
	const axeFile = axeOption ? String( true === axeOption ? '/tmp/wavira-axe.json' : axeOption ) : null;
	const budget = budgets( args );
	const quiet = '1' === process.env.WAVIRA_SCREENSHOT_QUIET;

	/**
	 * Say what is about to happen, before it happens.
	 *
	 * A run that dies inside a browser call leaves no other trace: the notes are
	 * printed at the end, so the note list of a failed run is empty and the log
	 * shows only the crash. One line per phase costs nothing and names the page.
	 *
	 * @param {string} message What is starting.
	 * @return {void}
	 */
	const say = ( message ) => {
		if ( ! quiet ) {
			process.stdout.write( `  → ${ message }\n` );
		}
	};

	let pages = [];

	try {
		pages = parsePages( options( args, 'page' ) );
	} catch ( error ) {
		fail( error.message );
	}

	const chrome = findChrome( option( args, 'chrome' ) );

	if ( ! chrome ) {
		fail(
			'no Chrome or Chromium found. Set CHROME_PATH, or pass --chrome=/path/to/chrome. ' +
				'On a machine without one, this is what the `wp-render` CI job is for.'
		);
	}

	const require = createRequire( import.meta.url );
	let puppeteer = null;

	for ( const candidate of [ 'puppeteer-core', resolve( ROOT, 'node_modules/puppeteer-core' ) ] ) {
		try {
			const loaded = await import( pathToFileURL( require.resolve( candidate ) ).href );

			puppeteer = loaded.default ?? loaded;
			break;
		} catch {
			// Try the next one.
		}
	}

	if ( ! puppeteer ) {
		fail( 'puppeteer-core is not installed. Run: npm install --no-save --prefix /tmp/render puppeteer-core, then NODE_PATH=/tmp/render/node_modules.' );
	}

	const notes = [];

	// The launch is the first thing that can fail on a machine that has a browser
	// binary but not the libraries it links against, and an unhandled rejection
	// there says nothing a reader can act on. Say which browser, and say what it
	// said.
	let browser;

	try {
		browser = await puppeteer.launch( {
			executablePath: chrome,
			headless: true,
			// Every CDP call is bounded. The default is generous, and a call that
			// has not answered in two minutes has not been answered (0.15.0).
			protocolTimeout: budget.protocol,
			args: [
				'--no-sandbox',
				'--disable-dev-shm-usage',
				'--hide-scrollbars',
				'--force-color-profile=srgb',
				'--font-render-hinting=none',
				'--disable-lcd-text',
			],
		} );
	} catch ( error ) {
		fail( `could not launch ${ chrome }: ${ String( error.message ).split( '\n' ).slice( 0, 4 ).join( ' ' ) }` );
	}

	if ( process.env.WAVIRA_SCREENSHOT_QUIET !== '1' ) {
		const version = await browser.version().catch( () => 'unknown version' );

		process.stdout.write( `browser: ${ version } (${ chrome })\n` );
	}

	// Every page this script opens gets the determinism style before the page's
	// own scripts run: a hover style, a transition or a blinking caret would
	// make two runs of the same page produce two different files, and the gate
	// that watches for a changed screenshot would never stop firing.
	const openPage = browser.newPage.bind( browser );

	browser.newPage = async () => {
		const page = await openPage();

		await page.evaluateOnNewDocument( () => {
			document.addEventListener( 'DOMContentLoaded', () => {
				const style = document.createElement( 'style' );

				style.textContent =
					'*,*::before,*::after{animation:none !important;transition:none !important;caret-color:transparent !important}';

				document.head.appendChild( style );
			} );
		} );

		return page;
	};

	const viewport = { width, height };

	/**
	 * Render one page at one size and write the PNG.
	 *
	 * @param {{file: string, url: string, scheme: string}} shot Shot description.
	 * @return {Promise<Object>} The open page plus what was written.
	 */
	const capture = async ( shot ) => {
		say( `render ${ shot.file } ← ${ shot.url } (${ shot.scheme })` );

		const page = await browser.newPage();

		await page.setViewport( { ...viewport, deviceScaleFactor: 1 } );

		if ( 'auto' !== shot.scheme ) {
			await page.emulateMediaFeatures( [ { name: 'prefers-color-scheme', value: shot.scheme } ] );
		}

		// An uncaught exception on the page is invisible in a PNG and usually
		// explains a section that renders empty: collect it and fail on it.
		const crashes = [];

		page.on( 'pageerror', ( error ) => {
			crashes.push( String( error && error.message ? error.message : error ).split( '\n' )[ 0 ] );
		} );

		const response = await page.goto( shot.url, { waitUntil: 'load', timeout } );
		const status = response ? response.status() : 0;

		// Fonts and images are waited for, but not forever: `document.fonts.ready`
		// and `image.decode()` both wait on a network request that may never
		// settle (a stalled font, an image behind a saturated server), and waiting
		// on that is what turns a slow page into a dead render. The budget is the
		// licence to take the picture anyway; the note says it was taken early.
		const settled = await page.evaluate( async ( settle ) => {
			const bounded = ( promise ) =>
				Promise.race( [
					Promise.resolve( promise ).catch( () => {} ),
					new Promise( ( resolve ) => setTimeout( resolve, settle ) ),
				] );

			await bounded( document.fonts.ready );
			await bounded(
				Promise.all(
					Array.from( document.images ).map( ( image ) =>
						image.decode ? image.decode().catch( () => {} ) : Promise.resolve()
					)
				)
			);
			window.scrollTo( 0, 0 );

			// Read after the wait: anything still incomplete is what was waited on.
			return Array.from( document.images ).filter( ( image ) => ! image.complete ).length;
		}, budget.settle );

		if ( settled > 0 ) {
			notes.push(
				`${ shot.file } — ${ settled } image(s) were still loading after ${ budget.settle }ms; the picture is of a page that had not finished`
			);
		}

		// The page's own language and direction are reported so a screenshot of
		// the wrong direction is visible in the log rather than in the file —
		// and so is whether the theme's own stylesheet actually applied, which is
		// the difference between a screenshot of the theme and a screenshot of
		// unstyled HTML. "The file was requested" and "the rules are in effect"
		// are two different facts, and only the second one is worth photographing
		// (0.14.0 shipped ten unstyled images because nothing checked it).
		const meta = await page.evaluate( () => {
			const rules = Array.from( document.styleSheets ).reduce( ( total, sheet ) => {
				try {
					return total + sheet.cssRules.length;
				} catch {
					return total; // A cross-origin sheet cannot be read; not ours.
				}
			}, 0 );

			const stylesheets = Array.from( document.querySelectorAll( 'link[rel="stylesheet"]' ) ).map(
				( link ) => link.getAttribute( 'href' ) || ''
			);

			// The player the theme mounts, and what is inside it. A music theme's
			// page can be complete and have nothing to play — see
			// `playerVerdict()`.
			const players = Array.from( document.querySelectorAll( '[data-wavira-player]' ) ).map( ( mount ) => ( {
				controls: mount.querySelectorAll( 'button, audio, input, select' ).length,
			} ) );

			return {
				lang: document.documentElement.lang,
				dir: document.documentElement.dir || 'ltr',
				height: document.documentElement.scrollHeight,
				text: ( document.body.innerText || '' ).trim().slice( 0, 200 ),
				rules: rules,
				stylesheets: stylesheets,
				players: players,
			};
		} );

		const bytes = await page.screenshot( { type: 'png' } );

		mkdirSync( dirname( resolve( shot.file ) ), { recursive: true } );
		writeFileSync( shot.file, bytes );

		const size = pngSize( bytes );

		notes.push(
			`${ shot.file } — ${ size ? `${ size.width }×${ size.height }` : 'NOT A PNG' }, ${ bytes.length } B, ` +
				`HTTP ${ status }, lang=${ meta.lang || '(none)' } dir=${ meta.dir }, ${ meta.height }px tall, ` +
				`${ meta.rules } CSS rule(s) in effect, ${ meta.players.length } player(s)`
		);

		if ( ! size ) {
			fail( `${ shot.file } is not a PNG` );
		}

		if ( size.width !== width || size.height !== height ) {
			fail( `${ shot.file } is ${ size.width }×${ size.height }, expected ${ width }×${ height }` );
		}

		if ( 200 !== status ) {
			fail( `${ shot.url } answered HTTP ${ status }` );
		}

		if ( meta.text.length < 40 ) {
			fail( `${ shot.file } looks blank (${ meta.text.length } characters of visible text) — the page did not render` );
		}

		// The theme's stylesheet has to be *linked* and *applied*. A page whose
		// CSS 404s (or comes back as HTML, which is what a WordPress router does
		// for a file that is not there) still renders, renders legibly, and
		// photographs as a wall of black text on white — the most expensive kind
		// of wrong image, because it looks like a finished render.
		const styling = stylesheetVerdict( meta );

		if ( '' !== styling ) {
			fail( `${ shot.url }: ${ styling }` );
		}

		const playing = playerVerdict( meta );

		if ( '' !== playing ) {
			fail( `${ shot.url }: ${ playing }` );
		}

		if ( crashes.length > 0 ) {
			fail( `${ shot.url } threw ${ crashes.length } JavaScript error(s) — the page is not the page the theme intends: ${ crashes[ 0 ] }` );
		}

		return { page, file: shot.file, bytes: bytes.length };
	};

	const written = [];

	try {
		const home = await capture( { file: resolve( ROOT, String( out ) ), url: String( url ), scheme: colour } );

		written.push( home.file );

		if ( extraDir ) {
			const dir = resolve( ROOT, String( extraDir ) );

			for ( const target of pages ) {
				const shot = await capture( { file: resolve( dir, `${ target.name }.png` ), url: target.url, scheme: 'light' } );

				written.push( shot.file );
				await shot.page.close();
			}

			const dark = await capture( { file: resolve( dir, 'home-dark.png' ), url: String( url ), scheme: 'dark' } );

			written.push( dark.file );
			await dark.page.close();
		}

		if ( axeFile ) {
			const axePath = require.resolve( 'axe-core/axe.min.js' );
			const targets = [ { name: 'home', url: String( url ) } ].concat( pages );
			const report = { tool: 'axe-core', generated_for: String( url ), pages: [] };

			for ( const target of targets ) {
				say( `axe ${ target.name} ← ${ target.url }` );

				const page = await browser.newPage();

				await page.setViewport( { ...viewport, deviceScaleFactor: 1 } );
				await page.goto( target.url, { waitUntil: 'load', timeout } );
				await page.addScriptTag( { path: axePath } );

				// `axe.run()` is the heaviest in-page call this script makes and
				// the one an unbounded wait has already killed a render with. It
				// gets a budget of its own, and a run that blows it is recorded as
				// a finding of a different kind rather than as silence: the report
				// has to say which page could not be measured.
				const measured = await page.evaluate( async ( axeBudget ) => {
					const outcome = await Promise.race( [
						window.axe.run( document, { resultTypes: [ 'violations' ] } ),
						new Promise( ( resolve ) => setTimeout( () => resolve( null ), axeBudget ) ),
					] );

					if ( ! outcome ) {
						return { timedOut: true };
					}

					return {
						timedOut: false,
						violations: outcome.violations.map( ( v ) => ( { id: v.id, impact: v.impact, help: v.help, nodes: v.nodes.length } ) ),
					};
				}, budget.axe );

				if ( measured.timedOut ) {
					fail( `axe did not finish on ${ target.name } within ${ budget.axe }ms — the page could not be measured` );
				}

				report.pages.push( { name: target.name, url: target.url, violations: measured.violations } );
				await page.close();
			}

			writeFileSync( resolve( ROOT, axeFile ), `${ JSON.stringify( report, null, '\t' ) }\n` );

			for ( const page of report.pages ) {
				notes.push(
					`axe — ${ page.name }: ${ page.violations.length } violation type(s) — ` +
						( page.violations.map( ( v ) => `${ v.id }(${ v.impact })` ).join( ', ' ) || 'none' )
				);
			}

			// The verdict is `tools/check-axe.mjs`'s; this file only records what
			// axe saw, so a finding cannot cost the screenshot it was measured on.
			const serious = report.pages
				.flatMap( ( page ) => page.violations.map( ( v ) => ( { ...v, page: page.name } ) ) )
				.filter( ( v ) => 'serious' === v.impact || 'critical' === v.impact );

			if ( serious.length ) {
				notes.push(
					`axe — ${ serious.length } serious/critical finding(s) for tools/check-axe.mjs to judge: ` +
						serious.map( ( v ) => `${ v.page }:${ v.id }` ).join( ', ' )
				);
			}
		}
	} finally {
		await browser.close().catch( () => {} );
	}

	for ( const note of notes ) {
		process.stdout.write( `  · ${ note }\n` );
	}

	process.stdout.write( `screenshot: OK — ${ written.length } image(s)${ axeFile ? `, axe report ${ axeFile }` : '' }\n` );

	return 0;
}

// Only run when executed, not when imported by a test.
//
// The rejection handler matters as much as the call: an unhandled rejection in
// Node 20 is a crash dump whose last line is the Node version, and the reason is
// somewhere in the middle of it — which is how the first two runs of `wp-render`
// failed with nothing legible to show (0.14.0). Anything this script does not
// expect says what it was doing when it broke.
if ( process.argv[ 1 ] && import.meta.url === pathToFileURL( process.argv[ 1 ] ).href ) {
	main( process.argv.slice( 2 ) )
		.then( ( status ) => process.exit( status ) )
		.catch( ( error ) => {
			const where = String( error.stack || '' ).split( '\n' ).slice( 0, 6 ).join( '\n    ' );

			process.stderr.write( `screenshot: unexpected ${ error.name || 'error' }: ${ error.message }\n    ${ where }\n` );
			process.exit( 1 );
		} );
}
