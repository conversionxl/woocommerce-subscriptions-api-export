<?php
/**
 * CLI commands to export subscriptions to ChartMogul.
 *
 * @package cxl
 */

namespace CXL_Upwork_01dd36a4283a21f14f\Commands;

use ChartMogul;
use Exception;
use WC_Subscription;
use WP_CLI;
use WP_CLI_Command;

/**
 * CLI commands to export subscriptions to ChartMogul.
 */
class SubscriptionExportChartMogulCommand extends WP_CLI_Command {

	/**
	 * Variable to store dry-run flag.
	 */
	private bool $dry_run = false;

	/**
	 * Variable for single subscription id.
	 */
	private ?int $id = null;

	/**
	 * Variable to check if we need to proceed for all subscriptions.
	 */
	private bool $fetch_all = false;

    /**
     * Variable to check if we need to create data source.
     */
    private bool $create_data_source = false;

    /**
     * Variable for data source.
     */
    private ?string $data_source = null;


    /**
     * Function to load other function on class initialize.
     *
     * @param array $args List of arguments pass with CLI command.
     * @param array $assoc_args List of associative arguments pass with CLI command.
     * @throws Exception
     */
	public function __construct( array $args, array $assoc_args ) {

		$this->set_command_args( $args, $assoc_args );

		$this->initialize_chartmogul();

		if ( $this->create_data_source ) {
			$this->create_chartmogul_data_source();
		} else {
			$this->export_subscriptions();
		}

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

		// Check all parameter.
		if ( ! empty( $assoc_args['data-source'] ) ) {
			$this->data_source = $assoc_args['data-source'];
		}

		// Check all parameter.
		if ( ! empty( $assoc_args['create-data-source'] ) ) {
			$this->create_data_source = true;

			if ( empty( $this->data_source ) ) {
				WP_CLI::error( 'Please pass data source name using --data-source' );
			}
		}

		// Check id.
		if ( ! empty( $assoc_args['id'] ) && is_numeric( $assoc_args['id'] ) ) {
			$this->id = $assoc_args['id'];
		}
	}

	/**
	 * Function to create data source ChartMogul.
	 *
	 * @return void
	 */
	private function create_chartmogul_data_source(): void {
		$ds = ChartMogul\DataSource::create([
			'name' => $this->data_source
		]);

		// Error Log, needs to be removed.
		WP_CLI::log( print_r( $ds, true ) );
		WP_CLI::log( 'Data source created successfully.' );
	}

    /**
     * Function to create customer ChartMogul.
     *
     * @param WC_Subscription $subscription
     * @return void
     */
	private function create_customer( WC_Subscription $subscription ): void {

		ChartMogul\Customer::create( [
			'data_source_uuid' => $this->data_source,
			'external_id'      => $subscription->get_customer_id(),
			'name'             => $subscription->get_billing_first_name() . ' ' . $subscription->get_billing_last_name(),
			'email'            => $subscription->get_billing_email(),
			'country'          => $subscription->get_billing_country(),
			'city'             => $subscription->get_billing_city(),
		] );

		WP_CLI::log( 'Customer created in ChartMogul.' );
	}

	/**
	 * Function to initialize ChartMogul.
	 *
	 * @return void
     * @throws Exception
	 */
	private function initialize_chartmogul(): void {

	    // @todo constant option name + `get_option()`.
		ChartMogul\Configuration::getDefaultConfiguration()
			->setAccountToken( '37803855593a9262c59b3b1fec5e88ae' )
			->setSecretKey( '7bc86adc87d56a6371d11014ba0a9ad5' );
	}

	/**
	 * Export subscriptions to ChartMogul.
	 *
	 * @return void
	 */
	private function export_subscriptions(): void {

	    // @todo parameter validity should be checked before any queries.
		$subscription_ids = $this->get_subscription_posts();

		if ( ! empty( $this->id ) ) {

			$this->export_subscription_to_chartmogul( $this->id );

			WP_CLI::log( WP_CLI::colorize( '%yExport finished.%n' ) );

		} elseif ( ! empty( $this->fetch_all ) ) {

			foreach ( $subscription_ids as $post_id ) {
				// Update post meta.
				$this->export_subscription_to_chartmogul( $post_id );
			}

			WP_CLI::log( WP_CLI::colorize( '%yExport finished.%n' ) );

		} else {
			WP_CLI::log( WP_CLI::colorize( '%yPlease either pass the subscription id or --all parameter.%n' ) );
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
	 * Export single subscription to ChartMogul.
	 *
	 * @param int $subscription_id Subscription ID.
	 *
	 * @return void
	 */
	private function export_subscription_to_chartmogul( int $subscription_id ): void {

		$subscription = wcs_get_subscription( $subscription_id );

		$this->create_customer( $subscription );

		$this->add_cli_log( $subscription_id );
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
				esc_html__( 'Subscription #%d would be sent to ChartMogul', 'cxl' ),
				esc_html( $subscription_id )
			);
		} else {
			// translator: %s: Meta key, %d post id.
			$cli_msg = sprintf(
				esc_html__( 'Subscription #%d sent to ChartMogul', 'cxl' ),
				esc_html( $subscription_id )
			);
		}

		WP_CLI::log( $cli_msg );
	}

}
