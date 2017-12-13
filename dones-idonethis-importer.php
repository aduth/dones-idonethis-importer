<?php
/**
 * Plugin Name: Dones - iDoneThis Importer
 * Plugin URI: https://dones.today
 * Description: Import iDoneThis tasks to the Dones theme.
 * Author: Andrew Duthie
 * Author URI: https://andrewduthie.com
 * Version: 1.0.0
 * License: GNU General Public License v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: dones-idonethis-importer
 *
 * @package dones-idonethis
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

if ( class_exists( 'WP_Importer' ) ) {
	require_once dirname( __FILE__ ) . '/class-dones-idonethis-import.php';
}

/**
 * Registers the Dones iDoneThis Importer class.
 */
function dones_idonethis_importer_init() {
	load_plugin_textdomain(
		'dones-idonethis-importer',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);

	$dones_idonethis_importer = new Dones_IDoneThis_Import();

	register_importer(
		'dones-idonethis',
		__( 'iDoneThis to Dones', 'dones-idonethis-importer' ),
		__( 'Import iDoneThis tasks to the Dones theme.', 'dones-idonethis-importer' ),
		array( &$dones_idonethis_importer, 'dispatch' )
	);
}
add_action( 'admin_init', 'dones_idonethis_importer_init' );
