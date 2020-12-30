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
	 * Variable to check if we need to create data source.
	 *
	 * @var bool
	 */
	private $create_data_source = false;
	
	/**
	 * Variable for data source.
	 *
	 * @var bool
	 */
	private $data_source = false;

	/**
	 * Function to load other function on class initialize.
	 *
	 * @param array $args       List of arguments pass with CLI command.
	 * @param array $assoc_args List of associative arguments pass with CLI command.
	 *
	 * @return bool
	 */
	public function __construct( $args, $assoc_args ) {

		$this->create_subscription( 'sdf','sdf','sdf' );
		$this->set_command_args( $args, $assoc_args );

		$this->intialize_chartmogul();

		if ( $this->create_data_source ) {
			$this->create_chartmogul_data_source();
		} else {
			$this->export_orders();
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
	private function set_command_args( $args, $assoc_args ) {

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
	private function create_chartmogul_data_source() {
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
	 * @return void
	 */
	private function create_customer( $order ) {

		$customer = ChartMogul\Customer::create([
			"data_source_uuid" => $this->data_source,
			"external_id" => $order->get_customer_id(),
			"name" => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			"email" => 	$order->get_billing_email(),
			"country" => $order->get_billing_country(),
			"city" => $order->get_billing_city(),
		]);

		WP_CLI::log( 'Customer Created Successfully.' );

		return $customer;
	}

	/**
	 * Function to create plan in ChartMogul.
	 *
	 * @return bool
	 */
	private function create_plan( $product ) {

		if ( 'subscription' !== $product->get_type() ) {
			return false; 
		}

		$subscription_interval = get_post_meta( $product->get_id(), '_subscription_period_interval', true );
		$subscription_period = get_post_meta( $product->get_id(), '_subscription_period', true );

		$plan = ChartMogul\Plan::create([
			"data_source_uuid" => $this->data_source,
			"name" => $product->get_name(),
			"interval_count" => subscription_interval,
			"interval_unit" => $subscription_period,
			"external_id" => $product->get_id()
		]);

		WP_CLI::log( 'Plan Created Successfully.' );

		return $plan;
	}

	/**
	 * Function to create subscription in ChartMogul.
	 *
	 * @return bool
	 */
	private function create_subscription( $plan_id, $order, $order_item ) {

		$product = $order_item->get_product();
		
		if ( 'subscription' !== $product->get_type() ) {
			return false; 
		}

		$service_start  = get_post_meta( $order->get_id(), '_schedule_start', true );
		$service_end    = get_post_meta( $order->get_id(), '_schedule_end', true );

		$subscription = new ChartMogul\LineItems\Subscription([
			'subscription_external_id' => $order->get_id(),
			'subscription_set_external_id' => $order->get_id(),
			'plan_uuid' =>  $plan_id,
			'service_period_start' =>  $service_start,
			'service_period_end' =>  $service_end,
			'amount_in_cents' => $item->get_total() * 100,
			'quantity' => $item->get_quantity(),
			'tax_amount_in_cents' => $item->get_total_tax(),
		]);

		return $subscription;
	}

	/**
	 * Function to create plan in ChartMogul.
	 *
	 * @return bool
	 */
	private function create_invoice( $order ) {

		$customer = $this->create_customer();

		// Iterating through each "line" items in the order
		foreach ($order->get_items() as $item_id => $item ) {

			$product = $item->get_product();

			if ( 'subscription' !== $product->get_type() ) {
				return false; 
			}

		}

		

		$subscription_interval = get_post_meta( $product->get_id(), '_subscription_period_interval', true );
		$subscription_period = get_post_meta( $product->get_id(), '_subscription_period', true );

		ChartMogul\Plan::create([
			"data_source_uuid" => $this->data_source,
			"name" => $product->get_name(),
			"interval_count" => subscription_interval,
			"interval_unit" => $subscription_period,
			"external_id" => $product->get_id()
		]);

		WP_CLI::log( 'Plan Created Successfully.' );

		return true;
	}

	/**
	 * Function to initialize ChartMogul.
	 *
	 * @return void
	 */
	private function intialize_chartmogul() {

		require( CXL_PATH .  '/vendor/autoload.php' );

		ChartMogul\Configuration::getDefaultConfiguration()
			->setAccountToken( '37803855593a9262c59b3b1fec5e88ae' )
			->setSecretKey( '7bc86adc87d56a6371d11014ba0a9ad5' );
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

		// Create Customer.
		create_customer( $order );

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
