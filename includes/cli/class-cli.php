<?php
/**
 * WP CLI integration.
 *
 * @since      1.2.0
 * @package    Multisite_Exporter
 * @subpackage Multisite_Exporter/CLI
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP CLI integration class.
 */
class ME_CLI {

	/**
	 * The single instance of the class.
	 *
	 * @since  1.2.0
	 * @var    ME_CLI
	 */
	protected static $_instance = null;

	/**
	 * Main ME_CLI Instance.
	 *
	 * @return ME_CLI - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Only register WP CLI commands when WP CLI is available
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$this->register_commands();
		}
	}

	/**
	 * Register WP CLI commands.
	 */
	private function register_commands() {
		require_once MULTISITE_EXPORTER_PLUGIN_DIR . 'includes/cli/class-me-cli-command.php';
		WP_CLI::add_command( 'multisite-exporter', 'ME_CLI_Command' );
	}
}
