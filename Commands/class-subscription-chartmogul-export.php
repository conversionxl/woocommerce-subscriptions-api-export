<?php
/**
 * CLI commands to export subscription order to Chartmogul.
 *
 * @package cxl
 */

/**
 * CLI commands to export subscription order to Chartmogul.
 */
class Subscription_Chartmogul_Export extends WP_CLI_Command {

	/**s
	 * Variable to store dry-run flag.
	 *
	 * @var bool
	 */
	private $dry_run = false;

	/**
	 * Variable for single order id.
	 *
	 * @var bool
	 */
	private $id = false;

	/**
	 * Variable to check if we need to proceed for all orders.
	 *
	 * @var bool
	 */
	private $fetch_all = false;
	

	/**
	 * Function to load other function on class initialize.
	 *
	 * @param array $args       List of arguments pass with CLI command.
	 * @param array $assoc_args List of associative arguments pass with CLI command.
	 *
	 * @return bool
	 */
	public function __construct( $args, $assoc_args ) {

		$this->set_command_args( $args, $assoc_args );

		$this->export_orders();
	}

	/**
	 * Function to set command arguments.
	 *
	 * @param array $args       List of arguments pass with CLI command.
	 * @param array $assoc_args List of associative arguments pass with CLI command.
	 *
	 * @return void
	 */
	private function set_command_args( $args, $assoc_args ) {

		// Check script mode.
		if ( ! empty( $assoc_args['dry-run'] ) ) {
			$this->dry_run = true;
		}

		// Check all parameter.
		if ( ! empty( $assoc_args['all'] ) ) {
			$this->fetch_all = true;
		}

		// Check id.
		if ( ! empty( $assoc_args['id'] ) && is_numeric( $assoc_args['id'] ) ) {
			$this->id = $assoc_args['id'];
		}
	}

	/**
	 * Export orders to ChartMogul.
	 *
	 * @return void
	 */
	private function export_orders() {

		WP_CLI::log( WP_CLI::colorize( '%yStarting script...%n' ) );

		$post_ids = $this->get_subscription_posts();

		if ( ! empty( $this->id ) ) {
		
			$this->export_order_to_chartmogul( $this->id );		

			WP_CLI::log( WP_CLI::colorize( '%yScript got completed.%n' ) );
		
		} elseif ( ! empty( $this->fetch_all ) ) {
			
			foreach ( $post_ids as $post_id ) {
				// Update post meta.
				$this->export_order_to_chartmogul( $post_id );
			}	

			WP_CLI::log( WP_CLI::colorize( '%yScript got completed.%n' ) );
		
		} else {
			WP_CLI::log( WP_CLI::colorize( '%yPlease either pass the order id or --all parameter.%n' ) );
		}
	}

	/**
	 * Function to select subscrition posts.
	 *
	 * @return array
	 */
	private function get_subscription_posts() {

		return get_posts(
			[
				'post_type'      => [ 'shop_subscription' ],
				'posts_per_page' => -1,
				'post_status'    => 'wc-completed',
				'fields'         => 'ids',
			]
		);

	}

	/**
	 * Export single order to chartmogul
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return void
	 */
	private function export_order_to_chartmogul( $order_id ) {

		$order = new WC_Order( $order_id );

		// @todo export order code.

		$this->add_cli_log( $order_id );
	}

	/**
	 * Function to add CLI log.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	private function add_cli_log( $post_id ) {

		if ( true === $this->dry_run ) {
			// translator: %s: Meta key, %d post id.
			$cli_msg = sprintf(
				esc_html__( 'Order#%d will be sent to ChartMogul', 'cxl' ),
				esc_html( $post_id )
			);
		} else {
			// translator: %s: Meta key, %d post id.
			$cli_msg = sprintf(
				esc_html__( 'Order#%d sent to ChartMogul', 'cxl' ),
				esc_html( $post_id )
			);
		}

		WP_CLI::log( $cli_msg );
	}

}
