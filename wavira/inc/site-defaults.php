<?php
/**
 * One-click Persian setup for the site.
 *
 * The theme and the plugin translate their own screens, but WordPress itself,
 * the timezone and the date format are *site* settings — and a Persian music
 * site that shows Gregorian dates in an English dashboard is not finished. Three
 * of those four values are the same for every buyer, so they are offered as one
 * action instead of a paragraph of instructions:
 *
 * - the site language (`WPLANG`), and the core language pack when the host can
 *   reach api.wordpress.org;
 * - the timezone (`Asia/Tehran`) and the week start (Saturday);
 * - the date format, so dates read as «۱۴۰۵/۰۷/۱۵» and not `2026/10/07`.
 *
 * When the Wavira Core plugin is active the theme calls its public function and
 * the two stay one implementation; without the plugin the theme applies the same
 * four options itself, because a theme that needs a plugin to be Persian would
 * contradict its own product statement.
 *
 * @package Wavira\Theme
 * @since   0.12.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Nonced URL of the "make the site Persian" action.
 *
 * @return string Admin URL.
 */
function wavira_persian_setup_url(): string {
	return wp_nonce_url( admin_url( 'admin-post.php?action=wavira_persian_setup' ), 'wavira_persian_setup' );
}

/**
 * Nonced URL that hides the Persian-setup notice for this user.
 *
 * @return string Admin URL.
 */
function wavira_persian_dismiss_url(): string {
	return wp_nonce_url( admin_url( 'admin-post.php?action=wavira_persian_dismiss' ), 'wavira_persian_dismiss' );
}

/**
 * Apply the Iranian defaults, through the plugin when it is there.
 *
 * @return string[] Human-readable notes about what happened.
 */
function wavira_apply_persian_defaults(): array {
	$notes = array();

	if ( function_exists( 'wavira_core_apply_persian_defaults' ) ) {
		$report = wavira_core_apply_persian_defaults();

		if ( is_array( $report ) && isset( $report['notices'] ) ) {
			$notes = array_map( 'strval', (array) $report['notices'] );
		}
	} else {
		update_option( 'WPLANG', 'fa_IR' );
		update_option( 'timezone_string', 'Asia/Tehran' );
		update_option( 'start_of_week', 6 );
		update_option( 'date_format', 'j F Y' );
		update_option( 'time_format', 'H:i' );
		$notes[] = __( 'Site language, timezone, week start and date format were set to the Iranian defaults.', 'wavira' );
	}

	if ( ! wavira_has_core_language_pack() ) {
		if ( ! function_exists( 'wp_download_language_pack' ) ) {
			require_once ABSPATH . 'wp-admin/includes/translation-install.php';
		}

		$downloaded = wp_download_language_pack( 'fa_IR' );

		$notes[] = $downloaded
			? __( 'The Persian translation of WordPress itself was installed.', 'wavira' )
			: __( 'The Persian translation of WordPress itself could not be downloaded: the server has to reach api.wordpress.org. The theme and the plugin are Persian either way.', 'wavira' );
	}

	return $notes;
}

/**
 * Whether a Persian translation of WordPress core is already installed.
 *
 * Modern WordPress stores translations as `fa_IR-<hash>.l10n.php`; older
 * releases use `fa_IR.mo`. Both are looked for, because the answer decides
 * whether a download is attempted at all.
 *
 * @param string $directory Language directory. Defaults to `WP_LANG_DIR`; the
 *                          parameter exists so the question can be tested
 *                          without writing into the real language directory.
 * @return bool
 */
function wavira_has_core_language_pack( string $directory = '' ): bool {
	$directory = '' === $directory ? WP_LANG_DIR : rtrim( $directory, '/\\' );
	$matches   = glob( $directory . '/fa_IR*' );

	return is_array( $matches ) && array() !== $matches;
}

/**
 * Handle the "make the site Persian" action.
 *
 * @return void
 */
function wavira_handle_persian_setup(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to change the site language.', 'wavira' ), 403 );
	}

	check_admin_referer( 'wavira_persian_setup' );

	$notes   = wavira_apply_persian_defaults();
	$referer = wp_get_referer();
	$target  = add_query_arg( 'wavira-persian', 'done', $referer ? $referer : admin_url() );

	set_transient( 'wavira_persian_notes_' . get_current_user_id(), $notes, 60 );

	wp_safe_redirect( $target );
	exit;
}
add_action( 'admin_post_wavira_persian_setup', 'wavira_handle_persian_setup' );

/**
 * Handle the dismissal of the Persian-setup notice.
 *
 * @return void
 */
function wavira_handle_persian_dismiss(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to change this.', 'wavira' ), 403 );
	}

	check_admin_referer( 'wavira_persian_dismiss' );

	update_user_meta( get_current_user_id(), 'wavira_persian_notice', 'dismissed' );

	$referer = wp_get_referer();

	wp_safe_redirect( $referer ? $referer : admin_url() );
	exit;
}
add_action( 'admin_post_wavira_persian_dismiss', 'wavira_handle_persian_dismiss' );

/**
 * Tell an administrator about it when the site is not Persian yet.
 *
 * Shown on the two screens where a theme decision is made — Appearance → Themes
 * and the Customizer — never on every screen, and never to a user who cannot act
 * on it. One dismissal per user, kept in user meta, because a nagging notice is
 * worse than an unfinished setting.
 *
 * @return void
 */
function wavira_persian_admin_notice(): void {
	if ( ! current_user_can( 'manage_options' ) || 0 === strpos( get_locale(), 'fa' ) ) {
		return;
	}

	if ( 'dismissed' === get_user_meta( get_current_user_id(), 'wavira_persian_notice', true ) ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	if ( ! $screen || ! in_array( $screen->id, array( 'themes', 'customize', 'appearance_page_wavira-demo' ), true ) ) {
		return;
	}

	$done  = isset( $_GET['wavira-persian'] ) && 'done' === sanitize_key( wp_unslash( (string) $_GET['wavira-persian'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading our own read-only redirect flag.
	$notes = array();

	if ( $done ) {
		$stored = get_transient( 'wavira_persian_notes_' . get_current_user_id() );

		$notes = is_array( $stored ) ? $stored : array();

		delete_transient( 'wavira_persian_notes_' . get_current_user_id() );
	}

	$type = $done ? 'success' : 'info';

	echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p><strong>' . esc_html__( 'Wavira', 'wavira' ) . '</strong> — ';

	if ( $done ) {
		esc_html_e( 'The Persian setup ran.', 'wavira' );

		if ( array() !== $notes ) {
			echo ' ' . esc_html( implode( ' ', $notes ) );
		}
	} else {
		esc_html_e( 'The site language is not Persian, so WordPress’ own screens stay in English. The theme and the plugin are already Persian.', 'wavira' );
		echo ' <a href="' . esc_url( wavira_persian_setup_url() ) . '">' . esc_html__( 'Make the site Persian', 'wavira' ) . '</a> · ';
		echo '<a href="' . esc_url( wavira_persian_dismiss_url() ) . '">' . esc_html__( 'Not now', 'wavira' ) . '</a>';
	}

	echo '</p></div>';
}
add_action( 'admin_notices', 'wavira_persian_admin_notice' );
