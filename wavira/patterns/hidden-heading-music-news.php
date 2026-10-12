<?php
/**
 * Title: Heading — music news
 * Slug: wavira/hidden-heading-music-news
 * Categories: hidden
 * Inserter: no
 *
 * Referenced by the front page and by the blog templates (`home.html`,
 * `index.html`), and available to any pattern that lists news, so the heading is
 * translated like every other string of the product — a block template cannot
 * run PHP (see hidden-404.php).
 *
 * On the page that *is* the news archive, this heading is the page's first-level
 * heading: WordPress calls that page `is_home()`, not `is_archive()`, so core's
 * own query-title block prints nothing there and a template built on it ships a
 * page with no `<h1>`. Everywhere else it names a section and, when the site has
 * a page for its posts, points at it.
 *
 * @package Wavira\Theme
 * @since   0.9.0
 */

defined( 'ABSPATH' ) || exit;

$wavira_posts_page = absint( get_option( 'page_for_posts' ) );
$wavira_news_url   = '';
$wavira_is_archive = is_home() && ! is_front_page();
$wavira_level      = $wavira_is_archive ? 1 : 2;

// The only page that lists every post is the one the owner chose for it. When
// there is none — and when the visitor is already on it — the honest link is
// no link: «مشاهدهٔ همه» that leads back here is a promise the site cannot keep.
if ( ! $wavira_is_archive && $wavira_posts_page > 0 ) {
	$wavira_news_url = (string) get_permalink( $wavira_posts_page );
}
?>
<!-- wp:group {"className":"wavira-section__head","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
<div class="wp-block-group wavira-section__head">
<!-- wp:heading {"level":<?php echo (int) $wavira_level; ?>} -->
<h<?php echo (int) $wavira_level; ?> class="wp-block-heading"><?php echo esc_html_x( 'Music news', 'heading above the news feed', 'wavira' ); ?></h<?php echo (int) $wavira_level; ?>>
<!-- /wp:heading -->
<?php if ( '' !== $wavira_news_url ) : ?>
<!-- wp:paragraph {"className":"wavira-section__more"} -->
<p class="wavira-section__more"><a href="<?php echo esc_url( $wavira_news_url ); ?>"><?php echo esc_html__( 'View all', 'wavira' ); ?></a></p>
<!-- /wp:paragraph -->
<?php endif; ?>
</div>
<!-- /wp:group -->
