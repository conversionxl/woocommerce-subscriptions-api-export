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
 *
 * @psalm-suppress UndefinedClass
 */
class SubscriptionExportChartMogulCommand extends WP_CLI_Command {

    /**
     * @var boolean Variable to store dry-run flag.
     */
    private bool $dry_run = false;

    /**
     * @var integer Variable for single subscription id.
     */
    private ?int $id = null;

    /**
     * @var boolean Variable to check if we need to proceed for all subscriptions.
     */
    private bool $fetch_all = false;

    /**
     * @var boolean Variable to check if we need to create data source.
     */
    private bool $create_data_source = false;

    /**
     * @var string Variable for data source.
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

        try {
            if ( $this->create_data_source ) {
                $this->create_chartmogul_data_source();
            } else {
                $this->export_subscriptions();
            }
        } catch ( Exception $e ) {
            WP_CLI::log( $e->getMessage() );
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
        ChartMogul\DataSource::create([
            'name' => $this->data_source,
        ]);

        WP_CLI::success( 'Data source created successfully.' );
    }

    /**
     * Function to create customer in ChartMogul.
     *
     * @param WC_Subscription $subscription
     * @return mixed
     */
    private function create_customer( WC_Subscription $subscription ) {

        $customer = ChartMogul\Customer::findByExternalId( [
            'data_source_uuid' => $this->data_source,
            'external_id'      => $subscription->get_customer_id(),
        ] );

        // If customer is already available.
        if ( ! empty( $customer ) ) {
            return $customer;
        }

        ChartMogul\Customer::create( [
            'data_source_uuid' => $this->data_source,
            'external_id'      => $subscription->get_customer_id(),
            'name'             => $subscription->get_billing_first_name() . ' ' . $subscription->get_billing_last_name(),
            'email'            => $subscription->get_billing_email(),
            'country'          => $subscription->get_billing_country(),
            'city'             => $subscription->get_billing_city(),
        ] );

        WP_CLI::success( 'Customer created in ChartMogul.' );
    }

    /**
     * Retrieve Plan UUID if plan is already pushed in ChartMogul.
     *
     * @param int $product_id WooCommerce Product ID.
     *
     * @return bool|Object
     */
    private function retrive_plan( $product_id ) {

        $plans = ChartMogul\Plan::all( [
            'data_source_uuid' => $this->data_source,
            'external_id'      => $product_id,
        ] );

        if ( ! empty( $plans ) ) {
            return $plans[0];
        }

        return false;
    }

    /**
     * Function to create plan in ChartMogul.
     *
     * @param /WC_Product $product Product Object.
     *
     * @return Object
     */
    private function create_plan( $product ) {

        $plan = $this->retrive_plan( $product->get_id() );
        if ( ! empty( $plan ) ) {
            return $plan;
        }

        if ( 'subscription' !== $product->get_type() ) {
            return false;
        }

        $plan = ChartMogul\Plan::create( [
            'data_source_uuid' => $this->data_source,
            'name'             => $product->get_name(),
            'interval_count'   => (int) get_post_meta( $product->get_id(), '_subscription_period_interval', true ),
            'interval_unit'    => get_post_meta( $product->get_id(), '_subscription_period', true ),
            'external_id'      => $product->get_id(),
        ] );

        WP_CLI::log( 'Plan Created Successfully.' );

        return $plan;
    }

    /**
     * Function to create subscription in ChartMogul.
     *
     * @param string          $plan_id      ChartMogul Plan id.
     * @param WC_Order_item   $order_item   Order Item.
     * @param WC_Order        $order        Current Order.
     * @param WC_Subscription $subscription Subscription Order.
     *
     * @retun Object
     */
    private function create_subscription( $plan_id, $order_item, $order, $subscription ) : object {

        $product = $order_item->get_product();

        if ( 'subscription' !== $product->get_type() ) {
            return $this->create_onetime_lineitem( $order_item, $order );
        }

        $subscription_parameter = [
            'subscription_external_id'     => $order->get_id(),
            'subscription_set_external_id' => $order->get_id(),
            'plan_uuid'                    => $plan_id,
            'service_period_start'         => get_post_meta( $subscription->get_id(), '_schedule_start', true ),
            'service_period_end'           => get_post_meta( $subscription->get_id(), '_schedule_end', true ),
            'amount_in_cents'              => (int) $order_item->get_total() * 100,
            'quantity'                     => $order_item->get_quantity(),
            'type'                         => 'subscription',
            'tax_amount_in_cents'          => (int) $order_item->get_total_tax() * 100,
        ];

        $subscription = new ChartMogul\LineItems\Subscription( $subscription_parameter );

        return $subscription;
    }

    /**
     * Function to create one time line item in ChartMogul.
     *
     * @param WC_Order_item $order_item   Order Item.
     * @param WC_Order      $order        Current Order.
     *
     * @retun Object
     */
    private function create_onetime_lineitem( $order_item, $order ) {

        $product = $order_item->get_product();

        if ( 'subscription' === $product->get_type() ) {
            return false;
        }

        $parameter = [
            'description'         => $order_item->get_name(),
            'amount_in_cents'     => $order_item->get_total() * 100,
            'quantity'            => $order_item->get_quantity(),
            'tax_amount_in_cents' => $order_item->get_total_tax(),
        ];

        $line_item = new ChartMogul\LineItems\OneTime( $parameter );

        return $line_item;
    }

    /**
     * Function to create invoice in ChartMogul.
     *
     * @param Object          $customer     ChartMogul Customer.
     * @param WC_Order        $order        WooCommerce Order.
     * @param WC_Subscription $subscription WooCommerce Subscription.
     *
     * @retun bool
     */
    private function create_invoice( $customer, $order, $subscription ) {

        $line_items = array();
        // Iterating through each "line" items in the order.
        foreach ( $order->get_items() as $item ) {

            $product = $item->get_product();

            if ( 'subscription' !== $product->get_type() ) {
                $line_items[] = $this->create_onetime_lineitem( $item, $order );
            } else {
                $plan         = $this->create_plan( $product );
                $line_items[] = $this->create_subscription( $plan->uuid, $item, $order, $subscription );
            }

        }

        $paid_date      = get_post_meta( $order->get_id(), '_paid_date', true );
        $payment_status = 'failed';
        if ( ! empty( $paid_date ) ) {
            $payment_status = 'successful';
        }

        $transaction = new ChartMogul\Transactions\Payment( [
            'date'   => $paid_date,
            'result' => $payment_status,
        ] );

        $invoice_parameter = [
            'external_id'          => $order->get_id(),
            'date'                 => $order->order_date,
            'currency'             => $order->get_currency(),
            'due_date'             => $order->order_date,
            'customer_external_id' => $order->get_user_id(),
            'line_items'           => $line_items,
            'transactions'         => [ $transaction ],
        ];

        $invoice = new ChartMogul\Invoice( $invoice_parameter );

        WP_CLI::success( 'Invoice Created Successfully.' );

        $customer_invoice_parameter = [
            'customer_uuid' => $customer->uuid,
            'invoices'      => [ $invoice ],
        ];

        ChartMogul\CustomerInvoices::create( $customer_invoice_parameter );

        return true;
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

        if ( ! empty( $this->id ) ) {

            $subscription = wcs_get_subscription( $this->id );
            if ( empty( $subscription ) ) {
                WP_CLI::error( 'Please pass valid subscription id.' );
            }

            $this->export_subscription_to_chartmogul( $this->id );

            WP_CLI::log( WP_CLI::colorize( '%yExport finished.%n' ) );

        } elseif ( ! empty( $this->fetch_all ) ) {

            $subscription_ids = $this->get_subscription_posts();

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
        $customer     = $this->create_customer( $subscription );
        $orders       = $subscription->get_related_orders();

        foreach ( $orders as $order_id ) {
            $order = wc_get_order( $order_id );

            if ( ! $this->dry_run ) {
                $this->create_invoice( $customer, $order, $subscription );
            }
        }

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
            /* translators: #%d: Subscription id */
            $cli_msg = sprintf(
                esc_html__( 'Subscription #%d would be sent to ChartMogul', 'cxl' ),
                esc_html( $subscription_id )
            );
        } else {
            /* translators: #%d: Subscription id */
            $cli_msg = sprintf(
                esc_html__( 'Subscription #%d sent to ChartMogul', 'cxl' ),
                esc_html( $subscription_id )
            );
        }

        WP_CLI::log( $cli_msg );
    }

}
