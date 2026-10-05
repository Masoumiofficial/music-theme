<?php
/**
 * Front-end markup shared by the shortcodes and the dynamic blocks.
 *
 * One implementation per surface: a shortcode, a block and a template that all
 * render "the album's tracklist" must produce identical markup, or the two
 * editors (classic and block) diverge the first time one of them changes
 * (REBUILD-PLAN 0.7.0).
 *
 * Every dynamic value is escaped here, once, with the core escaping functions.
 * The `wavira_*()` print wrappers exist so block render files and templates can
 * print without an `echo` of a variable — the markup they print is the output of
 * these getters and contains nothing that was not escaped above.
 *
 * @package Wavira\Theme
 * @since   0.7.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wavira_block_placeholder' ) ) {
	/**
	 * Editor-only hint for a dynamic block that has nothing to render.
	 *
	 * A dynamic block that prints nothing is invisible in the editor, which looks
	 * like a bug to the person editing the page. The hint is printed only while
	 * the block is rendered through the REST API (ServerSideRender); the front end
	 * stays silent, exactly like the shortcode (REBUILD-PLAN 0.7.0).
	 *
	 * @param string $message Message to show.
	 * @return void
	 */
	function wavira_block_placeholder( $message ) {
		if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
			return;
		}

		echo '<p class="wavira-block-placeholder">' . esc_html( (string) $message ) . '</p>';
	}
}

if ( ! function_exists( 'wavira_get_tracklist' ) ) {
	/**
	 * Ordered, linked tracklist of an album.
	 *
	 * Works without JavaScript, which is the point: the player's queue panel
	 * needs the engine, but an album page must still list its tracks and link to
	 * them (progressive enhancement, PERFORMANCE-AUDIT P2).
	 *
	 * @param int                  $album_id Album post ID.
	 * @param array<string, mixed> $args     `show_duration` (bool), `show_subtitle` (bool).
	 * @return string Markup, empty string when there is nothing to show.
	 */
	function wavira_get_tracklist( $album_id, $args = array() ) {
		$args     = wp_parse_args(
			$args,
			array(
				'show_duration' => true,
				'show_subtitle' => true,
			)
		);
		$album_id = absint( $album_id );

		if ( $album_id < 1 || ! function_exists( 'wavira_core_album_tracklist' ) ) {
			return '';
		}

		$rows = wavira_core_album_tracklist( $album_id );

		if ( array() === $rows ) {
			return '';
		}

		$html = '<ol class="wavira-tracklist">';

		foreach ( $rows as $index => $row ) {
			$subtitle = isset( $row['subtitle'] ) ? (string) $row['subtitle'] : '';

			$html .= '<li class="wavira-tracklist__item">';
			$html .= '<span class="wavira-tracklist__index" aria-hidden="true">' . esc_html( (string) ( $index + 1 ) ) . '</span>';
			$html .= '<span class="wavira-tracklist__title">';
			$html .= '<a href="' . esc_url( (string) $row['permalink'] ) . '">' . esc_html( (string) $row['title'] ) . '</a>';

			if ( $args['show_subtitle'] && '' !== $subtitle ) {
				$html .= ' <span class="wavira-tracklist__subtitle">' . esc_html( $subtitle ) . '</span>';
			}

			$html .= '</span>';

			if ( $args['show_duration'] && '' !== (string) $row['duration_label'] ) {
				$html .= '<span class="wavira-tracklist__duration">' . esc_html( (string) $row['duration_label'] ) . '</span>';
			}

			$html .= '</li>';
		}

		return $html . '</ol>';
	}
}

if ( ! function_exists( 'wavira_tracklist' ) ) {
	/**
	 * Print the tracklist of an album.
	 *
	 * @param int                  $album_id Album post ID.
	 * @param array<string, mixed> $args     See `wavira_get_tracklist()`.
	 * @return void
	 */
	function wavira_tracklist( $album_id, $args = array() ) {
		$markup = wavira_get_tracklist( $album_id, $args );

		if ( '' !== $markup ) {
			echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped field by field inside wavira_get_tracklist().
		}
	}
}

if ( ! function_exists( 'wavira_get_video' ) ) {
	/**
	 * The video of a video post: a hosted file, an oEmbed, or a plain link.
	 *
	 * @param int $post_id Video post ID.
	 * @return string Markup, empty string when the post has no video.
	 */
	function wavira_get_video( $post_id ) {
		$post_id = absint( $post_id );

		if ( $post_id < 1 || ! function_exists( 'wavira_core_video_source' ) ) {
			return '';
		}

		$source = wavira_core_video_source( $post_id );
		$url    = isset( $source['url'] ) ? (string) $source['url'] : '';

		if ( '' === $url ) {
			return '';
		}

		$poster = isset( $source['poster'] ) ? (string) $source['poster'] : '';

		if ( 'file' === (string) $source['kind'] ) {
			return sprintf(
				'<div class="wavira-video-frame"><video controls preload="metadata" playsinline%s src="%s"></video></div>',
				'' !== $poster ? ' poster="' . esc_url( $poster ) . '"' : '',
				esc_url( $url )
			);
		}

		$embed = function_exists( 'wp_oembed_get' ) ? wp_oembed_get( $url ) : false;

		if ( is_string( $embed ) && '' !== $embed ) {
			return '<div class="wavira-video-frame">' . $embed . '</div>';
		}

		// A provider that cannot be embedded still deserves a usable link.
		return sprintf(
			'<p class="wavira-video-link"><a href="%s">%s</a></p>',
			esc_url( $url ),
			esc_html__( 'Watch the video', 'wavira' )
		);
	}
}

if ( ! function_exists( 'wavira_video' ) ) {
	/**
	 * Print the video of a video post.
	 *
	 * @param int $post_id Video post ID.
	 * @return void
	 */
	function wavira_video( $post_id ) {
		$markup = wavira_get_video( $post_id );

		if ( '' !== $markup ) {
			echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- file URLs escaped above; oEmbed output is filtered by core.
		}
	}
}

if ( ! function_exists( 'wavira_get_genre_chips' ) ) {
	/**
	 * Genre terms as linked chips.
	 *
	 * @param array<string, mixed> $args `limit` (int), `orderby`, `order`, `show_count` (bool).
	 * @return string Markup, empty string when the taxonomy has no terms.
	 */
	function wavira_get_genre_chips( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'limit'      => 12,
				'orderby'    => 'count',
				'order'      => 'DESC',
				'show_count' => false,
			)
		);

		if ( ! function_exists( 'wavira_core_is_active' ) || ! wavira_core_is_active() ) {
			return '';
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'wavira_genre',
				'hide_empty' => true,
				'number'     => absint( $args['limit'] ),
				'orderby'    => (string) $args['orderby'],
				'order'      => (string) $args['order'],
			)
		);

		if ( ! is_array( $terms ) || array() === $terms ) {
			return '';
		}

		$html = '<p class="wavira-cluster wavira-genre-chips">';

		foreach ( $terms as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}

			$label = $args['show_count'] ? sprintf(
				/* translators: 1: genre name, 2: number of published items. */
				__( '%1$s (%2$d)', 'wavira' ),
				$term->name,
				(int) $term->count
			) : $term->name;

			$html .= '<a class="wavira-chip" href="' . esc_url( (string) get_term_link( $term ) ) . '">' . esc_html( $label ) . '</a> ';
		}

		return trim( $html ) . '</p>';
	}
}

if ( ! function_exists( 'wavira_genre_chips' ) ) {
	/**
	 * Print genre chips.
	 *
	 * @param array<string, mixed> $args See `wavira_get_genre_chips()`.
	 * @return void
	 */
	function wavira_genre_chips( $args = array() ) {
		$markup = wavira_get_genre_chips( $args );

		if ( '' !== $markup ) {
			echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- names and links escaped field by field inside wavira_get_genre_chips().
		}
	}
}

if ( ! function_exists( 'wavira_get_image' ) ) {
	/**
	 * An `<img>` built by WordPress, or nothing.
	 *
	 * Core's `wp_get_attachment_image()` is used on purpose: it emits `srcset`,
	 * `sizes`, `width` and `height` (so the rule "never disable core srcset" is
	 * satisfied by construction) and it escapes the attribute values. A payload
	 * carries an attachment ID whenever one exists; a bare URL is the fallback for
	 * data that came from somewhere else.
	 *
	 * @param array<string, mixed> $image Payload: `id`, `url`, `alt`.
	 * @param string               $size  Registered image size.
	 * @param string               $class CSS class for the image element.
	 * @return string Markup, empty string when there is no image.
	 */
	function wavira_get_image( $image, $size = 'wavira-cover-sm', $class = '' ) {
		$image = (array) $image;
		$id    = isset( $image['id'] ) ? absint( $image['id'] ) : 0;
		$alt   = isset( $image['alt'] ) ? (string) $image['alt'] : '';

		if ( $id > 0 && function_exists( 'wp_get_attachment_image' ) ) {
			return (string) wp_get_attachment_image(
				$id,
				$size,
				false,
				array(
					'class'    => $class,
					'loading'  => 'lazy',
					'decoding' => 'async',
					'alt'      => $alt,
				)
			);
		}

		$url = isset( $image['url'] ) ? (string) $image['url'] : '';

		if ( '' === $url ) {
			return '';
		}

		return sprintf(
			'<img class="%s" src="%s" alt="%s" loading="lazy" decoding="async" />',
			esc_attr( $class ),
			esc_url( $url ),
			esc_attr( $alt )
		);
	}
}

if ( ! function_exists( 'wavira_get_card' ) ) {
	/**
	 * One card: the shared markup of a work, an artist and a news item.
	 *
	 * The artist sections and the news feed render the same component, so a site
	 * owner who restyles a card does not discover later that the news grid was a
	 * second implementation. Every field is optional; the card prints what it was
	 * given and nothing else.
	 *
	 * @param array<string, mixed> $card {
	 *     Card data.
	 *
	 *     @type string                        $title     Card title.
	 *     @type string                        $permalink Link target (empty = plain text title).
	 *     @type string                        $subtitle  Secondary line under the title.
	 *     @type string                        $excerpt   Short paragraph.
	 *     @type string                        $date_label Localised date.
	 *     @type int                           $date      Unix timestamp for the `datetime` attribute.
	 *     @type array<int, array<string,string>> $kicker  Rows of `name` + `link`.
	 *     @type array<string, mixed>          $image     `id`/`url`/`alt` payload.
	 *     @type string                        $level     Heading level for the title (default h3).
	 *     @type string                        $class     Extra classes on the article.
	 * }
	 * @return string Markup, empty string when the card has no title.
	 */
	function wavira_get_card( $card ) {
		$card = (array) $card;

		$title     = isset( $card['title'] ) ? (string) $card['title'] : '';
		$permalink = isset( $card['permalink'] ) ? (string) $card['permalink'] : '';
		$subtitle  = isset( $card['subtitle'] ) ? (string) $card['subtitle'] : '';
		$excerpt   = isset( $card['excerpt'] ) ? (string) $card['excerpt'] : '';
		$date      = isset( $card['date'] ) ? (int) $card['date'] : 0;
		$date_html = isset( $card['date_label'] ) ? (string) $card['date_label'] : '';
		$image     = isset( $card['image'] ) ? (array) $card['image'] : array();
		$level     = isset( $card['level'] ) ? (string) $card['level'] : 'h3';
		$class     = isset( $card['class'] ) ? trim( 'wavira-card ' . (string) $card['class'] ) : 'wavira-card';

		if ( ! in_array( $level, array( 'h2', 'h3', 'h4' ), true ) ) {
			$level = 'h3';
		}

		if ( '' === $title ) {
			return '';
		}

		$heading = '<' . $level . ' class="wavira-card__title">';

		if ( '' !== $permalink ) {
			$heading .= '<a class="wavira-card__link" href="' . esc_url( $permalink ) . '">' . esc_html( $title ) . '</a>';
		} else {
			$heading .= esc_html( $title );
		}

		$heading .= '</' . $level . '>';

		$html = '<article class="' . esc_attr( $class ) . '">';

		$media = wavira_get_image( $image, 'wavira-cover-sm', 'wavira-card__image' );

		if ( '' !== $media ) {
			$html .= '<figure class="wavira-card__media">';

			if ( '' !== $permalink ) {
				$html .= '<a class="wavira-card__cover" href="' . esc_url( $permalink ) . '" tabindex="-1" aria-hidden="true">' . $media . '</a>';
			} else {
				$html .= $media;
			}

			$html .= '</figure>';
		}

		$html .= '<div class="wavira-card__body">';

		$kicker = isset( $card['kicker'] ) && is_array( $card['kicker'] ) ? $card['kicker'] : array();

		if ( array() !== $kicker ) {
			$html .= '<p class="wavira-card__kicker">';

			foreach ( $kicker as $term ) {
				$name = isset( $term['name'] ) ? (string) $term['name'] : '';
				$link = isset( $term['link'] ) ? (string) $term['link'] : '';

				if ( '' === $name ) {
					continue;
				}

				$html .= '' === $link
					? '<span class="wavira-chip">' . esc_html( $name ) . '</span>'
					: '<a class="wavira-chip" href="' . esc_url( $link ) . '">' . esc_html( $name ) . '</a>';
			}

			$html .= '</p>';
		}

		$html .= $heading;

		if ( '' !== $subtitle ) {
			$html .= '<p class="wavira-card__subtitle">' . esc_html( $subtitle ) . '</p>';
		}

		if ( '' !== $excerpt ) {
			$html .= '<p class="wavira-card__excerpt">' . esc_html( $excerpt ) . '</p>';
		}

		if ( '' !== $date_html ) {
			$html .= '<p class="wavira-card__meta"><time datetime="' . esc_attr( gmdate( 'c', $date ) ) . '">' . esc_html( $date_html ) . '</time></p>';
		}

		$html .= '</div></article>';

		return $html;
	}
}

if ( ! function_exists( 'wavira_card' ) ) {
	/**
	 * Print one card.
	 *
	 * @param array<string, mixed> $card See `wavira_get_card()`.
	 * @return void
	 */
	function wavira_card( $card ) {
		$markup = wavira_get_card( $card );

		if ( '' !== $markup ) {
			echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped field by field inside wavira_get_card().
		}
	}
}

if ( ! function_exists( 'wavira_get_section_head' ) ) {
	/**
	 * Section heading with an optional "view all" link.
	 *
	 * @param string $title Heading text (already translated).
	 * @param string $more  Link target; empty for a heading without a link.
	 * @param string $label Link label; ignored when empty.
	 * @return string Markup.
	 */
	function wavira_get_section_head( $title, $more = '', $label = '' ) {
		$html = '<div class="wavira-section__head"><h2>' . esc_html( (string) $title ) . '</h2>';

		if ( '' !== $more && '' !== $label ) {
			$html .= '<a class="wavira-section__more" href="' . esc_url( (string) $more ) . '">' . esc_html( (string) $label ) . '</a>';
		}

		return $html . '</div>';
	}
}
