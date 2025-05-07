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

	<form method="post" id="multisite-exporter-form">
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