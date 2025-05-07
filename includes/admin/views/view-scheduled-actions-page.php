<?php
/**
 * Scheduled actions page view.
 *
 * @since      1.0.1
 * @package    Multisite_Exporter
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register deletion success notice to show in admin
 * 
 * @param int $deleted_count Number of items deleted
 * @return void
 */
function me_register_deletion_notice( $deleted_count ) {
	add_action( 'admin_notices', function () use ($deleted_count) {
		$message = sprintf(
			_n(
				'%s scheduled action deleted successfully.',
				'%s scheduled actions deleted successfully.',
				$deleted_count,
				'multisite-exporter'
			),
			number_format_i18n( $deleted_count )
		);
		echo '<div class="updated notice is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	} );
}

// Check if Action Scheduler is available
if ( ! class_exists( 'ActionScheduler_AdminView' ) ) {
	echo '<div class="wrap"><h1>' . esc_html__( 'Scheduled Actions', 'multisite-exporter' ) . '</h1>';
	echo '<div class="error"><p>' . esc_html__( 'Action Scheduler is not available. Please make sure it is properly installed.', 'multisite-exporter' ) . '</p></div>';
	echo '</div>';
	return;
}

// Process bulk actions if submitted
if ( isset( $_POST[ '_wpnonce' ] ) && ( isset( $_POST[ 'action' ] ) && $_POST[ 'action' ] !== '-1' ) || ( isset( $_POST[ 'action2' ] ) && $_POST[ 'action2' ] !== '-1' ) ) {
	// Verify nonce
	check_admin_referer( 'bulk-' . 'action-scheduler-actions' ); // This matches WordPress standard list table nonce naming

	// Get selected items - use standard WordPress list table field name for checkboxes
	$action_ids = isset( $_POST[ 'action_scheduler_actions' ] ) ? array_map( 'intval', (array) $_POST[ 'action_scheduler_actions' ] ) : array();

	// Process based on the action
	if ( ! empty( $action_ids ) ) {
		$store = ActionScheduler::store();

		// Handle delete action
		if ( ( isset( $_POST[ 'action' ] ) && $_POST[ 'action' ] === 'delete' ) || ( isset( $_POST[ 'action2' ] ) && $_POST[ 'action2' ] === 'delete' ) ) {
			foreach ( $action_ids as $action_id ) {
				$store->delete_action( $action_id );
			}

			// Store deleted count in transient to show a notice after redirect
			$deleted_count = count( $action_ids );
			set_transient( 'me_bulk_actions_deleted_count', $deleted_count, 30 );

			// Only redirect if headers haven't been sent yet
			if ( ! headers_sent() ) {
				wp_redirect( add_query_arg( array(
					'page'    => 'me-scheduled-actions',
					'hook'    => 'me_process_site_export',
					'deleted' => $deleted_count,
				), admin_url( 'admin.php' ) ) );
				exit;
			} else {
				// If headers already sent, we'll show the notice immediately
				me_register_deletion_notice( $deleted_count );
			}
		}
	}
}

// Show success message after any deletion operation
if ( ( isset( $_GET[ 'deleted' ] ) && intval( $_GET[ 'deleted' ] ) > 0 ) ||
	( isset( $_GET[ 'bulk_deletion_complete' ] ) && isset( $_GET[ 'deleted_count' ] ) && intval( $_GET[ 'deleted_count' ] ) > 0 ) ) {

	// Get the count from either parameter and store it in a local variable
	$deleted_count = isset( $_GET[ 'deleted' ] ) ? intval( $_GET[ 'deleted' ] ) : intval( $_GET[ 'deleted_count' ] );

	// Use the shared function to register the notice
	me_register_deletion_notice( $deleted_count );
}

// Add a strong filter directly to the data store's query method
add_filter( 'action_scheduler_get_actions_query_args', array( ME_Admin::instance(), 'force_hook_filter' ), 999999 );

// Add a direct SQL filter if using the database store 
add_filter( 'action_scheduler_store_sql_query', array( ME_Admin::instance(), 'filter_as_sql_query' ), 999999 );

// Include required classes if not already included
if ( ! class_exists( 'ActionScheduler_Abstract_ListTable' ) ) {
	$as_dir = MULTISITE_EXPORTER_PLUGIN_DIR . 'vendor/woocommerce/action-scheduler/';
	require_once $as_dir . 'classes/abstracts/ActionScheduler_Abstract_ListTable.php';
}

if ( ! class_exists( 'ActionScheduler_ListTable' ) ) {
	$as_dir = MULTISITE_EXPORTER_PLUGIN_DIR . 'vendor/woocommerce/action-scheduler/';
	require_once $as_dir . 'classes/ActionScheduler_ListTable.php';
}

// Get store, logger and runner instances
$store  = ActionScheduler::store();
$logger = ActionScheduler::logger();
$runner = ActionScheduler_QueueRunner::instance();

/**
 * Custom extension of ActionScheduler_ListTable to enforce filtering by our hook
 * This class is defined within the file to ensure parent class is loaded first
 */
class ME_Filtered_List_Table extends ActionScheduler_ListTable {
	/**
	 * Constructor with forced hook filtering
	 *
	 * @param ActionScheduler_Store $store
	 * @param ActionScheduler_Logger $logger
	 * @param ActionScheduler_QueueRunner $runner
	 */
	public function __construct( $store, $logger, $runner ) {
		parent::__construct( $store, $logger, $runner );

		// Ensure our filters persist at every level
		add_filter( 'action_scheduler_list_table_query_args', array( $this, 'filter_by_hook' ), 999999 );

		// Update the plural and singular args to match what WordPress expects for nonce verification
		$this->_args = array(
			'plural'   => 'action-scheduler-actions',
			'singular' => 'action-scheduler-action',
		);
	}

	/**
	 * Force filtering by our export hook
	 *
	 * @param array $args
	 * @return array
	 */
	public function filter_by_hook( $args ) {
		// Override any existing hook filter
		$args[ 'hook' ] = 'me_process_site_export';
		return $args;
	}

	/**
	 * Define available bulk actions
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array(
			'delete' => __( 'Delete', 'multisite-exporter' ),
		);
	}

	/**
	 * Override column_cb method to ensure proper checkbox naming
	 * This fixes the "select all" checkbox functionality
	 *
	 * @param array $row The row to render
	 * @return string
	 */
	public function column_cb( $row ) {
		$checkbox_id = 'cb-select-' . $row[ 'ID' ];
		return sprintf(
			'<label class="screen-reader-text" for="%1$s">%2$s</label>' .
			'<input type="checkbox" name="%3$s[]" id="%1$s" value="%4$s" />',
			$checkbox_id,
			esc_html__( 'Select item', 'multisite-exporter' ),
			$this->get_bulk_actions_checkbox_name(),
			$row[ 'ID' ]
		);
	}

	/**
	 * Override the checkbox name to ensure it matches what our bulk action handler expects
	 * 
	 * @return string
	 */
	protected function get_bulk_actions_checkbox_name() {
		return 'action_scheduler_actions';
	}

	/**
	 * Override prepare_items to ensure hook filter is always applied
	 */
	public function prepare_items() {
		// Store original query args filter for later restoration
		$this->prepare_column_headers();

		$per_page = $this->get_items_per_page( $this->get_per_page_option_name(), $this->items_per_page );

		// Make sure the hook is always included in query args
		$query = array(
			'per_page' => $per_page,
			'offset'   => $this->get_items_offset(),
			'status'   => $this->get_request_status(),
			'orderby'  => $this->get_request_orderby(),
			'order'    => $this->get_request_order(),
			'search'   => $this->get_request_search_query(),
			'hook'     => 'me_process_site_export', // Always include our hook filter
		);

		// Rest of the method follows standard ActionScheduler_ListTable logic
		$this->items = array();
		$total_items = $this->store->query_actions( $query, 'count' );

		$status_labels = $this->store->get_status_labels();

		foreach ( $this->store->query_actions( $query ) as $action_id ) {
			try {
				$action = $this->store->fetch_action( $action_id );
			} catch (Exception $e) {
				continue;
			}

			if ( is_a( $action, 'ActionScheduler_NullAction' ) || $action->get_hook() !== 'me_process_site_export' ) {
				// Skip actions that aren't ours, double protection
				continue;
			}

			$this->items[ $action_id ] = array(
				'ID'          => $action_id,
				'hook'        => $action->get_hook(),
				'status_name' => $this->store->get_status( $action_id ),
				'status'      => $status_labels[ $this->store->get_status( $action_id ) ],
				'args'        => $action->get_args(),
				'group'       => $action->get_group(),
				'log_entries' => $this->logger->get_logs( $action_id ),
				'claim_id'    => $this->store->get_claim_id( $action_id ),
				'recurrence'  => $this->get_recurrence( $action ),
				'schedule'    => $action->get_schedule(),
			);
		}

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);
	}

	/**
	 * Override get_views to only show relevant status links
	 *
	 * @return array
	 */
	protected function get_views() {
		$views = parent::get_views();

		// Only keep the essential status filters and add hook filter to each URL
		foreach ( $views as $status => $link ) {
			// Add our hook parameter to each link
			$views[ $status ] = str_replace(
				'admin.php?page=',
				'admin.php?hook=me_process_site_export&page=',
				$link
			);
		}

		return $views;
	}

	/**
	 * Override process_bulk_action to handle POST submissions
	 * The parent class expects GET parameters, but our form uses POST
	 */
	protected function process_bulk_action() {
		// Detect when a bulk action is being triggered
		$action = $this->current_action();
		if ( ! $action ) {
			return;
		}

		// Check if this is a POST submission with our action_scheduler_actions field
		if ( isset( $_POST[ '_wpnonce' ] ) && isset( $_POST[ 'action_scheduler_actions' ] ) && is_array( $_POST[ 'action_scheduler_actions' ] ) ) {
			// Verify nonce
			check_admin_referer( 'bulk-' . $this->_args[ 'plural' ] );

			$method = 'bulk_' . $action;
			if ( array_key_exists( $action, $this->bulk_actions ) && is_callable( array( $this, $method ) ) ) {
				$ids = array_map( 'absint', $_POST[ 'action_scheduler_actions' ] );

				if ( ! empty( $ids ) ) {
					// Call the bulk method with the IDs
					$this->$method( $ids, '' );

					// Redirect to remove POST data and prevent refresh issues
					if ( isset( $_SERVER[ 'REQUEST_URI' ] ) ) {
						wp_safe_redirect(
							add_query_arg(
								array(
									'page'    => 'me-scheduled-actions',
									'hook'    => 'me_process_site_export',
									'deleted' => count( $ids ),
								),
								admin_url( 'admin.php' )
							)
						);
						exit;
					}
				}
			}
		}
	}

	/**
	 * Override bulk_delete method to properly delete actions
	 *
	 * @param array $ids Array of action IDs to delete
	 * @param string $ids_sql SQL (unused but required by parent class signature)
	 */
	protected function bulk_delete( array $ids, $ids_sql ) {
		foreach ( $ids as $action_id ) {
			try {
				$this->store->delete_action( $action_id );
			} catch (Exception $e) {
				// Log the error but continue with other deletions
				error_log( sprintf( 'Failed to delete scheduled action %d: %s', $action_id, $e->getMessage() ) );
			}
		}
	}
}

// Create our custom filtered list table with the required parameters
$list_table = new ME_Filtered_List_Table( $store, $logger, $runner );
$list_table->prepare_items();

// Start output
echo '<div class="wrap">';
echo '<h1>' . esc_html__( 'Export Tasks', 'multisite-exporter' ) . '</h1>';
echo '<p>' . esc_html__( 'This page shows only the export tasks for Multisite Exporter.', 'multisite-exporter' ) . '</p>';

// Display search form and navigation
echo '<form id="posts-filter" method="post">';
echo '<input type="hidden" name="page" value="me-scheduled-actions">';
echo '<input type="hidden" name="hook" value="me_process_site_export">'; // Always include hook in form
wp_nonce_field( 'bulk-' . 'action-scheduler-actions' ); // Match the nonce name used in our bulk action handler

// Display the views (status filters)
$list_table->views();

// Display the list table
$list_table->display();
echo '</form>';

echo '</div>';

// Remove our filters after we're done to avoid affecting other parts of the admin
remove_filter( 'action_scheduler_get_actions_query_args', array( ME_Admin::instance(), 'force_hook_filter' ), 999999 );
remove_filter( 'action_scheduler_store_sql_query', array( ME_Admin::instance(), 'filter_as_sql_query' ), 999999 );