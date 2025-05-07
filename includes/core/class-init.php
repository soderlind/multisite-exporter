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

		// Initialize WordPress filesystem
		global $wp_filesystem;

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		// Initialize the filesystem
		if ( ! WP_Filesystem() ) {
			// Unable to initialize filesystem - output error message
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Multisite Exporter: Unable to initialize WordPress filesystem' );
			}
			restore_current_blog();
			return $export_dir; // Return the path anyway, though operations will fail
		}

		// Create directory if it doesn't exist
		if ( ! $wp_filesystem->exists( $export_dir ) ) {
			wp_mkdir_p( $export_dir ); // wp_mkdir_p() is already a WordPress function

			// Create an index.php file for security
			$index_file = trailingslashit( $export_dir ) . 'index.php';
			if ( ! $wp_filesystem->exists( $index_file ) ) {
				$wp_filesystem->put_contents( $index_file, '<?php // Silence is golden' );
			}

			// Create .htaccess to prevent direct access to XML files
			$htaccess = trailingslashit( $export_dir ) . '.htaccess';
			if ( ! $wp_filesystem->exists( $htaccess ) ) {
				$htaccess_content = "Options -Indexes\n";
				$htaccess_content .= "<Files *.xml>\n";
				$htaccess_content .= "Order Allow,Deny\n";
				$htaccess_content .= "Deny from all\n";
				$htaccess_content .= "</Files>\n";
				$wp_filesystem->put_contents( $htaccess, $htaccess_content );
			}
		}

		// Switch back to the original site
		restore_current_blog();

		return $export_dir;
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

			// Get batch ID from export args
			$batch_id = isset( $export_args[ 'batch_id' ] ) ? $export_args[ 'batch_id' ] : '';

			// Update progress before starting the export
			if ( ! empty( $batch_id ) ) {
				$this->update_export_progress( $batch_id, $site_name );
			}

			// Get common export directory
			$export_dir = $this->get_export_directory();
			$file_name  = 'export-blog-' . $blog_id . '-' . $site_name . '-' . date( 'Ymd-His' ) . '.xml';
			$file_path  = trailingslashit( $export_dir ) . $file_name;

			// Generate export content with our custom function
			$exporter       = ME_Export::instance();
			$export_content = $exporter->generate_export( $export_args );

			// Initialize WordPress filesystem
			global $wp_filesystem;

			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			// Initialize the filesystem
			if ( ! WP_Filesystem() ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'Multisite Exporter: Unable to initialize WordPress filesystem for export' );
				}
				restore_current_blog();
				return;
			}

			// Save to file in the common export directory using WordPress filesystem
			if ( $wp_filesystem->put_contents( $file_path, $export_content, FS_CHMOD_FILE ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( "Export complete for Blog ID: $blog_id | File: $file_path" );
				}

				// Store export information in a transient for the admin interface
				$exports   = get_site_transient( 'multisite_exports' ) ?: array();
				$exports[] = array(
					'blog_id'   => $blog_id,
					'site_name' => $site_name,
					'file_name' => $file_name,
					'file_path' => $file_path,
					'date'      => current_time( 'mysql' ),
					'batch_id'  => $batch_id,
				);
				set_site_transient( 'multisite_exports', $exports, WEEK_IN_SECONDS );

				// Final progress update after export is complete
				if ( ! empty( $batch_id ) ) {
					$this->update_export_progress( $batch_id, $site_name, true );
				}
			} else {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( "Failed to write export file for Blog ID: $blog_id" );
				}
			}
		} catch (Exception $e) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "Export error for Blog ID: $blog_id | Error: " . $e->getMessage() );
			}
		}

		// Restore to the previous blog
		restore_current_blog();
	}

	/**
	 * Schedule exports for all subsites.
	 *
	 * @param array $export_args Export arguments.
	 */
	public function schedule_exports( $export_args = array() ) {
		$sites = get_sites( array( 'number' => 0 ) );

		// Delete old export files before starting new exports
		$this->cleanup_old_exports();

		// Generate a unique batch ID for this export run
		$batch_id    = 'batch_' . uniqid();
		$total_sites = count( $sites );

		// Store initial progress data
		$progress_data = array(
			'batch_id'        => $batch_id,
			'total_sites'     => $total_sites,
			'sites_processed' => 0,
			'percentage'      => 0,
			'current_site'    => '',
			'start_time'      => time(),
			'status'          => 'in_progress',
		);
		update_network_option( get_main_network_id(), 'multisite_exporter_progress_' . $batch_id, $progress_data );

		// Add export arguments to each scheduled action
		$export_args[ 'batch_id' ] = $batch_id;

		foreach ( $sites as $site ) {
			as_schedule_single_action(
				time(),
				'me_process_site_export',
				array( $site->blog_id, $export_args )
			);
		}
	}

	/**
	 * Clean up old export files
	 * 
	 * Removes all export files from the filesystem and updates the transient
	 */
	private function cleanup_old_exports() {
		// Get all exports from the transient
		$all_exports = get_site_transient( 'multisite_exports' ) ?: array();

		// Get the export directory
		$export_dir = $this->get_export_directory();

		// Initialize WordPress filesystem
		global $wp_filesystem;

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		// Initialize the filesystem
		if ( ! WP_Filesystem() ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Multisite Exporter: Unable to initialize WordPress filesystem for cleanup' );
			}
			return;
		}

		// Delete all export files
		$deleted_count = 0;

		// Go through all exports and delete all files
		foreach ( $all_exports as $export ) {
			$file_name = $export[ 'file_name' ];
			$file_path = trailingslashit( $export_dir ) . $file_name;

			// Delete the file using WordPress filesystem
			if ( $wp_filesystem->exists( $file_path ) ) {
				if ( $wp_filesystem->delete( $file_path ) ) {
					$deleted_count++;
				}
			}
		}

		// Look for any XML files that might not be in the transient but still exist in the directory
		$files = $wp_filesystem->dirlist( $export_dir );
		if ( is_array( $files ) ) {
			foreach ( $files as $file ) {
				// Only process XML files that match our export file pattern
				if ( isset( $file[ 'name' ] ) && preg_match( '/^export-blog-.*\.xml$/', $file[ 'name' ] ) ) {
					$file_path = trailingslashit( $export_dir ) . $file[ 'name' ];
					if ( $wp_filesystem->delete( $file_path ) ) {
						$deleted_count++;
					}
				}
			}
		}

		// Clear the transient entirely
		delete_site_transient( 'multisite_exports' );

		// Log the cleanup
		if ( $deleted_count > 0 && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "Multisite Exporter: Removed all {$deleted_count} export files before starting new exports." );
		}
	}

	/**
	 * Update the export progress for a batch
	 * 
	 * @param string $batch_id    The unique batch ID.
	 * @param string $site_name   The current site being exported.
	 * @param bool   $completed   Whether this site's export is completed.
	 */
	private function update_export_progress( $batch_id, $site_name, $completed = false ) {
		// Get current progress data
		$progress_data = get_network_option( get_main_network_id(), 'multisite_exporter_progress_' . $batch_id, array() );

		if ( empty( $progress_data ) ) {
			return;
		}

		// Update sites processed count if site export is completed
		if ( $completed ) {
			$progress_data[ 'sites_processed' ] = isset( $progress_data[ 'sites_processed' ] ) ?
				$progress_data[ 'sites_processed' ] + 1 : 1;
		}

		// Calculate percentage
		if ( isset( $progress_data[ 'sites_processed' ] ) && isset( $progress_data[ 'total_sites' ] ) && $progress_data[ 'total_sites' ] > 0 ) {
			$progress_data[ 'percentage' ] = round( ( $progress_data[ 'sites_processed' ] / $progress_data[ 'total_sites' ] ) * 100 );
		}

		// Update current site
		$progress_data[ 'current_site' ] = $site_name;

		// If all sites processed, mark as completed
		if ( isset( $progress_data[ 'sites_processed' ] ) && isset( $progress_data[ 'total_sites' ] ) &&
			$progress_data[ 'sites_processed' ] >= $progress_data[ 'total_sites' ] ) {
			$progress_data[ 'status' ]     = 'completed';
			$progress_data[ 'percentage' ] = 100;
			$progress_data[ 'end_time' ]   = time();

			// Add to completed batches list
			$completed_batches   = get_network_option( get_main_network_id(), 'multisite_exporter_completed_batches', array() );
			$completed_batches[] = $batch_id;
			update_network_option( get_main_network_id(), 'multisite_exporter_completed_batches', $completed_batches );
		}

		// Save updated progress data
		update_network_option( get_main_network_id(), 'multisite_exporter_progress_' . $batch_id, $progress_data );

		// Trigger action for other components to hook into
		do_action( 'me_export_progress_update', $batch_id, $site_name, $progress_data[ 'percentage' ] );
	}
}