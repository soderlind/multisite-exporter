<?php
/**
 * WP CLI Commands for Multisite Exporter
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
 * Multisite Exporter CLI commands.
 */
class ME_CLI_Command extends WP_CLI_Command {

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
	 *     # Export all content from all sites created after a specific date
	 *     $ wp multisite-exporter export --start_date=2023-01-01
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
		$site_ids      = isset( $assoc_args[ 'site_ids' ] ) ? explode( ',', $assoc_args[ 'site_ids' ] ) : array();
		$content_types = isset( $assoc_args[ 'content' ] ) ? explode( ',', $assoc_args[ 'content' ] ) : array( 'all' );
		$start_date    = isset( $assoc_args[ 'start_date' ] ) ? $assoc_args[ 'start_date' ] : null;
		$end_date      = isset( $assoc_args[ 'end_date' ] ) ? $assoc_args[ 'end_date' ] : null;

		// Validate content types
		$valid_content_types = array( 'all', 'posts', 'pages', 'media' );
		foreach ( $content_types as $type ) {
			if ( ! in_array( $type, $valid_content_types, true ) ) {
				WP_CLI::error( sprintf( 'Invalid content type: %s. Valid types are: %s', $type, implode( ', ', $valid_content_types ) ) );
				return;
			}
		}

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
				return;
			}
		}

		// Set up export arguments
		$export_args = array(
			'content'    => $content_types,
			'start_date' => $start_date,
			'end_date'   => $end_date,
		);

		// Create directory to store exports if not exists
		$init       = ME_Init::instance();
		$export_dir = $init->get_export_directory();

		WP_CLI::log( sprintf( 'Exporting content from %d site(s)...', count( $sites ) ) );

		// Set up a progress bar
		$progress = \WP_CLI\Utils\make_progress_bar( 'Exporting sites', count( $sites ) );

		$export_files = array();

		// Process each site
		foreach ( $sites as $site ) {
			$blog_id = $site->blog_id;

			// Switch to the site being exported
			switch_to_blog( $blog_id );

			// Get site information
			$site_name           = get_bloginfo( 'name' );
			$sanitized_site_name = sanitize_title( $site_name );

			WP_CLI::log( sprintf( 'Exporting %s (ID: %d)...', $site_name, $blog_id ) );

			// Generate export file name
			$file_name = 'export-blog-' . $blog_id . '-' . $sanitized_site_name . '-' . date( 'Ymd-His' ) . '.xml';
			$file_path = trailingslashit( $export_dir ) . $file_name;

			// Generate export content
			$exporter       = ME_Export::instance();
			$export_content = $exporter->generate_export( $export_args );

			// Save export file
			global $wp_filesystem;

			// Initialize filesystem if needed
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			if ( ! WP_Filesystem() ) {
				WP_CLI::error( 'Unable to initialize WordPress filesystem.' );
				restore_current_blog();
				return;
			}

			if ( ! $wp_filesystem->put_contents( $file_path, $export_content, FS_CHMOD_FILE ) ) {
				WP_CLI::error( sprintf( 'Failed to save export file for site %s (ID: %d).', $site_name, $blog_id ) );
				restore_current_blog();
				continue;
			}

			// Add file to our list of exports
			$export_files[] = array(
				'blog_id'   => $blog_id,
				'site_name' => $site_name,
				'file_path' => $file_path,
				'file_name' => $file_name,
			);

			// Restore to the previous blog
			restore_current_blog();

			// Advance the progress bar
			$progress->tick();
		}

		$progress->finish();

		// Check if we have any successful exports
		if ( empty( $export_files ) ) {
			WP_CLI::error( 'No exports were generated.' );
			return;
		}

		// Handle outputs based on the number of sites
		if ( count( $export_files ) === 1 ) {
			// Single site export - copy file to current directory
			$current_dir = getcwd();
			$destination = trailingslashit( $current_dir ) . $export_files[ 0 ][ 'file_name' ];

			if ( ! copy( $export_files[ 0 ][ 'file_path' ], $destination ) ) {
				WP_CLI::error( 'Failed to save export file to the current directory.' );
				return;
			}

			WP_CLI::success( sprintf( 'Export successful! File saved to: %s', $destination ) );
		} else {
			// Multiple sites - create zip file
			$current_dir  = getcwd();
			$zip_filename = 'multisite-export-' . date( 'Ymd-His' ) . '.zip';
			$zip_path     = trailingslashit( $current_dir ) . $zip_filename;

			WP_CLI::log( 'Creating zip archive...' );

			$zip = new ZipArchive();
			if ( $zip->open( $zip_path, ZipArchive::CREATE ) !== true ) {
				WP_CLI::error( 'Failed to create ZIP file.' );
				return;
			}

			// Add files to the zip
			foreach ( $export_files as $export ) {
				if ( file_exists( $export[ 'file_path' ] ) ) {
					$zip->addFile( $export[ 'file_path' ], $export[ 'file_name' ] );
				} else {
					WP_CLI::warning( sprintf( 'File not found: %s', $export[ 'file_path' ] ) );
				}
			}

			$zip->close();

			if ( ! file_exists( $zip_path ) ) {
				WP_CLI::error( 'Failed to create ZIP file.' );
				return;
			}

			WP_CLI::success( sprintf( 'Export successful! %d sites exported to: %s', count( $export_files ), $zip_path ) );
		}
	}
}
