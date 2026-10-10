<?php
/**
 * Title: Footer — design credit
 * Slug: wavira/hidden-footer-credit
 * Categories: hidden
 * Inserter: no
 *
 * The credit label is translatable; the author's own name and site are proper
 * nouns and stay as they are (attribution requirement, docs/BRAND-DECISION.md).
 *
 * @package Wavira\Theme
 * @since   0.7.0
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- wp:paragraph -->
<p><?php echo esc_html_x( 'Design and development:', 'credit label before the author link', 'wavira' ); ?> <a href="https://etehadwp.com/" rel="nofollow noopener">اتحاد وردپرس · Etehad WP</a><!-- wavira:i18n-exempt author attribution --></p>
<!-- /wp:paragraph -->
