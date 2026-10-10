<?php
/**
 * Public function API of Wavira Core.
 *
 * These functions are the **only** supported way for a theme (or a third-party
 * plugin) to read Wavira data. The theme calls a function and never a class
 * name, so the plugin can rename or move its internals without breaking a
 * template — and a site can switch themes without touching music data
 * (ARCHITECTURE.md §2, ADR 0002).
 *
 * Every function degrades to a safe default when the plugin is inactive, when
 * requirements are unmet or when a service was removed by a filter, because a
 * missing music feature must never be a fatal error on the front end.
 *
 * @package Wavira\Core
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wavira_core_is_active' ) ) {
	/**
	 * Whether the Wavira Core boot sequence completed.
	 *
	 * Uses the plugin's own action rather than a class check: `wavira_core_booted`
	 * is the documented signal that content, settings and REST are live
	 * (ARCHITECTURE.md §4).
	 *
	 * @return bool
	 */
	function wavira_core_is_active() {
		return did_action( 'wavira_core_booted' ) > 0;
	}
}

if ( ! function_exists( 'wavira_core_get_setting' ) ) {
	/**
	 * Read one Wavira setting, with the plugin's defaults and read-time filter.
	 *
	 * @param string $key      Setting key.
	 * @param mixed  $fallback Value returned for a key outside the schema.
	 * @return mixed
	 */
	function wavira_core_get_setting( $key, $fallback = null ) {
		if ( ! class_exists( '\Wavira\Core\Settings\Settings' ) ) {
			return $fallback;
		}

		return \Wavira\Core\Settings\Settings::get( (string) $key, $fallback );
	}
}

if ( ! function_exists( 'wavira_core_related_posts' ) ) {
	/**
	 * Related items for one music post, scored and cached by the plugin.
	 *
	 * @param int $post_id Source post ID.
	 * @param int $limit   Maximum number of items (0 = site setting).
	 * @return \WP_Post[] Empty array when the plugin or the service is unavailable.
	 */
	function wavira_core_related_posts( $post_id, $limit = 0 ) {
		if ( ! function_exists( 'wavira_core_is_active' ) || ! wavira_core_is_active() ) {
			return array();
		}

		if ( ! class_exists( '\Wavira\Core\Related\RelatedService' ) ) {
			return array();
		}

		return (array) \Wavira\Core\Related\RelatedService::posts( (int) $post_id, (int) $limit );
	}
}

if ( ! function_exists( 'wavira_core_track_playback' ) ) {
	/**
	 * Playback payload of one track, as consumed by the player engine.
	 *
	 * A template uses this for the no-JavaScript fallback and for the Media
	 * Session description; the engine itself asks the REST route so one page can
	 * hold any number of players (ADR 0005 §1).
	 *
	 * @param int $post_id Track post ID.
	 * @return array<string, mixed> Empty array when the item is not a playable track.
	 */
	function wavira_core_track_playback( $post_id ) {
		if ( ! function_exists( 'wavira_core_is_active' ) || ! wavira_core_is_active() ) {
			return array();
		}

		if ( ! class_exists( '\\Wavira\\Core\\Player\\Payload' ) ) {
			return array();
		}

		return (array) \Wavira\Core\Player\Payload::for_track( (int) $post_id );
	}
}

if ( ! function_exists( 'wavira_core_enqueue_player' ) ) {
	/**
	 * Ask for the player bundle on this request.
	 *
	 * The bundle belongs to Wavira Core, so the theme enqueues the registered
	 * handle instead of pointing at a plugin path. Returns false when the bundle
	 * was never built (an un-built checkout), which lets a template fall back to
	 * server-rendered audio.
	 *
	 * @return bool Whether the engine will load on this request.
	 */
	function wavira_core_enqueue_player() {
		if ( ! function_exists( 'wp_script_is' ) || ! wp_script_is( 'wavira-player', 'registered' ) ) {
			return false;
		}

		// The component's stylesheet is registered with the same handle and is
		// enqueued here too: a theme asks once for the player, not for its parts.
		if ( wp_style_is( 'wavira-player', 'registered' ) ) {
			wp_enqueue_style( 'wavira-player' );
		}

		wp_enqueue_script( 'wavira-player' );

		return true;
	}
}

if ( ! function_exists( 'wavira_core_album_tracklist' ) ) {
	/**
	 * Ordered, published tracks of an album.
	 *
	 * The album's own tracklist is authoritative (ADR 0012): entries that were
	 * deleted, unpublished or moved to another post type are skipped without
	 * reordering the rest, so the editor's playing order survives. A theme renders
	 * a tracklist from this data and never learns how the relation is stored.
	 *
	 * Post types are compared as the documented slugs (ADR 0011) on purpose: this
	 * file must keep working while the plugin boots and after a service filter.
	 *
	 * @param int $album_id Album post ID.
	 * @return array<int, array<string, mixed>> Rows of `id`, `title`, `subtitle`,
	 *                                          `permalink`, `duration`, `duration_label`.
	 */
	function wavira_core_album_tracklist( $album_id ) {
		$rows = array();

		if ( ! class_exists( 'Wavira\\Core\\Content\\MetaValues' ) ) {
			return $rows;
		}

		$album = get_post( absint( $album_id ) );

		if ( ! $album instanceof WP_Post || 'wavira_album' !== $album->post_type ) {
			return $rows;
		}

		foreach ( \Wavira\Core\Content\MetaValues::tracklist( $album->ID ) as $track_id ) {
			$track = get_post( $track_id );

			if ( ! $track instanceof WP_Post || 'wavira_track' !== $track->post_type || 'publish' !== $track->post_status ) {
				continue;
			}

			$rows[] = array(
				'id'             => (int) $track->ID,
				'title'          => get_the_title( $track ),
				'subtitle'       => \Wavira\Core\Content\MetaValues::text( $track->ID, \Wavira\Core\Content\MetaSchema::SUBTITLE ),
				'permalink'      => (string) get_permalink( $track ),
				'duration'       => \Wavira\Core\Content\MetaValues::int( $track->ID, \Wavira\Core\Content\MetaSchema::DURATION ),
				'duration_label' => \Wavira\Core\Content\MetaValues::duration_label( $track->ID ),
			);
		}

		return $rows;
	}
}

if ( ! function_exists( 'wavira_core_artist_profile' ) ) {
	/**
	 * The complete profile payload of an artist.
	 *
	 * One call answers a whole artist page — biography, avatar, social channels,
	 * counts, the works grouped by type and the image gallery — so the theme
	 * never queries the music model itself (ARCHITECTURE §1). Empty array when the
	 * plugin is inactive, when the ID is not a published artist, or when a filter
	 * removed the service.
	 *
	 * @param int                  $artist_id Artist post ID.
	 * @param array<string, mixed> $args      Optional: `limit`, `gallery_limit`, `sections`.
	 * @return array<string, mixed> See Wavira\Core\Content\ArtistProfile::for_artist().
	 */
	function wavira_core_artist_profile( $artist_id, $args = array() ) {
		if ( ! function_exists( 'wavira_core_is_active' ) || ! wavira_core_is_active() ) {
			return array();
		}

		if ( ! class_exists( 'Wavira\\Core\\Content\\ArtistProfile' ) ) {
			return array();
		}

		return (array) \Wavira\Core\Content\ArtistProfile::for_artist( (int) $artist_id, (array) $args );
	}
}

if ( ! function_exists( 'wavira_core_news_feed' ) ) {
	/**
	 * The site's news feed as lean items (title, excerpt, date, thumbnail, …).
	 *
	 * News is published as ordinary posts; this is the one place that decides
	 * what a news item contains, so the blog index, a category archive, a home
	 * page section and a shortcode all render the same card.
	 *
	 * @param array<string, mixed> $args Optional: `limit`, `category`, `offset`,
	 *                                   `exclude`, `post_type`.
	 * @return array<int, array<string, mixed>> Empty array when the plugin is inactive.
	 */
	function wavira_core_news_feed( $args = array() ) {
		if ( ! function_exists( 'wavira_core_is_active' ) || ! wavira_core_is_active() ) {
			return array();
		}

		if ( ! class_exists( 'Wavira\\Core\\News\\NewsFeed' ) ) {
			return array();
		}

		return (array) \Wavira\Core\News\NewsFeed::items( (array) $args );
	}
}

if ( ! function_exists( 'wavira_core_video_source' ) ) {
	/**
	 * Where a video post plays from.
	 *
	 * One function answers "file or embed?" so a template prints a `<video>`, an
	 * oEmbed or nothing at all without touching meta keys. Hosted videos prefer the
	 * highest quality, exactly like the audio player prefers 320 kbps.
	 *
	 * @param int $post_id Video post ID.
	 * @return array<string, mixed> {
	 *     @type string $kind   `file` (hosted), `embed` (provider URL) or `` (nothing).
	 *     @type string $url    Media URL or provider URL.
	 *     @type string $poster Poster image URL, empty string when there is none.
	 * }
	 */
	function wavira_core_video_source( $post_id ) {
		$empty = array(
			'kind'   => '',
			'url'    => '',
			'poster' => '',
		);

		if ( ! class_exists( 'Wavira\\Core\\Content\\MetaValues' ) ) {
			return $empty;
		}

		$post = get_post( absint( $post_id ) );

		if ( ! $post instanceof WP_Post || 'wavira_video' !== $post->post_type ) {
			return $empty;
		}

		$poster_id = \Wavira\Core\Content\MetaValues::int( $post->ID, \Wavira\Core\Content\MetaSchema::VIDEO_POSTER );

		if ( $poster_id < 1 ) {
			$poster_id = (int) get_post_thumbnail_id( $post );
		}

		$poster = $poster_id > 0 ? (string) wp_get_attachment_image_url( $poster_id, 'wavira-cover-lg' ) : '';
		$hosted = array(
			1080 => \Wavira\Core\Content\MetaSchema::VIDEO_1080,
			720  => \Wavira\Core\Content\MetaSchema::VIDEO_720,
			480  => \Wavira\Core\Content\MetaSchema::VIDEO_480,
		);

		foreach ( $hosted as $key ) {
			$url = \Wavira\Core\Content\MetaValues::url( $post->ID, $key );

			if ( '' !== $url ) {
				return array(
					'kind'   => 'file',
					'url'    => $url,
					'poster' => $poster,
				);
			}
		}

		$external = \Wavira\Core\Content\MetaValues::url( $post->ID, \Wavira\Core\Content\MetaSchema::VIDEO_URL );

		if ( '' !== $external ) {
			return array(
				'kind'   => 'embed',
				'url'    => $external,
				'poster' => $poster,
			);
		}

		return $empty;
	}
}

if ( ! function_exists( 'wavira_core_download_url' ) ) {
	/**
	 * A public download URL for a post, or an empty string when there is none.
	 *
	 * One function for every section: a track's audio, an album's master file, a
	 * hosted video and a cover or gallery image. The URL points at the product API
	 * (`wavira/v1/download/{id}`, ADR 0013), so a link is authorized and counted in
	 * one place, and the endpoint redirects to the file itself — PHP never proxies
	 * the bytes (ADR 0023).
	 *
	 * @param int $post_id Post ID of any type.
	 * @param int $quality Audio kbps or video height; 0 uses the best available.
	 * @return string URL, empty string when the post has nothing to hand out.
	 */
	function wavira_core_download_url( $post_id, $quality = 0 ) {
		if ( ! class_exists( 'Wavira\\Core\\Downloads\\Sources' ) ) {
			return '';
		}

		return \Wavira\Core\Downloads\Sources::url( absint( $post_id ), absint( $quality ) );
	}
}

if ( ! function_exists( 'wavira_core_can_download' ) ) {
	/**
	 * Whether a post may expose a download link at all.
	 *
	 * Ask this before printing a button: a theme that prints a download link for
	 * every track ends up with dead links on a site that turned downloads off.
	 *
	 * @param int $post_id Post ID of any type.
	 * @return bool
	 */
	function wavira_core_can_download( $post_id ) {
		if ( ! class_exists( 'Wavira\\Core\\Downloads\\Access' ) ) {
			return false;
		}

		return \Wavira\Core\Downloads\Access::allows( absint( $post_id ) );
	}
}

if ( ! function_exists( 'wavira_core_download_qualities' ) ) {
	/**
	 * The qualities a post can be downloaded in.
	 *
	 * A `320 kbps` audio file and a `1080p` video are both "a quality" to a
	 * visitor, so both come back in the same shape and the label is translated.
	 *
	 * **Empty when the visitor may not download**: a quality a theme cannot link
	 * to is not a quality, and the labels a theme prints come from here — so the
	 * access decision is made once, here, and a theme that lists these is not
	 * listing files the endpoint will refuse (ADR 0023).
	 *
	 * @param int $post_id Post ID of any type.
	 * @return array<int, array<string, mixed>> Each: quality, url, type, label.
	 */
	function wavira_core_download_qualities( $post_id ) {
		if ( ! class_exists( 'Wavira\\Core\\Downloads\\Sources' ) ) {
			return array();
		}

		$post_id = absint( $post_id );

		if ( ! \Wavira\Core\Downloads\Access::allows( $post_id ) ) {
			return array();
		}

		return \Wavira\Core\Downloads\Sources::available( $post_id );
	}
}

if ( ! function_exists( 'wavira_core_digits' ) ) {
	/**
	 * Persian numerals on a Persian site, Latin digits everywhere else.
	 *
	 * Every number a template prints (durations, bitrates, counts, sizes) goes
	 * through this, so a Persian page never mixes numeral systems. Machine
	 * output — IDs, JSON, slugs — never does.
	 *
	 * @param string|int|float $value Value to convert.
	 * @return string
	 */
	function wavira_core_digits( $value ) {
		if ( ! class_exists( 'Wavira\\Core\\Content\\Dates' ) ) {
			return (string) $value;
		}

		return \Wavira\Core\Content\Dates::digits( (string) $value );
	}
}

if ( ! function_exists( 'wavira_core_date_style' ) ) {
	/**
	 * Which calendar the front end prints: `jalali` or `gregorian`.
	 *
	 * Persian sites default to Jalali (Shamsi); every other locale to Gregorian.
	 * A site that runs another Persian-date plugin sets `wavira_core_date_style`
	 * to `gregorian` and the product steps aside (ADR 0017).
	 *
	 * @return string
	 */
	function wavira_core_date_style() {
		if ( ! class_exists( 'Wavira\\Core\\Content\\Dates' ) ) {
			return 'gregorian';
		}

		return (string) \Wavira\Core\Content\Dates::style();
	}
}

if ( ! function_exists( 'wavira_core_date_label' ) ) {
	/**
	 * A localised date label for a timestamp.
	 *
	 * Returns «۱۳ مهر ۱۴۰۵» on a Persian site and the site's own `date_format`
	 * everywhere else, so a template never has to know which calendar is in use.
	 *
	 * @param int    $timestamp Unix timestamp.
	 * @param string $format    Optional display format for the Gregorian style.
	 * @return string
	 */
	function wavira_core_date_label( $timestamp, $format = '' ) {
		if ( ! class_exists( 'Wavira\\Core\\Content\\Dates' ) ) {
			return '';
		}

		return (string) \Wavira\Core\Content\Dates::label( (int) $timestamp, (string) $format );
	}
}

if ( ! function_exists( 'wavira_core_cover_image' ) ) {
	/**
	 * The cover image of a post: `id`, `url`, `alt`, `width`, `height`.
	 *
	 * One function for every visual surface (hero, card, share image), so a theme
	 * never reads `wavira_cover` or the featured-image meta itself — which is what
	 * keeps a theme switch safe when the storage changes (ADR 0012).
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed> Empty array when the post has no image.
	 */
	function wavira_core_cover_image( $post_id ) {
		if ( ! class_exists( 'Wavira\\Core\\Content\\Cover' ) ) {
			return array();
		}

		return (array) \Wavira\Core\Content\Cover::payload( absint( $post_id ) );
	}
}

if ( ! function_exists( 'wavira_core_credit_names' ) ) {
	/**
	 * Names of the artists a work is credited to, in credit order.
	 *
	 * A theme needs this for a credit line, a document title or a meta
	 * description; the plugin owns it so a track, an album and a video cannot
	 * disagree about who made them (Content\\Credit).
	 *
	 * @param int $post_id Post ID.
	 * @return string[] Artist display names, empty when the plugin is inactive.
	 */
	function wavira_core_credit_names( $post_id ) {
		if ( ! class_exists( 'Wavira\\Core\\Content\\Credit' ) ) {
			return array();
		}

		return (array) \Wavira\Core\Content\Credit::names( absint( $post_id ) );
	}
}

if ( ! function_exists( 'wavira_core_seo_plugin_active' ) ) {
	/**
	 * Whether a third-party SEO plugin owns the generic SEO surface.
	 *
	 * The theme uses this to decide whether to print its own `meta description`
	 * and Open Graph fallbacks: two plugins describing one page is worse than
	 * none. Music structured data is separate — see
	 * `wavira_core_structured_data()`.
	 *
	 * @return bool
	 */
	function wavira_core_seo_plugin_active() {
		if ( ! class_exists( 'Wavira\\Core\\Seo\\SeoSupport' ) ) {
			return false;
		}

		return (bool) \Wavira\Core\Seo\SeoSupport::plugin_active();
	}
}

if ( ! function_exists( 'wavira_core_structured_data' ) ) {
	/**
	 * The schema.org graph of one music post, as plain arrays.
	 *
	 * A theme prints it (the product's themes print JSON-LD in `wp_head`); a
	 * headless consumer or another theme can reuse the same nodes, which is why
	 * the payload — not the `<script>` tag — is the API (ADR 0016).
	 *
	 * @param int $post_id Post ID. Defaults to the queried object.
	 * @return array<int, array<string, mixed>> Nodes, empty for a non-music post,
	 *                                          an unpublished post, or when a
	 *                                          filter disabled the output.
	 */
	function wavira_core_structured_data( $post_id = 0 ) {
		if ( ! class_exists( 'Wavira\\Core\\Seo\\StructuredData' ) || ! class_exists( 'Wavira\\Core\\Seo\\SeoSupport' ) ) {
			return array();
		}

		if ( ! \Wavira\Core\Seo\SeoSupport::structured_data_enabled() ) {
			return array();
		}

		$post_id = absint( $post_id );

		if ( $post_id < 1 && function_exists( 'get_queried_object_id' ) ) {
			$post_id = (int) get_queried_object_id();
		}

		if ( $post_id < 1 ) {
			return array();
		}

		/**
		 * Filters the structured-data graph before it is returned.
		 *
		 * @since 0.10.0
		 * @param array<int, array<string, mixed>> $nodes   Graph nodes.
		 * @param int                              $post_id Post ID.
		 */
		return (array) apply_filters(
			'wavira_core_structured_data',
			\Wavira\Core\Seo\StructuredData::graph( $post_id ),
			$post_id
		);
	}
}

if ( ! function_exists( 'wavira_core_apply_persian_defaults' ) ) {
	/**
	 * Apply the Iranian defaults a Persian music site expects.
	 *
	 * Locale, timezone, first day of the week, date and time format — the same
	 * policy the demo installer applies, exposed so a theme can offer it as a
	 * one-click action instead of re-implementing the list (and drifting).
	 *
	 * Deliberately narrow: it touches those five options and a blog description
	 * nobody wrote on purpose. It never publishes content, never changes a user
	 * and never runs on its own — a caller with `manage_options` decides.
	 *
	 * @return array<string, mixed> `ok` (bool) and `notices` (human-readable
	 *                              strings for the caller to display).
	 */
	function wavira_core_apply_persian_defaults() {
		if ( ! class_exists( 'Wavira\\Core\\Demo\\Fixtures' ) ) {
			return array(
				'ok'      => false,
				'notices' => array(),
			);
		}

		$notices = \Wavira\Core\Demo\Installer::apply_site_defaults( \Wavira\Core\Demo\Fixtures::persian() );

		return array(
			'ok'      => true,
			'notices' => $notices,
		);
	}
}
