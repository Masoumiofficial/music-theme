<?php
/**
 * The demo-content screen: install, or download, without a terminal.
 *
 * Most buyers of a WordPress product never open WP-CLI, and a demo that only a
 * developer can install is a demo most customers never see. This screen is the
 * same installer the `wp wavira seed` command runs (`Demo\Installer`), behind a
 * capability check and a nonce — one code path, two front doors.
 *
 * Two actions, both `POST`, both protected the WordPress way
 * (`admin_post_*` + `check_admin_referer`), so nothing here is reachable by a
 * link, an image tag, or a logged-out visitor:
 *
 * - **Import** the Persian demo catalogue (or the English fixture).
 * - **Download** the site's music content as a WXR file for another install.
 *
 * @package Wavira\Core
 */

namespace Wavira\Core\Admin;

use RuntimeException;
use Wavira\Core\Contracts\Registrable;
use Wavira\Core\Demo\Exporter;
use Wavira\Core\Demo\Installer;

defined( 'ABSPATH' ) || exit;

/**
 * Class DemoPage
 */
final class DemoPage implements Registrable {

	/**
	 * Screen slug.
	 */
	public const SLUG = 'wavira-demo';

	/**
	 * Admin-post action for the import.
	 */
	public const ACTION_IMPORT = 'wavira_import_demo';

	/**
	 * Admin-post action for the export.
	 */
	public const ACTION_EXPORT = 'wavira_export_demo';

	/**
	 * Capability that guards both actions.
	 */
	public const CAPABILITY = 'manage_options';

	/**
	 * Register the screen and the two handlers.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_post_' . self::ACTION_IMPORT, array( $this, 'handle_import' ) );
		add_action( 'admin_post_' . self::ACTION_EXPORT, array( $this, 'handle_export' ) );
	}

	/**
	 * Add the screen under Tools.
	 *
	 * @return void
	 */
	public function add_page(): void {
		add_management_page(
			__( 'Wavira demo content', 'wavira-core' ),
			__( 'Wavira demo content', 'wavira-core' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$notice = $this->notice();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Wavira demo content', 'wavira-core' ); ?></h1>

			<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by notice() from fixed strings and integers. ?>

			<p>
				<?php esc_html_e( 'The demo is a small, complete Persian music site: one artist, an album with four tracks (one of them a remix), a single, and a music video — with lyrics, genres, durations and Jalali dates.', 'wavira-core' ); ?>
			</p>

			<p>
				<strong><?php esc_html_e( 'No audio or video files are imported.', 'wavira-core' ); ?></strong>
				<?php esc_html_e( 'A demo pointing at files that do not exist would break the player on the first click; add your own media and the player picks it up.', 'wavira-core' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_IMPORT ); ?>" />
				<?php wp_nonce_field( self::ACTION_IMPORT ); ?>

				<fieldset>
					<legend class="screen-reader-text"><?php esc_html_e( 'Import options', 'wavira-core' ); ?></legend>

					<p>
						<label>
							<input type="radio" name="fixture" value="persian" checked="checked" />
							<?php esc_html_e( 'Persian demo (recommended): also sets the site language, the Tehran timezone, a Saturday week and Jalali dates.', 'wavira-core' ); ?>
						</label>
					</p>

					<p>
						<label>
							<input type="radio" name="fixture" value="english" />
							<?php esc_html_e( 'Neutral English fixture: content only, nothing about the site is changed.', 'wavira-core' ); ?>
						</label>
					</p>

					<p>
						<label>
							<input type="checkbox" name="force" value="1" />
							<?php esc_html_e( 'Replace a demo that was imported before (only posts this importer created are removed).', 'wavira-core' ); ?>
						</label>
					</p>
				</fieldset>

				<?php submit_button( __( 'Import demo content', 'wavira-core' ), 'primary', 'submit', false ); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Export the music content', 'wavira-core' ); ?></h2>

			<p>
				<?php esc_html_e( 'Download everything Wavira manages — artists, albums, tracks, videos, their terms and metadata — as a WXR file, the format any WordPress importer reads.', 'wavira-core' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_EXPORT ); ?>" />
				<?php wp_nonce_field( self::ACTION_EXPORT ); ?>
				<?php submit_button( __( 'Download WXR file', 'wavira-core' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Run the importer and come back with a notice.
	 *
	 * @return void
	 */
	public function handle_import(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to import demo content on this site.', 'wavira-core' ), 403 );
		}

		check_admin_referer( self::ACTION_IMPORT );

		$english = isset( $_POST['fixture'] ) && 'english' === sanitize_key( wp_unslash( (string) $_POST['fixture'] ) );

		try {
			$report = Installer::install(
				array(
					'force'   => isset( $_POST['force'] ),
					'english' => $english,
					'site'    => ! $english,
				)
			);

			$result = $report['skipped'] ? 'skipped' : 'imported';
			$args   = array(
				'wavira-demo' => $result,
				'releases'    => count( $report['releases'] ),
				'tracks'      => count( $report['tracks'] ),
				'media'       => (int) ( $report['media'] ?? 0 ),
			);
		} catch ( RuntimeException $exception ) {
			$args = array(
				'wavira-demo' => 'failed',
				'reason'      => rawurlencode( $exception->getMessage() ),
			);
		}

		wp_safe_redirect( add_query_arg( $args, $this->screen_url() ) );
		exit;
	}

	/**
	 * Stream a WXR export to the browser.
	 *
	 * @return void
	 */
	public function handle_export(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to export this site.', 'wavira-core' ), 403 );
		}

		check_admin_referer( self::ACTION_EXPORT );

		$document = Exporter::xml();

		if ( '1' !== $document['ok'] ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'wavira-demo' => 'failed',
						'reason'      => rawurlencode( $document['reason'] ),
					),
					$this->screen_url()
				)
			);
			exit;
		}

		nocache_headers();

		header( 'Content-Type: application/rss+xml; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . Exporter::file_name() . '"' );
		header( 'Content-Length: ' . strlen( $document['xml'] ) );

		// The document is generated by WordPress' own exporter, byte for byte.
		echo $document['xml']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a WXR document, not HTML.

		exit;
	}

	/**
	 * The URL of this screen.
	 *
	 * @return string
	 */
	private function screen_url(): string {
		return admin_url( 'tools.php?page=' . self::SLUG );
	}

	/**
	 * The notice for the current request, or an empty string.
	 *
	 * @return string Escaped-safe admin notice markup.
	 */
	private function notice(): string {
		$state  = isset( $_GET['wavira-demo'] ) ? sanitize_key( wp_unslash( (string) $_GET['wavira-demo'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: it only chooses which notice to print.
		$reason = isset( $_GET['reason'] ) ? sanitize_text_field( rawurldecode( wp_unslash( (string) $_GET['reason'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- same.
		$class  = 'notice-info';
		$text   = '';

		switch ( $state ) {
			case 'imported':
				$releases = isset( $_GET['releases'] ) ? (int) $_GET['releases'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a count for the message.
				$tracks   = isset( $_GET['tracks'] ) ? (int) $_GET['tracks'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- same.
				$class    = 'notice-success';
				$media    = isset( $_GET['media'] ) ? (int) $_GET['media'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- same.
				$text     = sprintf(
					/* translators: 1: number of releases, 2: number of tracks, 3: number of generated files. */
					_n(
						'Demo content imported: %1$d release, %2$d track and %3$d generated file (covers, audio, gallery photos). Open the front page to see it.',
						'Demo content imported: %1$d releases, %2$d tracks and %3$d generated files (covers, audio, gallery photos). Open the front page to see it.',
						$releases,
						'wavira-core'
					),
					$releases,
					$tracks,
					$media
				);
				break;
			case 'skipped':
				$class = 'notice-warning';
				$text  = __( 'This site already has tracks, so nothing was imported. Tick the replace box to import the demo anyway.', 'wavira-core' );
				break;
			case 'failed':
				$class = 'notice-error';
				$text  = '' !== $reason
					? sprintf(
						/* translators: %s: error message. */
						__( 'The demo content could not be imported: %s', 'wavira-core' ),
						$reason
					)
					: __( 'The demo content could not be imported.', 'wavira-core' );
				break;
		}

		if ( '' === $text ) {
			return '';
		}

		return sprintf(
			'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $class ),
			esc_html( $text )
		);
	}
}
