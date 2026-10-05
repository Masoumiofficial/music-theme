<?php
/**
 * Classification taxonomies: genre (required) plus optional mood, language,
 * label and year (ADR 0003, ADR 0012).
 *
 * Genres are never hardcoded — they are ordinary, editable terms.
 *
 * @package Wavira\Core\Content
 */

namespace Wavira\Core\Content;

use Wavira\Core\Contracts\Registrable;
use Wavira\Core\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class Taxonomies
 */
final class Taxonomies implements Registrable {

	/**
	 * Genre taxonomy.
	 *
	 * @var string
	 */
	public const GENRE = 'wavira_genre';

	/**
	 * Mood taxonomy (optional).
	 *
	 * @var string
	 */
	public const MOOD = 'wavira_mood';

	/**
	 * Language taxonomy (optional).
	 *
	 * @var string
	 */
	public const LANGUAGE = 'wavira_language';

	/**
	 * Record-label taxonomy (optional).
	 *
	 * @var string
	 */
	public const LABEL = 'wavira_label';

	/**
	 * Release-year taxonomy (optional).
	 *
	 * @var string
	 */
	public const YEAR = 'wavira_year';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_taxonomies' ), 1 );
	}

	/**
	 * Whether a taxonomy belongs to the music model.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return bool
	 */
	public static function is_music_taxonomy( string $taxonomy ): bool {
		return in_array( $taxonomy, array( self::GENRE, self::MOOD, self::LANGUAGE, self::LABEL, self::YEAR ), true );
	}

	/**
	 * Register the taxonomies and attach them to the music post types.
	 *
	 * @return void
	 */
	public function register_taxonomies(): void {
		foreach ( self::definitions() as $taxonomy => $definition ) {
			// Optional taxonomies can be switched off; genre is always available.
			if ( '' !== $definition['setting'] && ! Settings::get( $definition['setting'], false ) ) {
				continue;
			}

			register_taxonomy( $taxonomy, $definition['object_type'], $this->args_for( $definition ) );
		}
	}

	/**
	 * Taxonomy definitions.
	 *
	 * `setting` is the settings key that enables an optional taxonomy; an empty
	 * string means the taxonomy is always active.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function definitions(): array {
		return array(
			self::GENRE    => array(
				'name'        => __( 'Genres', 'wavira-core' ),
				'singular'    => __( 'Genre', 'wavira-core' ),
				'slug'        => 'genres',
				'rest_base'   => 'genres',
				'hierarchy'   => true,
				'setting'     => '',
				'object_type' => array( PostTypes::TRACK, PostTypes::ALBUM, PostTypes::VIDEO, PostTypes::ARTIST ),
			),
			self::MOOD     => array(
				'name'        => __( 'Moods', 'wavira-core' ),
				'singular'    => __( 'Mood', 'wavira-core' ),
				'slug'        => 'moods',
				'rest_base'   => 'moods',
				'hierarchy'   => false,
				'setting'     => 'enable_mood',
				'object_type' => array( PostTypes::TRACK, PostTypes::ALBUM ),
			),
			self::LANGUAGE => array(
				'name'        => __( 'Languages', 'wavira-core' ),
				'singular'    => __( 'Language', 'wavira-core' ),
				'slug'        => 'languages',
				'rest_base'   => 'languages',
				'hierarchy'   => false,
				'setting'     => 'enable_language',
				'object_type' => array( PostTypes::TRACK, PostTypes::ALBUM, PostTypes::VIDEO ),
			),
			self::LABEL    => array(
				'name'        => __( 'Labels', 'wavira-core' ),
				'singular'    => __( 'Label', 'wavira-core' ),
				'slug'        => 'labels',
				'rest_base'   => 'labels',
				'hierarchy'   => false,
				'setting'     => 'enable_label',
				'object_type' => array( PostTypes::ALBUM ),
			),
			self::YEAR     => array(
				'name'        => __( 'Release years', 'wavira-core' ),
				'singular'    => __( 'Release year', 'wavira-core' ),
				'slug'        => 'years',
				'rest_base'   => 'years',
				'hierarchy'   => false,
				'setting'     => 'enable_year',
				'object_type' => array( PostTypes::TRACK, PostTypes::ALBUM, PostTypes::VIDEO ),
			),
		);
	}

	/**
	 * Build registration arguments for a taxonomy definition.
	 *
	 * @param array<string, mixed> $definition Taxonomy definition.
	 * @return array<string, mixed>
	 */
	private function args_for( array $definition ): array {
		$labels = array(
			'name'          => $definition['name'],
			'singular_name' => $definition['singular'],
			'search_items'  => sprintf(
				/* translators: %s: taxonomy plural name. */
				__( 'Search %s', 'wavira-core' ),
				$definition['name']
			),
			'all_items'     => sprintf(
				/* translators: %s: taxonomy plural name. */
				__( 'All %s', 'wavira-core' ),
				$definition['name']
			),
			'edit_item'     => sprintf(
				/* translators: %s: taxonomy singular name. */
				__( 'Edit %s', 'wavira-core' ),
				$definition['singular']
			),
			'update_item'   => sprintf(
				/* translators: %s: taxonomy singular name. */
				__( 'Update %s', 'wavira-core' ),
				$definition['singular']
			),
			'add_new_item'  => sprintf(
				/* translators: %s: taxonomy singular name. */
				__( 'Add new %s', 'wavira-core' ),
				$definition['singular']
			),
			'new_item_name' => sprintf(
				/* translators: %s: taxonomy singular name. */
				__( 'New %s name', 'wavira-core' ),
				$definition['singular']
			),
		);

		return array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'hierarchical'       => (bool) $definition['hierarchy'],
			'show_ui'            => true,
			'show_admin_column'  => true,
			'show_in_rest'       => true,
			'rest_base'          => $definition['rest_base'],
			'query_var'          => true,
			'show_in_nav_menus'  => true,
			'rewrite'            => array(
				'slug'       => $definition['slug'],
				'with_front' => false,
			),
		);
	}
}
