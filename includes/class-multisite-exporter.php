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

/**
 * Main plugin class.
 *
 * @since      1.0.1
 * @package    Multisite_Exporter
 */
class Multisite_Exporter {

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
		$this->define_constants();
		$this->includes();
		$this->init_hooks();

		// Check if we're running in a multisite environment
		if ( ! is_multisite() ) {
			add_action( 'admin_notices', array( $this, 'multisite_notice' ) );
			return;
		}

		// Load the plugin text domain
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ), 9 );

		// Initialize classes
		add_action( 'plugins_loaded', array( $this, 'init' ), 10 );
	}

	/**
	 * Define plugin constants
	 *
	 * @return void
	 */
	private function define_constants() {
		define( 'MULTISITE_EXPORTER_VERSION', '1.0.1' );
		define( 'MULTISITE_EXPORTER_PLUGIN_DIR', plugin_dir_path( dirname( __FILE__ ) ) );
		define( 'MULTISITE_EXPORTER_PLUGIN_URL', plugin_dir_url( dirname( __FILE__ ) ) );
		define( 'MULTISITE_EXPORTER_PLUGIN_FILE', dirname( dirname( __FILE__ ) ) . '/multisite-exporter.php' );
	}

	/**
	 * Include required files
	 *
	 * @return void
	 */
	private function includes() {
		// Core includes
		include_once MULTISITE_EXPORTER_PLUGIN_DIR . 'includes/core/class-init.php';

		// Export functionality
		include_once MULTISITE_EXPORTER_PLUGIN_DIR . 'includes/export/class-export.php';

		// Admin includes
		if ( is_admin() ) {
			include_once MULTISITE_EXPORTER_PLUGIN_DIR . 'includes/admin/class-admin.php';
		}
	}

	/**
	 * Initialize hooks
	 *
	 * @return void
	 */
	private function init_hooks() {
		// Action Scheduler is now loaded directly in the main plugin file
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
}