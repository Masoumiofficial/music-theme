<?php
/**
 * Export the site's music content as a WordPress eXtended RSS (WXR) document.
 *
 * WXR is the interchange format every WordPress importer reads, and the one a
 * site owner already knows: this is the path for moving a demo or a live
 * catalogue to another installation without touching a database dump.
 *
 * The exporter is a thin, honest wrapper around WordPress' own `export_wp()`
 * rather than a second implementation of it. Re-implementing WXR would mean
 * maintaining the format: the term/meta/attachment rules, the escaping, and the
 * `wp_import` quirks that make an export round-trip. None of that is a Wavira
 * decision, and Core already does it.
 *
 * @package Wavira\Core
 */

namespace Wavira\Core\Demo;

use Wavira\Core\Content\PostTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Class Exporter
 */
final class Exporter {

	/**
	 * Whether `export_wp()` has already run in this request.
	 *
	 * WordPress declares its `wxr_*()` helpers *inside* `export_wp()`, so calling
	 * it a second time in the same request is a fatal "Cannot redeclare
	 * wxr_cdata()" error rather than a second document. This flag — together with
	 * the `wxr_cdata()` probe in `xml()`, which also catches an export run by a
	 * third party earlier in the request — turns that fatal into a refusal the
	 * caller can report.
	 *
	 * @var bool
	 */
	private static $exported = false;

	/**
	 * Build a WXR document for the current site.
	 *
	 * @param array<string, mixed> $args `content` (post type or `all`) and
	 *                                   `status` (post status or `all`).
	 * @return array<string, string> `ok` (`1`/`0`), `xml`, `reason`.
	 */
	public static function xml( array $args = array() ): array {
		$content = (string) ( $args['content'] ?? 'all' );
		$status  = (string) ( $args['status'] ?? 'all' );

		if ( ! function_exists( 'export_wp' ) ) {
			$path = ABSPATH . 'wp-admin/includes/export.php';

			if ( ! is_readable( $path ) ) {
				return array(
					'ok'     => '0',
					'xml'    => '',
					'reason' => 'wp-admin/includes/export.php is not readable on this installation',
				);
			}

			require_once $path;
		}

		if ( ! function_exists( 'export_wp' ) ) {
			return array(
				'ok'     => '0',
				'xml'    => '',
				'reason' => 'export_wp() is not available on this installation',
			);
		}

		if ( self::$exported || function_exists( 'wxr_cdata' ) ) {
			return array(
				'ok'     => '0',
				'xml'    => '',
				'reason' => 'WordPress can only run its exporter once per request: Core declares the wxr_*() helpers inside export_wp(), so a second call in the same request is fatal. Run the export again in a new request.',
			);
		}

		// Set before the call: whatever Core leaves behind, a second call must not
		// reach `export_wp()`.
		self::$exported = true;

		$xml = self::capture( $content, $status );

		if ( '' === trim( $xml ) ) {
			return array(
				'ok'     => '0',
				'xml'    => '',
				'reason' => 'the export produced no content',
			);
		}

		return array(
			'ok'     => '1',
			'xml'    => $xml,
			'reason' => '',
		);
	}

	/**
	 * Run WordPress' exporter and return the document instead of sending it.
	 *
	 * `export_wp()` is written for a browser download: it announces the file with
	 * `header()` and then prints the document. On a request where output has
	 * already started — WP-CLI, the test suite, a plugin that printed a notice
	 * before ours — PHP raises a warning for each of those calls even though the
	 * document itself is produced correctly, and a harness that converts warnings
	 * into exceptions would fail on something that is not a defect. The guard is
	 * installed only in that case, only for warnings, and only around this call.
	 *
	 * @param string $content Post type, or `all`.
	 * @param string $status  Post status, or `all`.
	 * @return string The WXR document.
	 */
	private static function capture( string $content, string $status ): string {
		$guard = headers_sent();

		if ( $guard ) {
			set_error_handler( array( __CLASS__, 'ignore_header_warning' ), E_WARNING ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- scoped to a single documented call and restored immediately.
		}

		try {
			ob_start();

			export_wp(
				array(
					'content' => $content,
					'status'  => $status,
				)
			);

			return (string) ob_get_clean();
		} finally {
			if ( $guard ) {
				restore_error_handler();
			}
		}
	}

	/**
	 * Handle the header warning `export_wp()` raises when output has started.
	 *
	 * The document is still generated: the warning is about a download header
	 * that cannot be set any more, and the caller has already decided what to do
	 * with the bytes.
	 *
	 * @return bool Always true: the warning is handled here.
	 */
	public static function ignore_header_warning(): bool {
		return true;
	}

	/**
	 * Write the WXR document to a file.
	 *
	 * @param string               $path Absolute path to write to.
	 * @param array<string, mixed> $args Arguments for `xml()`.
	 * @return array<string, mixed> `ok` (bool), `file`, `bytes`, `reason`.
	 */
	public static function to_file( string $path, array $args = array() ): array {
		$document = self::xml( $args );

		if ( '1' !== $document['ok'] ) {
			return array(
				'ok'     => false,
				'file'   => '',
				'bytes'  => 0,
				'reason' => $document['reason'],
			);
		}

		$written = file_put_contents( $path, $document['xml'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- a path the operator chose, never a front-end request.

		if ( false === $written ) {
			return array(
				'ok'     => false,
				'file'   => '',
				'bytes'  => 0,
				'reason' => sprintf( 'could not write %s', $path ),
			);
		}

		return array(
			'ok'     => true,
			'file'   => $path,
			'bytes'  => (int) $written,
			'reason' => '',
		);
	}

	/**
	 * A file name for a downloaded export.
	 *
	 * @return string File name without a path.
	 */
	public static function file_name(): string {
		return sprintf(
			'wavira-content-%1$s-%2$s.xml',
			sanitize_title( (string) get_bloginfo( 'name' ) ),
			gmdate( 'Ymd-His' )
		);
	}

	/**
	 * The post types the demo ships, in the order a reviewer reads them.
	 *
	 * @return string[]
	 */
	public static function demo_types(): array {
		return array( PostTypes::ARTIST, PostTypes::ALBUM, PostTypes::TRACK, PostTypes::VIDEO );
	}
}
