<?php
/**
 * Artist profile surfaces: header, biography, social channels, works, gallery.
 *
 * Persian music sites are artist-first: the artist page is where a listener
 * lands from a search result and it must answer "who is this, what did they
 * release, where do I follow them" without scrolling through a blog post. This
 * file renders that page from the payload the core plugin builds
 * (`wavira_core_artist_profile()`), so the music model stays in the plugin and a
 * theme switch keeps the profile working (ARCHITECTURE §1).
 *
 * The payload is read once per request per artist, not once per helper: the
 * profile block asks for the header, the works and the gallery, and each of them
 * would otherwise repeat the same queries (PERFORMANCE-AUDIT P1).
 *
 * @package Wavira\Theme
 * @since   0.9.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wavira_artist_data' ) ) {
	/**
	 * The artist payload for this request, resolved and cached once.
	 *
	 * @param int                  $artist_id Artist post ID.
	 * @param array<string, mixed> $args      `limit`, `gallery_limit`, `sections`.
	 * @return array<string, mixed> Empty array when the artist cannot be resolved.
	 */
	function wavira_artist_data( $artist_id, $args = array() ) {
		static $cache = array();

		$artist_id = absint( $artist_id );

		if ( $artist_id < 1 || ! function_exists( 'wavira_core_artist_profile' ) ) {
			return array();
		}

		$key = $artist_id . ':' . md5( (string) wp_json_encode( (array) $args ) );

		if ( ! isset( $cache[ $key ] ) ) {
			$cache[ $key ] = (array) wavira_core_artist_profile( $artist_id, (array) $args );
		}

		return $cache[ $key ];
	}
}

if ( ! function_exists( 'wavira_get_artist_avatar' ) ) {
	/**
	 * Artist portrait, with a music placeholder when there is no image.
	 *
	 * A profile without a portrait is the normal state on a new site; an empty
	 * grey square looks broken, so the fallback is an inline, currentColor SVG.
	 *
	 * @param array<string, mixed> $artist Artist payload.
	 * @return string Markup, never empty.
	 */
	function wavira_get_artist_avatar( $artist ) {
		$avatar = isset( $artist['avatar'] ) ? (array) $artist['avatar'] : array();
		$image  = wavira_get_image( $avatar, 'wavira-cover', 'wavira-artist__portrait' );

		if ( '' !== $image ) {
			return '<figure class="wavira-artist__avatar">' . $image . '</figure>';
		}

		$icon = function_exists( 'wavira_icon' ) ? wavira_icon( 'music' ) : '';

		if ( '' === $icon ) {
			return '';
		}

		return '<figure class="wavira-artist__avatar wavira-artist__avatar--placeholder" aria-hidden="true">' . $icon . '</figure>';
	}
}

if ( ! function_exists( 'wavira_get_artist_profile' ) ) {
	/**
	 * Artist header and biography.
	 *
	 * @param array<string, mixed> $artist Artist payload.
	 * @param array<string, mixed> $args   `show_bio`, `show_socials`, `show_counts`, `show_quote`.
	 * @return string Markup, empty string when there is nothing to show.
	 */
	function wavira_get_artist_profile( $artist, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'show_bio'     => true,
				'show_socials' => true,
				'show_counts'  => true,
				'show_quote'   => true,
			)
		);

		$name = isset( $artist['name'] ) ? (string) $artist['name'] : '';

		if ( '' === $name ) {
			return '';
		}

		$socials = isset( $artist['socials'] ) ? (array) $artist['socials'] : array();
		$counts  = isset( $artist['counts'] ) ? (array) $artist['counts'] : array();
		$quote   = isset( $artist['context'] ) ? (string) $artist['context'] : '';
		$bio     = isset( $artist['biography'] ) ? (string) $artist['biography'] : '';

		$html = '<section class="wavira-artist__profile">';

		$html .= wavira_get_artist_avatar( $artist );
		$html .= '<div class="wavira-artist__intro">';
		$html .= '<h1 class="wavira-artist__name">' . esc_html( $name ) . '</h1>';

		if ( $args['show_quote'] && '' !== $quote ) {
			$html .= '<p class="wavira-artist__quote wavira-measure">' . esc_html( $quote ) . '</p>';
		}

		if ( $args['show_socials'] && array() !== $socials ) {
			$html .= '<ul class="wavira-artist__socials">';

			foreach ( $socials as $social ) {
				$label = isset( $social['label'] ) ? (string) $social['label'] : '';
				$url   = isset( $social['url'] ) ? (string) $social['url'] : '';

				if ( '' === $label || '' === $url ) {
					continue;
				}

				$html .= '<li class="wavira-artist__social">';
				$html .= '<a class="wavira-chip" href="' . esc_url( $url ) . '" rel="me nofollow noopener external">';
				$html .= esc_html( $label );
				$html .= '<span class="wavira-visually-hidden">' . esc_html__( '(opens in a new tab)', 'wavira' ) . '</span>';
				$html .= '</a></li>';
			}

			$html .= '</ul>';
		}

		if ( $args['show_counts'] && array() !== $counts ) {
			$labels = array(
				'albums'  => __( 'Album', 'wavira' ),
				'tracks'  => __( 'Single', 'wavira' ),
				'videos'  => __( 'Video', 'wavira' ),
				'gallery' => __( 'Photo', 'wavira' ),
			);

			$html .= '<dl class="wavira-artist__counts">';

			foreach ( $labels as $key => $label ) {
				$count = isset( $counts[ $key ] ) ? (int) $counts[ $key ] : 0;

				if ( $count < 1 ) {
					continue;
				}

				$html .= '<div class="wavira-artist__count">';
				$html .= '<dt>' . esc_html( $label ) . '</dt>';
				$html .= '<dd>' . esc_html( wavira_core_digits( number_format_i18n( $count ) ) ) . '</dd>';
				$html .= '</div>';
			}

			$html .= '</dl>';
		}

		$html .= '</div></section>';

		if ( $args['show_bio'] && '' !== trim( $bio ) ) {
			$html .= '<section class="wavira-artist__bio wavira-prose wavira-measure">';
			$html .= wp_kses_post( $bio );
			$html .= '</section>';
		}

		return $html;
	}
}

if ( ! function_exists( 'wavira_get_artist_works' ) ) {
	/**
	 * The artist's works, grouped by type, each group with its own heading.
	 *
	 * A group with nothing in it prints nothing at all — an empty "Videos"
	 * heading is worse than no heading — and a group shows a "view all" link when
	 * the artist has more items than the group displays.
	 *
	 * @param array<string, mixed> $artist Artist payload.
	 * @return string Markup, empty string when the artist has no published work.
	 */
	function wavira_get_artist_works( $artist ) {
		$sections = isset( $artist['sections'] ) ? (array) $artist['sections'] : array();

		if ( array() === $sections ) {
			return '';
		}

		$html = '';

		foreach ( $sections as $section ) {
			$section = (array) $section;
			$items   = isset( $section['items'] ) ? (array) $section['items'] : array();

			if ( array() === $items ) {
				continue;
			}

			$label   = isset( $section['label'] ) ? (string) $section['label'] : '';
			$count   = isset( $section['count'] ) ? (int) $section['count'] : count( $items );
			$more    = isset( $section['more'] ) ? (string) $section['more'] : '';
			$heading = sprintf(
				/* translators: 1: section name, 2: number of items in that section, formatted for the locale. */
				__( '%1$s (%2$s)', 'wavira' ),
				$label,
				wavira_core_digits( number_format_i18n( $count ) )
			);

			$html .= '<section class="wavira-section wavira-artist__works">';
			$html .= wavira_get_section_head( $heading, $count > count( $items ) ? $more : '', __( 'View all', 'wavira' ) );
			$html .= '<ul class="wavira-cards">';

			foreach ( $items as $item ) {
				$item  = (array) $item;
				$image = isset( $item['cover'] ) ? (array) $item['cover'] : array();

				$html .= '<li class="wavira-cards__item">';
				$html .= wavira_get_card(
					array(
						'title'      => isset( $item['title'] ) ? (string) $item['title'] : '',
						'permalink'  => isset( $item['permalink'] ) ? (string) $item['permalink'] : '',
						'subtitle'   => isset( $item['subtitle'] ) ? (string) $item['subtitle'] : '',
						'date'       => isset( $item['date'] ) ? (int) $item['date'] : 0,
						'date_label' => isset( $item['date_label'] ) ? (string) $item['date_label'] : '',
						'kicker'     => isset( $item['genres'] ) ? (array) $item['genres'] : array(),
						'image'      => $image,
						'class'      => 'wavira-card--work',
					)
				);
				$html .= '</li>';
			}

			$html .= '</ul></section>';
		}

		return $html;
	}
}

if ( ! function_exists( 'wavira_get_artist_gallery' ) ) {
	/**
	 * Artist photo gallery: images attached to the artist post.
	 *
	 * @param array<string, mixed> $artist Artist payload.
	 * @param array<string, mixed> $args   `columns` (2–4).
	 * @return string Markup, empty string when the artist has no photos.
	 */
	function wavira_get_artist_gallery( $artist, $args = array() ) {
		$args = wp_parse_args( $args, array( 'columns' => 3 ) );

		$images = isset( $artist['gallery'] ) ? (array) $artist['gallery'] : array();

		if ( array() === $images ) {
			return '';
		}

		$columns = (int) $args['columns'];
		$columns = (int) max( 2, min( 4, $columns ) );

		$html  = '<section class="wavira-section wavira-gallery">';
		$html .= wavira_get_section_head( __( 'Photos', 'wavira' ) );
		$html .= '<ul class="wavira-gallery__items wavira-gallery__items--' . esc_attr( (string) $columns ) . '">';

		foreach ( $images as $image ) {
			$image   = (array) $image;
			$markup  = wavira_get_image( $image, 'wavira-cover-sm', 'wavira-gallery__image' );
			$caption = isset( $image['caption'] ) ? (string) $image['caption'] : '';

			if ( '' === $markup ) {
				continue;
			}

			$html .= '<li class="wavira-gallery__item"><figure class="wavira-gallery__figure">';
			$html .= $markup;

			// A captionless image gets no empty figcaption: the alt text carries
			// the meaning and an empty element carries nothing.
			if ( '' !== $caption ) {
				$html .= '<figcaption class="wavira-gallery__caption">' . esc_html( $caption ) . '</figcaption>';
			}

			$html .= '</figure></li>';
		}

		return $html . '</ul></section>';
	}
}

if ( ! function_exists( 'wavira_get_artist' ) ) {
	/**
	 * The complete artist page: profile, biography, works and gallery.
	 *
	 * @param int                  $artist_id Artist post ID.
	 * @param array<string, mixed> $args      Block or shortcode arguments.
	 * @return string Markup, empty string when the artist cannot be resolved.
	 */
	function wavira_get_artist( $artist_id, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'sections'      => array( 'albums', 'tracks', 'videos' ),
				'limit'         => 6,
				'gallery_limit' => 8,
				'columns'       => 3,
				'show_bio'      => true,
				'show_socials'  => true,
				'show_counts'   => true,
				'show_quote'    => true,
				'show_works'    => true,
				'show_gallery'  => true,
			)
		);

		$artist = wavira_artist_data(
			(int) $artist_id,
			array(
				'limit'         => (int) $args['limit'],
				'gallery_limit' => (int) $args['gallery_limit'],
				'sections'      => (array) $args['sections'],
			)
		);

		if ( array() === $artist ) {
			return '';
		}

		$html = '<div class="wavira-artist">';

		$html .= wavira_get_artist_profile(
			$artist,
			array(
				'show_bio'     => (bool) $args['show_bio'],
				'show_socials' => (bool) $args['show_socials'],
				'show_counts'  => (bool) $args['show_counts'],
				'show_quote'   => (bool) $args['show_quote'],
			)
		);

		if ( $args['show_works'] ) {
			$html .= wavira_get_artist_works( $artist );
		}

		if ( $args['show_gallery'] ) {
			$html .= wavira_get_artist_gallery( $artist, array( 'columns' => (int) $args['columns'] ) );
		}

		return $html . '</div>';
	}
}

if ( ! function_exists( 'wavira_get_artist_gallery_only' ) ) {
	/**
	 * Just the gallery, for a page that places the profile and the photos apart.
	 *
	 * @param int                  $artist_id Artist post ID.
	 * @param array<string, mixed> $args      `limit`, `columns`.
	 * @return string Markup.
	 */
	function wavira_get_artist_gallery_only( $artist_id, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'limit'   => 12,
				'columns' => 3,
			)
		);

		$artist = wavira_artist_data(
			(int) $artist_id,
			array(
				'limit'         => 1,
				'gallery_limit' => (int) $args['limit'],
				'sections'      => array(),
			)
		);

		if ( array() === $artist ) {
			return '';
		}

		return wavira_get_artist_gallery( $artist, array( 'columns' => (int) $args['columns'] ) );
	}
}

if ( ! function_exists( 'wavira_artist' ) ) {
	/**
	 * Print the complete artist page markup.
	 *
	 * @param int                  $artist_id Artist post ID.
	 * @param array<string, mixed> $args      See `wavira_get_artist()`.
	 * @return void
	 */
	function wavira_artist( $artist_id, $args = array() ) {
		$markup = wavira_get_artist( $artist_id, $args );

		if ( '' !== $markup ) {
			echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped field by field inside the artist helpers; the biography is passed through wp_kses_post().
		}
	}
}

if ( ! function_exists( 'wavira_artist_gallery' ) ) {
	/**
	 * Print the artist gallery markup.
	 *
	 * @param int                  $artist_id Artist post ID.
	 * @param array<string, mixed> $args      See `wavira_get_artist_gallery_only()`.
	 * @return void
	 */
	function wavira_artist_gallery( $artist_id, $args = array() ) {
		$markup = wavira_get_artist_gallery_only( $artist_id, $args );

		if ( '' !== $markup ) {
			echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped field by field inside wavira_get_artist_gallery().
		}
	}
}
