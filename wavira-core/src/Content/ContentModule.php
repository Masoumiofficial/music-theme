<?php
/**
 * Wires the content layer together and owns rewrite-rule lifecycle.
 *
 * @package Wavira\Core\Content
 */

namespace Wavira\Core\Content;

use Wavira\Core\Contracts\Registrable;

defined( 'ABSPATH' ) || exit;

/**
 * Class ContentModule
 */
final class ContentModule implements Registrable {

	/**
	 * Register the content layer.
	 *
	 * @return void
	 */
	public function register(): void {
		( new PostTypes() )->register();
		( new Taxonomies() )->register();
		( new Meta() )->register();

		add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ), 20 );
	}

	/**
	 * Flush rewrite rules exactly once per plugin version.
	 *
	 * Registering post types/taxonomies does not flush rules by itself; without
	 * this, archives 404 after an upgrade. Flushing on every request would be a
	 * performance bug, so it is gated on a version option.
	 *
	 * @return void
	 */
	public function maybe_flush_rewrite_rules(): void {
		if ( defined( 'WAVIRA_CORE_VERSION' ) && WAVIRA_CORE_VERSION === get_option( 'wavira_core_version' ) ) {
			return;
		}

		flush_rewrite_rules( false );

		if ( defined( 'WAVIRA_CORE_VERSION' ) ) {
			update_option( 'wavira_core_version', WAVIRA_CORE_VERSION, false );
		}
	}
}
