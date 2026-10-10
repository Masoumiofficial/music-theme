<?php
/**
 * Music content types: artist, album, track, video.
 *
 * Slugs and REST bases are defined exactly once here (ADR 0011) so nothing can
 * drift between the front end, the REST API and the migration tool.
 *
 * @package Wavira\Core\Content
 */

namespace Wavira\Core\Content;

use Wavira\Core\Contracts\Registrable;

defined( 'ABSPATH' ) || exit;

/**
 * Class PostTypes
 */
final class PostTypes implements Registrable {

	/**
	 * Artist post type.
	 *
	 * @var string
	 */
	public const ARTIST = 'wavira_artist';

	/**
	 * Album post type.
	 *
	 * @var string
	 */
	public const ALBUM = 'wavira_album';

	/**
	 * Track post type.
	 *
	 * @var string
	 */
	public const TRACK = 'wavira_track';

	/**
	 * Music video post type.
	 *
	 * @var string
	 */
	public const VIDEO = 'wavira_video';

	/**
	 * Register hooks.
	 *
	 * Post types are registered early on `init` so that taxonomies registered at
	 * priority 1 can attach to them.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_post_types' ), 0 );
	}

	/**
	 * All music post type names.
	 *
	 * @return string[]
	 */
	public static function all(): array {
		return array( self::ARTIST, self::ALBUM, self::TRACK, self::VIDEO );
	}

	/**
	 * Whether a post type belongs to the music model.
	 *
	 * @param string $post_type Post type name.
	 * @return bool
	 */
	public static function is_music_type( string $post_type ): bool {
		return in_array( $post_type, self::all(), true );
	}

	/**
	 * Public slug (rewrite + archive) for a post type.
	 *
	 * @param string $post_type Post type name.
	 * @return string Empty string for unknown types.
	 */
	public static function slug_for( string $post_type ): string {
		$definitions = self::definitions();

		return isset( $definitions[ $post_type ] ) ? $definitions[ $post_type ]['slug'] : '';
	}

	/**
	 * Register the four music post types.
	 *
	 * @return void
	 */
	public function register_post_types(): void {
		foreach ( self::definitions() as $post_type => $definition ) {
			register_post_type( $post_type, $this->args_for( $definition ) );
		}
	}

	/**
	 * Post type definitions: one source of truth for labels, slugs and REST bases.
	 *
	 * @return array<string, array<string, string>>
	 */
	private static function definitions(): array {
		return array(
			self::ARTIST => array(
				'name'      => __( 'Artists', 'wavira-core' ),
				'singular'  => __( 'Artist', 'wavira-core' ),
				'slug'      => 'artists',
				'rest_base' => 'artists',
				'icon'      => 'dashicons-groups',
			),
			self::ALBUM  => array(
				'name'      => __( 'Albums', 'wavira-core' ),
				'singular'  => __( 'Album', 'wavira-core' ),
				'slug'      => 'albums',
				'rest_base' => 'albums',
				'icon'      => 'dashicons-album',
			),
			self::TRACK  => array(
				'name'      => __( 'Tracks', 'wavira-core' ),
				'singular'  => __( 'Track', 'wavira-core' ),
				'slug'      => 'tracks',
				'rest_base' => 'tracks',
				'icon'      => 'dashicons-format-audio',
			),
			self::VIDEO  => array(
				'name'      => __( 'Music videos', 'wavira-core' ),
				'singular'  => __( 'Music video', 'wavira-core' ),
				'slug'      => 'videos',
				'rest_base' => 'videos',
				'icon'      => 'dashicons-video-alt3',
			),
		);
	}

	/**
	 * Build registration arguments for one post type definition.
	 *
	 * @param array<string, string> $definition Definition from self::definitions().
	 * @return array<string, mixed>
	 */
	private function args_for( array $definition ): array {
		$labels = array(
			'name'                  => $definition['name'],
			'singular_name'         => $definition['singular'],
			'menu_name'             => $definition['name'],
			'add_new_item'          => sprintf(
				/* translators: %s: content type singular name (e.g. "Track"). */
				__( 'Add new %s', 'wavira-core' ),
				$definition['singular']
			),
			'edit_item'             => sprintf(
				/* translators: %s: content type singular name. */
				__( 'Edit %s', 'wavira-core' ),
				$definition['singular']
			),
			'new_item'              => sprintf(
				/* translators: %s: content type singular name. */
				__( 'New %s', 'wavira-core' ),
				$definition['singular']
			),
			'view_item'             => sprintf(
				/* translators: %s: content type singular name. */
				__( 'View %s', 'wavira-core' ),
				$definition['singular']
			),
			'search_items'          => sprintf(
				/* translators: %s: content type plural name. */
				__( 'Search %s', 'wavira-core' ),
				$definition['name']
			),
			'not_found'             => __( 'Nothing found.', 'wavira-core' ),
			'not_found_in_trash'    => __( 'Nothing found in Trash.', 'wavira-core' ),
			'archives'              => sprintf(
				/* translators: %s: content type plural name. */
				__( '%s archive', 'wavira-core' ),
				$definition['name']
			),
			'featured_image'        => __( 'Cover image', 'wavira-core' ),
			'set_featured_image'    => __( 'Set cover image', 'wavira-core' ),
			'remove_featured_image' => __( 'Remove cover image', 'wavira-core' ),
			'use_featured_image'    => __( 'Use as cover image', 'wavira-core' ),
		);

		return array(
			'labels'                => $labels,
			'description'           => $definition['name'],
			'public'                => true,
			'publicly_queryable'    => true,
			'show_ui'               => true,
			'show_in_menu'          => true,
			'show_in_nav_menus'     => true,
			'show_in_admin_bar'     => true,
			'show_in_rest'          => true,
			'rest_base'             => $definition['rest_base'],
			'rest_controller_class' => 'WP_REST_Posts_Controller',
			'menu_icon'             => $definition['icon'],
			'hierarchical'          => false,
			'has_archive'           => $definition['slug'],
			'rewrite'               => array(
				'slug'       => $definition['slug'],
				'with_front' => false,
			),
			'query_var'             => true,
			'capability_type'       => 'post',
			'map_meta_cap'          => true,
			'supports'              => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'author' ),
			'delete_with_user'      => false,
			'taxonomies'            => array(),
		);
	}
}
