<?php
/**
 * Music-news surfaces: the blog as a news feed.
 *
 * Iranian music sites live on their news section — release announcements,
 * concert dates, interviews — and they publish it as ordinary posts in
 * categories, because that is what the editor, the RSS feed, the sitemap and the
 * theme all understand without a second content type (docs/DECISIONS.md 0.9.0).
 *
 * This file renders the feed: the block in a page or a template, the shortcode
 * in an article, and the blog templates. All three ask the core plugin for the
 * same items (`wavira_core_news_feed()`) and pass them through the same card
 * component as the artist sections (inc/markup.php), so "a news card" exists
 * once.
 *
 * @package Wavira\Theme
 * @since   0.9.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wavira_get_news' ) ) {
	/**
	 * A news feed as cards.
	 *
	 * @param array<string, mixed> $args {
	 *     Optional. Feed arguments.
	 *
	 *     @type int    $limit       Items to show (default 6, max 24).
	 *     @type string $source      `blog` (newest posts) or `category`.
	 *     @type string $category    Category slug. A slug implies the `category` source,
	 *                               whether or not `source` says so.
	 *     @type int    $offset      Items to skip.
	 *     @type bool   $show_date   Print the date line (default true).
	 *     @type bool   $show_excerpt Print the excerpt (default true).
	 *     @type bool   $show_image  Print the thumbnail (default true).
	 *     @type string $class       Extra classes for the wrapper.
	 *     @type int    $exclude     Post ID to leave out: the article the reader
	 *                               is already reading. One extra item is fetched
	 *                               so a feed of N still prints N.
	 *     @type string $title       Section heading. Printed by this function,
	 *                               not by the template, for the same reason the
	 *                               related section owns its own: a heading a
	 *                               visitor can read with nothing under it is a
	 *                               worse defect than no section at all.
	 * }
	 * @return string Markup, empty string when there is nothing to show.
	 */
	function wavira_get_news( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'limit'        => 6,
				'source'       => 'blog',
				'category'     => '',
				'offset'       => 0,
				'show_date'    => true,
				'show_excerpt' => true,
				'show_image'   => true,
				'class'        => '',
				'exclude'      => 0,
				'title'        => '',
			)
		);

		if ( ! function_exists( 'wavira_core_news_feed' ) ) {
			return '';
		}

		$category = (string) $args['category'];

		// A slug is a request for that category, so it decides the source: asking for
		// one category and silently getting the whole blog is the misread this guards.
		// The reverse is refused too — `source=category` without a slug must not mean
		// "every post".
		if ( '' !== $category ) {
			$args['source'] = 'category';
		} elseif ( 'category' === (string) $args['source'] ) {
			return '';
		}

		$exclude = absint( $args['exclude'] );
		$limit   = (int) $args['limit'];

		if ( $limit < 1 ) {
			$limit = 6;
		}

		// One extra, so leaving the current article out does not shorten the feed.
		$items = wavira_core_news_feed(
			array(
				'limit'    => $limit + ( $exclude > 0 ? 1 : 0 ),
				'category' => $category,
				'offset'   => (int) $args['offset'],
			)
		);

		if ( array() === $items ) {
			return '';
		}

		$classes = trim( 'wavira-news ' . (string) $args['class'] );
		$html    = '<div class="' . esc_attr( $classes ) . '">';
		$shown   = 0;
		$cards   = '';

		foreach ( $items as $item ) {
			$item = (array) $item;
			$id   = isset( $item['id'] ) ? absint( $item['id'] ) : 0;

			if ( $exclude > 0 && $id === $exclude ) {
				continue;
			}

			if ( $shown >= $limit ) {
				break;
			}

			++$shown;

			$image = $args['show_image'] && isset( $item['thumbnail'] ) ? (array) $item['thumbnail'] : array();

			$cards .= '<li class="wavira-news__item">';
			$cards .= wavira_get_card(
				array(
					'title'      => isset( $item['title'] ) ? (string) $item['title'] : '',
					'permalink'  => isset( $item['permalink'] ) ? (string) $item['permalink'] : '',
					'excerpt'    => $args['show_excerpt'] && isset( $item['excerpt'] ) ? (string) $item['excerpt'] : '',
					'date'       => $args['show_date'] && isset( $item['date'] ) ? (int) $item['date'] : 0,
					'date_label' => $args['show_date'] && isset( $item['date_label'] ) ? (string) $item['date_label'] : '',
					'kicker'     => isset( $item['categories'] ) ? (array) $item['categories'] : array(),
					'image'      => $image,
					'class'      => 'wavira-card--news',
				)
			);
			$cards .= '</li>';
		}

		if ( $shown < 1 ) {
			return '';
		}

		if ( '' !== (string) $args['title'] && function_exists( 'wavira_get_section_head' ) ) {
			$html .= wavira_get_section_head( (string) $args['title'] );
		}

		return $html . '<ul class="wavira-news__list">' . $cards . '</ul></div>';
	}
}

if ( ! function_exists( 'wavira_news' ) ) {
	/**
	 * Print a news feed.
	 *
	 * @param array<string, mixed> $args See `wavira_get_news()`.
	 * @return void
	 */
	function wavira_news( $args = array() ) {
		$markup = wavira_get_news( $args );

		if ( '' !== $markup ) {
			echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped field by field inside wavira_get_news() and wavira_get_card().
		}
	}
}

if ( ! function_exists( 'wavira_get_news_categories' ) ) {
	/**
	 * Category chips of the news section.
	 *
	 * A news section that only lists the newest items buries everything older
	 * than a week; the chips are the way a reader moves to "concerts" or
	 * "interviews" instead of paging through the archive.
	 *
	 * @param array<string, mixed> $args `limit` (default 8), `orderby` (count|name).
	 * @return string Markup, empty string when there are no categories in use.
	 */
	function wavira_get_news_categories( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'limit'   => 8,
				'orderby' => 'count',
			)
		);

		if ( ! taxonomy_exists( 'category' ) ) {
			return '';
		}

		$terms = get_categories(
			array(
				'orderby'    => in_array( $args['orderby'], array( 'count', 'name' ), true ) ? (string) $args['orderby'] : 'count',
				'order'      => 'DESC',
				'number'     => absint( $args['limit'] ),
				'hide_empty' => true,
			)
		);

		if ( ! is_array( $terms ) || array() === $terms ) {
			return '';
		}

		$html = '<p class="wavira-cluster wavira-news__categories">';

		foreach ( $terms as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}

			$html .= '<a class="wavira-chip" href="' . esc_url( (string) get_term_link( $term ) ) . '">' . esc_html( $term->name ) . '</a> ';
		}

		return trim( $html ) . '</p>';
	}
}
