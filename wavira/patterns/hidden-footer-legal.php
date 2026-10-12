<?php
/**
 * Title: Footer — legal line
 * Slug: wavira/hidden-footer-legal
 * Categories: hidden
 * Inserter: no
 *
 * The year is printed by PHP rather than hard-coded in the template part: a
 * block template's text cannot be translated, and a frozen year is a defect on
 * any site older than one season.
 *
 * @package Wavira\Theme
 * @since   0.7.0
 */

defined( 'ABSPATH' ) || exit;

$wavira_note = (string) wavira_option( 'footer_note' );
?>

<!-- wp:paragraph -->
<p>© <span class="wavira-footer__year"><?php echo esc_html( (string) wp_date( 'Y' ) ); ?></span> <?php echo esc_html_x( '— all rights reserved.', 'footer legal line after the copyright year', 'wavira' ); ?></p>
<!-- /wp:paragraph -->
<?php if ( '' !== $wavira_note ) : ?>
<!-- wp:paragraph {"className":"wavira-footer__note"} -->
<p class="wavira-footer__note"><?php echo esc_html( $wavira_note ); ?></p>
<!-- /wp:paragraph -->
<?php endif; ?>
