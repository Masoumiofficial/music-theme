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

define( 'WAVIRA_THEME_VERSION', '0.5.0' );
define( 'WAVIRA_THEME_DIR', trailingslashit( get_template_directory() ) );
define( 'WAVIRA_THEME_URI', trailingslashit( get_template_directory_uri() ) );

require_once WAVIRA_THEME_DIR . 'inc/setup.php';
require_once WAVIRA_THEME_DIR . 'inc/helpers.php';
require_once WAVIRA_THEME_DIR . 'inc/hooks.php';
require_once WAVIRA_THEME_DIR . 'inc/assets.php';
require_once WAVIRA_THEME_DIR . 'inc/player.php';
