<?php
/**
 * Export history page view.
 *
 * @since      1.0.1
 * @package    Multisite_Exporter
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Outputs pagination links
 * 
 * @param int $current_page Current page number
 * @param int $total_pages Total number of pages
 * @param int $total_exports Total number of export items
 * @return void
 */
function me_output_pagination( $current_page, $total_pages, $total_exports ) {
	?>
	<div class="tablenav-pages">
		<span class="displaying-num">
			<?php
			printf(
				/* translators: %s: Number of exports */
				_n( '%s export', '%s exports', $total_exports, 'multisite-exporter' ),
				number_format_i18n( $total_exports )
			);
			?>
		</span>
		<span class="pagination-links">
			<?php
			// First page link
			if ( $current_page > 1 ) {
				printf(
					'<a class="first-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">%s</span></a>',
					esc_url( add_query_arg( 'paged', 1 ) ),
					esc_html__( 'First page', 'multisite-exporter' ),
					'&laquo;'
				);
			} else {
				printf(
					'<span class="tablenav-pages-navspan button disabled" aria-hidden="true">%s</span>',
					'&laquo;'
				);
			}

			// Previous page link
			if ( $current_page > 1 ) {
				printf(
					'<a class="prev-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">%s</span></a>',
					esc_url( add_query_arg( 'paged', max( 1, $current_page - 1 ) ) ),
					esc_html__( 'Previous page', 'multisite-exporter' ),
					'&lsaquo;'
				);
			} else {
				printf(
					'<span class="tablenav-pages-navspan button disabled" aria-hidden="true">%s</span>',
					'&lsaquo;'
				);
			}

			// Current page text input (for top pagination) or text (for bottom pagination)
			if ( did_action( 'me_top_pagination' ) === 0 ) {
				printf(
					'<span class="paging-input"><input class="current-page" id="current-page-selector" type="text" name="paged" value="%s" size="1" aria-describedby="table-paging" data-total-pages="%s" data-base-url="%s"> %s <span class="total-pages">%s</span></span>',
					$current_page,
					intval( $total_pages ),
					esc_js( remove_query_arg( 'paged' ) ),
					esc_html__( 'of', 'multisite-exporter' ),
					number_format_i18n( $total_pages )
				);
				// Mark that top pagination has been output
				do_action( 'me_top_pagination' );
			} else {
				printf(
					'<span class="paging-input">%s %s <span class="total-pages">%s</span></span>',
					$current_page,
					esc_html__( 'of', 'multisite-exporter' ),
					number_format_i18n( $total_pages )
				);
			}

			// Next page link
			if ( $current_page < $total_pages ) {
				printf(
					'<a class="next-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">%s</span></a>',
					esc_url( add_query_arg( 'paged', min( $total_pages, $current_page + 1 ) ) ),
					esc_html__( 'Next page', 'multisite-exporter' ),
					'&rsaquo;'
				);
			} else {
				printf(
					'<span class="tablenav-pages-navspan button disabled" aria-hidden="true">%s</span>',
					'&rsaquo;'
				);
			}

			// Last page link
			if ( $current_page < $total_pages ) {
				printf(
					'<a class="last-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">%s</span></a>',
					esc_url( add_query_arg( 'paged', $total_pages ) ),
					esc_html__( 'Last page', 'multisite-exporter' ),
					'&raquo;'
				);
			} else {
				printf(
					'<span class="tablenav-pages-navspan button disabled" aria-hidden="true">%s</span>',
					'&raquo;'
				);
			}
			?>
		</span>
	</div>
	<?php
}

// Get all exports
$all_exports = get_site_transient( 'multisite_exports' ) ?: array();

// Check if there are active scheduled exports
$has_active_exports = false;
if ( class_exists( 'ActionScheduler' ) ) {
	$store           = ActionScheduler::store();
	$pending_actions = $store->query_actions( array(
		'hook'     => 'me_process_site_export',
		'status'   => ActionScheduler_Store::STATUS_PENDING,
		'per_page' => 1,
	) );

	$running_actions = $store->query_actions( array(
		'hook'     => 'me_process_site_export',
		'status'   => ActionScheduler_Store::STATUS_RUNNING,
		'per_page' => 1,
	) );

	$has_active_exports = ! empty( $pending_actions ) || ! empty( $running_actions );
}

// Pagination settings
$per_page      = 10; // Number of exports to show per page
$current_page  = isset( $_GET[ 'paged' ] ) ? max( 1, intval( $_GET[ 'paged' ] ) ) : 1;
$total_exports = count( $all_exports );
$total_pages   = ceil( $total_exports / $per_page );

// Ensure current page doesn't exceed total pages
if ( $current_page > $total_pages && $total_pages > 0 ) {
	$current_page = $total_pages;
}

// Calculate which exports to show on this page
$offset  = ( $current_page - 1 ) * $per_page;
$exports = array_slice( $all_exports, $offset, $per_page );
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Exports', 'multisite-exporter' ); ?></h1>

	<?php if ( $has_active_exports ) : ?>
		<!-- Always show the processing indicator when exports are active -->
		<div class="notice notice-info">
			<p>
				<span class="spinner is-active" style="float:none; margin-top:0; margin-right:10px;"></span>
				<?php esc_html_e( 'Export process is currently running.', 'multisite-exporter' ); ?>
				<?php if ( empty( $exports ) ) : ?>
					<?php esc_html_e( 'Exports will appear here when they are completed.', 'multisite-exporter' ); ?>
				<?php endif; ?>
			</p>
			<p>
				<a href="<?php echo esc_url( network_admin_url( 'admin.php?page=me-scheduled-actions' ) ); ?>">
					<?php esc_html_e( 'View scheduled actions', 'multisite-exporter' ); ?>
				</a>
			</p>
		</div>
		<!-- Add auto-refresh meta tag to refresh the page every 10 seconds -->
		<meta http-equiv="refresh" content="10">
	<?php endif; ?>

	<?php
	if ( ! empty( $exports ) ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" id="exports-form"
			class="multisite-exporter-table">
			<input type="hidden" name="action" value="me_download_selected_exports">
			<?php wp_nonce_field( 'download_selected_exports', 'me_download_nonce' ); ?>
			<input type="hidden" name="select_all_pages" id="select_all_pages" value="0">

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

				<div id="select-all-pages-notice" class="alignleft hidden"
					style="margin-left: 10px; padding: 5px; background-color: #f7f7f7; border: 1px solid #ccc; display: none;">
					<span>
						<?php
						printf(
							/* translators: %1$d: Number of items on current page, %2$d: Total number of items */
							esc_html__( 'All %1$d exports on this page are selected. ', 'multisite-exporter' ),
							count( $exports )
						);
						?>
					</span>
					<a href="#" id="select-across-pages">
						<?php
						printf(
							/* translators: %d: Total number of items */
							esc_html__( 'Select all %d exports across all pages', 'multisite-exporter' ),
							$total_exports
						);
						?>
					</a>
				</div>

				<div id="all-selected-notice" class="alignleft hidden"
					style="margin-left: 10px; padding: 5px; background-color: #f7f7f7; border: 1px solid #ccc; display: none;">
					<?php
					printf(
						/* translators: %d: Total number of items */
						esc_html__( 'All %d exports across all pages are selected. ', 'multisite-exporter' ),
						$total_exports
					);
					?>
					<a href="#" id="clear-selection"><?php esc_html_e( 'Clear selection', 'multisite-exporter' ); ?></a>
				</div>

				<?php if ( $total_pages > 1 ) : ?>
					<?php me_output_pagination( $current_page, $total_pages, $total_exports ); ?>
				<?php endif; ?>
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

			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav bottom">
					<?php me_output_pagination( $current_page, $total_pages, $total_exports ); ?>
				</div>
			<?php endif; ?>
		</form>
		<?php
	} else {
		if ( ! $has_active_exports ) {
			echo '<p>' . esc_html__( 'No exports found.', 'multisite-exporter' ) . '</p>';
		}
	}
	?>
</div>