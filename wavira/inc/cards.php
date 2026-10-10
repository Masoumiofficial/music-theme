<?php
/**
 * Card actions — play and download on the cards themselves.
 *
 * A grid of covers is a shop window: without this, a visitor has to open every
 * single one of them to hear anything or to take anything. Two controls on the
 * cover — «play it here» and «take it» — answer the two questions a music site
 * is asked, and they are enhancements twice over:
 *
 *   · the play control is a real link to the track, so a browser with no script
 *     (or a site with the plugin switched off) opens the page — exactly what a
 *     link has always done;
 *   · a post with nothing to download prints no download control, because a
 *     download link that leads to an error page is worse than no link at all.
 *
 * The controls are added to the rendered query loop rather than to a block, so
 * every grid in the product gets them — the home page, the archives, the
 * related-items section of a single — without a copy of this markup in each
 * template.
 *
 * @package Wavira\Theme
 * @since   0.15.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * What a card of each music type offers, and how it is played.
 *
 * `context` is one of the engine's queue contexts (see the player block); an
 * empty context means «this type is not playable», which a video is not — a
 * video opens on its own page.
 *
 * @return array<string, array{context: string, play: bool, download: bool}>
 */
function wavira_card_action_map() {
	return array(
		'wavira_track'  => array(
			'context'  => 'tracks',
			'play'     => 'track',
			'download' => true,
		),
		'wavira_album'  => array(
			'context'  => 'album',
			'play'     => 'context',
			'download' => true,
		),
		'wavira_artist' => array(
			'context'  => 'artist',
			'play'     => 'context',
			'download' => false,
		),
		'wavira_video'  => array(
			'context'  => '',
			'play'     => false,
			'download' => true,
		),
	);
}

/**
 * One icon, inline, so a card costs no extra request.
 *
 * @param string $name `play` or `download`.
 * @return string SVG markup, or an empty string for an unknown name.
 */
function wavira_card_action_icon( $name ) {
	$paths = array(
		'play'     => '<path d="M8 5.5v13l11-6.5-11-6.5Z"/>',
		'download' => '<path d="M12 4v9m0 0 3.5-3.5M12 13l-3.5-3.5M5 17h14"/>',
	);

	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}

	$stroked = 'download' === $name;
	$shape   = $stroked
		? 'fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"'
		: 'fill="currentColor"';

	return '<svg class="wavira-card__icon" viewBox="0 0 24 24" width="20" height="20" '
		. $shape . ' aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
}

/**
 * The controls for one card.
 *
 * @param int    $post_id   Post ID.
 * @param string $post_type Post type.
 * @return string HTML, or an empty string when the card offers nothing.
 */
function wavira_card_actions( $post_id, $post_type ) {
	$post_id = absint( $post_id );

	if ( $post_id < 1 ) {
		return '';
	}

	$map = wavira_card_action_map();

	if ( ! isset( $map[ $post_type ] ) ) {
		return '';
	}

	$rules   = $map[ $post_type ];
	$title   = get_the_title( $post_id );
	$actions = '';

	if ( ! empty( $rules['play'] ) && '' !== $rules['context'] ) {
		/* translators: %s: the track, album or artist being played. */
		$label = sprintf( __( 'Play: %s', 'wavira' ), $title );

		if ( 'track' === $rules['play'] ) {
			$play_attributes = sprintf(
				'data-wavira-play="%d" data-wavira-context="%s"',
				$post_id,
				esc_attr( $rules['context'] )
			);
		} else {
			$play_attributes = sprintf(
				'data-wavira-play-context="%s" data-wavira-play-id="%d"',
				esc_attr( $rules['context'] ),
				$post_id
			);
		}

		$actions .= sprintf(
			'<a class="wavira-card__action wavira-card__action--play" href="%s" %s aria-label="%s">%s</a>',
			esc_url( get_permalink( $post_id ) ),
			$play_attributes,
			esc_attr( $label ),
			wavira_card_action_icon( 'play' )
		);
	}

	if ( ! empty( $rules['download'] ) && function_exists( 'wavira_core_download_url' ) ) {
		$url = wavira_core_download_url( $post_id, 0 );

		if ( '' !== $url ) {
			/* translators: %s: the track, album or video being downloaded. */
			$label   = sprintf( __( 'Download: %s', 'wavira' ), $title );
			$actions .= sprintf(
				'<a class="wavira-card__action wavira-card__action--download" href="%s" download aria-label="%s">%s</a>',
				esc_url( $url ),
				esc_attr( $label ),
				wavira_card_action_icon( 'download' )
			);
		}
	}

	if ( '' === $actions ) {
		return '';
	}

	return '<span class="wavira-card__actions">' . $actions . '</span>';
}

/**
 * Add the controls to every card of a query loop.
 *
 * The post-template block is where a card is a card: inside it core has already
 * set the loop up, so the ID and the type come from the classes core prints
 * (`post-34`, `type-wavira_album`) rather than from a second query.
 *
 * @param string $content Rendered block.
 * @param array  $block   Parsed block.
 * @return string The block, with controls on the cards that have any.
 */
function wavira_render_card_actions( $content, $block ) {
	if ( empty( $block['blockName'] ) || 'core/post-template' !== $block['blockName'] ) {
		return $content;
	}

	if ( is_admin() || ! empty( $block['attrs']['waviraHideActions'] ) ) {
		return $content;
	}

	if ( false === strpos( $content, '<li' ) ) {
		return $content;
	}

	$rendered = preg_replace_callback(
		'/<li\b([^>]*)>(.*?)<\/li>/s',
		static function ( $matches ) {
			$attributes = $matches[1];
			$body       = $matches[2];

			if ( ! preg_match( '/\bpost-(\d+)\b/', $attributes, $id_match )
				|| ! preg_match( '/\btype-([a-z0-9_]+)\b/', $attributes, $type_match ) ) {
				return $matches[0];
			}

			$actions = wavira_card_actions( (int) $id_match[1], $type_match[1] );

			if ( '' === $actions ) {
				return $matches[0];
			}

			$class  = 'has-wavira-actions';
			$static = false === strpos( $body, '<figure' );

			// No cover to sit on: the controls become a row under the title
			// rather than a panel floating over nothing.
			if ( $static ) {
				$class .= ' has-wavira-actions--row';
			}

			if ( false !== strpos( $attributes, 'class="' ) ) {
				$attributes = preg_replace( '/class="/', 'class="' . $class . ' ', $attributes, 1 );
			} else {
				$attributes .= ' class="' . $class . '"';
			}

			return '<li' . $attributes
				. ' data-wavira-card data-wavira-post="' . (int) $id_match[1] . '"'
				. ' data-wavira-kind="' . esc_attr( $type_match[1] ) . '">'
				. $body . $actions . '</li>';
		},
		$content
	);

	// `null` means the pattern could not run, not that the loop was empty: the
	// block's own HTML is still the honest answer.
	return null === $rendered ? $content : $rendered;
}

add_filter( 'render_block', 'wavira_render_card_actions', 10, 2 );
