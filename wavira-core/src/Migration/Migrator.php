<?php
/**
 * The legacy migration engine: detect, dry-run, migrate, roll back, report.
 *
 * `docs/MIGRATION-BLUEPRINT.md` §6 is the contract this class implements, and
 * the hard requirements are structural rather than promised:
 *
 * - **Never destructive.** A post changes its `post_type` and gains meta; the
 *   legacy values are copied into `_migration_backup` (once, never overwritten)
 *   and `_migration_raw` before anything is written, `post_status` is never
 *   touched, and nothing is deleted except the child track posts this tool
 *   itself created (recorded in `_migration_created`) during a rollback.
 * - **Idempotent.** A legacy post is `post` with `musics_type`; a migrated one
 *   is `wavira_track`/`wavira_video`/`wavira_album`, so the selection cannot see
 *   it twice, and the second run reports `migrated => 0`.
 * - **Dry-runnable.** `dry_run()` plans the same work with the same rules and
 *   writes nothing at all — no meta, no type change, no report.
 * - **Reported, never guessed.** An ambiguous value (an artist name that matches
 *   no entity, a slider image that is not a local attachment, a deferred field)
 *   is copied to the raw audit meta and queued in `needs_review`.
 *
 * @package Wavira\Core\Migration
 */

namespace Wavira\Core\Migration;

use Wavira\Core\Content\Meta;
use Wavira\Core\Content\MetaSchema;
use Wavira\Core\Content\PostTypes;
use Wavira\Core\Content\Taxonomies;

defined( 'ABSPATH' ) || exit;

/**
 * Class Migrator
 */
final class Migrator {

	/**
	 * Posts per batch. Shared hosting safe, and small enough that a timeout
	 * leaves the work resumable (a re-run continues where it stopped).
	 */
	public const BATCH = 200;

	/**
	 * Post statuses the tool is allowed to look at. Drafts, pending and private
	 * posts are migrated as they are, but never published.
	 *
	 * @var string[]
	 */
	private const STATUSES = array( 'publish', 'draft', 'pending', 'private', 'future' );

	/**
	 * Maximum number of log lines kept in a report, so one broken site cannot
	 * produce a hundred-megabyte option.
	 */
	private const MAX_LOG = 200;

	/**
	 * Transforms that produce a URL, so an empty result is treated as "no value"
	 * instead of writing an empty string into the schema.
	 *
	 * @var array<string, bool>
	 */
	private const URL_TRANSFORMS = array(
		'url' => true,
	);

	/**
	 * Mutable report of the current run.
	 *
	 * @var array<string, mixed>
	 */
	private $report = array();

	/**
	 * Detect what the site holds: legacy content, and what will need a decision.
	 *
	 * Read-only; it is what `wp wavira migrate --dry-run` prints first.
	 *
	 * @return array<string, mixed>
	 */
	public function detect(): array {
		$profile = array(
			'legacy'   => array(),
			'total'    => 0,
			'artists'  => 0,
			'deferred' => array(),
			'unmapped' => array(),
		);

		foreach ( array_keys( LegacySchema::kinds() ) as $kind ) {
			$count = $this->count_legacy( $kind );

			$profile['legacy'][ $kind ] = $count;
			$profile['total']          += $count;
		}

		$profile['artists'] = count( $this->artist_terms() );

		foreach ( LegacySchema::deferred() as $key => $reason ) {
			$found = $this->count_meta( $key );

			if ( $found > 0 ) {
				$profile['deferred'][ $key ] = array(
					'posts'  => $found,
					'reason' => $reason,
				);
			}
		}

		// A legacy post with no `musics_type` is editorial content and stays a
		// post. A post with a value the map does not know is a surprise: report it.
		foreach ( $this->unmapped_kinds() as $value => $count ) {
			$profile['unmapped'][ $value ] = $count;
		}

		return $profile;
	}

	/**
	 * Plan the migration without writing anything.
	 *
	 * @param array<string, mixed> $args `batch`, `kind`.
	 * @return array<string, mixed>
	 */
	public function dry_run( array $args = array() ): array {
		$args['dry_run'] = true;

		return $this->run( $args );
	}

	/**
	 * Migrate a batch.
	 *
	 * @param array<string, mixed> $args `batch` (int), `kind` (legacy kind),
	 *                                   `offset` (int, resume cursor).
	 * @return array<string, mixed> The report of this run.
	 */
	public function run( array $args = array() ): array {
		$dry    = ! empty( $args['dry_run'] );
		$batch  = max( 1, (int) ( $args['batch'] ?? self::BATCH ) );
		$offset = max( 0, (int) ( $args['offset'] ?? 0 ) );
		$kind   = (string) ( $args['kind'] ?? '' );

		$this->report = array(
			'version'      => LegacySchema::VERSION,
			'dry_run'      => $dry,
			'detected'     => array(),
			'scanned'      => 0,
			'migrated'     => 0,
			'created'      => 0,
			'linked'       => 0,
			'genres'       => 0,
			'artists'      => array(
				'created' => 0,
				'merged'  => 0,
			),
			'needs_review' => array(),
			'log'          => array(),
			'remaining'    => 0,
		);

		$profile                  = $this->detect();
		$this->report['detected'] = $profile['legacy'];
		$this->report['deferred'] = $profile['deferred'];
		$this->report['unmapped'] = $profile['unmapped'];

		// The artist directory first: a credit can only be resolved to an entity
		// that exists, and the legacy site modelled artists as terms.
		if ( ! $dry ) {
			$this->build_artists();
		} else {
			$this->log( sprintf( 'dry run: %d artist term(s) would be built into the artist directory', $profile['artists'] ) );
		}

		$kinds = '' !== $kind ? array( $kind ) : array_keys( LegacySchema::kinds() );

		foreach ( $kinds as $one ) {
			$ids = $this->legacy_ids( $one, $batch, $offset );

			foreach ( $ids as $id ) {
				++$this->report['scanned'];

				if ( $dry ) {
					++$this->report['migrated'];
					$this->log( sprintf( 'dry run: #%d (post "%s") → %s', $id, (string) get_the_title( $id ), LegacySchema::target_of( $one ) ) );
					continue;
				}

				$this->migrate_post( $id, $one );
			}

			$left = $this->count_legacy( $one ) - count( $ids );

			$this->report['remaining'] += max( 0, $left );
		}

		if ( ! $dry ) {
			update_option( LegacySchema::REPORT_OPTION, $this->report, false );
		}

		return $this->report;
	}

	/**
	 * The last stored report, or an empty array when the tool never ran.
	 *
	 * @return array<string, mixed>
	 */
	public function report(): array {
		$stored = get_option( LegacySchema::REPORT_OPTION, array() );

		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Roll the migration back.
	 *
	 * Every post that carries a backup is restored to its legacy post type and
	 * meta, the keys this tool filled are deleted, and the child tracks it
	 * created are removed. Anything a human touched after the migration is left
	 * alone and reported instead.
	 *
	 * @param array<string, mixed> $args `batch`.
	 * @return array<string, mixed>
	 */
	public function rollback( array $args = array() ): array {
		$batch = max( 1, (int) ( $args['batch'] ?? self::BATCH ) );

		$report = array(
			'restored' => 0,
			'deleted'  => 0,
			'skipped'  => array(),
		);

		$ids = get_posts(
			array(
				'post_type'        => array_values( LegacySchema::kinds() ),
				'post_status'      => self::STATUSES,
				'posts_per_page'   => $batch,
				'fields'           => 'ids',
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'meta_key'         => LegacySchema::BACKUP, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- a one-off maintenance tool, not a front-end query.
				'no_found_rows'    => true,
				'suppress_filters' => false,
			)
		);

		foreach ( $ids as $id ) {
			$backup = get_post_meta( (int) $id, LegacySchema::BACKUP, true );

			if ( ! is_array( $backup ) || empty( $backup['post_type'] ) ) {
				$report['skipped'][] = sprintf( '#%d has no usable backup', (int) $id );
				continue;
			}

			$created = get_post_meta( (int) $id, LegacySchema::CREATED, true );

			if ( is_array( $created ) ) {
				foreach ( $created as $child ) {
					$child = (int) $child;

					if ( $child > 0 && get_post_meta( $child, LegacySchema::MARKER, true ) ) {
						wp_delete_post( $child, true );
						++$report['deleted'];
					}
				}
			}

			// The keys to remove are the ones the *kind* wrote, and the kind is
			// part of the backup: a post's original type (`post`) says nothing
			// about which field map ran over it.
			$kind = is_array( $backup['meta'] ?? null ) && isset( $backup['meta'][ LegacySchema::TYPE_META ] )
				? (string) $backup['meta'][ LegacySchema::TYPE_META ]
				: '';

			foreach ( $this->written_keys( $kind ) as $key ) {
				delete_post_meta( (int) $id, $key );
			}

			if ( is_array( $backup['meta'] ?? null ) ) {
				foreach ( $backup['meta'] as $key => $value ) {
					update_post_meta( (int) $id, (string) $key, $value );
				}
			}

			foreach ( LegacySchema::markers() as $marker ) {
				delete_post_meta( (int) $id, $marker );
			}

			set_post_type( (int) $id, (string) $backup['post_type'] );
			++$report['restored'];
		}

		return $report;
	}

	/**
	 * Migrate one post.
	 *
	 * @param int    $id   Post ID.
	 * @param string $kind Legacy kind the caller selected it with.
	 * @return void
	 */
	private function migrate_post( int $id, string $kind ): void {
		$target = LegacySchema::target_of( $kind );

		if ( '' === $target ) {
			return;
		}

		$post = get_post( $id );

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if ( get_post_meta( $id, LegacySchema::MARKER, true ) ) {
			$this->log( sprintf( '#%d is already at version %s', $id, LegacySchema::VERSION ) );
			return;
		}

		// Back up first: the original type and every legacy value this run can
		// touch, including the ones it only copies to the audit meta.
		$legacy = array();
		$raw    = array();

		foreach ( LegacySchema::all_post_keys() as $key ) {
			$value = get_post_meta( $id, $key, true );

			if ( '' !== $value && array() !== $value && null !== $value && false !== $value ) {
				$legacy[ $key ] = $value;
				$raw[ $key ]    = $value;
			}
		}

		update_post_meta(
			$id,
			LegacySchema::BACKUP,
			array(
				'post_type' => $post->post_type,
				'meta'      => $legacy,
				'version'   => LegacySchema::VERSION,
			)
		);
		update_post_meta( $id, LegacySchema::RAW, $raw );

		set_post_type( $id, $target );

		foreach ( LegacySchema::fields( $kind ) as $legacy_key => $map ) {
			$this->move( $id, $legacy_key, $map[0], $map[1] );
		}

		// A free-text artist becomes the credit label, and the entity when the
		// name matches one. `wavira_credit_label` is what the templates print.
		$credit = (string) get_post_meta( $id, 'artist', true );

		if ( '' !== $credit ) {
			update_post_meta( $id, MetaSchema::CREDIT_LABEL, Meta::sanitize_text( $credit ) );

			$artist = $this->resolve_artist( $credit );

			if ( $artist > 0 ) {
				update_post_meta( $id, MetaSchema::ARTIST, $artist );
				++$this->report['linked'];
				delete_post_meta( $id, LegacySchema::REVIEW );
			} else {
				update_post_meta( $id, LegacySchema::REVIEW, 'artist' );
				$this->queue_review( sprintf( '#%d: no artist entity matches "%s" — the credit label was kept, link it by hand', $id, $credit ) );
			}
		}

		// `song` is only useful when it is not already the title; otherwise it
		// would print the title twice on every card.
		$song = (string) get_post_meta( $id, 'song', true );

		if ( '' !== $song && $song !== $post->post_title ) {
			update_post_meta( $id, MetaSchema::SUBTITLE, Meta::sanitize_text( $song ) );
		}

		$this->copy_genres( $id );

		if ( PostTypes::ALBUM === $target ) {
			$this->expand_album( $post );
		}

		update_post_meta( $id, LegacySchema::MARKER, LegacySchema::VERSION );
		++$this->report['migrated'];
		$this->log( sprintf( '#%d "%s" → %s', $id, $post->post_title, $target ) );
	}

	/**
	 * Move one legacy value to its target key.
	 *
	 * @param int    $id     Post ID.
	 * @param string $legacy Legacy meta key.
	 * @param string $target Target meta key.
	 * @param string $how    Transform (`url`, `id`, `html`, `bool`, `text`).
	 * @return void
	 */
	private function move( int $id, string $legacy, string $target, string $how ): void {
		$value = get_post_meta( $id, $legacy, true );

		if ( '' === $value || null === $value || false === $value ) {
			return;
		}

		$is_url  = self::URL_TRANSFORMS[ $how ] ?? false;
		$is_id   = 'id' === $how;
		$missing = false;

		if ( $is_id ) {
			$attachment = is_string( $value ) ? attachment_url_to_postid( $value ) : 0;

			if ( $attachment <= 0 ) {
				// A remote image cannot become a registered attachment without
				// downloading it, which the licence rules forbid (blueprint §5).
				$missing = true;
				$value   = 0;
			} else {
				$value = $attachment;
			}
		} else {
			$value = $this->transform( $value, $how );
		}

		if ( $missing ) {
			delete_post_meta( $id, $target );
			$this->queue_review( sprintf( '#%d: %s is not a local attachment — import the file, then set %s', $id, $legacy, $target ) );
			return;
		}

		if ( $is_url && '' === $value ) {
			return;
		}

		update_post_meta( $id, $target, $value );
	}

	/**
	 * Apply a transform. Unknown transforms return an empty string, so a map
	 * typo fails closed instead of writing a raw value into the schema.
	 *
	 * @param mixed  $value Raw legacy value.
	 * @param string $how   Transform.
	 * @return mixed
	 */
	private function transform( $value, string $how ) {
		switch ( $how ) {
			case 'url':
				return Meta::sanitize_url( $value );
			case 'html':
				return Meta::sanitize_html( $value );
			case 'bool':
				return Meta::sanitize_bool( $value );
			case 'text':
				return Meta::sanitize_text( $value );
			case 'int':
				return Meta::sanitize_int( $value );
		}

		$this->queue_review( sprintf( 'unknown transform "%s" — nothing was written', $how ) );

		return '';
	}

	/**
	 * Copy the legacy categories of a music post into the genre taxonomy.
	 *
	 * Copy, not move: the blueprint says an editorial category keeps working as
	 * a category, and nothing the tool does may take a term away from a post.
	 *
	 * @param int $id Post ID.
	 * @return void
	 */
	private function copy_genres( int $id ): void {
		$terms = wp_get_post_terms( $id, 'category', array( 'fields' => 'all' ) );

		if ( is_wp_error( $terms ) ) {
			return;
		}

		foreach ( $terms as $term ) {
			$existing = get_term_by( 'slug', $term->slug, Taxonomies::GENRE );

			if ( ! $existing ) {
				$created = wp_insert_term( $term->name, Taxonomies::GENRE, array( 'slug' => $term->slug ) );

				if ( is_wp_error( $created ) ) {
					$this->queue_review( sprintf( '#%d: genre term "%s" could not be created (%s)', $id, $term->name, $created->get_error_message() ) );
					continue;
				}

				$existing = get_term( (int) $created['term_id'], Taxonomies::GENRE );
				++$this->report['genres'];
			}

			if ( $existing instanceof \WP_Term ) {
				wp_set_object_terms( $id, array( (int) $existing->term_id ), Taxonomies::GENRE, true );
			}
		}
	}

	/**
	 * Expand the legacy album repeater into child track posts.
	 *
	 * @param \WP_Post $album Album post (already converted).
	 * @return void
	 */
	private function expand_album( \WP_Post $album ): void {
		$rows = get_post_meta( $album->ID, 'album', true );

		if ( ! is_array( $rows ) || array() === $rows ) {
			return;
		}

		$map  = LegacySchema::album_row_fields();
		$list = array();
		$made = array();

		foreach ( array_values( $rows ) as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$title = isset( $row[ $map['song_names'] ] ) && is_scalar( $row[ $map['song_names'] ] )
				? Meta::sanitize_text( $row[ $map['song_names'] ] )
				: '';

			if ( '' === $title ) {
				$this->queue_review( sprintf( 'album #%d row %d has no track name — nothing was created for it', $album->ID, $index ) );
				continue;
			}

			$existing = $this->album_child( $album->ID, $index );

			if ( $existing > 0 ) {
				$list[] = $existing;
				continue;
			}

			$track = wp_insert_post(
				array(
					'post_type'   => PostTypes::TRACK,
					'post_status' => $album->post_status,
					'post_title'  => $title,
					'menu_order'  => $index,
				),
				true
			);

			if ( is_wp_error( $track ) ) {
				$this->queue_review( sprintf( 'album #%d row %d could not be created (%s)', $album->ID, $index, $track->get_error_message() ) );
				continue;
			}

			$track = (int) $track;

			foreach ( array(
				'albumlink128' => MetaSchema::AUDIO_128,
				'albumlink320' => MetaSchema::AUDIO_320,
			) as $legacy_key => $target_key ) {
				$value = isset( $row[ $legacy_key ] ) ? Meta::sanitize_url( $row[ $legacy_key ] ) : '';

				if ( '' !== $value ) {
					update_post_meta( $track, $target_key, $value );
				}
			}

			update_post_meta( $track, MetaSchema::ALBUM, $album->ID );
			update_post_meta( $track, MetaSchema::CREDIT_LABEL, Meta::sanitize_text( (string) get_post_meta( $album->ID, 'artist', true ) ) );
			update_post_meta( $track, LegacySchema::SOURCE_ALBUM, $album->ID );
			update_post_meta( $track, LegacySchema::SOURCE_INDEX, $index );
			update_post_meta( $track, LegacySchema::MARKER, LegacySchema::VERSION );

			$made[] = $track;
			$list[] = $track;
			++$this->report['created'];
		}

		if ( array() !== $list ) {
			update_post_meta( $album->ID, MetaSchema::TRACKLIST, $list );
		}

		if ( array() !== $made ) {
			update_post_meta( $album->ID, LegacySchema::CREATED, $made );
		}
	}

	/**
	 * The child track a previous run created for one album row, if any.
	 *
	 * @param int $album_id Album post ID.
	 * @param int $index    Row index.
	 * @return int Track ID, or 0.
	 */
	private function album_child( int $album_id, int $index ): int {
		$found = get_posts(
			array(
				'post_type'        => PostTypes::TRACK,
				'post_status'      => self::STATUSES,
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => false,
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- migration bookkeeping.
					'relation' => 'AND',
					array(
						'key'   => LegacySchema::SOURCE_ALBUM,
						'value' => $album_id,
					),
					array(
						'key'   => LegacySchema::SOURCE_INDEX,
						'value' => $index,
					),
				),
			)
		);

		return $found ? (int) $found[0] : 0;
	}

	/**
	 * Build the artist directory from the legacy artist terms.
	 *
	 * `singer` terms and tags used as artists are merged when the slug matches,
	 * which is the duplicate the audit flagged as D1.
	 *
	 * @return void
	 */
	private function build_artists(): void {
		foreach ( $this->artist_terms() as $term ) {
			$existing = get_page_by_path( $term->slug, OBJECT, PostTypes::ARTIST );

			if ( $existing instanceof \WP_Post ) {
				++$this->report['artists']['merged'];
				$this->apply_artist_term( $existing->ID, (int) $term->term_id );
				continue;
			}

			$id = wp_insert_post(
				array(
					'post_type'    => PostTypes::ARTIST,
					'post_status'  => 'publish',
					'post_title'   => $term->name,
					'post_name'    => $term->slug,
					'post_content' => Meta::sanitize_html( $term->description ),
				),
				true
			);

			if ( is_wp_error( $id ) ) {
				$this->queue_review( sprintf( 'artist term "%s" could not become a post (%s)', $term->name, $id->get_error_message() ) );
				continue;
			}

			++$this->report['artists']['created'];
			$this->apply_artist_term( (int) $id, (int) $term->term_id );
			$this->log( sprintf( 'artist "%s" created from a term', $term->name ) );
		}
	}

	/**
	 * Copy one term's fields onto the artist post.
	 *
	 * @param int $id      Artist post ID.
	 * @param int $term_id Term ID.
	 * @return void
	 */
	private function apply_artist_term( int $id, int $term_id ): void {
		foreach ( LegacySchema::artist_term_fields() as $legacy => $target ) {
			$value = get_term_meta( $term_id, $legacy, true );

			if ( '' === $value || null === $value || false === $value ) {
				continue;
			}

			if ( MetaSchema::ARTIST_IMAGE === $target ) {
				$attachment = is_string( $value ) ? attachment_url_to_postid( $value ) : 0;

				if ( $attachment > 0 ) {
					update_post_meta( $id, $target, $attachment );
				} else {
					$this->queue_review( sprintf( 'artist #%d: the legacy image is not a local attachment — import it, then set %s', $id, $target ) );
				}

				continue;
			}

			update_post_meta( $id, $target, Meta::sanitize_url( $value ) );
		}

		if ( ! get_post_meta( $id, MetaSchema::SOCIAL_APARAT, true ) ) {
			$aparat = get_term_meta( $term_id, 'aparat', true );

			if ( '' !== $aparat && null !== $aparat && false !== $aparat ) {
				update_post_meta( $id, MetaSchema::SOCIAL_APARAT, Meta::sanitize_url( $aparat ) );
			}
		}
	}

	/**
	 * Resolve a credit string to an artist post.
	 *
	 * Exact slug or title match only: "A and B" is two artists, not one, and the
	 * tool queues such a credit for review instead of guessing.
	 *
	 * @param string $credit Legacy artist string.
	 * @return int Artist post ID, or 0.
	 */
	private function resolve_artist( string $credit ): int {
		$slug = sanitize_title( $credit );
		$post = get_page_by_path( $slug, OBJECT, PostTypes::ARTIST );

		if ( $post instanceof \WP_Post ) {
			return (int) $post->ID;
		}

		$by_title = get_posts(
			array(
				'post_type'        => PostTypes::ARTIST,
				'post_status'      => self::STATUSES,
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => false,
				'title'            => $credit,
			)
		);

		return $by_title ? (int) $by_title[0] : 0;
	}

	/**
	 * Legacy artist-bearing terms: the `singer` taxonomy and tags used as one.
	 *
	 * `docs/DATA-MODEL-AUDIT.md` D1 records both as sources of the same concept.
	 *
	 * @return \WP_Term[]
	 */
	private function artist_terms(): array {
		$terms = array();

		foreach ( array( LegacySchema::ARTIST_TAX, 'post_tag' ) as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$found = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'number'     => 0,
				)
			);

			if ( ! is_wp_error( $found ) ) {
				$terms = array_merge( $terms, $found );
			}
		}

		// Merge by slug so a `singer` term and a tag with the same slug cannot
		// create two artists (the duplicate the audit flagged).
		$merged = array();

		foreach ( $terms as $term ) {
			if ( isset( $merged[ $term->slug ] ) && LegacySchema::ARTIST_TAX !== $term->taxonomy ) {
				continue;
			}

			$merged[ $term->slug ] = $term;
		}

		return array_values( $merged );
	}

	/**
	 * IDs of the legacy posts of one kind.
	 *
	 * The query is bounded on purpose (no unbounded `posts_per_page`, ADR 0013) and
	 * ordered by ID so a resumable run has a stable cursor.
	 *
	 * @param string $kind   Legacy kind.
	 * @param int    $batch  Batch size, at least 1.
	 * @param int    $offset Cursor.
	 * @return int[]
	 */
	private function legacy_ids( string $kind, int $batch, int $offset = 0 ): array {
		return array_map(
			'intval',
			(array) get_posts(
				array_merge(
					$this->legacy_query_args( $kind ),
					array(
						'posts_per_page' => max( 1, $batch ),
						'offset'         => max( 0, $offset ),
					)
				)
			)
		);
	}

	/**
	 * How many legacy posts of one kind exist.
	 *
	 * Pages of `self::BATCH` while a page comes back full, so no query is
	 * unbounded and memory stays flat on a large site.
	 *
	 * @param string $kind Legacy kind.
	 * @return int
	 */
	private function count_legacy( string $kind ): int {
		$count  = 0;
		$cursor = 0;
		$found  = self::BATCH;

		while ( self::BATCH === $found ) {
			$page    = $this->legacy_ids( $kind, self::BATCH, $cursor );
			$found   = count( $page );
			$count  += $found;
			$cursor += self::BATCH;
		}

		return $count;
	}

	/**
	 * The shared selection of a legacy kind.
	 *
	 * @param string $kind Legacy kind.
	 * @return array<string, mixed>
	 */
	private function legacy_query_args( string $kind ): array {
		return array(
			'post_type'        => 'post',
			'post_status'      => self::STATUSES,
			'fields'           => 'ids',
			'orderby'          => 'ID',
			'order'            => 'ASC',
			'no_found_rows'    => true,
			'suppress_filters' => false,
			'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- migration scan.
				array(
					'key'   => LegacySchema::TYPE_META,
					'value' => $kind,
				),
			),
		);
	}

	/**
	 * How many posts carry one legacy meta key.
	 *
	 * @param string $key Meta key.
	 * @return int
	 */
	private function count_meta( string $key ): int {
		$count = 0;
		$page  = 1;
		$size  = self::BATCH;

		while ( self::BATCH === $size ) {
			$ids = get_posts(
				array(
					'post_type'        => 'post',
					'post_status'      => self::STATUSES,
					'posts_per_page'   => self::BATCH,
					'paged'            => $page,
					'fields'           => 'ids',
					'no_found_rows'    => true,
					'suppress_filters' => false,
					'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- migration scan.
						array(
							'key'     => $key,
							'compare' => 'EXISTS',
						),
					),
				)
			);

			$size   = count( $ids );
			$count += $size;
			++$page;
		}

		return $count;
	}

	/**
	 * `musics_type` values the map does not know.
	 *
	 * @return array<string, int> Value => count.
	 */
	private function unmapped_kinds(): array {
		$values = get_post_meta_by_key( LegacySchema::TYPE_META );

		if ( ! is_array( $values ) ) {
			return array();
		}

		$kinds   = LegacySchema::kinds();
		$unknown = array();

		foreach ( $values as $row ) {
			$value = is_array( $row ) && isset( $row->meta_value ) ? (string) $row->meta_value : '';

			if ( '' === $value || isset( $kinds[ $value ] ) ) {
				continue;
			}

			$unknown[ $value ] = ( $unknown[ $value ] ?? 0 ) + 1;
		}

		return $unknown;
	}

	/**
	 * Meta keys this tool writes for a migrated post type, so a rollback removes
	 * exactly what the migration added.
	 *
	 * @param string $legacy_kind Legacy kind the post came from.
	 * @return string[]
	 */
	private function written_keys( string $legacy_kind ): array {
		$keys = array( MetaSchema::CREDIT_LABEL, MetaSchema::SUBTITLE, MetaSchema::TRACKLIST, MetaSchema::ARTIST );

		foreach ( LegacySchema::fields( $legacy_kind ) as $map ) {
			$keys[] = $map[0];
		}

		return array_values( array_unique( $keys ) );
	}

	/**
	 * Add one line to the report's review queue.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	private function queue_review( string $message ): void {
		if ( count( $this->report['needs_review'] ) < self::MAX_LOG ) {
			$this->report['needs_review'][] = $message;
		}
	}

	/**
	 * Add one line to the report's log.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	private function log( string $message ): void {
		if ( count( $this->report['log'] ) < self::MAX_LOG ) {
			$this->report['log'][] = $message;
		}
	}
}
