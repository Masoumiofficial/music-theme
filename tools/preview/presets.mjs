/**
 * Emit the CSS custom properties WordPress generates from `wavira/theme.json`.
 *
 * The harness runs without WordPress, so the preset variables the theme CSS
 * consumes (`--wp--preset--color--*`, `--wp--preset--spacing--*`,
 * `--wp--preset--shadow--*`, `--wp--preset--font-size--*`,
 * `--wp--preset--font-family--*`, `--wp--custom--*` and the layout variables)
 * have to be produced here.
 *
 * Naming follows core exactly: preset slugs and custom keys are kebab-cased and
 * nested custom keys are joined with `--`
 * (`WP_Theme_JSON::flatten_tree()` → `strtolower( _wp_to_kebab_case( $property ) )`,
 * `_wp_to_kebab_case()`). Values are emitted verbatim — core's fluid-typography
 * conversion (`typography.fluid`) is *not* reproduced, so on a live site the
 * font-size presets may resolve to a `clamp()` instead. Everything the layout
 * depends on (colours, spacing, shadows, radii, custom properties) is exact.
 */

import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const THEME_JSON = resolve( import.meta.dirname, '..', '..', 'wavira/theme.json' );

/**
 * Kebab-case a theme.json key the way WordPress does.
 *
 * @param {string} key Raw key.
 * @return {string} Kebab-cased key.
 */
export function kebab( key ) {
	return String( key )
		.replace( /([a-z0-9])([A-Z])/g, '$1-$2' )
		.replace( /[_/]/g, '-' )
		.toLowerCase();
}

/**
 * Flatten `settings.custom` into custom-property declarations.
 *
 * @param {Object} tree  Custom settings tree.
 * @param {string} prefix Current path prefix.
 * @param {Array}  out   Collected declarations.
 * @return {Array} Declarations of `[ name, value ]` pairs.
 */
function flatten( tree, prefix, out ) {
	for ( const [ key, value ] of Object.entries( tree ) ) {
		const name = prefix ? `${ prefix }--${ kebab( key ) }` : kebab( key );

		if ( value && 'object' === typeof value && ! Array.isArray( value ) ) {
			flatten( value, name, out );
		} else {
			out.push( [ `--wp--custom--${ name }`, String( value ) ] );
		}
	}

	return out;
}

/**
 * Build the harness preset stylesheet.
 *
 * @return {string} CSS text.
 */
export function presetsCss() {
	const theme = JSON.parse( readFileSync( THEME_JSON, 'utf8' ) );
	const settings = theme.settings ?? {};
	const declarations = [];

	for ( const item of settings.color?.palette ?? [] ) {
		declarations.push( [ `--wp--preset--color--${ kebab( item.slug ) }`, item.color ] );
	}

	for ( const item of settings.spacing?.spacingSizes ?? [] ) {
		declarations.push( [ `--wp--preset--spacing--${ kebab( item.slug ) }`, item.size ] );
	}

	for ( const item of settings.shadow?.presets ?? [] ) {
		declarations.push( [ `--wp--preset--shadow--${ kebab( item.slug ) }`, item.shadow ] );
	}

	for ( const item of settings.typography?.fontSizes ?? [] ) {
		declarations.push( [ `--wp--preset--font-size--${ kebab( item.slug ) }`, item.size ] );
	}

	for ( const item of settings.typography?.fontFamilies ?? [] ) {
		declarations.push( [ `--wp--preset--font-family--${ kebab( item.slug ) }`, item.fontFamily ] );
	}

	flatten( settings.custom ?? {}, '', declarations );

	if ( settings.layout?.contentSize ) {
		declarations.push( [ '--wp--style--global--content-size', settings.layout.contentSize ] );
	}

	if ( settings.layout?.wideSize ) {
		declarations.push( [ '--wp--style--global--wide-size', settings.layout.wideSize ] );
	}

	const body = declarations.map( ( [ name, value ] ) => `\t${ name }: ${ value };` ).join( '\n' );

	return `/* Generated from wavira/theme.json by tools/preview/presets.mjs — do not edit. */\n:root {\n${ body }\n}\n`;
}
