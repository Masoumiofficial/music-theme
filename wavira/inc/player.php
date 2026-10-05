<?php
/**
 * Player mount point and progressive-enhancement fallback.
 *
 * A template renders a mount point; the engine builds its own `<audio>`, its
 * own controls and its own state (ADR 0005 §1). The theme therefore never
 * prints an element with a fixed ID and never assumes a single player per page.
 *
 * Without JavaScript the mount point keeps a native `<audio>` element with the
 * preferred source, so a track page is still usable (progressive enhancement,
 * docs/PERFORMANCE-AUDIT.md P2).
 *
 * @package Wavira\Theme
 * @since   0.5.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Print a player mount point.
 *
 * @param array<string, mixed> $args {
 *     Optional. Mount arguments.
 *
 *     @type string $context  Queue context: album, artist, genre, tracks or related.
 *     @type int    $id       Source post ID for album, artist and related queues.
 *     @type string $slug     Genre slug for a genre queue.
 *     @type int    $limit    Maximum queue length (0 = site setting).
 *     @type int    $track    Track to load first (0 = let the engine ask the queue).
 *     @type string $orderby  Queue order: date, title, menu_order, modified or rand.
 *     @type string $order    Queue direction: asc or desc.
 *     @type bool   $autoplay Start playback once the page is ready, when the browser allows it.
 *     @type bool   $sticky   Render the sticky/mini player variant.
 *     @type bool   $fallback Print the no-JavaScript `<audio>` fallback (default true).
 *     @type string $class    Extra CSS classes for the mount element.
 * }
 * @return void
 */
function wavira_player_mount( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'context'  => 'tracks',
			'id'       => 0,
			'slug'     => '',
			'limit'    => 0,
			'track'    => 0,
			'orderby'  => 'date',
			'order'    => 'desc',
			'autoplay' => false,
			'sticky'   => false,
			'fallback' => true,
			'class'    => '',
		)
	);

	$context  = (string) $args['context'];
	$track_id = (int) $args['track'];
	$playback = $track_id > 0 ? wavira_core_track_playback( $track_id ) : array();

	// A single-track mount without playback data has nothing to offer.
	if ( $track_id > 0 && empty( $playback['sources'] ) ) {
		return;
	}

	$classes = trim( 'wavira-player ' . (string) $args['class'] );

	if ( $args['sticky'] ) {
		$classes .= ' wavira-player--sticky';
	}

	$attributes = array(
		'data-wavira-player' => '1',
		'data-context'       => $context,
		'data-id'            => (string) (int) $args['id'],
		'data-slug'          => (string) $args['slug'],
		'data-limit'         => (string) (int) $args['limit'],
		'data-orderby'       => (string) $args['orderby'],
		'data-order'         => (string) $args['order'],
		'data-has-track'     => $track_id > 0 ? '1' : '0',
	);

	if ( $args['autoplay'] ) {
		$attributes['data-autoplay'] = '1';
	}

	if ( $track_id > 0 ) {
		$attributes['data-track'] = (string) $track_id;
	}

	wavira_core_enqueue_player();

	echo '<div class="' . esc_attr( $classes ) . '"';

	foreach ( $attributes as $name => $value ) {
		echo ' ' . esc_attr( $name ) . '="' . esc_attr( $value ) . '"';
	}

	echo '>';

	if ( $args['fallback'] && $track_id > 0 ) {
		wavira_player_fallback( $track_id, $playback );
	}

	echo '</div>';
}

/**
 * Native audio fallback shown until (and unless) the engine takes over.
 *
 * @param int                  $track_id Track post ID.
 * @param array<string, mixed> $playback Playback payload.
 * @return void
 */
function wavira_player_fallback( $track_id, $playback ) {
	$sources = isset( $playback['sources'] ) && is_array( $playback['sources'] ) ? $playback['sources'] : array();
	$source  = wavira_player_preferred_source( $sources );

	if ( '' === $source ) {
		return;
	}

	$title = isset( $playback['title'] ) ? (string) $playback['title'] : get_the_title( $track_id );
	?>
	<audio class="wavira-player__fallback" controls preload="none" src="<?php echo esc_url( $source ); ?>">
		<?php echo esc_html( $title ); ?>
	</audio>
	<p class="wavira-player__fallback-link">
		<a href="<?php echo esc_url( (string) get_permalink( $track_id ) ); ?>">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: track title. */
					__( 'Open %s', 'wavira' ),
					$title
				)
			);
			?>
		</a>
	</p>
	<?php
}

/**
 * Pick the best available source URL from a payload source map.
 *
 * @param array<int|string, string> $sources Source map: quality (or `external`) to URL.
 * @return string Empty string when the track has no playable source.
 */
function wavira_player_preferred_source( $sources ) {
	foreach ( array( 320, 128 ) as $quality ) {
		if ( ! empty( $sources[ $quality ] ) ) {
			return (string) $sources[ $quality ];
		}
	}

	if ( ! empty( $sources['external'] ) ) {
		return (string) $sources['external'];
	}

	return '';
}
