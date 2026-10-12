<?php
/**
 * Cover artwork resolution shared by the REST payloads and the player.
 *
 * One place decides which attachment and which size represent the artwork of a
 * post, so the card, the player and the API can never disagree.
 *
 * @package Wavira\Core\Content
 */

namespace Wavira\Core\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Class Cover
 */
final class Cover {

	/**
	 * Attachment ID that represents the artwork of a post.
	 *
	 * The featured image wins; the dedicated `wavira_cover` field is the escape
	 * hatch for artwork that is not the post thumbnail.
	 *
	 * @param int $post_id Post ID.
	 * @return int Attachment ID, 0 when the post has no artwork.
	 */
	public static function id( int $post_id ): int {
		$attachment_id = (int) get_post_thumbnail_id( $post_id );

		if ( ! $attachment_id ) {
			$attachment_id = MetaValues::int( $post_id, MetaSchema::COVER );
		}

		return $attachment_id;
	}

	/**
	 * Public URL of an attachment, using the theme size when it exists.
	 *
	 * `wavira-cover` is registered by the theme. Under any other theme the plugin
	 * must still return a usable image, so the chain ends at the full size and
	 * never returns an empty string for an existing attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	public static function url( int $attachment_id ): string {
		$candidates = array(
			'wavira-cover',
			'large',
			'medium',
			'thumbnail',
			'full',
		);

		foreach ( $candidates as $size ) {
			$url = wp_get_attachment_image_url( $attachment_id, $size );

			if ( is_string( $url ) && '' !== $url ) {
				return $url;
			}
		}

		return '';
	}

	/**
	 * Complete cover payload for a post.
	 *
	 * `srcset`/`sizes` are included on purpose: responsive images are never
	 * disabled in this product (PERFORMANCE-AUDIT P1), so consumers can choose a
	 * smaller file without the server knowing the viewport.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed> Empty array when the post has no artwork.
	 */
	public static function payload( int $post_id ): array {
		$attachment_id = self::id( $post_id );

		if ( ! $attachment_id ) {
			return array();
		}

		$url = self::url( $attachment_id );

		if ( '' === $url ) {
			return array();
		}

		$srcset = wp_get_attachment_image_srcset( $attachment_id, 'wavira-cover' );
		$sizes  = wp_get_attachment_image_sizes( $attachment_id, 'wavira-cover' );
		$meta   = wp_get_attachment_metadata( $attachment_id );

		return array(
			'id'     => $attachment_id,
			'url'    => $url,
			'alt'    => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'width'  => is_array( $meta ) && isset( $meta['width'] ) ? (int) $meta['width'] : 0,
			'height' => is_array( $meta ) && isset( $meta['height'] ) ? (int) $meta['height'] : 0,
			'srcset' => is_string( $srcset ) ? $srcset : '',
			'sizes'  => is_string( $sizes ) ? $sizes : '',
		);
	}
}
