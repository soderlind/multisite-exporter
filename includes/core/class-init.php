<?php
/**
 * Core initialization class.
 *
 * @since      1.0.1
 * @package    Multisite_Exporter
 * @subpackage Multisite_Exporter/Core
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core initialization class.
 */
class ME_Init {

	/**
	 * The single instance of the class.
	 *
	 * @since  1.0.1
	 * @var    ME_Init
	 */
	protected static $_instance = null;

	/**
	 * Main ME_Init Instance.
	 *
	 * @return ME_Init - Main instance.
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
		// Register hooks for scheduled actions
		add_action( 'me_process_site_export', array( $this, 'process_site_export_callback' ), 10, 2 );
	}

	/**
	 * Get export directory path for saving all exports in one common location
	 * 
	 * Creates a directory in the main site's uploads folder if it doesn't exist
	 *
	 * @return string Path to the export directory
	 */
	public function get_export_directory() {
		// Switch to the main site to get its upload directory
		$original_blog_id = get_current_blog_id();
		switch_to_blog( 1 );

		// Get main site upload directory
		$upload_dir         = wp_upload_dir();
		$default_export_dir = trailingslashit( $upload_dir[ 'basedir' ] ) . 'multisite-exports';

		/**
		 * Filter the export directory path.
		 *
		 * @since 1.0.1
		 *
		 * @param string $default_export_dir Default export directory path.
		 */
		$export_dir = apply_filters( 'multisite_exporter_directory', $default_export_dir );

		// Create directory if it doesn't exist
		if ( ! file_exists( $export_dir ) ) {
			wp_mkdir_p( $export_dir );

			// Create an index.php file for security
			$index_file = $export_dir . '/index.php';
			if ( ! file_exists( $index_file ) ) {
				file_put_contents( $index_file, '<?php // Silence is golden' );
			}

			// Create .htaccess to prevent direct access to XML files
			$htaccess = $export_dir . '/.htaccess';
			if ( ! file_exists( $htaccess ) ) {
				$htaccess_content = "Options -Indexes\n";
				$htaccess_content .= "<Files *.xml>\n";
				$htaccess_content .= "Order Allow,Deny\n";
				$htaccess_content .= "Deny from all\n";
				$htaccess_content .= "</Files>\n";
				file_put_contents( $htaccess, $htaccess_content );
			}
		}

		// Switch back to the original site
		restore_current_blog();

		return $export_dir;
	}

	/**
	 * Schedule exports for all subsites.
	 *
	 * @param array $export_args Export arguments.
	 */
	public function schedule_exports( $export_args = array() ) {
		$sites = get_sites( array( 'number' => 0 ) );

		foreach ( $sites as $site ) {
			as_schedule_single_action(
				time(),
				'me_process_site_export',
				array( $site->blog_id, $export_args )
			);
		}
	}

	/**
	 * Process site export callback.
	 *
	 * @param int   $blog_id Blog ID.
	 * @param array $export_args Export arguments.
	 */
	public function process_site_export_callback( $blog_id, $export_args ) {
		try {
			// Switch to the site being exported
			switch_to_blog( $blog_id );

			// Get site information for the filename
			$site_name = get_bloginfo( 'name' );
			$site_name = sanitize_title( $site_name );

			// Get common export directory
			$export_dir = $this->get_export_directory();
			$file_name  = 'export-blog-' . $blog_id . '-' . $site_name . '-' . date( 'Ymd-His' ) . '.xml';
			$file_path  = trailingslashit( $export_dir ) . $file_name;

			// Generate export content with our custom function
			$exporter       = ME_Export::instance();
			$export_content = $exporter->generate_export( $export_args );

			// Save to file in the common export directory
			if ( file_put_contents( $file_path, $export_content ) ) {
				error_log( "Export complete for Blog ID: $blog_id | File: $file_path" );

				// Store export information in a transient for the admin interface
				$exports   = get_site_transient( 'multisite_exports' ) ?: array();
				$exports[] = array(
					'blog_id'   => $blog_id,
					'site_name' => $site_name,
					'file_name' => $file_name,
					'file_path' => $file_path,
					'date'      => current_time( 'mysql' ),
				);
				set_site_transient( 'multisite_exports', $exports, WEEK_IN_SECONDS );
			} else {
				error_log( "Failed to write export file for Blog ID: $blog_id" );
			}
		} catch (Exception $e) {
			error_log( "Export error for Blog ID: $blog_id | Error: " . $e->getMessage() );
		}

		// Restore to the previous blog
		restore_current_blog();
	}
}