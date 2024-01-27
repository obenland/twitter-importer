<?php
/*
Plugin Name: Twitter and X Importer
Plugin URI: https://wordpress.org/extend/plugins/twitter-importer/
Description: Import tweets as posts from a Twitter or X export.
Author: wordpressdotorg
Author URI: https://wordpress.org/
Version: 0.1
License: GPL version 2 or later - https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

if ( ! defined( 'WP_LOAD_IMPORTERS' ) ) {
	return;
}

// Load Importer API.
require_once ABSPATH . 'wp-admin/includes/import.php';

if ( ! class_exists( 'WP_Importer' ) ) {
	$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
	if ( file_exists( $class_wp_importer ) ) {
		require_once $class_wp_importer;
	}
}

/**
 * Twitter and X Importer.
 */
if ( class_exists( 'WP_Importer' ) ) {
	require_once __DIR__ . '/class-twitter-importer.php';

	$twitter_importer = new Twitter_Importer();

	register_importer( 'twitter', __( 'Twitter and X', 'twitter-importer' ), __( 'Import tweets as posts from a Twitter or X export.', 'twitter-importer' ), array(
		$twitter_importer,
		'dispatch'
	) );
}

/**
 * Load plugin textdomain.
 */
function twitter_importer_init() {
	load_plugin_textdomain( 'twitter-importer' );
}
add_action( 'init', 'twitter_importer_init' );
