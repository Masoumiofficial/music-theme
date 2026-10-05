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
 * - the editor's own strings are printed from PHP
 *   (`wavira_block_editor_strings()` + `wp_add_inline_script()`), so the one
 *   gettext catalogue that translates the front end translates the editor too
 *   (ADR 0015);
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

if ( ! function_exists( 'wavira_block_editor_strings' ) ) {
	/**
	 * Strings the block editor script prints.
	 *
	 * The array is keyed by the English source string — the same key the gettext
	 * catalogue uses — so the editor needs no separate JSON translation file: one
	 * catalogue covers the editor and the front end (ADR 0015). A string missing
	 * from the payload falls back to its English key in the script.
	 *
	 * @return array<string, string> Source string to translated string.
	 */
	function wavira_block_editor_strings() {
		$strings = array();

		// One statement per string: a four-row array of these keys would have to be
		// padded to its longest key, and the padding is unreadable next to a
		// sentence. The key is the English source, which is also the gettext msgid.
		$strings['Tracklist of the album chosen in the sidebar.'] = __( 'Tracklist of the album chosen in the sidebar.', 'wavira' );

		$strings['Player for an album, artist or genre queue.'] = __( 'Player for an album, artist or genre queue.', 'wavira' );

		$strings['The video of this post: file, embed or link.'] = __( 'The video of this post: file, embed or link.', 'wavira' );

		$strings['Genre chips, most used first.'] = __( 'Genre chips, most used first.', 'wavira' );

		return $strings;
	}
}

if ( ! function_exists( 'wavira_register_block_editor_script' ) ) {
	/**
	 * Register the shared editor script and hand it its translated strings.
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
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-server-side-render' ),
			wavira_asset_version( $relative ),
			true
		);

		wp_add_inline_script(
			'wavira-blocks-editor',
			'window.waviraBlocks = ' . wp_json_encode( array( 'strings' => wavira_block_editor_strings() ) ) . ';',
			'before'
		);
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
