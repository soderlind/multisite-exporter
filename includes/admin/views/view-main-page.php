<?php
/**
 * Main admin page view.
 *
 * @since      1.0.1
 * @package    Multisite_Exporter
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Multisite Exporter', 'multisite-exporter' ); ?></h1>
	<p><?php esc_html_e( 'This tool exports content from all subsites in your multisite installation. Exports are saved to a common folder and can be downloaded from the', 'multisite-exporter' ); ?>
		<a
			href="<?php echo esc_url( network_admin_url( 'admin.php?page=multisite-exporter-history' ) ); ?>"><?php esc_html_e( 'Exports', 'multisite-exporter' ); ?></a>
		<?php esc_html_e( 'page.', 'multisite-exporter' ); ?>
	</p>

	<!-- Progress tracking container - initially hidden -->
	<div id="multisite-exporter-progress" class="me-progress-container" style="display: none;">
		<h3><?php esc_html_e( 'Export Progress', 'multisite-exporter' ); ?></h3>
		<div class="me-progress-bar-container">
			<div class="me-progress-bar"></div>
		</div>
		<div class="me-progress-info">
			<span class="me-progress-percentage">0%</span>
			<span class="me-current-site"></span>
			<span class="me-scheduled-info"></span>
		</div>
	</div>

	<form method="post" id="multisite-exporter-form">
		<?php wp_nonce_field( 'multisite_exporter_action', 'me_nonce' ); ?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Content', 'multisite-exporter' ); ?></th>
				<td>
					<select name="content[]" id="me-content-select" class="me-select2" multiple="multiple"
						style="width: 100%;">
						<option value="all" selected><?php esc_html_e( 'All Content', 'multisite-exporter' ); ?>
						</option>
						<option value="posts"><?php esc_html_e( 'Posts', 'multisite-exporter' ); ?></option>
						<option value="pages"><?php esc_html_e( 'Pages', 'multisite-exporter' ); ?></option>
						<option value="attachment"><?php esc_html_e( 'Media', 'multisite-exporter' ); ?></option>
						<?php
						// Get all registered custom post types
						$custom_post_types = get_post_types( array( '_builtin' => false, 'public' => true ), 'objects' );

						// Add each custom post type as an option
						foreach ( $custom_post_types as $post_type ) {
							$label = $post_type->labels->singular_name ?? $post_type->name;
							echo '<option value="' . esc_attr( $post_type->name ) . '">' . esc_html( $label ) . '</option>';
						}
						?>
					</select>
					<p class="description">
						<?php esc_html_e( 'Select content types to export. Choosing "All Content" will include everything.', 'multisite-exporter' ); ?>
					</p>
				</td>
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