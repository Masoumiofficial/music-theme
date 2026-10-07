<?php
/**
 * WordPress-free harness for the demo exporter's `export_wp()` contract.
 *
 * WordPress cannot answer the three questions this file asks inside one
 * process, and `export_wp()` is short enough to stub honestly:
 *
 *   - it declares its `wxr_*()` helpers *inside* `export_wp()`, so a second
 *     call in the same request is a fatal "Cannot redeclare wxr_cdata()" error
 *     and every later assertion would never run;
 *   - it is written for a browser download, so it calls `header()` even when
 *     output has already started, and a harness that converts warnings into
 *     exceptions fails a document that was produced correctly;
 *   - an export that finds nothing must be reported, not returned as an empty
 *     success.
 *
 * Usage: php tools/harness/export.php [main|foreign|empty]
 * Exit status is 0 when every check passes. Development only: the file is not
 * part of the theme or the plugin archive.
 *
 * @package Wavira
 */

error_reporting( E_ALL );
ini_set( 'display_errors', '1' );

define( 'ABSPATH', __DIR__ . '/' );

$scenario = $argv[1] ?? 'main';

$GLOBALS['wavira_export_calls'] = 0;
$GLOBALS['wavira_export_empty'] = ( 'empty' === $scenario );

// A third party that ran WordPress' exporter earlier in the request leaves the
// helpers behind — the exporter must notice and not call export_wp() again.
if ( 'foreign' === $scenario ) {
	function wxr_cdata( $str ) {
		return $str;
	}
}

/**
 * Stand-in for WordPress' export_wp().
 *
 * @param array $args Export arguments.
 * @return void
 */
function export_wp( $args = array() ) {
	$GLOBALS['wavira_export_calls']++;

	// This is what WordPress does: announce the download, then print.
	header( 'Content-Description: File Transfer' );
	header( 'Content-Disposition: attachment; filename="export.xml"' );
	header( 'Content-Type: text/xml; charset=UTF-8', true );

	if ( ! $GLOBALS['wavira_export_empty'] ) {
		echo '<?xml version="1.0"?><rss version="2.0"><channel>';
		echo '<wp:wxr_version>1.2</wp:wxr_version>';
		echo '<item><title>' . $args['content'] . '</title></item>';
		echo '</channel></rss>';
	}

	// WordPress declares its helpers inside this function; after the first call
	// they exist for the rest of the request.
	if ( ! function_exists( 'wxr_cdata' ) ) {
		function wxr_cdata( $str ) {
			return $str;
		}
	}
}

/**
 * Stand-in for get_bloginfo().
 *
 * @param string $what Requested field.
 * @return string
 */
function get_bloginfo( $what = '' ) {
	return 'دمو';
}

/**
 * Stand-in for sanitize_title().
 *
 * @param string $value Raw value.
 * @return string
 */
function sanitize_title( $value ) {
	return trim( preg_replace( '/[^a-z0-9-]+/', '-', strtolower( $value ) ), '-' );
}

require dirname( __DIR__, 2 ) . '/wavira-core/src/Demo/Exporter.php';

use Wavira\Core\Demo\Exporter;

$fail = 0;

/**
 * Record one check.
 *
 * @param string $label What is being checked.
 * @param bool   $ok    Result.
 * @return void
 */
function check( $label, $ok ) {
	global $fail;

	echo ( $ok ? "  OK   " : "  FAIL " ) . $label . "\n";

	if ( ! $ok ) {
		$fail++;
	}
}

echo "scenario: {$scenario}\n";

if ( 'empty' === $scenario ) {
	$empty = Exporter::xml();

	check( 'export_wp() ran once', 1 === $GLOBALS['wavira_export_calls'] );
	check( 'an empty export is reported as a failure', '0' === $empty['ok'] );
	check( 'and says why', '' !== $empty['reason'] );
	check( 'and returns no document', '' === $empty['xml'] );
} elseif ( 'foreign' === $scenario ) {
	$result = Exporter::xml();

	check( 'a request where export_wp() already ran is refused', '0' === $result['ok'] );
	check( 'with the one-export-per-request reason', false !== strpos( $result['reason'], 'once per request' ) );
	check( 'and export_wp() is not called at all', 0 === $GLOBALS['wavira_export_calls'] );
} else {
	// Output has already started (the CI failure): the guard must keep the header
	// warnings away from a caller that converts warnings into exceptions.
	echo "output starts here\n";

	$raised = array();
	set_error_handler(
		function ( $no, $str ) use ( &$raised ) {
			$raised[] = $str;

			return true;
		},
		E_WARNING
	);

	$document = Exporter::xml( array( 'content' => 'all' ) );

	restore_error_handler();

	check( 'the document is produced', '1' === $document['ok'] );
	check( 'the document carries the WXR version', false !== strpos( $document['xml'], 'wxr_version' ) );
	check( 'export_wp() ran once', 1 === $GLOBALS['wavira_export_calls'] );
	check( 'no header warning reached the caller', array() === $raised );
	check( 'no PHP notice was printed', false === strpos( $document['xml'], 'Cannot modify header' ) );

	// The second export of the request: WordPress would fatally redeclare
	// wxr_cdata() here, so it must be refused instead — and this process must
	// still be alive to report it.
	$again = Exporter::xml();

	check( 'a second export in one request is refused, never fatal', '0' === $again['ok'] );
	check( 'with the one-export-per-request reason', false !== strpos( $again['reason'], 'once per request' ) );
	check( 'and returns no document', '' === $again['xml'] );
	check( 'and export_wp() was not called again', 1 === $GLOBALS['wavira_export_calls'] );

	$name = Exporter::file_name();

	check( 'file name ends in .xml', '.xml' === substr( $name, -4 ) );
	check( 'file name is ASCII and safe', 1 === preg_match( '/^[A-Za-z0-9._-]+$/', $name ) );
}

echo 0 === $fail ? "ALL OK\n" : "{$fail} FAILURE(S)\n";

exit( 0 === $fail ? 0 : 1 );
