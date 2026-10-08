/**
 * Editor registration for the theme's dynamic blocks.
 *
 * One hand-written file for every block, because the product ships without a
 * build step (ADR 0006): it uses the `wp.*` globals WordPress already prints in
 * the editor and nothing else. Each block has no `save()` output — the markup is
 * produced on the server by `render.php`, so a visitor (and a future theme
 * version) always sees data current at request time.
 *
 * The preview uses `ServerSideRender`, which calls the block's render file
 * through the REST API; a block with nothing to show renders the placeholder the
 * render file prints in that context.
 *
 * The strings of this file are translated in PHP, not with a JSON script
 * translation: `wavira_block_editor_strings()` is printed into
 * `window.waviraBlocks` by `wp_add_inline_script()`, so one catalogue
 * (`languages/fa_IR.mo`) covers the editor and the front end, and the theme
 * needs neither a build step nor a hash-named JSON file (ADR 0015).
 */
( function ( blocks, element, serverSideRender, blockEditor ) {
	'use strict';

	var el = element.createElement;
	var catalogue = ( window.waviraBlocks && window.waviraBlocks.strings ) || {};
	var __ = function ( text ) {
		return Object.prototype.hasOwnProperty.call( catalogue, text ) ? catalogue[ text ] : text;
	};
	var SSR = serverSideRender && serverSideRender.default ? serverSideRender.default : serverSideRender;
	var useBlockProps = blockEditor && blockEditor.useBlockProps ? blockEditor.useBlockProps : function () {
		return {};
	};

	/**
	 * Build an edit component that previews the server-rendered markup.
	 *
	 * @param {string} name        Block name registered in PHP.
	 * @param {string} description One-line hint shown above the preview.
	 * @return {Function} Edit component.
	 */
	function preview( name, description ) {
		return function ( props ) {
			var wrapper = useBlockProps( { className: 'wavira-block' } );

			return el(
				'div',
				wrapper,
				el( 'p', { className: 'wavira-block-hint' }, description ),
				el( SSR, { block: name, attributes: props.attributes } )
			);
		};
	}

	/**
	 * Register one dynamic block.
	 *
	 * @param {string}   name        Block name registered in PHP.
	 * @param {string}   description Preview hint.
	 * @param {Function} [supports]  Optional extra settings.
	 * @return {void}
	 */
	function register( name, description, supports ) {
		blocks.registerBlockType(
			name,
			Object.assign(
				{
					edit: preview( name, description ),
					save: function () {
						return null;
					},
				},
				supports || {}
			)
		);
	}

	register(
		'wavira/tracklist',
		__( 'Tracklist of the album chosen in the sidebar.', 'wavira' )
	);

	register(
		'wavira/player',
		__( 'Player for an album, artist or genre queue.', 'wavira' )
	);

	register( 'wavira/video', __( 'The video of this post: file, embed or link.', 'wavira' ) );

	register( 'wavira/genre-chips', __( 'Genre chips, most used first.', 'wavira' ) );

	register(
		'wavira/artist-profile',
		__( 'Profile, works and photos of an artist.', 'wavira' )
	);

	register(
		'wavira/download',
		__( 'Download button for a track, an album, a video or an image — hidden when there is no file.', 'wavira' )
	);

	register( 'wavira/artist-gallery', __( 'Photos attached to this post: artist, release or video.', 'wavira' ) );

	register( 'wavira/news', __( 'The newest news posts as cards.', 'wavira' ) );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.serverSideRender,
	window.wp.blockEditor
);
