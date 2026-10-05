<?php
/**
 * Dynamic block registration (phase 0.7.0).
 *
 * The blocks are the editor-facing surfaces of the theme; they render through
 * the same markup helpers as the shortcodes (`inc/markup.php`), so a tracklist
 * looks identical whichever editor produced the page.
 *
 * Two deliberate choices:
 *
 * - the editor script is registered by hand and passed to every block through
 *   `editor_script_handles`, because `block.json` cannot declare script
 *   dependencies and the product must run with no build step (ADR 0006);
 * - blocks are registered before the patterns (`init` priority 5), so a pattern
 *   that contains a `wavira/*` block is never validated against a missing block.
 *
 * @package Wavira\Theme
 * @since   0.7.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wavira_block_names' ) ) {
	/**
	 * Blocks that ship with the theme, as directory names.
	 *
	 * One list, used by the registrar and by the blocks gate, so a new block
	 * cannot be half-added (registered but untested, or tested but unregistered).
	 *
	 * @return string[]
	 */
	function wavira_block_names() {
		return array( 'tracklist', 'player', 'video', 'genre-chips' );
	}
}

if ( ! function_exists( 'wavira_register_block_editor_script' ) ) {
	/**
	 * Register the shared editor script.
	 *
	 * @return void
	 */
	function wavira_register_block_editor_script() {
		$relative = 'blocks/editor.js';
		$path     = WAVIRA_THEME_DIR . $relative;

		if ( ! file_exists( $path ) ) {
			return;
		}

		wp_register_script(
			'wavira-blocks-editor',
			WAVIRA_THEME_URI . $relative,
			array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-block-editor', 'wp-components', 'wp-server-side-render' ),
			wavira_asset_version( $relative ),
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'wavira-blocks-editor', 'wavira', WAVIRA_THEME_DIR . 'languages' );
		}
	}
}
add_action( 'init', 'wavira_register_block_editor_script', 5 );

if ( ! function_exists( 'wavira_register_blocks' ) ) {
	/**
	 * Register every theme block from its metadata directory.
	 *
	 * @return void
	 */
	function wavira_register_blocks() {
		foreach ( wavira_block_names() as $block ) {
			$directory = WAVIRA_THEME_DIR . 'blocks/' . $block;

			if ( ! file_exists( $directory . '/block.json' ) ) {
				continue;
			}

			register_block_type(
				$directory,
				array( 'editor_script_handles' => array( 'wavira-blocks-editor' ) )
			);
		}
	}
}
add_action( 'init', 'wavira_register_blocks', 5 );
