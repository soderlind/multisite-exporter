<?php
/**
 * Admin class.
 *
 * @since      1.0.1
 * @package    Multisite_Exporter
 * @subpackage Multisite_Exporter/Admin
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin class.
 */
class ME_Admin {

	/**
	 * The single instance of the class.
	 *
	 * @since  1.0.1
	 * @var    ME_Admin
	 */
	protected static $_instance = null;

	/**
	 * Main ME_Admin Instance.
	 *
	 * @return ME_Admin - Main instance.
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
		// Add menu
		add_action( 'network_admin_menu', array( $this, 'add_admin_menu' ) );

		// Register plugin assets (styles and scripts)
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// AJAX handlers
		add_action( 'wp_ajax_me_download_export', array( $this, 'handle_export_download' ) );
		add_action( 'wp_ajax_me_download_selected_exports', array( $this, 'download_selected_exports' ) );
	}

	/**
	 * Enqueue admin assets (styles and scripts).
	 * 
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load assets on our plugin pages
		if ( strpos( $hook, 'multisite-exporter' ) === false ) {
			return;
		}

		// Enqueue common styles
		wp_enqueue_style(
			'multisite-exporter-styles',
			MULTISITE_EXPORTER_PLUGIN_URL . 'css/multisite-exporter.css',
			array(),
			filemtime( MULTISITE_EXPORTER_PLUGIN_DIR . 'css/multisite-exporter.css' )
		);

		// Enqueue common admin scripts
		wp_enqueue_script(
			'multisite-exporter-admin',
			MULTISITE_EXPORTER_PLUGIN_URL . 'js/multisite-exporter-admin.js',
			array( 'jquery' ),
			filemtime( MULTISITE_EXPORTER_PLUGIN_DIR . 'js/multisite-exporter-admin.js' ),
			true
		);

		// Enqueue page-specific scripts
		if ( strpos( $hook, 'multisite-exporter-history' ) !== false ) {
			wp_enqueue_script(
				'multisite-exporter-history',
				MULTISITE_EXPORTER_PLUGIN_URL . 'js/history-page.js',
				array( 'jquery' ),
				filemtime( MULTISITE_EXPORTER_PLUGIN_DIR . 'js/history-page.js' ),
				true
			);
		}
	}

	/**
	 * Add top-level admin menu for Multisite Exporter.
	 */
	public function add_admin_menu() {
		add_menu_page(
			'Multisite Exporter',   // Page title
			'MS Exporter',          // Menu title (shorter for menu space)
			'manage_network',       // Capability
			'multisite-exporter',   // Menu slug
			array( $this, 'render_main_page' ), // Callback function
			'dashicons-download',   // Icon (download icon is appropriate for export)
			30                      // Position (after Comments which is 25)
		);

		// Add submenu for export history
		add_submenu_page(
			'multisite-exporter',  // Parent slug
			'Export History',      // Page title
			'Export History',      // Menu title
			'manage_network',      // Capability
			'multisite-exporter-history', // Menu slug
			array( $this, 'render_history_page' ) // Callback function
		);

		// Add submenu for Action Scheduler admin
		add_submenu_page(
			'multisite-exporter',          // Parent slug
			esc_html__( 'Scheduled Actions', 'multisite-exporter' ), // Page title
			esc_html__( 'Scheduled Actions', 'multisite-exporter' ), // Menu title
			'manage_network',              // Capability
			'me-scheduled-actions',        // Menu slug - unique to avoid conflicts
			array( $this, 'render_action_scheduler_page' ) // Callback function
		);
	}

	/**
	 * Render the main admin page.
	 */
	public function render_main_page() {
		// Add nonce for security
		if ( isset( $_POST[ 'me_run' ] ) && check_admin_referer( 'multisite_exporter_action', 'me_nonce' ) ) {
			$export_args = [ 
				'content'    => sanitize_text_field( $_POST[ 'content' ] ?? 'all' ),
				'post_type'  => sanitize_text_field( $_POST[ 'post_type' ] ?? '' ),
				'start_date' => sanitize_text_field( $_POST[ 'start_date' ] ?? '' ),
				'end_date'   => sanitize_text_field( $_POST[ 'end_date' ] ?? '' ),
			];

			$init = ME_Init::instance();
			$init->schedule_exports( $export_args );

			echo '<div class="updated"><p>' . esc_html__( 'Export has been scheduled for all subsites! View results in the', 'multisite-exporter' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=multisite-exporter-history' ) ) . '">' . esc_html__( 'Export History', 'multisite-exporter' ) . '</a> ' . esc_html__( 'page.', 'multisite-exporter' ) . '</p></div>';
		}

		// Include the main page view
		include_once MULTISITE_EXPORTER_PLUGIN_DIR . 'includes/admin/views/view-main-page.php';
	}

	/**
	 * Render the export history page.
	 */
	public function render_history_page() {
		// Include the history page view
		include_once MULTISITE_EXPORTER_PLUGIN_DIR . 'includes/admin/views/view-history-page.php';
	}

	/**
	 * Display the Action Scheduler admin screen under our own menu
	 */
	public function render_action_scheduler_page() {
		// Include the action scheduler page view
		include_once MULTISITE_EXPORTER_PLUGIN_DIR . 'includes/admin/views/view-scheduled-actions-page.php';
	}

	/**
	 * Handle export file downloads
	 */
	public function handle_export_download() {
		// Check if user has permission
		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( 'You do not have permission to access this file.' );
		}

		// Verify nonce
		$file = isset( $_GET[ 'file' ] ) ? sanitize_text_field( base64_decode( $_GET[ 'file' ] ) ) : '';
		if ( ! wp_verify_nonce( $_GET[ 'nonce' ], 'download_export_' . $file ) ) {
			wp_die( 'Security check failed.' );
		}

		// Use the Export class to download the file
		$exporter = ME_Export::instance();
		$exporter->download_export_file( $file );
	}

	/**
	 * Handle the download of multiple selected export files as a zip archive
	 */
	public function download_selected_exports() {
		// Check nonce for security
		if ( ! isset( $_POST[ 'me_download_nonce' ] ) || ! wp_verify_nonce( $_POST[ 'me_download_nonce' ], 'download_selected_exports' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'multisite-exporter' ) );
		}

		// Check if we're selecting all exports across all pages
		$select_all_pages = isset( $_POST[ 'select_all_pages' ] ) && $_POST[ 'select_all_pages' ] == '1';

		// Get selected exports or all exports if select_all_pages is true
		$all_exports = get_site_transient( 'multisite_exports' ) ?: array();

		if ( $select_all_pages ) {
			// Use all available exports
			$selected = array_column( $all_exports, 'file_name' );
		} else {
			// Use only explicitly selected exports
			$selected = isset( $_POST[ 'selected_exports' ] ) ? (array) $_POST[ 'selected_exports' ] : array();
		}

		// Ensure we have selected exports
		if ( empty( $selected ) ) {
			wp_die( esc_html__( 'No exports selected.', 'multisite-exporter' ) );
		}

		// If there's only one file selected, just download it directly
		if ( count( $selected ) === 1 ) {
			$file_name = $selected[ 0 ];
			$exporter  = ME_Export::instance();
			$exporter->download_export_file( $file_name );
			exit;
		}

		// Create a zip file containing all selected exports
		$zip       = new ZipArchive();
		$temp_file = tempnam( sys_get_temp_dir(), 'me_exports_' );

		if ( $zip->open( $temp_file, ZipArchive::CREATE ) !== true ) {
			wp_die( esc_html__( 'Could not create ZIP file.', 'multisite-exporter' ) );
		}

		// Get the export directory
		$init       = ME_Init::instance();
		$export_dir = $init->get_export_directory();

		$found_files = array();

		// Add files to the zip
		foreach ( $selected as $file_name ) {
			$file_path = trailingslashit( $export_dir ) . $file_name;

			if ( file_exists( $file_path ) ) {
				$zip->addFile( $file_path, $file_name );
				$found_files[] = $file_name;
			}
		}

		// Check if we found all files
		if ( count( $found_files ) !== count( $selected ) ) {
			$missing       = array_diff( $selected, $found_files );
			$error_message = sprintf(
				/* translators: %s: Comma-separated list of missing files */
				esc_html__( 'Could not find some export files: %s', 'multisite-exporter' ),
				implode( ', ', $missing )
			);
			wp_die( $error_message );
		}

		$zip->close();

		// Output the zip file
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="multisite-exports-' . date( 'Y-m-d' ) . '.zip"' );
		header( 'Content-Length: ' . filesize( $temp_file ) );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		readfile( $temp_file );
		unlink( $temp_file );
		exit;
	}

	/**
	 * Force Action Scheduler query args to only show our hook
	 */
	public function force_hook_filter( $query_args ) {
		$query_args[ 'hook' ] = 'me_process_site_export';
		return $query_args;
	}

	/**
	 * Filter the SQL query directly if needed
	 */
	public function filter_as_sql_query( $sql ) {
		global $wpdb;

		// Only modify the SQL if we're on our custom page
		if ( isset( $_GET[ 'page' ] ) && $_GET[ 'page' ] === 'me-scheduled-actions' ) {
			// Ensure the SQL contains a WHERE clause for our hook
			if ( ! strpos( $sql, "hook = 'me_process_site_export'" ) ) {
				// If there's a WHERE clause, add our condition
				if ( strpos( $sql, 'WHERE' ) !== false ) {
					$sql = str_replace(
						'WHERE',
						"WHERE (a.hook = 'me_process_site_export') AND ",
						$sql
					);
				} else {
					// If no WHERE clause exists, add one
					$sql .= " WHERE (a.hook = 'me_process_site_export')";
				}
			}
		}

		return $sql;
	}
}