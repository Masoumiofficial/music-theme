<?php
/**
 * Theme hook adjustments (small, documented, reversible).
 *
 * @package Wavira\Theme
 * @since   0.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Add contextual body classes without UA sniffing (see PERFORMANCE-AUDIT P9).
 *
 * @param array $classes Existing classes.
 * @return array
 */
function wavira_body_classes( $classes ) {
	if ( ! is_singular() ) {
		$classes[] = 'wavira-archive-view';
	}

	if ( is_rtl() ) {
		$classes[] = 'wavira-rtl';
	}

	if ( wavira_has_core() ) {
		$classes[] = 'wavira-has-core';
	}

	return $classes;
}
add_filter( 'body_class', 'wavira_body_classes' );

/**
 * Remove the core emoji detection script (dead weight on every request;
 * emoji render natively everywhere this product supports).
 *
 * @return void
 */
function wavira_disable_emoji_assets() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
}
add_action( 'init', 'wavira_disable_emoji_assets' );

/**
 * Keep excerpts short and predictable.
 *
 * @param int $length Current length.
 * @return int
 */
function wavira_excerpt_length( $length ) {
	unset( $length );

	return 28;
}
add_filter( 'excerpt_length', 'wavira_excerpt_length', 20 );

/**
 * Engagement bar, podcast timestamped chapters and prev/next track navigation.
 *
 * @param int $post_id Music post ID.
 * @return string Escaped HTML markup.
 */
function wavira_get_engagement_bar( $post_id ) {
	$post_id = absint( $post_id );

	if ( $post_id < 1 || ! function_exists( 'wavira_core_post_stats' ) ) {
		return '';
	}

	if ( is_singular() && (int) get_queried_object_id() === $post_id && function_exists( 'wavira_core_record_view' ) ) {
		wavira_core_record_view( $post_id );
	}

	$stats = wavira_core_post_stats( $post_id );

	if ( array() === $stats ) {
		return '';
	}

	$views_label = isset( $stats['views_label'] ) ? (string) $stats['views_label'] : '0';
	$likes_label = isset( $stats['likes_label'] ) ? (string) $stats['likes_label'] : '0';
	$liked       = ! empty( $stats['liked'] );
	$like_class  = 'wavira-engagement__like' . ( $liked ? ' is-liked' : '' );

	$html  = '<div class="wavira-engagement" data-wavira-engagement="' . $post_id . '">';
	$html .= '<span class="wavira-engagement__views">';
	$html .= '<span class="wavira-engagement__label">' . esc_html__( 'Views:', 'wavira' ) . '</span> ';
	$html .= '<strong class="wavira-engagement__count" data-wavira-view-count="' . $post_id . '">' . esc_html( $views_label ) . '</strong>';
	$html .= '</span>';

	$html .= '<button type="button" class="' . esc_attr( $like_class ) . '"';
	$html .= ' data-wavira-like="' . $post_id . '"';
	$html .= ' aria-pressed="' . ( $liked ? 'true' : 'false' ) . '">';
	$html .= '<span class="wavira-engagement__heart" aria-hidden="true">&#9829;</span> ';
	$html .= '<span class="wavira-engagement__label">' . esc_html__( 'Like', 'wavira' ) . '</span> ';
	$html .= '<strong class="wavira-engagement__count" data-wavira-like-count="' . $post_id . '">' . esc_html( $likes_label ) . '</strong>';
	$html .= '</button>';
	$html .= '</div>';

	if ( 'wavira_track' === get_post_type( $post_id ) && function_exists( 'wavira_core_podcast_chapters' ) ) {
		$chapters = wavira_core_podcast_chapters( $post_id );

		if ( array() !== $chapters ) {
			$html .= '<div class="wavira-chapters" data-wavira-chapters="' . $post_id . '">';
			$html .= '<h3 class="wavira-chapters__heading">' . esc_html__( 'Chapters & timestamps', 'wavira' ) . '</h3>';
			$html .= '<ol class="wavira-chapters__list">';

			foreach ( $chapters as $chapter ) {
				$seconds = isset( $chapter['time'] ) ? absint( $chapter['time'] ) : 0;
				$time    = isset( $chapter['label'] ) ? wavira_core_digits( (string) $chapter['label'] ) : '';
				$title   = isset( $chapter['title'] ) ? (string) $chapter['title'] : '';

				if ( '' === $title ) {
					continue;
				}

				$html .= '<li class="wavira-chapters__item">';
				$html .= '<button type="button" class="wavira-chapters__btn" data-wavira-seek="' . $seconds . '" data-wavira-seek-track="' . $post_id . '">';
				$html .= '<span class="wavira-chapters__time">' . esc_html( $time ) . '</span> ';
				$html .= '<span class="wavira-chapters__title">' . esc_html( $title ) . '</span>';
				$html .= '</button>';
				$html .= '</li>';
			}

			$html .= '</ol></div>';
		}
	}

	if ( is_singular( 'wavira_track' ) && (int) get_queried_object_id() === $post_id ) {
		$prev = get_previous_post();
		$next = get_next_post();

		if ( $prev instanceof WP_Post || $next instanceof WP_Post ) {
			$html .= '<nav class="wavira-post-nav" aria-label="' . esc_attr__( 'Track navigation', 'wavira' ) . '">';

			if ( $prev instanceof WP_Post ) {
				$html .= '<a class="wavira-post-nav__link wavira-post-nav__link--prev" href="' . esc_url( (string) get_permalink( $prev ) ) . '">';
				$html .= '<span class="wavira-post-nav__kicker">' . esc_html__( 'Previous track', 'wavira' ) . '</span> ';
				$html .= '<strong class="wavira-post-nav__title">' . esc_html( get_the_title( $prev ) ) . '</strong>';
				$html .= '</a>';
			}

			if ( $next instanceof WP_Post ) {
				$html .= '<a class="wavira-post-nav__link wavira-post-nav__link--next" href="' . esc_url( (string) get_permalink( $next ) ) . '">';
				$html .= '<span class="wavira-post-nav__kicker">' . esc_html__( 'Next track', 'wavira' ) . '</span> ';
				$html .= '<strong class="wavira-post-nav__title">' . esc_html( get_the_title( $next ) ) . '</strong>';
				$html .= '</a>';
			}

			$html .= '</nav>';
		}
	}

	return $html;
}

/**
 * Dynamic Hero Spotlight Slider for the front-page banner.
 *
 * @return string Escaped HTML markup.
 */
function wavira_get_hero_slider() {
	if ( ! function_exists( 'wavira_has_core' ) || ! wavira_has_core() ) {
		return '';
	}

	if ( function_exists( 'wavira_option' ) && ! wavira_option( 'hero_slider', true ) ) {
		return '';
	}

	$items = get_posts(
		array(
			'post_type'              => array( 'wavira_track', 'wavira_album', 'wavira_video' ),
			'post_status'            => 'publish',
			'posts_per_page'         => 4,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		)
	);

	if ( array() === $items ) {
		return '';
	}

	$type_labels = array(
		'wavira_track' => __( 'Track', 'wavira' ),
		'wavira_album' => __( 'Album', 'wavira' ),
		'wavira_video' => __( 'Music video', 'wavira' ),
	);

	$html  = '<div class="wavira-hero-slider" data-wavira-slider>';
	$html .= '<div class="wavira-hero-slider__track">';

	foreach ( $items as $index => $item ) {
		if ( ! $item instanceof WP_Post ) {
			continue;
		}

		$post_id    = (int) $item->ID;
		$post_type  = (string) $item->post_type;
		$title      = get_the_title( $item );
		$permalink  = (string) get_permalink( $item );
		$cover      = function_exists( 'wavira_core_cover_image' ) ? wavira_core_cover_image( $post_id ) : array();
		$credits    = function_exists( 'wavira_core_credit_names' ) ? wavira_core_credit_names( $post_id ) : array();
		$artist_str = array() !== $credits ? implode( '، ', $credits ) : '';
		$badge      = isset( $type_labels[ $post_type ] ) ? $type_labels[ $post_type ] : '';
		$active_cls = 0 === $index ? ' is-active' : '';

		$html .= '<article class="wavira-hero-slider__slide' . $active_cls . '" data-wavira-slide="' . $index . '" data-wavira-card data-wavira-post="' . $post_id . '" data-wavira-kind="' . esc_attr( $post_type ) . '">';

		if ( ! empty( $cover['url'] ) ) {
			$html .= '<a class="wavira-hero-slider__media" href="' . esc_url( $permalink ) . '" tabindex="-1" aria-hidden="true">';
			$html .= '<img class="wavira-hero-slider__img" src="' . esc_url( (string) $cover['url'] ) . '" alt="' . esc_attr( $title ) . '" loading="eager" />';
			$html .= '</a>';
		}

		$html .= '<div class="wavira-hero-slider__body">';

		if ( '' !== $badge ) {
			$html .= '<span class="wavira-chip wavira-chip--accent">' . esc_html( $badge ) . '</span>';
		}

		$html .= '<h3 class="wavira-hero-slider__title"><a href="' . esc_url( $permalink ) . '">' . esc_html( $title ) . '</a></h3>';

		if ( '' !== $artist_str ) {
			$html .= '<p class="wavira-hero-slider__artist">' . esc_html( $artist_str ) . '</p>';
		}

		$html .= '<div class="wavira-hero-slider__actions">';

		if ( 'wavira_track' === $post_type ) {
			$html .= '<a class="wp-block-button__link wp-element-button wavira-hero-slider__play" href="' . esc_url( $permalink ) . '" data-wavira-play="' . $post_id . '" data-wavira-context="tracks">' . esc_html__( 'Play now', 'wavira' ) . '</a>';
		} elseif ( 'wavira_album' === $post_type ) {
			$html .= '<a class="wp-block-button__link wp-element-button wavira-hero-slider__play" href="' . esc_url( $permalink ) . '" data-wavira-play-context="album" data-wavira-play-id="' . $post_id . '">' . esc_html__( 'Play album', 'wavira' ) . '</a>';
		}

		$html .= '<a class="wavira-hero-slider__more" href="' . esc_url( $permalink ) . '">' . esc_html__( 'View details', 'wavira' ) . '</a>';
		$html .= '</div></div></article>';
	}

	$html .= '</div>';

	if ( count( $items ) > 1 ) {
		$html .= '<div class="wavira-hero-slider__controls">';
		$html .= '<button type="button" class="wavira-hero-slider__nav" data-wavira-slider-prev aria-label="' . esc_attr__( 'Previous slide', 'wavira' ) . '">&#8249;</button>';
		$html .= '<button type="button" class="wavira-hero-slider__nav" data-wavira-slider-next aria-label="' . esc_attr__( 'Next slide', 'wavira' ) . '">&#8250;</button>';
		$html .= '</div>';
	}

	$html .= '</div>';

	return $html;
}

/**
 * Top Artists Ranking section with Weekly, Monthly, Yearly and All-Time tabs.
 *
 * @return string Escaped HTML markup.
 */
function wavira_get_top_artists_section() {
	if ( ! function_exists( 'wavira_core_top_artists' ) ) {
		return '';
	}

	if ( function_exists( 'wavira_option' ) && ! wavira_option( 'top_artists_section', true ) ) {
		return '';
	}

	$periods = array(
		'week'  => __( 'Weekly', 'wavira' ),
		'month' => __( 'Monthly', 'wavira' ),
		'year'  => __( 'Yearly', 'wavira' ),
		'all'   => __( 'All time', 'wavira' ),
	);

	$all_artists = wavira_core_top_artists( 'all', 6 );

	if ( array() === $all_artists ) {
		return '';
	}

	$html  = '<div class="wp-block-group wavira-section wavira-top-artists" data-wavira-top-artists>';
	$html .= '<div class="wavira-section__head wavira-top-artists__head">';
	$html .= '<h2>' . esc_html__( 'Top artists', 'wavira' ) . '</h2>';
	$html .= '<div class="wavira-top-artists__tabs" role="tablist" aria-label="' . esc_attr__( 'Top artists time range', 'wavira' ) . '">';

	$first = true;
	foreach ( $periods as $slug => $label ) {
		$active = $first ? ' is-active' : '';
		$html  .= '<button type="button" role="tab" class="wavira-top-artists__tab' . $active . '"';
		$html  .= ' aria-selected="' . ( $first ? 'true' : 'false' ) . '"';
		$html  .= ' data-wavira-period-tab="' . esc_attr( $slug ) . '">';
		$html  .= esc_html( $label ) . '</button>';
		$first  = false;
	}

	$html .= '</div></div>';

	$first = true;
	foreach ( array_keys( $periods ) as $slug ) {
		$rows   = 'all' === $slug ? $all_artists : wavira_core_top_artists( $slug, 6 );
		$hidden = $first ? '' : ' hidden';
		$html  .= '<ol class="wavira-top-artists__grid" role="tabpanel" data-wavira-period-panel="' . esc_attr( $slug ) . '"' . $hidden . '>';

		foreach ( $rows as $artist ) {
			$aid         = isset( $artist['id'] ) ? absint( $artist['id'] ) : 0;
			$name        = isset( $artist['name'] ) ? (string) $artist['name'] : '';
			$permalink   = isset( $artist['permalink'] ) ? (string) $artist['permalink'] : '';
			$rank_label  = isset( $artist['rank_label'] ) ? (string) $artist['rank_label'] : '';
			$views_label = isset( $artist['views_label'] ) ? (string) $artist['views_label'] : '';
			$works_label = isset( $artist['works_label'] ) ? (string) $artist['works_label'] : '';
			$cover       = isset( $artist['cover'] ) && is_array( $artist['cover'] ) ? $artist['cover'] : array();

			if ( '' === $name ) {
				continue;
			}

			$html .= '<li class="wavira-top-artists__card" data-wavira-card data-wavira-post="' . $aid . '" data-wavira-kind="wavira_artist">';
			$html .= '<span class="wavira-top-artists__rank" aria-hidden="true">' . esc_html( $rank_label ) . '</span>';

			if ( ! empty( $cover['url'] ) ) {
				$html .= '<a class="wavira-top-artists__avatar" href="' . esc_url( $permalink ) . '" tabindex="-1" aria-hidden="true">';
				$html .= '<img src="' . esc_url( (string) $cover['url'] ) . '" alt="' . esc_attr( $name ) . '" loading="lazy" />';
				$html .= '</a>';
			}

			$html .= '<div class="wavira-top-artists__info">';
			$html .= '<h3 class="wavira-top-artists__name"><a href="' . esc_url( $permalink ) . '">' . esc_html( $name ) . '</a></h3>';
			$html .= '<p class="wavira-top-artists__meta">';
			/* translators: 1: number of works, 2: number of views. */
			$html .= esc_html( sprintf( __( '%1$s works · %2$s views', 'wavira' ), $works_label, $views_label ) );
			$html .= '</p></div></li>';
		}

		$html .= '</ol>';
		$first = false;
	}

	$html .= '</div>';

	return $html;
}

/**
 * Inject the Hero Slider, Top Artists section and singular Engagement Bar.
 *
 * @param string               $block_content Rendered block HTML.
 * @param array<string, mixed> $block         Parsed block array.
 * @return string Filtered block HTML.
 */
function wavira_inject_dynamic_surfaces( $block_content, $block ) {
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return $block_content;
	}

	$name  = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
	$class = isset( $block['attrs']['className'] ) ? (string) $block['attrs']['className'] : '';

	if ( 'core/group' === $name && is_front_page() ) {
		if ( false !== strpos( $class, 'wavira-banner__inner' ) ) {
			return $block_content . wavira_get_hero_slider();
		}

		if ( false !== strpos( $class, 'wavira-section--tracklist' ) ) {
			return $block_content . wavira_get_top_artists_section();
		}
	}

	if ( 'wavira/related' === $name && is_singular( array( 'wavira_track', 'wavira_album', 'wavira_video' ) ) ) {
		return wavira_get_engagement_bar( (int) get_queried_object_id() ) . $block_content;
	}

	return $block_content;
}
add_filter( 'render_block', 'wavira_inject_dynamic_surfaces', 15, 2 );
