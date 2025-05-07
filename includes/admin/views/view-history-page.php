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

// Get all exports
$all_exports = get_site_transient( 'multisite_exports' ) ?: array();

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
	<h1><?php esc_html_e( 'Export History', 'multisite-exporter' ); ?></h1>
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

							// Current page text input
							printf(
								'<span class="paging-input"><input class="current-page" id="current-page-selector" type="text" name="paged" value="%s" size="1" aria-describedby="table-paging" data-total-pages="%s" data-base-url="%s"> %s <span class="total-pages">%s</span></span>',
								$current_page,
								intval( $total_pages ),
								esc_js( remove_query_arg( 'paged' ) ),
								esc_html__( 'of', 'multisite-exporter' ),
								number_format_i18n( $total_pages )
							);

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

							// Current page text
							printf(
								'<span class="paging-input">%s %s <span class="total-pages">%s</span></span>',
								$current_page,
								esc_html__( 'of', 'multisite-exporter' ),
								number_format_i18n( $total_pages )
							);

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
				</div>
			<?php endif; ?>
		</form>
		<?php
	} else {
		echo '<p>' . esc_html__( 'No exports found.', 'multisite-exporter' ) . '</p>';
	}
	?>
</div>