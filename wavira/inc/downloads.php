<?php
/**
 * Download links: one builder for tracks, albums, videos and images.
 *
 * The product API hands out files (`wavira_core_download_url()`, ADR 0013 +
 * ADR 0023) and this file turns that into the markup a template prints. The rule
 * is the same everywhere: **no file, no link.** A download button that leads to a
 * 404 is worse than a page without one, and a music site without a single audio
 * file is the normal state of a fresh install.
 *
 * @package Wavira\Theme
 * @since   0.15.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wavira_download_qualities' ) ) {
	/**
	 * The qualities a post can be downloaded in.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int, array<string, mixed>> Each: quality, url, type, label.
	 */
	function wavira_download_qualities( $post_id ) {
		if ( ! function_exists( 'wavira_core_download_qualities' ) ) {
			return array();
		}

		return (array) wavira_core_download_qualities( absint( $post_id ) );
	}
}

if ( ! function_exists( 'wavira_download_type_label' ) ) {
	/**
	 * What the download is called, for the button of a given post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $type    Download type (`track`, `album`, `video`, `image`).
	 * @return string Translated label.
	 */
	function wavira_download_type_label( $post_id, $type ) {
		switch ( $type ) {
			case 'album':
				return __( 'Download the album', 'wavira' );
			case 'video':
				return __( 'Download the video', 'wavira' );
			case 'image':
				return __( 'Download the image', 'wavira' );
			case 'track':
				return __( 'Download the track', 'wavira' );
		}

		return __( 'Download', 'wavira' );
	}
}

if ( ! function_exists( 'wavira_download_size_label' ) ) {
	/**
	 * A file size as a short, translated string (or an empty one).
	 *
	 * @param int $bytes Size in bytes.
	 * @return string e.g. `4.2 MB`, empty string when the size is unknown.
	 */
	function wavira_download_size_label( $bytes ) {
		$bytes = (int) $bytes;

		if ( $bytes < 1 ) {
			return '';
		}

		if ( $bytes >= MB_IN_BYTES ) {
			/* translators: %s: file size in megabytes. */
			return sprintf( __( '%s MB', 'wavira' ), number_format_i18n( round( $bytes / MB_IN_BYTES, 1 ), 1 ) );
		}

		/* translators: %s: file size in kilobytes. */
		return sprintf( __( '%s KB', 'wavira' ), number_format_i18n( round( $bytes / KB_IN_BYTES ) ) );
	}
}

if ( ! function_exists( 'wavira_get_download' ) ) {
	/**
	 * Markup for a download control, or an empty string when there is nothing to download.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $variant `button`, `link` or `list`.
	 * @param int    $quality Requested quality (0 = best available).
	 * @param string $label   Label override, empty string for the default.
	 * @param bool   $sizes   Whether to print file sizes (list variant only).
	 * @return string HTML, empty string when the post has no downloadable file.
	 */
	function wavira_get_download( $post_id, $variant = 'button', $quality = 0, $label = '', $sizes = false ) {
		$post_id = absint( $post_id );

		if ( $post_id < 1 || ! function_exists( 'wavira_core_download_qualities' ) ) {
			return '';
		}

		$qualities = wavira_download_qualities( $post_id );

		if ( empty( $qualities ) ) {
			return '';
		}

		$type  = (string) $qualities[0]['type'];
		$label = '' !== $label ? $label : wavira_download_type_label( $post_id, $type );

		if ( 'list' === $variant && count( $qualities ) > 1 ) {
			$items = '';

			foreach ( $qualities as $item ) {
				$url = wavira_core_download_url( $post_id, (int) $item['quality'] );

				if ( '' === $url ) {
					continue;
				}

				$item_label = (string) ( $item['label'] ?? '' );

				if ( '' !== $item_label && function_exists( 'wavira_core_digits' ) ) {
					$item_label = wavira_core_digits( $item_label );
				}

				$suffix = '' !== $item_label ? ' <span class="wavira-download__meta">' . esc_html( $item_label ) . '</span>' : '';

				if ( $sizes ) {
					$size = wavira_download_size_label( wavira_download_file_size( $post_id, (int) $item['quality'], $type ) );

					if ( '' !== $size ) {
						$suffix .= ' <span class="wavira-download__meta">' . esc_html( $size ) . '</span>';
					}
				}

				$items .= sprintf(
					'<li class="wavira-download__item"><a class="wavira-download__link" href="%s" download>%s%s</a></li>',
					esc_url( $url ),
					esc_html( $label ),
					$suffix // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above from esc_html() pieces.
				);
			}

			if ( '' === $items ) {
				return '';
			}

			return '<ul class="wavira-download wavira-download--list">' . $items . '</ul>';
		}

		$url = wavira_core_download_url( $post_id, $quality );

		if ( '' === $url ) {
			return '';
		}

		$chosen = $quality > 0 ? $quality : (int) $qualities[0]['quality'];

		$suffix = '';
		$meta   = wavira_download_meta_label( $post_id, $chosen, $type, $sizes );

		if ( '' !== $meta ) {
			$suffix = ' <span class="wavira-download__meta">' . esc_html( $meta ) . '</span>';
		}

		$class = 'link' === $variant ? 'wavira-download wavira-download--link' : 'wavira-download wavira-download--button';

		return sprintf(
			'<p class="%s"><a class="wavira-download__link" href="%s" download>%s%s</a></p>',
			esc_attr( $class ),
			esc_url( $url ),
			esc_html( $label ),
			$suffix // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above from esc_html() pieces.
		);
	}
}

if ( ! function_exists( 'wavira_download_meta_label' ) ) {
	/**
	 * The quality and size shown next to one download link.
	 *
	 * @param int    $post_id Post ID.
	 * @param int    $quality Quality.
	 * @param string $type    Download type.
	 * @param bool   $sizes   Whether to include the file size.
	 * @return string Label, empty string when there is nothing to say.
	 */
	function wavira_download_meta_label( $post_id, $quality, $type, $sizes = true ) {
		$parts = array();

		$digits = static function ( $value ) {
			return function_exists( 'wavira_core_digits' ) ? wavira_core_digits( $value ) : $value;
		};

		// The label comes from the plugin (`Sources::quality_label()`), which
		// knows the unit and the catalogue that translates it: a theme that
		// glues “kbps” onto a number here prints English on a Persian site.
		foreach ( wavira_download_qualities( $post_id ) as $item ) {
			$item_quality = (int) ( $item['quality'] ?? 0 );
			$item_label   = (string) ( $item['label'] ?? '' );

			if ( $item_quality === (int) $quality && '' !== $item_label ) {
				$parts[] = $digits( $item_label );
				break;
			}
		}

		if ( $sizes ) {
			$size = wavira_download_size_label( wavira_download_file_size( $post_id, $quality, $type ) );

			if ( '' !== $size ) {
				$parts[] = $size;
			}
		}

		return implode( ' · ', $parts );
	}
}

if ( ! function_exists( 'wavira_download_file_size' ) ) {
	/**
	 * The stored file size of one quality, when the site knows it.
	 *
	 * @param int    $post_id Post ID.
	 * @param int    $quality Quality.
	 * @param string $type    Download type.
	 * @return int Bytes, 0 when unknown.
	 */
	function wavira_download_file_size( $post_id, $quality, $type ) {
		if ( 'track' !== $type ) {
			return 0;
		}

		$keys = array(
			128 => 'wavira_file_size_128',
			320 => 'wavira_file_size_320',
		);

		if ( ! isset( $keys[ $quality ] ) ) {
			return 0;
		}

		return (int) get_post_meta( absint( $post_id ), $keys[ $quality ], true );
	}
}

if ( ! function_exists( 'wavira_download' ) ) {
	/**
	 * Print a download control.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $variant Variant.
	 * @param int    $quality Quality.
	 * @param string $label   Label override.
	 * @param bool   $sizes   Whether to print sizes.
	 * @return void
	 */
	function wavira_download( $post_id, $variant = 'button', $quality = 0, $label = '', $sizes = false ) {
		$markup = wavira_get_download( $post_id, $variant, $quality, $label, $sizes );

		if ( '' !== $markup ) {
			echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in wavira_get_download().
		}
	}
}
