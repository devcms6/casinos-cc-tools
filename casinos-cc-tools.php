<?php
/**
 * Plugin Name:       Casinos.cc Tools
 * Plugin URI:        https://github.com/didoivanov/casinos-cc-tools
 * Description:       Interactive casino tools (wagering calculator, withdrawal checker) driven by per-casino tool profiles. Manual entry or JSON import.
 * Version:           0.1.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Casinos.cc
 * License:           GPL-2.0-or-later
 * Text Domain:       casinos-cc-tools
 * Domain Path:       /languages
 * Update URI:        https://github.com/didoivanov/casinos-cc-tools
 */

defined( 'ABSPATH' ) || exit;

define( 'CCT_VERSION', '0.1.1' );
define( 'CCT_FILE', __FILE__ );
define( 'CCT_DIR', plugin_dir_path( __FILE__ ) );
define( 'CCT_URL', plugin_dir_url( __FILE__ ) );
define( 'CCT_SCHEMA_VERSION', 1 );

/*
 * GitHub repository used for updates. Can be overridden in wp-config.php:
 * define( 'CCT_GITHUB_REPO', 'owner/repo' );
 */
if ( ! defined( 'CCT_GITHUB_REPO' ) ) {
	define( 'CCT_GITHUB_REPO', 'didoivanov/casinos-cc-tools' );
}

require_once CCT_DIR . 'includes/class-cct-schema.php';
require_once CCT_DIR . 'includes/class-cct-profiles.php';
require_once CCT_DIR . 'includes/class-cct-shortcodes.php';
require_once CCT_DIR . 'includes/class-cct-updater.php';

if ( is_admin() ) {
	require_once CCT_DIR . 'includes/class-cct-admin.php';
	require_once CCT_DIR . 'includes/class-cct-import.php';
}

add_action( 'plugins_loaded', function () {
	load_plugin_textdomain( 'casinos-cc-tools', false, dirname( plugin_basename( CCT_FILE ) ) . '/languages' );

	CCT_Profiles::init();
	CCT_Shortcodes::init();
	CCT_Updater::init();

	if ( is_admin() ) {
		CCT_Admin::init();
		CCT_Import::init();
	}
} );
