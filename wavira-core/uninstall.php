<?php
/**
 * Uninstall routine for Wavira Core.
 *
 * Data safety rule (CODING-STANDARD.md / ADR 0007): deleting a plugin must never
 * destroy a catalogue by surprise. Content (CPTs, taxonomies, meta) is therefore
 * kept by default; it is removed only when the site owner explicitly opted in
 * with the `wavira_settings['remove_data_on_uninstall']` setting.
 *
 * @package Wavira\Core
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$wavira_settings = get_option( 'wavira_settings', array() );

// Collect the site's music content only when the owner asked for deletion.
if ( is_array( $wavira_settings ) && ! empty( $wavira_settings['remove_data_on_uninstall'] ) ) {
	$wavira_post_types = array( 'wavira_artist', 'wavira_album', 'wavira_track', 'wavira_video' );

	foreach ( $wavira_post_types as $wavira_post_type ) {
		$wavira_posts = get_posts(
			array(
				'post_type'      => $wavira_post_type,
				'post_status'    => 'any',
				'numberposts'    => -1, // Uninstall only: explicit user opt-in, run once, off the front end.
				'fields'         => 'ids',
				'suppress_filters' => true,
			)
		);

		foreach ( $wavira_posts as $wavira_post_id ) {
			wp_delete_post( $wavira_post_id, true );
		}
	}
}

// Settings and caches are always removed — they are regenerated on activation.
delete_option( 'wavira_settings' );
delete_option( 'wavira_player_settings' );
delete_option( 'wavira_core_version' );
delete_transient( 'wavira_related_cache_version' );
