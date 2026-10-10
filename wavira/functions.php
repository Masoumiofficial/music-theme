<?php
/**
 * Wavira Music — theme bootstrap.
 *
 * This file must stay a thin bootstrap: only constants and `require_once`
 * statements. All behaviour lives in inc/*.php (see docs/ARCHITECTURE.md).
 *
 * @package Wavira\Theme
 * @since   0.2.0
 */

defined( 'ABSPATH' ) || exit;

define( 'WAVIRA_THEME_VERSION', '0.15.0' );
define( 'WAVIRA_THEME_DIR', trailingslashit( get_template_directory() ) );
define( 'WAVIRA_THEME_URI', trailingslashit( get_template_directory_uri() ) );

require_once WAVIRA_THEME_DIR . 'inc/setup.php';
require_once WAVIRA_THEME_DIR . 'inc/helpers.php';
require_once WAVIRA_THEME_DIR . 'inc/options.php';
require_once WAVIRA_THEME_DIR . 'inc/site-defaults.php';
require_once WAVIRA_THEME_DIR . 'inc/customizer.php';
require_once WAVIRA_THEME_DIR . 'inc/admin-panel.php';
require_once WAVIRA_THEME_DIR . 'inc/markup.php';
require_once WAVIRA_THEME_DIR . 'inc/cards.php';
require_once WAVIRA_THEME_DIR . 'inc/downloads.php';
require_once WAVIRA_THEME_DIR . 'inc/hooks.php';
require_once WAVIRA_THEME_DIR . 'inc/performance.php';
require_once WAVIRA_THEME_DIR . 'inc/seo.php';
require_once WAVIRA_THEME_DIR . 'inc/assets.php';
require_once WAVIRA_THEME_DIR . 'inc/player.php';
require_once WAVIRA_THEME_DIR . 'inc/artists.php';
require_once WAVIRA_THEME_DIR . 'inc/news.php';
require_once WAVIRA_THEME_DIR . 'inc/shortcodes.php';
require_once WAVIRA_THEME_DIR . 'inc/blocks.php';
