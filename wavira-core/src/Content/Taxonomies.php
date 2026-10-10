<?php
/**
 * Classification taxonomies: genre and kind (always on) plus optional mood,
 * language, label and year (ADR 0003, ADR 0012).
 *
 * Genres are never hardcoded — they are ordinary, editable terms. `kind` is the
 * one taxonomy with a fixed vocabulary (music, remix, noha, podcast), because it
 * answers "what *is* this audio item", it is what a Persian music site browses
 * by («ریمیکس‌ها»، «نوحه‌ها»، «پادکست‌ها»), and it is the field a publishing
 * plugin has to fill so the item lands somewhere meaningful instead of being
 * flattened into a generic track (see `src/Integrations/MusicPublisher.php`).
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
	 * Kind taxonomy: what the audio item is (single, remix, noha, podcast).
	 *
	 * @var string
	 */
	public const KIND = 'wavira_kind';

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
		return in_array( $taxonomy, array( self::GENRE, self::KIND, self::MOOD, self::LANGUAGE, self::LABEL, self::YEAR ), true );
	}

	/**
	 * The fixed vocabulary of the kind taxonomy.
	 *
	 * Term slug => label. Slugs are stable (they are in URLs and in the import
	 * map); labels are translated. `music` is the default every item falls back
	 * to, so an item is never kind-less.
	 *
	 * @return array<string, string>
	 */
	public static function kinds(): array {
		return array(
			'music'   => __( 'Single', 'wavira-core' ),
			'remix'   => __( 'Remix', 'wavira-core' ),
			'noha'    => __( 'Noha', 'wavira-core' ),
			'podcast' => __( 'Podcast', 'wavira-core' ),
		);
	}

	/**
	 * The kind slug a source value means.
	 *
	 * @param string $value Raw value (a `musics_type`, a free label, a slug).
	 * @return string Kind slug, or an empty string when nothing matches — an
	 *                unknown value is reported, never filed under a kind it may
	 *                not be.
	 */
	public static function normalize_kind( string $value ): string {
		$value = strtolower( trim( $value ) );

		if ( '' === $value ) {
			return '';
		}

		if ( isset( self::kinds()[ $value ] ) ) {
			return $value;
		}

		$aliases = self::kind_aliases();

		return $aliases[ $value ] ?? '';
	}

	/**
	 * Source spellings that mean one of the kinds.
	 *
	 * The audited publishing plugin names its values after its own panel:
	 * `musicss`, `musicss_remix`, `musicss_nohe`, `musicss_podcast`. The map is
	 * explicit on purpose — `music` is a substring of `musicss_remix`, so a
	 * loose match would file every remix under "Single", and `nohe`/`noha` are
	 * two transliterations of the same Persian word (نوحه).
	 *
	 * @return array<string, string> Source value => kind slug.
	 */
	public static function kind_aliases(): array {
		return array(
			'musicss'         => 'music',
			'musicss_remix'   => 'remix',
			'musicss_nohe'    => 'noha',
			'musicss_podcast' => 'podcast',
			'single'          => 'music',
			'song'            => 'music',
			'nohe'            => 'noha',
		);
	}

	/**
	 * Make sure every kind term exists.
	 *
	 * Called by the seeder and by the importer: a term that does not exist
	 * cannot be assigned, and `wp_set_object_terms()` would fail silently.
	 *
	 * @return void
	 */
	public static function ensure_kind_terms(): void {
		if ( ! taxonomy_exists( self::KIND ) ) {
			return;
		}

		foreach ( self::kinds() as $slug => $label ) {
			if ( ! term_exists( $slug, self::KIND ) ) {
				wp_insert_term( $label, self::KIND, array( 'slug' => $slug ) );
			}
		}
	}

	/**
	 * Register the taxonomies and attach them to the music post types.
	 *
	 * @return void
	 */
	public function register_taxonomies(): void {
		foreach ( self::definitions() as $taxonomy => $definition ) {
			// Optional taxonomies can be switched off; genre is always available.
			if ( '' !== $definition['setting'] && ! Settings::get( $definition['setting'] ) ) {
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
			self::KIND     => array(
				'name'        => __( 'Kinds', 'wavira-core' ),
				'singular'    => __( 'Kind', 'wavira-core' ),
				'slug'        => 'kinds',
				'rest_base'   => 'kinds',
				'hierarchy'   => false,
				'setting'     => '',
				'object_type' => array( PostTypes::TRACK ),
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
