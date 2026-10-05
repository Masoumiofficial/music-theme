<?php
/**
 * WP-CLI commands: `wp wavira verify` and `wp wavira seed`.
 *
 * The commands exist so that the data model can be validated and exercised on a
 * real installation without shipping demo content by accident. Seed data is
 * generated text only — no third-party media, no real lyrics (ADR 0010).
 *
 * @package Wavira\Core\Admin
 */

namespace Wavira\Core\Admin;

use Wavira\Core\Content\MetaSchema;
use Wavira\Core\Content\MetaValues;
use Wavira\Core\Content\PostTypes;
use Wavira\Core\Content\Taxonomies;
use Wavira\Core\Contracts\Registrable;
use Wavira\Core\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class Cli
 */
final class Cli implements Registrable {

	/**
	 * Register hooks (only inside a WP-CLI request).
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		\WP_CLI::add_command( 'wavira verify', array( $this, 'verify' ) );
		\WP_CLI::add_command( 'wavira seed', array( $this, 'seed' ) );
	}

	/**
	 * Verify the data model: post types, taxonomies and meta registration.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wavira verify
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Associative arguments (unused).
	 * @return void
	 */
	public function verify( $args = array(), $assoc_args = array() ): void {
		unset( $args, $assoc_args );

		$errors = array();

		foreach ( PostTypes::all() as $post_type ) {
			if ( ! post_type_exists( $post_type ) ) {
				$errors[] = sprintf( 'Post type missing: %s', $post_type );
			}
		}

		foreach ( array( Taxonomies::GENRE, Taxonomies::MOOD, Taxonomies::LANGUAGE, Taxonomies::LABEL, Taxonomies::YEAR ) as $taxonomy ) {
			$enabled = Taxonomies::GENRE === $taxonomy || Settings::get( 'enable_' . str_replace( 'wavira_', '', $taxonomy ), false );

			if ( $enabled && ! taxonomy_exists( $taxonomy ) ) {
				$errors[] = sprintf( 'Taxonomy missing: %s', $taxonomy );
			}
		}

		foreach ( MetaSchema::all() as $key => $field ) {
			foreach ( $field['entities'] as $post_type ) {
				if ( ! registered_meta_key_exists( 'post', $key, $post_type ) ) {
					$errors[] = sprintf( 'Meta not registered: %s on %s', $key, $post_type );
				}
			}
		}

		$counts = array();

		foreach ( PostTypes::all() as $post_type ) {
			$count                = wp_count_posts( $post_type );
			$counts[ $post_type ] = isset( $count->publish ) ? (int) $count->publish : 0;
		}

		\WP_CLI::log( 'Published content:' );

		foreach ( $counts as $post_type => $count ) {
			\WP_CLI::log( sprintf( '  %-18s %d', $post_type, $count ) );
		}

		$genres = get_terms(
			array(
				'taxonomy'   => Taxonomies::GENRE,
				'hide_empty' => false,
				'fields'     => 'count',
			)
		);

		\WP_CLI::log( sprintf( '  %-18s %d', 'genres', is_wp_error( $genres ) ? 0 : (int) $genres ) );

		if ( empty( $errors ) ) {
			\WP_CLI::success( 'Data model verified: post types, taxonomies and registered meta are all present.' );
			return;
		}

		foreach ( $errors as $error ) {
			\WP_CLI::warning( $error );
		}

		\WP_CLI::error( sprintf( '%d problem(s) found.', count( $errors ) ) );
	}

	/**
	 * Create a minimal, licence-clean demo set (artist, album, tracks, video).
	 *
	 * Idempotent: does nothing when the site already has tracks, unless --force.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Seed even when tracks already exist.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wavira seed
	 *     wp wavira seed --force
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function seed( $args = array(), $assoc_args = array() ): void {
		unset( $args );

		$force     = isset( $assoc_args['force'] );
		$count     = wp_count_posts( PostTypes::TRACK );
		$has_tracks = isset( $count->publish ) && (int) $count->publish > 0;

		if ( $has_tracks && ! $force ) {
			\WP_CLI::warning( 'Tracks already exist — nothing seeded. Use --force to seed anyway.' );
			return;
		}

		$artist_id = wp_insert_post(
			array(
				'post_type'    => PostTypes::ARTIST,
				'post_title'   => __( 'Demo Artist', 'wavira-core' ),
				'post_content' => __( 'Generated demo biography. Replace with real content.', 'wavira-core' ),
				'post_status'  => 'publish',
			),
			true
		);

		if ( is_wp_error( $artist_id ) ) {
			\WP_CLI::error( $artist_id->get_error_message() );
		}

		$album_id = wp_insert_post(
			array(
				'post_type'   => PostTypes::ALBUM,
				'post_title'  => __( 'Demo Album', 'wavira-core' ),
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $album_id ) ) {
			\WP_CLI::error( $album_id->get_error_message() );
		}

		$track_ids = array();

		for ( $index = 1; $index <= 3; $index++ ) {
			$track_id = wp_insert_post(
				array(
					'post_type'   => PostTypes::TRACK,
					'post_title'  => sprintf(
						/* translators: %d: demo track number. */
						__( 'Demo Track %d', 'wavira-core' ),
						$index
					),
					'post_status' => 'publish',
					'menu_order'  => $index,
				),
				true
			);

			if ( is_wp_error( $track_id ) ) {
				\WP_CLI::error( $track_id->get_error_message() );
			}

			update_post_meta( $track_id, MetaSchema::ARTIST, (int) $artist_id );
			update_post_meta( $track_id, MetaSchema::ALBUM, (int) $album_id );
			update_post_meta( $track_id, MetaSchema::DURATION, 180 + ( $index * 7 ) );
			update_post_meta( $track_id, MetaSchema::LYRICS, __( 'Generated demo lyrics — replace this text.', 'wavira-core' ) );

			$track_ids[] = (int) $track_id;
		}

		update_post_meta( $album_id, MetaSchema::ARTIST, (int) $artist_id );
		update_post_meta( $album_id, MetaSchema::TRACKLIST, $track_ids );
		update_post_meta( $album_id, MetaSchema::ALBUM_TYPE, 'album' );

		$video_id = wp_insert_post(
			array(
				'post_type'   => PostTypes::VIDEO,
				'post_title'  => __( 'Demo Music Video', 'wavira-core' ),
				'post_status' => 'publish',
			),
			true
		);

		if ( ! is_wp_error( $video_id ) ) {
			update_post_meta( $video_id, MetaSchema::ARTIST, (int) $artist_id );
			update_post_meta( $video_id, MetaSchema::ALBUM, (int) $album_id );
			update_post_meta( $video_id, MetaSchema::VIDEO_SOURCE, 'other' );
		}

		$genre = wp_insert_term( __( 'Demo Genre', 'wavira-core' ), Taxonomies::GENRE );

		if ( ! is_wp_error( $genre ) && isset( $genre['term_id'] ) ) {
			foreach ( array_merge( $track_ids, array( (int) $album_id ) ) as $post_id ) {
				wp_set_object_terms( $post_id, array( (int) $genre['term_id'] ), Taxonomies::GENRE, false );
			}
		}

		\WP_CLI::success(
			sprintf(
				'Seeded artist #%1$d, album #%2$d, %3$d tracks and a video.',
				(int) $artist_id,
				(int) $album_id,
				count( $track_ids )
			)
		);
	}
}
