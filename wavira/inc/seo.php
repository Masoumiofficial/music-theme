<?php
/**
 * SEO presentation: music structured data, and the fallbacks a site needs when
 * it runs no SEO plugin.
 *
 * The split is deliberate (FEATURE-MAP F-73, ADR 0016):
 *
 * - **Structured data** is printed here, from the graph the plugin builds
 *   (`wavira_core_structured_data()`). Music schema is a gap in every
 *   general-purpose SEO plugin, so it is emitted even when one is active, and a
 *   site can switch it off with `wavira_core_structured_data_enabled`.
 * - **`meta description`, Open Graph and Twitter cards** are fallbacks: they are
 *   printed only when no SEO plugin is active, because two descriptions of one
 *   page is worse than one. Rank Math, Yoast, SEOPress and All in One SEO are
 *   detected by the plugin; `wavira_theme_seo_plugin_active` overrides it.
 * - **Document titles** stay core's (`title-tag`); the one addition is the
 *   artist name on a music single, because "Track name" alone is a poor search
 *   result when the artist is the searchable part.
 *
 * @package Wavira\Theme
 * @since   0.10.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wavira_has_seo_plugin' ) ) {
	/**
	 * Whether a third-party SEO plugin owns the generic SEO surface.
	 *
	 * @return bool
	 */
	function wavira_has_seo_plugin() {
		$active = function_exists( 'wavira_core_seo_plugin_active' ) ? (bool) wavira_core_seo_plugin_active() : false;

		/**
		 * Filters whether a third-party SEO plugin is treated as active.
		 *
		 * @since 0.10.0
		 * @param bool $active Detection result.
		 */
		return (bool) apply_filters( 'wavira_theme_seo_plugin_active', $active );
	}
}

if ( ! function_exists( 'wavira_get_structured_data' ) ) {
	/**
	 * The schema.org graph of one post, as arrays.
	 *
	 * @param int $post_id Post ID. 0 = the queried object.
	 * @return array<int, array<string, mixed>> Empty when there is nothing to say.
	 */
	function wavira_get_structured_data( $post_id = 0 ) {
		if ( ! function_exists( 'wavira_core_structured_data' ) ) {
			return array();
		}

		return (array) wavira_core_structured_data( absint( $post_id ) );
	}
}

if ( ! function_exists( 'wavira_get_structured_data_markup' ) ) {
	/**
	 * A JSON-LD `<script>` tag for one post, or an empty string.
	 *
	 * One node is encoded as a bare object (what a validator expects for a single
	 * entity); several are wrapped in an `@graph`, so two nodes can reference each
	 * other by `@id`.
	 *
	 * @param int $post_id Post ID. 0 = the queried object.
	 * @return string
	 */
	function wavira_get_structured_data_markup( $post_id = 0 ) {
		$nodes = wavira_get_structured_data( $post_id );

		if ( array() === $nodes ) {
			return '';
		}

		$context = 'https://schema.org';
		$payload = 1 === count( $nodes )
			? array_merge( array( '@context' => $context ), (array) reset( $nodes ) )
			: array(
				'@context' => $context,
				'@graph'   => array_values( $nodes ),
			);

		// `JSON_HEX_TAG` matters: a post title may contain `</script>`, and that
		// must not be able to close the tag it sits in.
		$json = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG );

		if ( ! is_string( $json ) || '' === $json ) {
			return '';
		}

		return (string) wp_get_inline_script_tag( $json, array( 'type' => 'application/ld+json' ) );
	}
}

if ( ! function_exists( 'wavira_print_structured_data' ) ) {
	/**
	 * Print the music structured data.
	 *
	 * @return void
	 */
	function wavira_print_structured_data() {
		$markup = wavira_get_structured_data_markup();

		if ( '' !== $markup ) {
			echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_inline_script_tag() escapes the attributes; the body is JSON, not HTML.
		}
	}
}
add_action( 'wp_head', 'wavira_print_structured_data', 4 );

if ( ! function_exists( 'wavira_meta_description' ) ) {
	/**
	 * A description for the current view, when the site has no SEO plugin.
	 *
	 * @return string Plain text, empty when nothing sensible can be said.
	 */
	function wavira_meta_description() {
		if ( is_singular() ) {
			$post = get_queried_object();

			if ( $post instanceof WP_Post ) {
				$excerpt = trim( wp_strip_all_tags( (string) $post->post_excerpt, true ) );

				if ( '' !== $excerpt ) {
					return $excerpt;
				}

				return trim( wp_trim_words( wp_strip_all_tags( (string) $post->post_content, true ), 30, '…' ) );
			}
		}

		if ( is_category() || is_tag() || is_tax() ) {
			return trim( wp_strip_all_tags( term_description(), true ) );
		}

		if ( is_author() ) {
			return trim( wp_strip_all_tags( (string) get_the_author_meta( 'description' ), true ) );
		}

		return trim( wp_strip_all_tags( (string) get_bloginfo( 'description', 'display' ), true ) );
	}
}

if ( ! function_exists( 'wavira_current_url' ) ) {
	/**
	 * The canonical URL of the current view.
	 *
	 * @return string
	 */
	function wavira_current_url() {
		if ( is_singular() ) {
			$permalink = get_permalink();

			return is_string( $permalink ) ? $permalink : '';
		}

		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();

			if ( $term instanceof WP_Term ) {
				$link = get_term_link( $term );

				if ( is_string( $link ) ) {
					return $link;
				}
			}
		}

		if ( is_author() ) {
			$author = get_queried_object();

			if ( $author instanceof WP_User ) {
				return (string) get_author_posts_url( $author->ID );
			}
		}

		if ( is_front_page() ) {
			return (string) home_url( '/' );
		}

		$paged = max( 1, (int) get_query_var( 'paged' ) );

		return (string) get_pagenum_link( $paged );
	}
}

if ( ! function_exists( 'wavira_open_graph_type' ) ) {
	/**
	 * The Open Graph type of the current view.
	 *
	 * @return string
	 */
	function wavira_open_graph_type() {
		if ( is_singular() ) {
			$type = (string) get_post_type();

			if ( 'wavira_track' === $type ) {
				return 'music.song';
			}

			if ( 'wavira_album' === $type ) {
				return 'music.album';
			}

			if ( 'wavira_artist' === $type ) {
				return 'profile';
			}

			if ( 'wavira_video' === $type ) {
				return 'video.other';
			}

			return 'article';
		}

		return 'website';
	}
}

if ( ! function_exists( 'wavira_get_share_image' ) ) {
	/**
	 * The image a share card should use.
	 *
	 * @return string Absolute URL, empty string when the view has no image.
	 */
	function wavira_get_share_image() {
		if ( ! is_singular() ) {
			return '';
		}

		$post_id = (int) get_queried_object_id();
		$image   = function_exists( 'wavira_core_cover_image' ) ? (array) wavira_core_cover_image( $post_id ) : array();
		$url     = isset( $image['url'] ) ? (string) $image['url'] : '';

		if ( '' !== $url ) {
			return $url;
		}

		$id = isset( $image['id'] ) ? absint( $image['id'] ) : 0;

		if ( $id < 1 ) {
			return '';
		}

		$full = wp_get_attachment_image_url( $id, 'wavira-cover-lg' );

		return is_string( $full ) ? $full : '';
	}
}

if ( ! function_exists( 'wavira_print_social_meta' ) ) {
	/**
	 * `meta description`, Open Graph and Twitter card fallbacks.
	 *
	 * Prints nothing at all when a SEO plugin is active: cooperating means
	 * staying out of the way (ADR 0016 §2).
	 *
	 * @return void
	 */
	function wavira_print_social_meta() {
		if ( wavira_has_seo_plugin() ) {
			return;
		}

		$title       = wp_get_document_title();
		$url         = wavira_current_url();
		$description = wavira_meta_description();
		$image       = wavira_get_share_image();

		if ( '' !== $description ) {
			printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $description ) );
		}

		printf( '<meta property="og:site_name" content="%s" />' . "\n", esc_attr( get_bloginfo( 'name', 'display' ) ) );
		printf( '<meta property="og:type" content="%s" />' . "\n", esc_attr( wavira_open_graph_type() ) );
		printf( '<meta property="og:title" content="%s" />' . "\n", esc_attr( $title ) );

		if ( '' !== $description ) {
			printf( '<meta property="og:description" content="%s" />' . "\n", esc_attr( $description ) );
		}

		if ( '' !== $url ) {
			printf( '<meta property="og:url" content="%s" />' . "\n", esc_url( $url ) );
		}

		printf( '<meta property="og:locale" content="%s" />' . "\n", esc_attr( get_locale() ) );

		if ( '' !== $image ) {
			printf( '<meta property="og:image" content="%s" />' . "\n", esc_url( $image ) );
		}

		if ( wavira_is_singular_post() ) {
			printf( '<meta property="article:published_time" content="%s" />' . "\n", esc_attr( (string) get_the_date( 'c' ) ) );
			printf( '<meta property="article:modified_time" content="%s" />' . "\n", esc_attr( (string) get_the_modified_date( 'c' ) ) );
		}

		printf( '<meta name="twitter:card" content="%s" />' . "\n", '' !== $image ? 'summary_large_image' : 'summary' );
	}
}
add_action( 'wp_head', 'wavira_print_social_meta', 5 );

if ( ! function_exists( 'wavira_is_singular_post' ) ) {
	/**
	 * Whether the current view is a single post of any public type.
	 *
	 * Kept as a tiny helper so `wavira_print_social_meta()` reads as a list of
	 * tags, not as a list of conditions.
	 *
	 * @return bool
	 */
	function wavira_is_singular_post() {
		return is_singular() && '' !== (string) get_post_type();
	}
}

if ( ! function_exists( 'wavira_document_title_parts' ) ) {
	/**
	 * Add the artist to a music single's title.
	 *
	 * @param array<string, string> $parts Title parts.
	 * @return array<string, string>
	 */
	function wavira_document_title_parts( $parts ) {
		if ( ! is_singular( array( 'wavira_track', 'wavira_album', 'wavira_video' ) ) || ! function_exists( 'wavira_core_credit_names' ) ) {
			return $parts;
		}

		$names = (array) wavira_core_credit_names( (int) get_queried_object_id() );
		$first = isset( $names[0] ) ? trim( (string) $names[0] ) : '';
		$title = isset( $parts['title'] ) ? (string) $parts['title'] : '';

		if ( '' === $first || '' === $title || false !== strpos( $title, $first ) ) {
			return $parts;
		}

		$parts['title'] = $title . ' · ' . $first;

		return $parts;
	}
}
add_filter( 'document_title_parts', 'wavira_document_title_parts' );
