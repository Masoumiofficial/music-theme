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
 */
( function ( blocks, element, i18n, serverSideRender, blockEditor ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;
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
		__( 'Tracklist of the album this block sits in, or of the album chosen in the sidebar.', 'wavira' )
	);

	register(
		'wavira/player',
		__( 'Player instance: an album, an artist, a genre, the latest tracks or the related items of this post.', 'wavira' )
	);

	register( 'wavira/video', __( 'The video of this post, as a hosted file, an embed or a link.', 'wavira' ) );

	register( 'wavira/genre-chips', __( 'Linked genre chips, most used first.', 'wavira' ) );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.i18n,
	window.wp.serverSideRender,
	window.wp.blockEditor
);
