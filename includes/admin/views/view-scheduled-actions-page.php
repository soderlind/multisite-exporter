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

// Check if Action Scheduler is available
if ( ! class_exists( 'ActionScheduler_AdminView' ) ) {
	echo '<div class="wrap"><h1>' . esc_html__( 'Scheduled Actions', 'multisite-exporter' ) . '</h1>';
	echo '<div class="error"><p>' . esc_html__( 'Action Scheduler is not available. Please make sure it is properly installed.', 'multisite-exporter' ) . '</p></div>';
	echo '</div>';
	return;
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
}

// Create our custom filtered list table with the required parameters
$list_table = new ME_Filtered_List_Table( $store, $logger, $runner );
$list_table->prepare_items();

// Start output
echo '<div class="wrap">';
echo '<h1>' . esc_html__( 'Export Tasks', 'multisite-exporter' ) . '</h1>';
echo '<p>' . esc_html__( 'This page shows only the export tasks for Multisite Exporter.', 'multisite-exporter' ) . '</p>';

// Display search form and navigation
echo '<form id="posts-filter" method="get">';
echo '<input type="hidden" name="page" value="me-scheduled-actions">';
echo '<input type="hidden" name="hook" value="me_process_site_export">'; // Always include hook in form

// Display the views (status filters)
$list_table->views();

// Display the list table
$list_table->display();
echo '</form>';

echo '</div>';

// Remove our filters after we're done to avoid affecting other parts of the admin
remove_filter( 'action_scheduler_get_actions_query_args', array( ME_Admin::instance(), 'force_hook_filter' ), 999999 );
remove_filter( 'action_scheduler_store_sql_query', array( ME_Admin::instance(), 'filter_as_sql_query' ), 999999 );