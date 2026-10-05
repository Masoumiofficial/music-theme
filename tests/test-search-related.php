<?php
/**
 * Runtime verification of the search and related services.
 *
 * @package Wavira\Tests
 */

use Wavira\Core\Content\MetaSchema;
use Wavira\Core\Related\RelatedService;
use Wavira\Core\Search\SearchService;
use Wavira\Core\Support\Cache;

/**
 * Class Test_Search_Related
 */
class Test_Search_Related extends WP_UnitTestCase {

	/**
	 * SQL captured from `posts_request` during a callback.
	 *
	 * @var string[]
	 */
	private array $sql = array();

	/**
	 * Active capture closure.
	 *
	 * @var callable|null
	 */
	private $capture = null;

	/**
	 * Start capturing every SQL statement WP_Query runs.
	 *
	 * @return void
	 */
	private function capture_queries(): void {
		$this->sql     = array();
		$this->capture = function ( $request ) {
			$this->sql[] = (string) $request;

			return $request;
		};

		add_filter( 'posts_request', $this->capture );
	}

	/**
	 * Stop capturing.
	 *
	 * @return void
	 */
	private function stop_capturing(): void {
		if ( null !== $this->capture ) {
			remove_filter( 'posts_request', $this->capture );
			$this->capture = null;
		}
	}

	/**
	 * Create a published track, optionally in a genre.
	 *
	 * @param string $title     Track title.
	 * @param int    $genre_id  Genre term ID (0 = none).
	 * @param int    $artist_id Artist post ID (0 = none).
	 * @return int
	 */
	private function make_track( string $title, int $genre_id = 0, int $artist_id = 0 ): int {
		$track_id = self::factory()->post->create(
			array(
				'post_type'   => 'wavira_track',
				'post_status' => 'publish',
				'post_title'  => $title,
			)
		);

		if ( $genre_id > 0 ) {
			wp_set_post_terms( $track_id, array( $genre_id ), 'wavira_genre' );
		}

		if ( $artist_id > 0 ) {
			update_post_meta( $track_id, MetaSchema::ARTIST, $artist_id );
		}

		return $track_id;
	}

	/**
	 * Create a genre term.
	 *
	 * @param string $name Term name.
	 * @return int
	 */
	private function make_genre( string $name ): int {
		$term = wp_insert_term( $name, 'wavira_genre' );

		return (int) $term['term_id'];
	}

	/**
	 * Search matches title terms and reports totals.
	 *
	 * @return void
	 */
	public function test_search_matches_terms() {
		$rock = $this->make_genre( 'Rock' );

		$wanted    = $this->make_track( 'Golden Hour', $rock );
		$unrelated = $this->make_track( 'Silent Rain' );

		$result = SearchService::search( array( 'term' => 'Golden' ) );

		$this->assertSame( 1, $result['total'] );
		$this->assertSame( $wanted, $result['items'][0]->ID );
		$this->assertNotSame( $unrelated, $result['items'][0]->ID );

		$by_genre = SearchService::search(
			array(
				'term'  => 'Silent',
				'genre' => 'rock',
			)
		);

		$this->assertSame( 0, $by_genre['total'], 'the genre filter is applied to search' );
	}

	/**
	 * Search never runs an unbounded query.
	 *
	 * @return void
	 */
	public function test_search_queries_are_bounded() {
		$rock = $this->make_genre( 'Rock' );

		for ( $i = 0; $i < 3; $i++ ) {
			$this->make_track( 'Bounded Track ' . $i, $rock );
		}

		$this->capture_queries();
		$result = SearchService::search( array( 'term' => 'Bounded' ) );
		$this->stop_capturing();

		$this->assertNotEmpty( $this->sql );
		$this->assertLessThanOrEqual( 50, count( $result['items'] ) );

		foreach ( $this->sql as $statement ) {
			$this->assertMatchesRegularExpression( '/LIMIT\s+\d+/i', $statement, 'every search query carries a LIMIT' );
		}
	}

	/**
	 * Suggestions are grouped by public slug and bounded.
	 *
	 * @return void
	 */
	public function test_suggestions_are_grouped() {
		$artist_id = self::factory()->post->create(
			array(
				'post_type'   => 'wavira_artist',
				'post_status' => 'publish',
				'post_title'  => 'Nova Singer',
			)
		);

		$this->make_track( 'Nova Song', 0, $artist_id );

		$groups = SearchService::suggest( 'Nova', 5 );

		$this->assertArrayHasKey( 'artists', $groups );
		$this->assertArrayHasKey( 'tracks', $groups );
		$this->assertSame( 'Nova Singer', $groups['artists'][0]['title'] );
		$this->assertArrayHasKey( 'url', $groups['tracks'][0] );
	}

	/**
	 * Related items prefer shared genres and never include the source.
	 *
	 * @return void
	 */
	public function test_related_prefers_shared_genres() {
		$rock = $this->make_genre( 'Rock' );
		$pop  = $this->make_genre( 'Pop' );

		$source  = $this->make_track( 'Source', $rock );
		$sibling = $this->make_track( 'Sibling', $rock );
		$other   = $this->make_track( 'Other', $pop );

		Cache::flush();

		$ids = RelatedService::ids( $source, 5 );

		$this->assertNotContains( $source, $ids, 'never related to itself' );
		$this->assertContains( $sibling, $ids );
		$this->assertContains( $other, $ids, 'the list is completed from the catalogue' );

		$this->assertSame( $sibling, $ids[0], 'shared genre ranks first' );
	}

	/**
	 * The related limit is obeyed and bounded by the site setting.
	 *
	 * @return void
	 */
	public function test_related_limit_is_respected() {
		$rock = $this->make_genre( 'Rock' );

		$source = $this->make_track( 'Source', $rock );

		for ( $i = 0; $i < 6; $i++ ) {
			$this->make_track( 'Neighbour ' . $i, $rock );
		}

		Cache::flush();

		$this->assertCount( 1, RelatedService::ids( $source, 1 ) );
		$this->assertCount( 3, RelatedService::ids( $source, 3 ) );
		$this->assertLessThanOrEqual( RelatedService::MAX_ITEMS, count( RelatedService::ids( $source, 500 ) ) );
	}

	/**
	 * Related queries are bounded and cached (second call runs no query).
	 *
	 * @return void
	 */
	public function test_related_results_are_cached_and_bounded() {
		$rock = $this->make_genre( 'Rock' );

		$source = $this->make_track( 'Source', $rock );
		$this->make_track( 'Neighbour', $rock );

		Cache::flush();

		$this->capture_queries();
		RelatedService::ids( $source, 3 );
		$first = count( $this->sql );
		$this->stop_capturing();

		$this->assertGreaterThan( 0, $first );

		foreach ( $this->sql as $statement ) {
			$this->assertMatchesRegularExpression( '/LIMIT\s+\d+/i', $statement, 'every related query carries a LIMIT' );
		}

		$this->capture_queries();
		RelatedService::ids( $source, 3 );
		$this->stop_capturing();

		$this->assertCount( 0, $this->sql, 'the second call is served from the cache' );

		Cache::flush();

		$this->capture_queries();
		RelatedService::ids( $source, 3 );
		$this->stop_capturing();

		$this->assertGreaterThan( 0, count( $this->sql ), 'a flushed cache recomputes' );
	}

	/**
	 * The related filter can suppress the block.
	 *
	 * @return void
	 */
	public function test_related_filter_can_suppress() {
		$rock   = $this->make_genre( 'Rock' );
		$source = $this->make_track( 'Source', $rock );

		$this->make_track( 'Neighbour', $rock );
		Cache::flush();

		add_filter( 'wavira_related_ids', '__return_empty_array' );
		$ids = RelatedService::ids( $source, 3 );
		remove_filter( 'wavira_related_ids', '__return_empty_array' );

		$this->assertSame( array(), $ids );
	}

	/**
	 * Related posts keep the computed order.
	 *
	 * @return void
	 */
	public function test_related_posts_are_returned_in_order() {
		$rock = $this->make_genre( 'Rock' );

		$source = $this->make_track( 'Source', $rock );
		$first  = $this->make_track( 'First', $rock );
		$second = $this->make_track( 'Second', $rock );

		Cache::flush();

		$expected = RelatedService::ids( $source, 2 );
		$posts    = RelatedService::posts( $source, 2 );

		$this->assertSame( $expected, wp_list_pluck( $posts, 'ID' ) );
		$this->assertContains( $first, $expected );
		$this->assertContains( $second, $expected );
	}
}
