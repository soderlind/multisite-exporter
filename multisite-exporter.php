<?php
/*
Plugin Name: Multisite Exporter
Description: Runs WordPress Exporter on each subsite in a multisite, in the background.
Version: 1.1.3
Author: Per Søderlind
Author URI: https://soderlind.no
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: multisite-exporter
Domain Path: /languages
Network: true
*/

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'MULTISITE_EXPORTER_VERSION', '1.1.3' );
define( 'MULTISITE_EXPORTER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MULTISITE_EXPORTER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MULTISITE_EXPORTER_PLUGIN_FILE', __FILE__ );

// Load Action Scheduler before plugins_loaded
$action_scheduler_file = MULTISITE_EXPORTER_PLUGIN_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
if ( file_exists( $action_scheduler_file ) ) {
	require_once $action_scheduler_file;
}

require_once MULTISITE_EXPORTER_PLUGIN_DIR . 'vendor/autoload.php';

// Include the main plugin class
require_once MULTISITE_EXPORTER_PLUGIN_DIR . 'includes/class-multisite-exporter.php';

/**
 * Returns the main instance of Multisite_Exporter.
 *
 * @since  1.0.1
 * @return Multisite_Exporter
 */
function Multisite_Exporter() {
	return Multisite_Exporter::instance();
}

// Global for backwards compatibility
$GLOBALS[ 'multisite_exporter' ] = Multisite_Exporter();