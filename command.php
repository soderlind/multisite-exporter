<?php
/**
 * Multisite Exporter WP-CLI Package
 *
 * @package Multisite_Exporter
 * @since 1.2.2
 */

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

// Check if the command is already registered (in case the plugin is loaded normally)
if ( ! class_exists( 'ME_CLI_Command' ) ) {
	// Define constants if they don't exist (when installed as a package)
	if ( ! defined( 'MULTISITE_EXPORTER_PLUGIN_DIR' ) ) {
		define( 'MULTISITE_EXPORTER_PLUGIN_DIR', __DIR__ . '/' );
	}

	// Include the necessary CLI command file
	if ( file_exists( __DIR__ . '/includes/cli/class-me-cli-command.php' ) ) {
		require_once __DIR__ . '/includes/cli/class-me-cli-command.php';
	}

	// Include export functions if needed
	if ( file_exists( __DIR__ . '/includes/export/class-export.php' ) ) {
		require_once __DIR__ . '/includes/export/class-export.php';
	}

	if ( file_exists( __DIR__ . '/includes/export/class-wxr-validator.php' ) ) {
		require_once __DIR__ . '/includes/export/class-wxr-validator.php';
	}

	// Register the command
	if ( class_exists( 'ME_CLI_Command' ) ) {
		WP_CLI::add_command( 'multisite-exporter', 'ME_CLI_Command' );
	}
}
