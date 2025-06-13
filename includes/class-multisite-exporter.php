<?php
/**
 * The main plugin class.
 *
 * @since      1.0.1
 * @package    Multisite_Exporter
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
/**
 * Main plugin class.
 *
 * @since      1.0.1
 * @package    Multisite_Exporter
 */
class Multisite_Exporter {


	/**
	 * GitHub URL for update checker.
	 * @var string
	 */
	private $github_url = 'https://github.com/soderlind/multisite-exporter';

	/**
	 * The single instance of the class.
	 *
	 * @since 1.0.1
	 * @var Multisite_Exporter
	 */
	protected static $_instance = null;

	/**
	 * Main Multisite_Exporter Instance.
	 *
	 * Ensures only one instance of Multisite_Exporter is loaded or can be loaded.
	 *
	 * @since 1.0.1
	 * @return Multisite_Exporter - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Multisite_Exporter Constructor.
	 */
	public function __construct() {
		$this->includes();
		\Soderlind\WordPress\GitHub_Plugin_Updater::create_with_assets(
			$this->github_url,
			MULTISITE_EXPORTER_PLUGIN_FILE,
			'multisite-exporter',
			'/multisite-exporter\.zip/',
			'main'
		);

		// Check if we're running in a multisite environment
		if ( ! is_multisite() ) {
			add_action( 'admin_notices', array( $this, 'multisite_notice' ) );
			return;
		}

		// Load the plugin text domain
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ), 9 );

		// Initialize classes
		add_action( 'plugins_loaded', array( $this, 'init' ), 10 );

		// Pretty format Action Scheduler arguments
		add_filter( 'action_scheduler_list_table_column_args', array( $this, 'pretty_format_args' ), 10, 2 );
	}

	/**
	 * Include required files
	 *
	 * @return void
	 */
	private function includes() {
		// Core includes
		include_once MULTISITE_EXPORTER_PLUGIN_DIR . 'includes/core/class-init.php';

		// Export includes
		include_once MULTISITE_EXPORTER_PLUGIN_DIR . 'includes/export/class-wxr-validator.php';
		include_once MULTISITE_EXPORTER_PLUGIN_DIR . 'includes/export/class-export.php';

		// Admin includes
		if ( is_admin() ) {
			include_once MULTISITE_EXPORTER_PLUGIN_DIR . 'includes/admin/class-admin.php';
		}

		// CLI includes
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			include_once MULTISITE_EXPORTER_PLUGIN_DIR . 'includes/cli/class-cli.php';
		}
	}

	/**
	 * Initialize the plugin
	 *
	 * @return void
	 */
	public function init() {
		// Check if Action Scheduler is available
		if ( ! class_exists( 'ActionScheduler' ) ) {
			add_action( 'network_admin_notices', array( $this, 'action_scheduler_notice' ) );
			return;
		}

		// Initialize plugin components
		ME_Init::instance();

		if ( is_admin() ) {
			ME_Admin::instance();
		}

		ME_Export::instance();

		// Initialize CLI commands if in WP CLI context
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			ME_CLI::instance();
		}
	}

	/**
	 * Load plugin text domain
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'multisite-exporter',
			false,
			dirname( plugin_basename( MULTISITE_EXPORTER_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Notice for multisite requirement
	 *
	 * @return void
	 */
	public function multisite_notice() {
		echo '<div class="error"><p>' . esc_html__( 'Multisite Exporter only works on multisite installations.', 'multisite-exporter' ) . '</p></div>';
	}

	/**
	 * Notice for Action Scheduler requirement
	 *
	 * @return void
	 */
	public function action_scheduler_notice() {
		echo '<div class="error"><p>' . esc_html__( 'Action Scheduler is required for Multisite Exporter to work. Please run composer install.', 'multisite-exporter' ) . '</p></div>';
	}

	/**
	 * Pretty format Action Scheduler arguments as JSON
	 * 
	 * @param string $html The HTML to display for the args column.
	 * @param array  $row  The row data.
	 * @return string
	 */
	public function pretty_format_args( $html, $row ) {
		// Return default HTML 
		return $html;
	}

	/**
	 * Highlight JSON string with syntax colors
	 *
	 * @param string $json JSON string to highlight
	 * @return string Highlighted HTML
	 */
	private function highlight_json( $json ) {
		// This function is no longer used
		return $json;
	}
}