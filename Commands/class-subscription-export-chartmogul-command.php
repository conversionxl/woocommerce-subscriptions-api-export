<?php
/**
 * CLI commands to export subscription order to ChartMogul.
 *
 * @package cxl
 */

namespace CXL_Upwork_01dd36a4283a21f14f;

use WP_CLI;
use WP_CLI_Command;

/**
 * CLI commands to export subscription order to ChartMogul.
 */
class Subscription_Export_ChartMogul_Command extends WP_CLI_Command {

	/**
	 * Variable to store dry-run flag.
	 */
	private bool $dry_run = false;

	/**
	 * Variable for single order id.
	 */
	private ?int $id;

	/**
	 * Variable to check if we need to proceed for all orders.
	 */
	private bool $fetch_all = false;


	/**
	 * Function to load other function on class initialize.
	 *
	 * @param array $args       List of arguments pass with CLI command.
	 * @param array $assoc_args List of associative arguments pass with CLI command.
	 */
	public function __construct( array $args, array $assoc_args ) {

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
	private function set_command_args( array $args, array $assoc_args ): void {

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
	private function export_orders(): void {

	    // @todo parameter validity should be checked before any queries.
		$post_ids = $this->get_subscription_posts();

		if ( ! empty( $this->id ) ) {

			$this->export_order_to_chartmogul( $this->id );

			WP_CLI::log( WP_CLI::colorize( '%yExport finished.%n' ) );

		} elseif ( ! empty( $this->fetch_all ) ) {

			foreach ( $post_ids as $post_id ) {
				// Update post meta.
				$this->export_order_to_chartmogul( $post_id );
			}

			WP_CLI::log( WP_CLI::colorize( '%yExport finished.%n' ) );

		} else {
			WP_CLI::log( WP_CLI::colorize( '%yPlease either pass the order id or --all parameter.%n' ) );
		}
	}

	/**
	 * Function to select subscriptions.
	 *
	 * @return array
	 */
	private function get_subscription_posts(): array {

		return wcs_get_subscriptions( [
            'subscriptions_per_page' => -1, // @todo batched processing, memory limit concerns.
        ] );

	}

	/**
	 * Export single order to ChartMogul.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return void
	 */
	private function export_order_to_chartmogul( int $order_id ): void {

		$order = wc_get_order( $order_id );

		// @todo export order code.

		$this->add_cli_log( $order_id );
	}

	/**
	 * Function to add CLI log.
	 *
	 * @param int $subscription_id Post ID.
	 *
	 * @return void
	 */
	private function add_cli_log( int $subscription_id ): void {

		if ( true === $this->dry_run ) {
			// translator: %s: Meta key, %d post id.
			$cli_msg = sprintf(
				esc_html__( 'Order #%d would be sent to ChartMogul', 'cxl' ),
				esc_html( $subscription_id )
			);
		} else {
			// translator: %s: Meta key, %d post id.
			$cli_msg = sprintf(
				esc_html__( 'Order #%d sent to ChartMogul', 'cxl' ),
				esc_html( $subscription_id )
			);
		}

		WP_CLI::log( $cli_msg );
	}

}
