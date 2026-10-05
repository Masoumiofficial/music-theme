<?php
/**
 * Flushes computed caches when music content or settings change.
 *
 * One place decides invalidation, so no service has to guess when its data went
 * stale (ADR 0009).
 *
 * @package Wavira\Core\Support
 */

namespace Wavira\Core\Support;

use Wavira\Core\Content\PostTypes;
use Wavira\Core\Content\Taxonomies;
use Wavira\Core\Contracts\Registrable;
use Wavira\Core\Settings\SettingsSchema;

defined( 'ABSPATH' ) || exit;

/**
 * Class CacheInvalidator
 */
final class CacheInvalidator implements Registrable {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'save_post', array( $this, 'on_save_post' ), 10, 1 );
		add_action( 'deleted_post', array( $this, 'on_deleted_post' ), 10, 1 );
		add_action( 'edited_term', array( $this, 'on_term_change' ), 10, 3 );
		add_action( 'delete_term', array( $this, 'on_term_change' ), 10, 3 );
		add_action( 'update_option_' . SettingsSchema::OPTION, array( $this, 'on_settings_update' ) );
	}

	/**
	 * Flush caches after a music post is saved.
	 *
	 * @param int $post_id Saved post ID.
	 * @return void
	 */
	public function on_save_post( $post_id ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( PostTypes::is_music_type( (string) get_post_type( $post_id ) ) ) {
			Cache::flush();
		}
	}

	/**
	 * Flush caches after a post is deleted.
	 *
	 * `deleted_post` fires after the row is gone, so the type must be passed in.
	 *
	 * @param int $post_id Deleted post ID.
	 * @return void
	 */
	public function on_deleted_post( $post_id ): void {
		unset( $post_id );

		// Post type is no longer readable at this point: flush unconditionally.
		Cache::flush();
	}

	/**
	 * Flush caches when a music taxonomy term changes.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return void
	 */
	public function on_term_change( $term_id, $tt_id, $taxonomy ): void {
		unset( $term_id, $tt_id );

		if ( Taxonomies::is_music_taxonomy( (string) $taxonomy ) ) {
			Cache::flush();
		}
	}

	/**
	 * Flush caches when the settings option is updated.
	 *
	 * @return void
	 */
	public function on_settings_update(): void {
		Cache::flush();
	}
}
