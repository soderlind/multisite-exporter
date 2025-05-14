<?php
/**
 * Multisite Exporter WP-CLI Package
 *
 * @package Multisite_Exporter
 * @since 1.2.3
 */

if ( ! defined( 'WP_CLI' ) ) {
	return;
}

// Register a simple help command that will direct users to run the command with --help
$multisite_exporter_help = function() {
	WP_CLI::line( 'Multisite Exporter CLI Commands:' );
	WP_CLI::line( '' );
	WP_CLI::line( 'Usage: wp multisite-exporter export [--site_ids=<ids>] [--content=<content_types>] [--start_date=<date>] [--end_date=<date>]' );
	WP_CLI::line( '' );
	WP_CLI::line( 'For more details, use: wp help multisite-exporter export' );
};

WP_CLI::add_command( 'multisite-exporter', $multisite_exporter_help );

// Only add the export command if we're not already in the context of the full plugin
if ( ! class_exists( 'ME_CLI_Command' ) ) {
	// Define required constants if they don't exist
	if ( ! defined( 'MULTISITE_EXPORTER_PLUGIN_DIR' ) ) {
		define( 'MULTISITE_EXPORTER_PLUGIN_DIR', __DIR__ . '/' );
	}
	
	if ( ! defined( 'MULTISITE_EXPORTER_VERSION' ) ) {
		define( 'MULTISITE_EXPORTER_VERSION', '1.2.3' );
	}
	
	if ( ! defined( 'MULTISITE_EXPORTER_PLUGIN_URL' ) ) {
		define( 'MULTISITE_EXPORTER_PLUGIN_URL', '' );
	}
	
	if ( ! defined( 'MULTISITE_EXPORTER_PLUGIN_FILE' ) ) {
		define( 'MULTISITE_EXPORTER_PLUGIN_FILE', __FILE__ );
	}
		/**
		 * Exports content from one or more sites in a multisite network.
		 *
		 * ## OPTIONS
		 *
		 * [--site_ids=<ids>]
		 * : Comma-separated list of site IDs to export. Default is all sites.
		 *
		 * [--content=<content_types>]
		 * : Comma-separated list of content types to export: all, posts, pages, media. Default is 'all'.
		 * 
		 * [--start_date=<date>]
		 * : Optional start date for filtering content in YYYY-MM-DD format.
		 *
		 * [--end_date=<date>]
		 * : Optional end date for filtering content in YYYY-MM-DD format.
		 * 
		 * ## EXAMPLES
		 *
		 *     # Export all content from all sites
		 *     $ wp multisite-exporter export
		 *
		 *     # Export only posts and pages from sites with IDs 1, 2, and 3
		 *     $ wp multisite-exporter export --site_ids=1,2,3 --content=posts,pages
		 *
		 * @param array $args       Positional arguments.
		 * @param array $assoc_args Associative arguments.
		 */
		public function export( $args, $assoc_args ) {
			// Check if we're running in a multisite environment
			if ( ! is_multisite() ) {
				WP_CLI::error( 'This command can only be run on a multisite installation.' );
				return;
			}
			
			// Parse arguments
			$site_ids      = isset( $assoc_args['site_ids'] ) ? explode( ',', $assoc_args['site_ids'] ) : array();
			$content_types = isset( $assoc_args['content'] ) ? explode( ',', $assoc_args['content'] ) : array( 'all' );
			$start_date    = isset( $assoc_args['start_date'] ) ? $assoc_args['start_date'] : null;
			$end_date      = isset( $assoc_args['end_date'] ) ? $assoc_args['end_date'] : null;
			
			// Validate content types
			$valid_content_types = array( 'all', 'posts', 'pages', 'media' );
			foreach ( $content_types as $type ) {
				if ( ! in_array( $type, $valid_content_types, true ) ) {
					WP_CLI::error( sprintf( 'Invalid content type: %s. Valid types are: %s', $type, implode( ', ', $valid_content_types ) ) );
					return;
				}
			}
			
			// Get sites to export
			$sites = $this->get_sites_to_export($site_ids);
			if (empty($sites)) {
				return;
			}
			
			WP_CLI::line( sprintf( 'Starting export for %d sites...', count( $sites ) ) );
			
			// Set up the export
			$this->perform_export($sites, $content_types, $start_date, $end_date);
		}
		
		/**
		 * Get sites to export based on provided IDs
		 * 
		 * @param array $site_ids Site IDs to export
		 * @return array Sites to export
		 */
		private function get_sites_to_export($site_ids) {
			// Get all sites or specific sites
			if ( empty( $site_ids ) ) {
				$sites = get_sites( array( 'number' => 0 ) );
			} else {
				$sites = array();
				foreach ( $site_ids as $site_id ) {
					$site = get_site( $site_id );
					if ( $site ) {
						$sites[] = $site;
					} else {
						WP_CLI::warning( sprintf( 'Site with ID %s not found.', $site_id ) );
					}
				}
				
				if ( empty( $sites ) ) {
					WP_CLI::error( 'No valid sites found with the provided IDs.' );
					return array();
				}
			}
			
			return $sites;
		}
		
		/**
		 * Perform the export of sites
		 * 
		 * @param array $sites Sites to export
		 * @param array $content_types Content types to export
		 * @param string $start_date Start date for filtering
		 * @param string $end_date End date for filtering
		 */
		private function perform_export($sites, $content_types, $start_date, $end_date) {
			// Set up progress bar
			$progress = \WP_CLI\Utils\make_progress_bar( 'Exporting sites', count( $sites ) );
			$timestamp = date('Ymd-His');
			
			// Create a temporary directory for exports
			$tmp_dir = get_temp_dir() . 'multisite-export-' . $timestamp;
			if (!file_exists($tmp_dir)) {
				mkdir($tmp_dir, 0755, true);
			}
			
			// Array to store export file paths
			$export_files = array();
			
			// Process each site
			foreach ( $sites as $site ) {
				$blog_id = $site->blog_id;
				$progress->tick();
				
				// Switch to the site
				switch_to_blog( $blog_id );
				$blog_name = get_bloginfo( 'name' );
				$blog_url = get_bloginfo( 'url' );
				
				WP_CLI::log( sprintf( 'Exporting site %d: %s (%s)', $blog_id, $blog_name, $blog_url ) );
				
				// Prepare export arguments
				$export_args = array();
				
				// Handle content type filtering
				if ( ! in_array( 'all', $content_types, true ) ) {
					if ( in_array( 'posts', $content_types, true ) ) {
						$export_args['content'] = 'post';
					}
					if ( in_array( 'pages', $content_types, true ) ) {
						if ( isset( $export_args['content'] ) ) {
							$export_args['content'] .= ',page';
						} else {
							$export_args['content'] = 'page';
						}
					}
					if ( in_array( 'media', $content_types, true ) ) {
						$export_args['content'] = isset( $export_args['content'] ) ? $export_args['content'] . ',attachment' : 'attachment';
					}
				}
				
				// Handle date filtering
				if ( $start_date ) {
					$export_args['start_date'] = $start_date;
				}
				if ( $end_date ) {
					$export_args['end_date'] = $end_date;
				}
				
				// Get the export content
				ob_start();
				export_wp( $export_args );
				$export_content = ob_get_clean();
				
				// Create export filename
				$filename = sanitize_file_name( $blog_name . '-' . $blog_id . '-' . date( 'Ymd' ) . '.xml' );
				$export_file = $tmp_dir . '/' . $filename;
				
				// Save the export file
				file_put_contents( $export_file, $export_content );
				$export_files[] = $export_file;
				
				WP_CLI::log( sprintf( 'Export saved for site %d', $blog_id ) );
				
				// Restore the current blog
				restore_current_blog();
			}
			
			$progress->finish();
			
			// Create a ZIP file if multiple sites were exported
			if ( count( $export_files ) > 1 ) {
				$zip_file = getcwd() . '/multisite-export-' . $timestamp . '.zip';
				$this->create_zip_archive( $export_files, $zip_file );
				WP_CLI::success( sprintf( 'Successfully exported %d sites to %s', count( $sites ), $zip_file ) );
				
				// Clean up individual files
				foreach ( $export_files as $file ) {
					unlink( $file );
				}
				rmdir( $tmp_dir );
			} else if ( count( $export_files ) === 1 ) {
				// Just move the single file to the current directory
				$dest_file = getcwd() . '/' . basename( $export_files[0] );
				rename( $export_files[0], $dest_file );
				rmdir( $tmp_dir );
				WP_CLI::success( sprintf( 'Successfully exported site to %s', $dest_file ) );
			}
		}
		
		/**
		 * Create a ZIP archive of export files
		 *
		 * @param array $files Array of file paths to include in the ZIP
		 * @param string $destination Path to the ZIP file to create
		 * @return bool True on success, false on failure
		 */
		private function create_zip_archive( $files, $destination ) {
			if ( ! class_exists( 'ZipArchive' ) ) {
				WP_CLI::error( 'ZipArchive class is not available on this system.' );
				return false;
			}
			
			$zip = new ZipArchive();
			if ( $zip->open( $destination, ZipArchive::CREATE ) !== true ) {
				WP_CLI::error( 'Could not create ZIP archive.' );
				return false;
			}
			
			foreach ( $files as $file ) {
				if ( file_exists( $file ) ) {
					$zip->addFile( $file, basename( $file ) );
				}
			}
			
			$zip->close();
			return true;
		}
	}

	// Register the command
	WP_CLI::add_command( 'multisite-exporter', 'ME_CLI_Command' );
}
