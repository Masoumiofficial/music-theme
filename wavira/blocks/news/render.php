<?php
/**
 * Server render — music news block.
 *
 * The feed reads ordinary posts: the newest ones, or the newest in one category.
 * It is the block a site owner drops on the home page or inside an article
 * (`[wavira_news]` renders the same markup), while the blog templates use core's
 * Query Loop so pagination, feeds and the archive title stay core's business.
 *
 * @package Wavira\Theme
 * @since   0.9.0
 *
 * @var array $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

$wavira_source  = isset( $attributes['source'] ) ? (string) $attributes['source'] : 'blog';
$wavira_columns = isset( $attributes['columns'] ) ? absint( $attributes['columns'] ) : 3;
$wavira_columns = (int) max( 1, min( 4, $wavira_columns ) );

if ( ! in_array( $wavira_source, array( 'blog', 'category' ), true ) ) {
	$wavira_source = 'blog';
}

$wavira_chips = isset( $attributes['showCategories'] ) && (bool) $attributes['showCategories']
	? wavira_get_news_categories()
	: '';

$wavira_markup = wavira_get_news(
	array(
		'limit'        => isset( $attributes['perPage'] ) ? absint( $attributes['perPage'] ) : 6,
		'source'       => 'category' === $wavira_source ? 'category' : 'blog',
		'category'     => isset( $attributes['category'] ) ? (string) $attributes['category'] : '',
		'show_date'    => isset( $attributes['showDate'] ) && (bool) $attributes['showDate'],
		'show_excerpt' => isset( $attributes['showExcerpt'] ) && (bool) $attributes['showExcerpt'],
		'show_image'   => isset( $attributes['showImage'] ) && (bool) $attributes['showImage'],
		'class'        => 'wavira-news--cols-' . $wavira_columns,
	)
);

if ( '' === $wavira_markup ) {
	wavira_block_placeholder( __( 'No news posts yet. Publish one and it appears here.', 'wavira' ) );

	return;
}

if ( '' !== $wavira_chips ) {
	echo '<div class="wavira-news__filters">' . $wavira_chips . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped field by field inside wavira_get_news_categories().
}

echo $wavira_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped field by field inside wavira_get_news() and wavira_get_card().
