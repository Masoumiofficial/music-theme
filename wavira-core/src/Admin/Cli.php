<?php
/**
 * WP-CLI commands: `wp wavira verify` and `wp wavira seed`.
 *
 * The commands exist so that the data model can be validated and exercised on a
 * real installation without shipping demo content by accident. Seed data is
 * generated text only — no third-party media, no real lyrics (ADR 0010).
 *
 * @package Wavira\Core\Admin
 */

namespace Wavira\Core\Admin;

use Wavira\Core\Content\MetaSchema;
use Wavira\Core\Content\MetaValues;
use Wavira\Core\Content\PostTypes;
use Wavira\Core\Content\Taxonomies;
use Wavira\Core\Contracts\Registrable;
use Wavira\Core\Demo\Exporter;
use Wavira\Core\Demo\Installer;
use Wavira\Core\Migration\LegacySchema;
use Wavira\Core\Migration\Migrator;
use Wavira\Core\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class Cli
 */
final class Cli implements Registrable {

	/**
	 * Register hooks (only inside a WP-CLI request).
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		\WP_CLI::add_command( 'wavira verify', array( $this, 'verify' ) );
		\WP_CLI::add_command( 'wavira seed', array( $this, 'seed' ) );
		\WP_CLI::add_command( 'wavira export-demo', array( $this, 'export_demo' ) );
		\WP_CLI::add_command( 'wavira migrate', array( $this, 'migrate' ) );
	}

	/**
	 * Migrate a site that ran the legacy theme into the Wavira model.
	 *
	 * Read the plan first: `--dry-run` prints exactly what would happen and
	 * writes nothing. The tool is idempotent and resumable, never publishes or
	 * unpublishes a post, keeps every legacy value in `_migration_backup` and
	 * `_migration_raw`, and reports what it refuses to guess (an artist name
	 * that matches no entity, a slider image that is not a local attachment, a
	 * field the model does not implement).
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Plan only; write nothing at all.
	 *
	 * [--detect]
	 * : Print the site profile (legacy content, deferred fields, unknown kinds)
	 * and stop.
	 *
	 * [--status]
	 * : Print the report of the last run.
	 *
	 * [--rollback]
	 * : Restore every migrated post from its backup and remove the tracks this
	 * tool created.
	 *
	 * [--source=<source>]
	 * : Which site built this content: `legacy` (the audited theme, default) or
	 * `music-publisher` (the "Sajad Music Publisher" plugin). Both write
	 * `post` + `musics_type`, so the value decides which field map runs.
	 *
	 * [--kind=<kind>]
	 * : Limit the run to one kind. Legacy: mp3, mp4, album. Publishing plugin:
	 * musicss, musicss_remix, musicss_nohe, musicss_podcast, musicss_video,
	 * musicss_album.
	 *
	 * [--batch=<number>]
	 * : Posts per run. Default 200.
	 *
	 * [--offset=<number>]
	 * : Skip the first N posts of a kind (resume cursor). Default 0.
	 *
	 * [--report=<file>]
	 * : Also write the report as JSON to this path.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wavira migrate --detect
	 *     wp wavira migrate --detect --source=music-publisher
	 *     wp wavira migrate --dry-run
	 *     wp wavira migrate --batch=500 --report=/tmp/migration.json
	 *     wp wavira migrate --rollback
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Associative arguments: see OPTIONS.
	 * @return void
	 */
	public function migrate( $args = array(), $assoc_args = array() ): void {
		unset( $args );

		$migrator = new Migrator();
		$source   = isset( $assoc_args['source'] ) ? (string) $assoc_args['source'] : LegacySchema::SOURCE_LEGACY;

		if ( ! LegacySchema::has_source( $source ) ) {
			\WP_CLI::error(
				sprintf(
					'Unknown --source "%s". Known sources: %s.',
					$source,
					implode( ', ', array_keys( LegacySchema::sources() ) )
				)
			);
		}

		$migrator->source( $source );

		if ( isset( $assoc_args['detect'] ) ) {
			$this->print_profile( $migrator->detect() );

			return;
		}

		if ( isset( $assoc_args['status'] ) ) {
			$stored = $migrator->report();

			if ( array() === $stored ) {
				\WP_CLI::warning( 'No migration report on this site yet — run `wp wavira migrate --dry-run` first.' );

				return;
			}

			$this->print_report( $stored );

			return;
		}

		if ( isset( $assoc_args['rollback'] ) ) {
			$report = $migrator->rollback( array( 'batch' => (int) ( $assoc_args['batch'] ?? Migrator::BATCH ) ) );

			\WP_CLI::log( sprintf( 'Restored %d post(s); deleted %d created track(s).', (int) $report['restored'], (int) $report['deleted'] ) );

			foreach ( $report['skipped'] as $line ) {
				\WP_CLI::warning( (string) $line );
			}

			\WP_CLI::success( 'Rollback finished.' );

			return;
		}

		$report = $migrator->run(
			array(
				'dry_run' => isset( $assoc_args['dry-run'] ),
				'batch'   => (int) ( $assoc_args['batch'] ?? Migrator::BATCH ),
				'offset'  => (int) ( $assoc_args['offset'] ?? 0 ),
				'kind'    => (string) ( $assoc_args['kind'] ?? '' ),
			)
		);

		if ( isset( $assoc_args['report'] ) ) {
			$path = (string) $assoc_args['report'];
			$json = (string) wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			$ok   = false !== file_put_contents( $path, $json ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- a CLI report the operator asked for by path.

			if ( ! $ok ) {
				\WP_CLI::warning( sprintf( 'Could not write the report to %s.', $path ) );
			}
		}

		$this->print_report( $report );
	}

	/**
	 * Print the detection profile.
	 *
	 * @param array<string, mixed> $profile Profile from `Migrator::detect()`.
	 * @return void
	 */
	private function print_profile( array $profile ): void {
		$source = (string) ( $profile['source'] ?? LegacySchema::SOURCE_LEGACY );
		$label  = (string) ( LegacySchema::sources()[ $source ]['label'] ?? $source );

		\WP_CLI::log( sprintf( 'Source: %s (%s).', $source, $label ) );
		\WP_CLI::log( 'Content found:' );

		foreach ( $profile['legacy'] as $kind => $count ) {
			\WP_CLI::log(
				sprintf(
					'  %-16s %4d post(s) → %s',
					(string) $kind,
					(int) $count,
					(string) LegacySchema::target_of( (string) $kind, $source )
				)
			);
		}

		\WP_CLI::log( sprintf( '  %-16s %4d', 'artist terms:', (int) $profile['artists'] ) );

		foreach ( $profile['deferred'] as $key => $info ) {
			\WP_CLI::log( sprintf( '  deferred %s: %d post(s) — %s', (string) $key, (int) $info['posts'], (string) $info['reason'] ) );
		}

		foreach ( $profile['unmapped'] as $value => $count ) {
			\WP_CLI::warning( sprintf( 'musics_type "%s" is not in the map (%d post(s)) — nothing will be converted for it.', (string) $value, (int) $count ) );
		}

		\WP_CLI::success( sprintf( '%d convertible post(s) found.', (int) $profile['total'] ) );
	}

	/**
	 * Print a run report.
	 *
	 * @param array<string, mixed> $report Report from the migrator.
	 * @return void
	 */
	private function print_report( array $report ): void {
		$dry = ! empty( $report['dry_run'] );

		if ( ! empty( $report['source'] ) ) {
			\WP_CLI::log( sprintf( 'Source: %s.', (string) $report['source'] ) );
		}

		foreach ( (array) ( $report['log'] ?? array() ) as $line ) {
			\WP_CLI::log( '  ' . (string) $line );
		}

		foreach ( (array) ( $report['needs_review'] ?? array() ) as $line ) {
			\WP_CLI::warning( (string) $line );
		}

		\WP_CLI::log(
			sprintf(
				'%s: scanned %d, %s %d, created %d track(s), linked %d artist(s), %d genre(s); %d post(s) left.',
				$dry ? 'Dry run' : 'Migrated',
				(int) $report['scanned'],
				$dry ? 'would migrate' : 'migrated',
				(int) $report['migrated'],
				(int) $report['created'],
				(int) $report['linked'],
				(int) $report['genres'],
				(int) $report['remaining']
			)
		);

		if ( ! empty( $report['artists']['created'] ) || ! empty( $report['artists']['merged'] ) ) {
			\WP_CLI::log(
				sprintf(
					'Artist directory: %d created, %d merged onto an existing artist.',
					(int) $report['artists']['created'],
					(int) $report['artists']['merged']
				)
			);
		}

		if ( ! empty( $report['remaining'] ) && ! $dry ) {
			\WP_CLI::log( 'Run the command again to continue (the tool is idempotent), or raise --batch.' );
		}

		\WP_CLI::success( $dry ? 'Dry run finished — nothing was written.' : 'Migration batch finished.' );
	}

	/**
	 * Verify the data model: post types, taxonomies and meta registration.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wavira verify
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Associative arguments (unused).
	 * @return void
	 */
	public function verify( $args = array(), $assoc_args = array() ): void {
		unset( $args, $assoc_args );

		$errors = array();

		foreach ( PostTypes::all() as $post_type ) {
			if ( ! post_type_exists( $post_type ) ) {
				$errors[] = sprintf( 'Post type missing: %s', $post_type );
			}
		}

		foreach ( array( Taxonomies::GENRE, Taxonomies::KIND, Taxonomies::MOOD, Taxonomies::LANGUAGE, Taxonomies::LABEL, Taxonomies::YEAR ) as $taxonomy ) {
			$always  = in_array( $taxonomy, array( Taxonomies::GENRE, Taxonomies::KIND ), true );
			$enabled = $always || Settings::get( 'enable_' . str_replace( 'wavira_', '', $taxonomy ), false );

			if ( $enabled && ! taxonomy_exists( $taxonomy ) ) {
				$errors[] = sprintf( 'Taxonomy missing: %s', $taxonomy );
			}
		}

		foreach ( MetaSchema::all() as $key => $field ) {
			foreach ( $field['entities'] as $post_type ) {
				if ( ! registered_meta_key_exists( 'post', $key, $post_type ) ) {
					$errors[] = sprintf( 'Meta not registered: %s on %s', $key, $post_type );
				}
			}
		}

		$counts = array();

		foreach ( PostTypes::all() as $post_type ) {
			$count                = wp_count_posts( $post_type );
			$counts[ $post_type ] = isset( $count->publish ) ? (int) $count->publish : 0;
		}

		\WP_CLI::log( 'Published content:' );

		foreach ( $counts as $post_type => $count ) {
			\WP_CLI::log( sprintf( '  %-18s %d', $post_type, $count ) );
		}

		$genres = get_terms(
			array(
				'taxonomy'   => Taxonomies::GENRE,
				'hide_empty' => false,
				'fields'     => 'count',
			)
		);

		\WP_CLI::log( sprintf( '  %-18s %d', 'genres', is_wp_error( $genres ) ? 0 : (int) $genres ) );

		if ( empty( $errors ) ) {
			\WP_CLI::success( 'Data model verified: post types, taxonomies and registered meta are all present.' );
			return;
		}

		foreach ( $errors as $error ) {
			\WP_CLI::warning( $error );
		}

		\WP_CLI::error( sprintf( '%d problem(s) found.', count( $errors ) ) );
	}

	/**
	 * Install the demo content (Persian by default).
	 *
	 * Persian by default: the product is sold to Persian-language sites, so the
	 * demo it ships with is a Persian music site — Persian content, `fa_IR`,
	 * Asia/Tehran, a week that starts on Saturday, and Jalali dates on the front
	 * end (ADR 0017). `--english` installs the neutral English fixture instead
	 * and leaves the site's locale, timezone and menus alone.
	 *
	 * Idempotent: does nothing when the site already has tracks, unless --force.
	 * The same installer runs behind the admin screen `Tools → Wavira demo
	 * content`, so a buyer without WP-CLI gets the identical demo. Audio files
	 * are never seeded: a fabricated media URL that 404s is worse than no URL at
	 * all, and the player is covered by the harness in `tools/preview/` and by
	 * the PHP suite.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Re-import even when tracks already exist. Only posts a previous import
	 *   created are replaced; anything the owner wrote is left alone.
	 *
	 * [--english]
	 * : Seed the English fixture, and leave the site's locale, timezone and
	 *   menus untouched.
	 *
	 * [--no-site]
	 * : Seed the content only; do not touch site options or menus.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wavira seed
	 *     wp wavira seed --force
	 *     wp wavira seed --english --no-site
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function seed( $args = array(), $assoc_args = array() ): void {
		unset( $args );

		$english = isset( $assoc_args['english'] );

		try {
			$report = Installer::install(
				array(
					'force'   => isset( $assoc_args['force'] ),
					'english' => $english,
					'site'    => ! isset( $assoc_args['no-site'] ),
				)
			);
		} catch ( \RuntimeException $exception ) {
			\WP_CLI::error( $exception->getMessage() );
			return;
		}

		foreach ( (array) $report['notices'] as $notice ) {
			\WP_CLI::warning( (string) $notice );
		}

		if ( $report['skipped'] ) {
			return;
		}

		if ( ! $english && ! isset( $assoc_args['no-site'] ) ) {
			\WP_CLI::log( '  site   fa_IR · Asia/Tehran · week starts on Saturday · Jalali dates' );
		}

		if ( (int) $report['menu'] > 0 ) {
			\WP_CLI::log( '  menu   primary menu created' );
		}

		if ( (int) $report['replaced'] > 0 ) {
			\WP_CLI::log( sprintf( '  replaced %d post(s) of the previous import', (int) $report['replaced'] ) );
		}

		\WP_CLI::success(
			sprintf(
				'Seeded artist #%1$d, %2$d releases, %3$d tracks and a video.',
				(int) $report['artist'],
				count( (array) $report['releases'] ),
				count( (array) $report['tracks'] )
			)
		);
	}

	/**
	 * Export the site's music content as a WXR file.
	 *
	 * WXR is the format every WordPress importer reads, so this is how a demo —
	 * or a live catalogue — moves to another installation: the file goes in
	 * through `Tools → Import` on the target site. The same document is
	 * available as a download from `Tools → Wavira demo content`.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<path>]
	 * : Where to write the document. Default: `wavira-content-<site>-<date>.xml`
	 *   in the current directory.
	 *
	 * [--type=<post-type>]
	 * : Export one post type only (wavira_track, wavira_album, wavira_artist,
	 *   wavira_video). Default: every content type.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wavira export-demo
	 *     wp wavira export-demo --file=/tmp/demo.xml
	 *     wp wavira export-demo --type=wavira_track
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Associative arguments: see OPTIONS.
	 * @return void
	 */
	public function export_demo( $args = array(), $assoc_args = array() ): void {
		unset( $args );

		$type = isset( $assoc_args['type'] ) ? (string) $assoc_args['type'] : 'all';

		if ( 'all' !== $type && ! in_array( $type, Exporter::demo_types(), true ) ) {
			\WP_CLI::error(
				sprintf(
					'Unknown --type "%s". Known types: all, %s.',
					$type,
					implode( ', ', Exporter::demo_types() )
				)
			);
		}

		$path   = isset( $assoc_args['file'] ) ? (string) $assoc_args['file'] : getcwd() . '/' . Exporter::file_name();
		$result = Exporter::to_file( $path, array( 'content' => $type ) );

		if ( ! $result['ok'] ) {
			\WP_CLI::error( (string) $result['reason'] );
			return;
		}

		\WP_CLI::success(
			sprintf(
				'Wrote %1$s (%2$s).',
				(string) $result['file'],
				size_format( (int) $result['bytes'] )
			)
		);
	}
}
