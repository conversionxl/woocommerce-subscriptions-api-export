<?php
/**
 * CLI commands to export subscriptions to ChartMogul.
 *
 * @package cxl
 */

namespace CXL_Upwork_01dd36a4283a21f14f\Commands;

use ChartMogul;
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
     * Variable to store tax in cents.
     */
    private int $accounting_tax = 0;

    /**
     * Variable to store subtotal in cents.
     */
    private int $accounting_subtotal = 0;

    /**
     * Variable to store total in cents.
     */
    private int $accounting_total = 0;

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
     * Variable for data source uuid.
     */
    private ?string $data_source_uuid = null;

    /**
     * Function to load other function on class initialize.
     *
     * @param array $args List of arguments pass with CLI command.
     * @param array $assoc_args List of associative arguments pass with CLI command.
     * @throws \Exception
     */
    public function __construct( array $args, array $assoc_args ) {

        $ping = $this->initialize_chartmogul();

        if ( 'pong!' !== $ping ) {

            WP_CLI::log( 'No ping to Chartmogul!' );
            exit;
        }

        $this->set_command_args( $args, $assoc_args );

        try {
            if ( $this->create_data_source ) {
                $this->create_chartmogul_data_source();
            } else {
                $this->export_subscriptions();
            }
        } catch ( \Throwable $e ) {
            WP_CLI::log( $e->getMessage() );
        }

    }

    /**
     * Function to set UUID for provided Data Source name.
     *
     * @param string $data_source_name ChartMogul Data Source name.
     * @return string UUID or null value.
     */
    private function getDataSourceUUIDbyName( string $data_source_name ): string {

        $all_data_sources   = ChartMogul\DataSource::all();
        $total_data_sources = count( $all_data_sources );
        $i                  = 0;

        for ( ; $i < $total_data_sources; $i++ ) {
            if ( $data_source_name === $all_data_sources[ $i ]->name ) {

                WP_CLI::log( 'Data source ' . $all_data_sources[ $i ]->name . ' UUID: ' . $all_data_sources[ $i ]->uuid );
                return $all_data_sources[ $i ]->uuid;
            }
        }

        return null;
    }

    /**
     * Function to set command arguments.
     *
     * @param array $args       List of arguments pass with CLI command.
     * @param array $assoc_args List of associative arguments pass with CLI command.
     */
    private function set_command_args( array $args, array $assoc_args ): void {

        // Check script mode.
        if ( array_key_exists( 'dry-run', $assoc_args ) ) {
            $this->dry_run = true;
        }

        // Check all parameter.
        if ( array_key_exists( 'all', $assoc_args ) ) {
            $this->fetch_all = true;
        }

        // Check all parameter.
        if ( array_key_exists( 'data-source', $assoc_args ) ) {
            $this->data_source      = strtolower( $assoc_args['data-source'] );
            $this->data_source_uuid = $this->getDataSourceUUIDbyName( trim( $this->data_source ) );

        }

        // Check all parameter.
        if ( array_key_exists( 'create-data-source', $assoc_args ) ) {

            $this->create_data_source = true;

            if ( array_key_exists( $this->data_source ) ) {
                WP_CLI::error( 'Please pass data source name using --data-source' );
            }
        }

        // Check id.
        if ( array_key_exists( 'id', $assoc_args ) && is_numeric( $assoc_args['id'] ) ) {
            $this->id = $assoc_args['id'];
        }
    }

    /**
     * Function to create data source ChartMogul.
     */
    private function create_chartmogul_data_source(): void {

        ChartMogul\DataSource::create([
            'name' => strtolower( $this->data_source ),
        ]);

        // Add validation if Data source is created / failed.
        // WP_CLI::log( 'Data source created successfully.' ).
    }

    /**
     * Function to create customer ChartMogul.
     *
     * @return object.
     */
    private function create_customer( WC_Subscription $subscription ): object {

        $customer = ChartMogul\Customer::findByExternalId( [
            'data_source_uuid' => $this->data_source_uuid,
            'external_id'      => $subscription->get_customer_id(),
        ] );

        
        if ( is_object( $customer ) ) {
            WP_CLI::log( 'Customer was already created' );
            return $customer;
        }
        

        $customer = ChartMogul\Customer::create( [
            'data_source_uuid' => $this->data_source_uuid,
            'external_id'      => $subscription->get_customer_id(),
            'name'             => $subscription->get_billing_first_name() . ' ' . $subscription->get_billing_last_name(),
            'email'            => $subscription->get_billing_email(),
            'country'          => $subscription->get_billing_country(),
            'city'             => $subscription->get_billing_city(),
        ] );

        WP_CLI::log( 'Customer created in ChartMogul.' );
        return $customer;
    }

    /**
     * Function to create plan in ChartMogul.
     *
     * @todo $product?
     */
    private function create_plan( $product ): bool {

        if ( 'subscription' !== $product->get_type() ) {
            return false;
        }

        $plan = ChartMogul\Plan::create( [
            'data_source_uuid' => $this->data_source_uuid,
            'name'             => $product->get_name(),
            'interval_count'   => get_post_meta( $product->get_id(), '_subscription_period_interval', true ),
            'interval_unit'    => get_post_meta( $product->get_id(), '_subscription_period', true ),
            'external_id'      => $product->get_id(),
        ] );

        WP_CLI::log( 'Plan Created Successfully.' );

        return $plan;
    }

    /**
     * Function to set accounting variables.
     */
    private function set_accounting_taxes_totals( $order_item_total_tax, $order_item_total ): void {

        /* rewrite in arrows */

        $this->accounting_tax      = (int) $order_item_total_tax * 100;
        $this->accounting_subtotal = (int) $order_item_total * 100;
        $this->accounting_total    = $this->accounting_subtotal + $this->accounting_tax;

    }

    /**
     * Function to create subscription in ChartMogul.
     */
    private function create_subscription( int $plan_id, $order_item, $order ) {

        $product = $order_item->get_product();

        if ( 'subscription' !== $product->get_type() ) {
            return $this->create_onetime_lineitem( $order_item, $order );
        }

        $this->set_accounting_taxes_totals( $order_item->get_total_tax(), $order_item->get_total() );

        $subscription_parameter = new ChartMogul\LineItems\Subscription([
            'subscription_external_id'     => $order->get_id(),
            'subscription_set_external_id' => $order->get_id(),
            'plan_uuid'                    => $plan_id,
            'service_period_start'         => get_post_meta( $order->get_id(), '_schedule_start', true ),
            'service_period_end'           => get_post_meta( $order->get_id(), '_schedule_end', true ),
            'amount_in_cents'              => $this->accounting_total,
            'quantity'                     => $order_item->get_quantity(),
            'tax_amount_in_cents'          => $this->accounting_tax,
        ]);

        return new ChartMogul\LineItems\Subscription( $subscription_parameter );
    }

    /**
     * Function to create one time line item in ChartMogul.
     */
    private function create_onetime_lineitem( $order_item ) {

        $product = $order_item->get_product();

        if ( 'subscription' === $product->get_type() ) {
            return false;
        }

        $this->set_accounting_taxes_totals( $order_item->get_total_tax(), $order_item->get_total() );

        $parameter = [
            'description'         => $order_item->get_name(),
            'amount_in_cents'     => $this->accounting_total,
            'quantity'            => $order_item->get_quantity(),
            'tax_amount_in_cents' => $this->accounting_tax,
        ];

        return new ChartMogul\LineItems\OneTime( $parameter );
    }

    /**
     * Function to create invoice in ChartMogul.
     */
    private function create_invoice( $customer, $order ) {

        $order_items = $order
            ? $order->get_items()
            : null;
        $line_items  = array();

        // Iterating through each "line" items in the order.
        if ( null !== $order_items ) {
            foreach ( $order_items as $item_id => $item ) {

                $product = $item->get_product();

                if ( 'subscription' !== $product->get_type() ) {
                    $line_items[] = $this->create_onetime_lineitem( $item );
                } else {
                    $plan         = $this->create_plan( $product );
                    $line_items[] = $this->create_subscription( $plan->uuid, $item, $order );
                }

            }
        }

        if ( 0 === count( $line_items ) ) {
            return true;
        }

        $paid_date      = $order->get_date_paid();
        $payment_status = 'failed';
        if ( strlen( $paid_date ) > 0 ) {
            $payment_status = 'successful';
            $paid_date      = $order->get_date_paid()->__toString();
        } else {
            // transactions.date can't be empty.
            // Must be an ISO 8601 formatted time. The timezone defaults to UTC unless otherwise specified. The time defaults to 00:00:00 unless specified otherwise.
            $paid_date = $order->get_date_created()->__toString();
        }

        $transaction = new ChartMogul\Transactions\Payment( [
            'date'   => $paid_date,
            'result' => $payment_status,
        ] );

        $invoice_parameter = [
            'external_id'          => $order->get_id(),
            'date'                 => $order->get_date_created()->date( 'Y-m-d H:i:s' ),
            'currency'             => $order->get_currency(),
            'due_date'             => $order->get_date_created()->date( 'Y-m-d H:i:s' ),
            'customer_external_id' => $order->get_user_id(),
            'line_items'           => $line_items,
            'transactions'         => [ $transaction ],
        ];

        $invoice = new ChartMogul\Invoice( $invoice_parameter );

        // WP_CLI::success( 'Invoice Created Successfully.' ).

        $customer_invoice_parameter = [
            'customer_uuid' => $customer->uuid,
            'invoices'      => [ $invoice ],
        ];

        // Detect if invoice exists already.
        $existing_invoices = ChartMogul\Invoice::all([
            'external_id' => $order->get_id(),
            'page'        => 1,
            'per_page'    => 200,
        ]);

        if ( 0 === $existing_invoices->total_pages ) {

            ChartMogul\CustomerInvoices::create( $customer_invoice_parameter );
        }

        /*
        WP_CLI::log( 'Invoice Created Successfully.' . print_r( $invoice, true ) ).
        WP_CLI::log( 'CI.' . print_r( $ci, true ) ).
        */
        return true;

    }

    /**
     * Function to initialize ChartMogul.
     *
     * @throws \Exception.
     */
    private function initialize_chartmogul(): string {

        // @todo constant option name + `get_option()`.
        ChartMogul\Configuration::getDefaultConfiguration()
            ->setAccountToken( '37803855593a9262c59b3b1fec5e88ae' )
            ->setSecretKey( '7bc86adc87d56a6371d11014ba0a9ad5' );

        return ChartMogul\Ping::ping()->data;
    }

    /**
     * Export subscriptions to ChartMogul.
     */
    private function export_subscriptions(): void {

        if ( $this->id > 0 ) {

            $subscription = wcs_get_subscription( $this->id );

            if ( ! is_object( $subscription ) ) {
                WP_CLI::error( 'Please pass valid subscription id.' );
            }
			
            $this->export_subscription_to_chartmogul( $this->id );

            WP_CLI::log( WP_CLI::colorize( '%yExport finished.%n' ) );

        } elseif ( true === $this->fetch_all ) {

            $i                   = 0;
            $subscriptions       = $this->get_subscription_posts();
            $total_subscriptions = count( $subscriptions );

            WP_CLI::log( 'Total subscriptions: [' . $total_subscriptions . ']' );

            foreach ( $subscriptions as $subscription ) {

                $subscription_id = $subscription->get_ID();
                // $progress_percentage = round( $i / $total_subscriptions * 100 ).
                // "Progress: $i/$total_subscriptions  [$progress_percentage%]   \r".
                // ++$i.
                $this->export_subscription_to_chartmogul( $subscription_id );

                usleep( 1 );
            }

            WP_CLI::log( WP_CLI::colorize( '%yExport finished.%n' ) );

        } else {
            WP_CLI::log( WP_CLI::colorize( '%yPlease either pass the subscription id or --all parameter.%n' ) );
        }
    }

    /**
     * Function to select subscriptions.
     *
     * @return array all subscriptions.
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
     */
    private function export_subscription_to_chartmogul( int $subscription_id ): void {

        $subscription = wcs_get_subscription( $subscription_id );
        $customer     = $this->create_customer( $subscription );
        $orders       = $subscription->get_related_orders();

        foreach ( $orders as $order_id ) {

            $order = wc_get_order( $order_id );
            $this->create_invoice( $customer, $order );
        }

        $this->add_cli_log( $subscription_id );

    }

    /**
     * Function to add CLI log.
     *
     * @param int $subscription_id Post ID.
     */
    private function add_cli_log( int $subscription_id ): void {

        if ( true === $this->dry_run ) {

            $cli_msg = sprintf(
                // translators: %d is replaced with "integer".
                esc_html__( 'Subscription #%d would be sent to ChartMogul', 'cxl' ),
                esc_html( $subscription_id )
            );
        } else {

            $cli_msg = sprintf(
                // translators: %d is replaced with "integer".
                esc_html__( 'Subscription #%d sent to ChartMogul', 'cxl' ),
                esc_html( $subscription_id )
            );
        }

        WP_CLI::log( $cli_msg );
    }

}
