<?php
/*
Plugin Name: Multisite Exporter
Description: Runs WordPress Exporter on each subsite in a multisite, in the background.
Version: 1.0
Author: Per Søderlind
Author URI: https://soderlind.no
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: multisite-exporter
Domain Path: /languages
Network: true
*/

// Initialize Action Scheduler
if ( file_exists( __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php' ) ) {
	require_once __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php';
}

// Check multisite.
if ( ! is_multisite() ) {
	add_action( 'admin_notices', function () {
		echo '<div class="error"><p>' . esc_html__( 'Multisite Exporter only works on multisite installations.', 'multisite-exporter' ) . '</p></div>';
	} );
	return;
}

/**
 * Load plugin text domain for translations
 */
function me_load_textdomain() {
	load_plugin_textdomain( 'multisite-exporter', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'me_load_textdomain', 9 );

// Register admin hooks
function me_register_admin_hooks() {
	// Check if Action Scheduler is available after initialization.
	if ( ! class_exists( 'ActionScheduler' ) ) {
		add_action( 'network_admin_notices', function () {
			echo '<div class="error"><p>' . esc_html__( 'Action Scheduler is required for Multisite Exporter to work. Please run composer install.', 'multisite-exporter' ) . '</p></div>';
		} );
		return;
	}

	// Add menu only if Action Scheduler is available
	add_action( 'network_admin_menu', 'me_add_admin_menu' );

	// Register plugin styles
	add_action( 'admin_enqueue_scripts', 'me_enqueue_admin_styles' );
}
add_action( 'plugins_loaded', 'me_register_admin_hooks', 10 );

/**
 * Enqueue admin styles for the plugin
 */
function me_enqueue_admin_styles( $hook ) {
	// Only load the CSS on our plugin pages
	if ( strpos( $hook, 'multisite-exporter' ) === false ) {
		return;
	}

	wp_enqueue_style(
		'multisite-exporter-styles',
		plugin_dir_url( __FILE__ ) . 'css/multisite-exporter.css',
		array(),
		filemtime( plugin_dir_path( __FILE__ ) . 'css/multisite-exporter.css' )
	);
}

/**
 * Get export directory path for saving all exports in one common location
 * 
 * Creates a directory in the main site's uploads folder if it doesn't exist
 *
 * @return string Path to the export directory
 */
function me_get_export_directory() {
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
 */
function me_schedule_exports( $export_args = [] ) {
	$sites = get_sites( [ 'number' => 0 ] );

	foreach ( $sites as $site ) {
		as_schedule_single_action(
			time(),
			'me_process_site_export',
			[ $site->blog_id, $export_args ]
		);
	}
}

/**
 * Export callback.
 */
add_action( 'me_process_site_export', 'me_process_site_export_callback', 10, 2 );

/**
 * Custom XML CDATA wrapper for our own export
 */
function me_wxr_cdata( $str ) {
	if ( ! seems_utf8( $str ) ) {
		$str = utf8_encode( $str );
	}
	$str = '<![CDATA[' . str_replace( ']]>', ']]]]><![CDATA[>', $str ) . ']]>';
	return $str;
}

/**
 * Generate a custom WXR export without using WordPress core export functions
 * This avoids the headers and function redeclaration issues
 */
function me_generate_export( $args = [] ) {
	global $wpdb, $post;

	// Set default arguments
	$defaults = [ 
		'content'    => 'all',
		'author'     => false,
		'category'   => false,
		'start_date' => false,
		'end_date'   => false,
		'status'     => false,
		'post_type'  => '',
	];
	$args     = wp_parse_args( $args, $defaults );

	// Start XML output
	$output = '<?xml version="1.0" encoding="' . get_bloginfo( 'charset' ) . "\" ?>\n";
	$output .= "<!-- This is a WordPress eXtended RSS file generated by Multisite Exporter plugin -->\n";
	$output .= '<rss version="2.0" xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/" ';
	$output .= 'xmlns:content="http://purl.org/rss/1.0/modules/content/" ';
	$output .= 'xmlns:wfw="http://wellformedweb.org/CommentAPI/" ';
	$output .= 'xmlns:dc="http://purl.org/dc/elements/1.1/" ';
	$output .= 'xmlns:wp="http://wordpress.org/export/1.2/">';
	$output .= "\n<channel>\n";

	// Add site info
	$output .= '<title>' . me_wxr_cdata( get_bloginfo( 'name' ) ) . '</title>';
	$output .= '<link>' . get_bloginfo( 'url' ) . '</link>';
	$output .= '<description>' . me_wxr_cdata( get_bloginfo( 'description' ) ) . '</description>';
	$output .= '<pubDate>' . gmdate( 'D, d M Y H:i:s +0000' ) . '</pubDate>';
	$output .= '<language>' . me_wxr_cdata( get_bloginfo( 'language' ) ) . '</language>';
	$output .= '<wp:wxr_version>1.2</wp:wxr_version>';
	$output .= '<wp:base_site_url>' . ( is_multisite() ? network_home_url() : get_bloginfo( 'url' ) ) . '</wp:base_site_url>';
	$output .= '<wp:base_blog_url>' . get_bloginfo( 'url' ) . '</wp:base_blog_url>';

	// Build post query
	$where = "post_status != 'auto-draft'";
	$join  = "";

	if ( 'all' !== $args[ 'content' ] && post_type_exists( $args[ 'content' ] ) ) {
		$ptype = get_post_type_object( $args[ 'content' ] );
		if ( ! $ptype->can_export ) {
			$args[ 'content' ] = 'post';
		}
		$where .= $wpdb->prepare( " AND post_type = %s", $args[ 'content' ] );
	} else if ( $args[ 'post_type' ] ) {
		$where .= $wpdb->prepare( " AND post_type = %s", $args[ 'post_type' ] );
	} else {
		$post_types   = get_post_types( [ 'can_export' => true ] );
		$placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
		$where .= $wpdb->prepare( " AND post_type IN ($placeholders)", $post_types );
	}

	// Filter by status
	if ( $args[ 'status' ] && ( 'post' === $args[ 'content' ] || 'page' === $args[ 'content' ] ) ) {
		$where .= $wpdb->prepare( " AND post_status = %s", $args[ 'status' ] );
	}

	// Filter by category
	if ( $args[ 'category' ] && 'post' === $args[ 'content' ] ) {
		$term = term_exists( $args[ 'category' ], 'category' );
		if ( $term ) {
			$join  = "INNER JOIN {$wpdb->term_relationships} ON ({$wpdb->posts}.ID = {$wpdb->term_relationships}.object_id)";
			$where .= $wpdb->prepare( " AND {$wpdb->term_relationships}.term_taxonomy_id = %d", $term[ 'term_taxonomy_id' ] );
		}
	}

	// Filter by author
	if ( $args[ 'author' ] ) {
		$where .= $wpdb->prepare( " AND post_author = %d", $args[ 'author' ] );
	}

	// Filter by date
	if ( $args[ 'start_date' ] ) {
		$where .= $wpdb->prepare( " AND post_date >= %s", gmdate( 'Y-m-d', strtotime( $args[ 'start_date' ] ) ) );
	}

	if ( $args[ 'end_date' ] ) {
		$where .= $wpdb->prepare( " AND post_date < %s", gmdate( 'Y-m-d', strtotime( '+1 month', strtotime( $args[ 'end_date' ] ) ) ) );
	}

	// Get posts
	$posts = $wpdb->get_results( "SELECT * FROM {$wpdb->posts} $join WHERE $where ORDER BY post_date_gmt ASC" );

	// Get authors
	$authors = [];
	foreach ( $posts as $post ) {
		$authors[ $post->post_author ] = get_userdata( $post->post_author );
	}

	// Add authors to XML
	foreach ( $authors as $author ) {
		if ( ! $author )
			continue;

		$output .= "\t<wp:author>";
		$output .= '<wp:author_id>' . $author->ID . '</wp:author_id>';
		$output .= '<wp:author_login>' . me_wxr_cdata( $author->user_login ) . '</wp:author_login>';
		$output .= '<wp:author_email>' . me_wxr_cdata( $author->user_email ) . '</wp:author_email>';
		$output .= '<wp:author_display_name>' . me_wxr_cdata( $author->display_name ) . '</wp:author_display_name>';
		$output .= '<wp:author_first_name>' . me_wxr_cdata( $author->first_name ) . '</wp:author_first_name>';
		$output .= '<wp:author_last_name>' . me_wxr_cdata( $author->last_name ) . '</wp:author_last_name>';
		$output .= "</wp:author>\n";
	}

	// Add categories
	$categories = get_categories( [ 'get' => 'all' ] );
	foreach ( $categories as $category ) {
		$output .= "\t<wp:category>";
		$output .= '<wp:term_id>' . $category->term_id . '</wp:term_id>';
		$output .= '<wp:category_nicename>' . me_wxr_cdata( $category->slug ) . '</wp:category_nicename>';
		$output .= '<wp:category_parent>' . me_wxr_cdata( $category->parent ? get_category( $category->parent )->slug : '' ) . '</wp:category_parent>';
		$output .= '<wp:cat_name>' . me_wxr_cdata( $category->name ) . '</wp:cat_name>';
		$output .= '<wp:category_description>' . me_wxr_cdata( $category->description ) . '</wp:category_description>';
		$output .= "</wp:category>\n";
	}

	// Add tags
	$tags = get_tags( [ 'get' => 'all' ] );
	foreach ( $tags as $tag ) {
		$output .= "\t<wp:tag>";
		$output .= '<wp:term_id>' . $tag->term_id . '</wp:term_id>';
		$output .= '<wp:tag_slug>' . me_wxr_cdata( $tag->slug ) . '</wp:tag_slug>';
		$output .= '<wp:tag_name>' . me_wxr_cdata( $tag->name ) . '</wp:tag_name>';
		$output .= '<wp:tag_description>' . me_wxr_cdata( $tag->description ) . '</wp:tag_description>';
		$output .= "</wp:tag>\n";
	}

	// Add posts
	foreach ( $posts as $post ) {
		setup_postdata( $post );

		$output .= "\t<item>\n";
		$output .= '<title>' . me_wxr_cdata( get_the_title( $post->ID ) ) . '</title>';
		$output .= '<link>' . get_permalink( $post->ID ) . '</link>';
		$output .= '<pubDate>' . mysql2date( 'D, d M Y H:i:s +0000', get_post_time( 'Y-m-d H:i:s', true, $post->ID ), false ) . '</pubDate>';
		$output .= '<dc:creator>' . me_wxr_cdata( get_the_author_meta( 'login', $post->post_author ) ) . '</dc:creator>';
		$output .= '<guid isPermaLink="false">' . get_the_guid( $post->ID ) . '</guid>';
		$output .= '<description></description>';
		$output .= '<content:encoded>' . me_wxr_cdata( $post->post_content ) . '</content:encoded>';
		$output .= '<excerpt:encoded>' . me_wxr_cdata( $post->post_excerpt ) . '</excerpt:encoded>';
		$output .= '<wp:post_id>' . $post->ID . '</wp:post_id>';
		$output .= '<wp:post_date>' . me_wxr_cdata( $post->post_date ) . '</wp:post_date>';
		$output .= '<wp:post_date_gmt>' . me_wxr_cdata( $post->post_date_gmt ) . '</wp:post_date_gmt>';
		$output .= '<wp:post_modified>' . me_wxr_cdata( $post->post_modified ) . '</wp:post_modified>';
		$output .= '<wp:post_modified_gmt>' . me_wxr_cdata( $post->post_modified_gmt ) . '</wp:post_modified_gmt>';
		$output .= '<wp:comment_status>' . me_wxr_cdata( $post->comment_status ) . '</wp:comment_status>';
		$output .= '<wp:ping_status>' . me_wxr_cdata( $post->ping_status ) . '</wp:ping_status>';
		$output .= '<wp:post_name>' . me_wxr_cdata( $post->post_name ) . '</wp:post_name>';
		$output .= '<wp:status>' . me_wxr_cdata( $post->post_status ) . '</wp:status>';
		$output .= '<wp:post_parent>' . $post->post_parent . '</wp:post_parent>';
		$output .= '<wp:menu_order>' . $post->menu_order . '</wp:menu_order>';
		$output .= '<wp:post_type>' . me_wxr_cdata( $post->post_type ) . '</wp:post_type>';
		$output .= '<wp:post_password>' . me_wxr_cdata( $post->post_password ) . '</wp:post_password>';
		$output .= '<wp:is_sticky>' . ( is_sticky( $post->ID ) ? 1 : 0 ) . '</wp:is_sticky>';

		if ( 'attachment' === $post->post_type ) {
			$output .= '<wp:attachment_url>' . me_wxr_cdata( wp_get_attachment_url( $post->ID ) ) . '</wp:attachment_url>';
		}

		// Add post terms
		$taxonomies = get_object_taxonomies( $post->post_type );
		if ( ! empty( $taxonomies ) ) {
			$terms = wp_get_object_terms( $post->ID, $taxonomies );
			foreach ( $terms as $term ) {
				$output .= "\t\t<category domain=\"{$term->taxonomy}\" nicename=\"{$term->slug}\">" . me_wxr_cdata( $term->name ) . "</category>\n";
			}
		}

		// Add postmeta
		$postmeta = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->postmeta WHERE post_id = %d", $post->ID ) );
		foreach ( $postmeta as $meta ) {
			if ( '_edit_lock' === $meta->meta_key ) {
				continue;
			}
			$output .= "\t\t<wp:postmeta>\n";
			$output .= "\t\t\t<wp:meta_key>" . me_wxr_cdata( $meta->meta_key ) . "</wp:meta_key>\n";
			$output .= "\t\t\t<wp:meta_value>" . me_wxr_cdata( $meta->meta_value ) . "</wp:meta_value>\n";
			$output .= "\t\t</wp:postmeta>\n";
		}

		// Add comments
		$comments = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_approved <> 'spam'", $post->ID ) );
		foreach ( $comments as $comment ) {
			$output .= "\t\t<wp:comment>\n";
			$output .= "\t\t\t<wp:comment_id>" . $comment->comment_ID . "</wp:comment_id>\n";
			$output .= "\t\t\t<wp:comment_author>" . me_wxr_cdata( $comment->comment_author ) . "</wp:comment_author>\n";
			$output .= "\t\t\t<wp:comment_author_email>" . me_wxr_cdata( $comment->comment_author_email ) . "</wp:comment_author_email>\n";
			$output .= "\t\t\t<wp:comment_author_url>" . esc_url_raw( $comment->comment_author_url ) . "</wp:comment_author_url>\n";
			$output .= "\t\t\t<wp:comment_author_IP>" . me_wxr_cdata( $comment->comment_author_IP ) . "</wp:comment_author_IP>\n";
			$output .= "\t\t\t<wp:comment_date>" . me_wxr_cdata( $comment->comment_date ) . "</wp:comment_date>\n";
			$output .= "\t\t\t<wp:comment_date_gmt>" . me_wxr_cdata( $comment->comment_date_gmt ) . "</wp:comment_date_gmt>\n";
			$output .= "\t\t\t<wp:comment_content>" . me_wxr_cdata( $comment->comment_content ) . "</wp:comment_content>\n";
			$output .= "\t\t\t<wp:comment_approved>" . me_wxr_cdata( $comment->comment_approved ) . "</wp:comment_approved>\n";
			$output .= "\t\t\t<wp:comment_type>" . me_wxr_cdata( $comment->comment_type ) . "</wp:comment_type>\n";
			$output .= "\t\t\t<wp:comment_parent>" . $comment->comment_parent . "</wp:comment_parent>\n";
			$output .= "\t\t\t<wp:comment_user_id>" . $comment->user_id . "</wp:comment_user_id>\n";

			// Comment meta
			$commentmeta = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->commentmeta WHERE comment_id = %d", $comment->comment_ID ) );
			foreach ( $commentmeta as $meta ) {
				$output .= "\t\t\t<wp:commentmeta>\n";
				$output .= "\t\t\t\t<wp:meta_key>" . me_wxr_cdata( $meta->meta_key ) . "</wp:meta_key>\n";
				$output .= "\t\t\t\t<wp:meta_value>" . me_wxr_cdata( $meta->meta_value ) . "</wp:meta_value>\n";
				$output .= "\t\t\t</wp:commentmeta>\n";
			}

			$output .= "\t\t</wp:comment>\n";
		}

		$output .= "\t</item>\n";
	}

	$output .= "</channel>\n";
	$output .= "</rss>";

	return $output;
}

function me_process_site_export_callback( $blog_id, $export_args ) {
	try {
		// Switch to the site being exported
		switch_to_blog( $blog_id );

		// Get site information for the filename
		$site_name = get_bloginfo( 'name' );
		$site_name = sanitize_title( $site_name );

		// Get common export directory
		$export_dir = me_get_export_directory();
		$file_name  = 'export-blog-' . $blog_id . '-' . $site_name . '-' . date( 'Ymd-His' ) . '.xml';
		$file_path  = trailingslashit( $export_dir ) . $file_name;

		// Generate export content with our custom function
		$export_content = me_generate_export( $export_args );

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

/**
 * Add top-level admin menu for Multisite Exporter.
 */
function me_add_admin_menu() {
	add_menu_page(
		'Multisite Exporter',   // Page title
		'MS Exporter',          // Menu title (shorter for menu space)
		'manage_network',       // Capability
		'multisite-exporter',   // Menu slug
		'me_exporter_admin_page', // Callback function
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
		'me_exporter_history_page' // Callback function
	);
}

/**
 * Render the export history page
 */
function me_exporter_history_page() {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Export History', 'multisite-exporter' ); ?></h1>
		<?php
		$exports = get_site_transient( 'multisite_exports' );

		if ( ! empty( $exports ) ) {
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" id="exports-form"
				class="multisite-exporter-table">
				<input type="hidden" name="action" value="me_download_selected_exports">
				<?php wp_nonce_field( 'download_selected_exports', 'me_download_nonce' ); ?>

				<div class="tablenav top">
					<div class="alignleft actions bulkactions">
						<input type="submit" id="doaction" class="button action"
							value="<?php esc_attr_e( 'Download Selected', 'multisite-exporter' ); ?>">
					</div>
					<div class="alignleft actions">
						<a href="#" class="button"
							id="select-all"><?php esc_html_e( 'Select All', 'multisite-exporter' ); ?></a>
						<a href="#" class="button"
							id="deselect-all"><?php esc_html_e( 'Deselect All', 'multisite-exporter' ); ?></a>
					</div>
				</div>

				<table class="widefat fixed" style="margin-top: 20px;">
					<thead>
						<tr>
							<th class="check-column"><input type="checkbox" id="cb-select-all"></th>
							<th><?php esc_html_e( 'Blog ID', 'multisite-exporter' ); ?></th>
							<th><?php esc_html_e( 'Site Name', 'multisite-exporter' ); ?></th>
							<th><?php esc_html_e( 'Export File', 'multisite-exporter' ); ?></th>
							<th><?php esc_html_e( 'Date', 'multisite-exporter' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'multisite-exporter' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $exports as $export ) {
							echo '<tr>';
							echo '<td class="check-column"><input type="checkbox" name="selected_exports[]" value="' . esc_attr( $export[ 'file_name' ] ) . '"></td>';
							echo '<td>' . esc_html( $export[ 'blog_id' ] ) . '</td>';
							echo '<td>' . esc_html( $export[ 'site_name' ] ) . '</td>';
							echo '<td>' . esc_html( $export[ 'file_name' ] ) . '</td>';
							echo '<td>' . esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $export[ 'date' ] ) ) ) . '</td>';
							echo '<td>';

							// Create a download URL using WordPress admin-ajax.php
							$download_url = add_query_arg(
								array(
									'action' => 'me_download_export',
									'file'   => base64_encode( $export[ 'file_name' ] ),
									'nonce'  => wp_create_nonce( 'download_export_' . $export[ 'file_name' ] ),
								),
								admin_url( 'admin-ajax.php' )
							);

							echo '<a href="' . esc_url( $download_url ) . '" class="button button-small">' . esc_html__( 'Download', 'multisite-exporter' ) . '</a>';
							echo '</td></tr>';
						}
						?>
					</tbody>
				</table>
			</form>
			<script type="text/javascript">
				jQuery(document).ready(function ($) {
					// Select/deselect all checkboxes
					$('#cb-select-all').on('click', function () {
						$('input[name="selected_exports[]"]').prop('checked', this.checked);
					});

					// Select all button
					$('#select-all').on('click', function (e) {
						e.preventDefault();
						$('input[name="selected_exports[]"]').prop('checked', true);
						$('#cb-select-all').prop('checked', true);
					});

					// Deselect all button
					$('#deselect-all').on('click', function (e) {
						e.preventDefault();
						$('input[name="selected_exports[]"]').prop('checked', false);
						$('#cb-select-all').prop('checked', false);
					});

					// Update header checkbox when individual checkboxes change
					$('input[name="selected_exports[]"]').on('change', function () {
						var allChecked = $('input[name="selected_exports[]"]:checked').length === $('input[name="selected_exports[]"]').length;
						$('#cb-select-all').prop('checked', allChecked);
					});
				});
			</script>
			<?php
		} else {
			echo '<p>' . esc_html__( 'No exports found.', 'multisite-exporter' ) . '</p>';
		}
		?>
	</div>
	<?php
}

/**
 * Handle export file downloads
 */
add_action( 'wp_ajax_me_download_export', 'me_handle_export_download' );
function me_handle_export_download() {
	// Check if user has permission
	if ( ! current_user_can( 'manage_network' ) ) {
		wp_die( 'You do not have permission to access this file.' );
	}

	// Verify nonce
	$file = isset( $_GET[ 'file' ] ) ? sanitize_text_field( base64_decode( $_GET[ 'file' ] ) ) : '';
	if ( ! wp_verify_nonce( $_GET[ 'nonce' ], 'download_export_' . $file ) ) {
		wp_die( 'Security check failed.' );
	}

	// Get the export directory path
	$export_dir = me_get_export_directory();
	$file_path  = trailingslashit( $export_dir ) . $file;

	// Check if file exists
	if ( ! file_exists( $file_path ) ) {
		wp_die( 'File not found.' );
	}

	// Force download
	header( 'Content-Description: File Transfer' );
	header( 'Content-Type: application/xml' );
	header( 'Content-Disposition: attachment; filename="' . basename( $file_path ) . '"' );
	header( 'Content-Length: ' . filesize( $file_path ) );
	header( 'Cache-Control: must-revalidate' );
	header( 'Pragma: public' );
	header( 'Expires: 0' );

	readfile( $file_path );
	exit;
}

/**
 * Handle the download of multiple selected export files as a zip archive
 */
add_action( 'wp_ajax_me_download_selected_exports', 'me_handle_selected_exports_download' );
function me_handle_selected_exports_download() {
	// Check if user has permission
	if ( ! current_user_can( 'manage_network' ) ) {
		wp_die( 'You do not have permission to access these files.' );
	}

	// Verify nonce
	if ( ! isset( $_POST[ 'me_download_nonce' ] ) || ! wp_verify_nonce( $_POST[ 'me_download_nonce' ], 'download_selected_exports' ) ) {
		wp_die( 'Security check failed.' );
	}

	// Get selected export files
	$selected_exports = isset( $_POST[ 'selected_exports' ] ) ? (array) $_POST[ 'selected_exports' ] : [];

	// If no files were selected, redirect back with an error message
	if ( empty( $selected_exports ) ) {
		wp_redirect( add_query_arg( 'error', 'no-selection', admin_url( 'admin.php?page=multisite-exporter-history' ) ) );
		exit;
	}

	// Get the export directory path
	$export_dir = me_get_export_directory();

	// Create a temporary file for the zip
	$zip_filename = 'multisite-exports-' . date( 'Ymd-His' ) . '.zip';
	$zip_filepath = trailingslashit( get_temp_dir() ) . $zip_filename;

	// Create new zip archive
	$zip = new ZipArchive();
	if ( $zip->open( $zip_filepath, ZipArchive::CREATE ) !== true ) {
		wp_die( 'Could not create ZIP file.' );
	}

	// Get available exports from transient
	$available_exports = get_site_transient( 'multisite_exports' ) ?: [];
	$file_map          = [];

	// Create a lookup map for file names to file paths
	foreach ( $available_exports as $export ) {
		$file_map[ $export[ 'file_name' ] ] = $export;
	}

	// Add selected files to the zip archive
	foreach ( $selected_exports as $file_name ) {
		// Sanitize the file name
		$file_name = sanitize_file_name( $file_name );

		// Make sure the file exists in our tracked exports
		if ( isset( $file_map[ $file_name ] ) ) {
			$file_path = trailingslashit( $export_dir ) . $file_name;

			// Check if file exists
			if ( file_exists( $file_path ) ) {
				// Add file to zip with filename as is
				$zip->addFile( $file_path, $file_name );
			}
		}
	}

	// Close the zip file
	$zip->close();

	// Check if file was created successfully
	if ( ! file_exists( $zip_filepath ) ) {
		wp_die( 'Error creating ZIP file.' );
	}

	// Set headers for download
	header( 'Content-Description: File Transfer' );
	header( 'Content-Type: application/zip' );
	header( 'Content-Disposition: attachment; filename="' . basename( $zip_filepath ) . '"' );
	header( 'Content-Length: ' . filesize( $zip_filepath ) );
	header( 'Cache-Control: must-revalidate' );
	header( 'Pragma: public' );
	header( 'Expires: 0' );

	// Output file
	readfile( $zip_filepath );

	// Delete the temporary file
	unlink( $zip_filepath );

	exit;
}

function me_exporter_admin_page() {
	// Add nonce for security
	if ( isset( $_POST[ 'me_run' ] ) && check_admin_referer( 'multisite_exporter_action', 'me_nonce' ) ) {
		$export_args = [ 
			'content'    => sanitize_text_field( $_POST[ 'content' ] ?? 'all' ),
			'post_type'  => sanitize_text_field( $_POST[ 'post_type' ] ?? '' ),
			'start_date' => sanitize_text_field( $_POST[ 'start_date' ] ?? '' ),
			'end_date'   => sanitize_text_field( $_POST[ 'end_date' ] ?? '' ),
		];
		me_schedule_exports( $export_args );
		echo '<div class="updated"><p>' . esc_html__( 'Export has been scheduled for all subsites! View results in the', 'multisite-exporter' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=multisite-exporter-history' ) ) . '">' . esc_html__( 'Export History', 'multisite-exporter' ) . '</a> ' . esc_html__( 'page.', 'multisite-exporter' ) . '</p></div>';
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Multisite Exporter', 'multisite-exporter' ); ?></h1>
		<p><?php esc_html_e( 'This tool exports content from all subsites in your multisite installation. Exports are saved to a common folder and can be downloaded from the', 'multisite-exporter' ); ?>
			<a
				href="<?php echo esc_url( admin_url( 'admin.php?page=multisite-exporter-history' ) ); ?>"><?php esc_html_e( 'Export History', 'multisite-exporter' ); ?></a>
			<?php esc_html_e( 'page.', 'multisite-exporter' ); ?>
		</p>
		<form method="post">
			<?php wp_nonce_field( 'multisite_exporter_action', 'me_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Content', 'multisite-exporter' ); ?></th>
					<td>
						<select name="content">
							<option value="all"><?php esc_html_e( 'All Content', 'multisite-exporter' ); ?></option>
							<option value="posts"><?php esc_html_e( 'Posts', 'multisite-exporter' ); ?></option>
							<option value="pages"><?php esc_html_e( 'Pages', 'multisite-exporter' ); ?></option>
							<option value="attachment"><?php esc_html_e( 'Media', 'multisite-exporter' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Post Type (optional)', 'multisite-exporter' ); ?></th>
					<td><input type="text" name="post_type"
							placeholder="<?php esc_attr_e( 'e.g., product', 'multisite-exporter' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Start Date (YYYY-MM-DD)', 'multisite-exporter' ); ?></th>
					<td><input type="date" name="start_date"></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'End Date (YYYY-MM-DD)', 'multisite-exporter' ); ?></th>
					<td><input type="date" name="end_date"></td>
				</tr>
			</table>
			<?php submit_button( __( 'Run Export for All Subsites', 'multisite-exporter' ), 'primary', 'me_run' ); ?>
		</form>
	</div>
	<?php
}