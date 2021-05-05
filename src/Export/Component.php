<?php
/**
 * CLI commands to export subscriptions to ChartMogul.
 *
 * @package cxl
 */

namespace CXL\WC\ChartMogul\Export;

use Automattic\WooCommerce\Admin\Overrides\OrderRefund;
use ChartMogul;
use ChartMogul\Customer as CMCustomer;
use CXL\WC\ChartMogul\ChartMogul\Component as CMComponent;
use CXL\WC\ChartMogul\Tools\Logger\Component as Logger;
use CXL\WC\ChartMogul\WC\Memberships as WCMemberships;
use CXL\WC\ChartMogul\WP\Component as WPComponent;
use DateTime;
use Throwable;
use WC_Memberships_User_Membership;
use WC_Order;
use WC_Order_item;
use WC_Product;
use WC_Subscriptions_Order;
use WC_Subscriptions_Product;
use CXL\WC\ChartMogul\WC\Orders as WCOrders;
use CXL\WC\ChartMogul\WC\Subscriptions as WCSubscriptions;

/**
 * CLI commands to export subscriptions to ChartMogul.
 *
 * @psalm-suppress UndefinedClass
 */
class Component {

	/** @var bool Variable to store dry-run flag. */
	private bool $dry_run = false;

	/**
	 * @var int|null Variable for single subscription id.
	 */
	private ?int $subscription_id = null;

	/**
	 * @var int|null Variable for order id.
	 */
	private ?int $order_id = null;

	/**
	 * @var bool Variable to check if we need to proceed for all subscriptions.
	 */
	private bool $fetch_all_subscriptions = false;

	/**
	 * @var bool Variable to check if we need to proceed for all orders.
	 */
	private bool $fetch_all_orders = false;

	/**
	 * @var string $modifier A date/time string. Valid formats are explained in <a href="https://secure.php.net/manual/en/datetime.formats.php">Date and Time Formats</a>.
	 */
	private string $date_time_modifier = '-1 month';

	/**
	 * @var bool Variable to check if we need to create data source.
	 */
	private bool $create_data_source = false;

	/**
	 * @var string|null Variable for data source.
	 */
	private ?string $data_source = null;

	/**
	 * Variable for data source uuid.
	 */
	private ?string $data_source_uuid = null;

	/**
	 * Should customer be recorded as lead?
	 */
	private bool $is_lead = false;

	/**
	 * Did customer signup for trial?
	 */
	private bool $has_trial = false;

	/**
	 * Function to load other function on class initialize.
	 *
	 * @param  array $options
	 * @throws Throwable
	 */
	public function __construct( array $options = [] ) {

		// Setup class variables
		foreach ( array_keys( get_object_vars( $this ) ) as $key ) {
			if ( isset( $options[ $key ] ) ) {
				$this->$key = $options[ $key ];
			}
		}

		try {
			if ( $this->create_data_source ) {
				CMComponent::createDataSource( $this->data_source );
			} else {
				$this->exportToChartMogul();
			}
		} catch ( Throwable $e ) {
			Logger::log()->error( 'Exception thrown', [ 'Message' => $e->getMessage() ] );
		}

	}

	/**
	 * Export data such as orders, subscription etc to ChartMogul.
	 */
	private function exportToChartMogul(): void {

		if ( ! empty( $this->subscription_id ) ) {

			$subscription = wcs_get_subscription( $this->subscription_id );
			if ( ! $subscription ) {
				Logger::log()->critical( 'Please pass valid subscription id.', [ 'subscription_id' => $this->subscription_id ] );
				return;
			}

			// if customer has subscription, mark customer as lead.
			$this->is_lead = true;

			// did customer signup for trial?
			$this->has_trial = WCSubscriptions::hasTrial( $subscription, false );

			$this->exportSubscriptionToChartMogul( $this->subscription_id );

			Logger::log()->info( '%yExport finished.%n' );

		} elseif ( ! empty( $this->order_id ) ) {

			$this->createOrder( $this->order_id );

			Logger::log()->info( '%yExport finished.%n' );

		} elseif ( true === $this->fetch_all_subscriptions ) {

			$subscriptions = WCSubscriptions::getAllSubscriptions();

			foreach ( $subscriptions as $subscription ) {
				// Update post meta.
				$this->exportSubscriptionToChartMogul( $subscription->get_ID() );
			}

			Logger::log()->info( '%yExport finished.%n' );

		} elseif ( true === $this->fetch_all_orders ) {

			foreach ( WCOrders::getOrdersByDateCreated( $this->date_time_modifier ) as $order_id ) {
				$this->createOrder( $order_id );
			}

			Logger::log()->info( '%yExport finished.%n' );

		} else {
			Logger::log()->info( '%yPlease either pass the subscription id or --all-subscriptions parameter.%n' );
		}
	}

	/**
	 * Export order to ChartMogul
	 */
	private function createOrder( int $order_id ): void {

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			Logger::log()->critical( 'Please pass valid order id.', [ 'order_id' => $order_id ] );
			return;
		} elseif ( ! in_array( $order->get_status(), [ 'completed', 'failed', 'refunded' ], true ) ) {
			Logger::log()->critical(
				'Please pass order with allowed status.',
				[
					'current_status'   => $order->get_status(),
					'allowed_statuses' => [ 'completed', 'failed', 'refunded' ],
				] );
			return;
		}

		$subscription = WCSubscriptions::getSubscriptionForOrder( $order->get_id() );

		if ( $subscription ) {
			// if customer has subscription, mark customer as lead.
			$this->is_lead = true;

			// did customer signup for trial?
			$this->has_trial = WCSubscriptions::hasTrial( $subscription, false );

			$this->exportSubscriptionToChartMogul( $subscription->get_id() );
		} else {
			Logger::log()->notice( 'Order does not contain subscription.', [ 'order_id' => $order_id ] );

			// if order total is $0, then mark customer as lead.
			if ( 0 === absint( $order->get_total() ) ) {
				$this->is_lead = true;
			}

			// did customer signup for trial?
			$this->has_trial = false;

			$this->exportOrderToChartMogul( $order );
		}
	}

	/**
	 * Function to create customer in ChartMogul.
	 */
	private function createCustomer( WC_Order $order ): object {

		Logger::log()->info( 'Creating customer', [ 'order_id' => $order->get_id() ] );

		$customer_data = [
			'data_source_uuid' => $this->data_source_uuid,
			'external_id'      => $order->get_customer_id(),
			'name'             => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'email'            => $order->get_billing_email(),
			'country'          => $order->get_billing_country(),
			'city'             => $order->get_billing_city(),
		];

		$user = WPComponent::getUser( $order->get_customer_id() );

		if ( $user && $user->user_registered && $this->is_lead ) {
			$customer_data['lead_created_at'] = mysql2date( __( 'Y-m-d H:i:s' ), $user->user_registered );
		}

		$subscription = WCSubscriptions::getSubscriptionForOrder( $order->get_id() );

		// Checks if subscription started with trial.
		if ( $subscription && $this->has_trial && $subscription->get_date( 'start' ) ) {
			$customer_data['free_trial_started_at'] = $subscription->get_date( 'start' );
		}

		$customer = CMComponent::findCustomerByExternalId( $this->data_source_uuid, $order->get_customer_id() );

		if ( $customer ) {
			// update require customer_uuid
			$customer = CMCustomer::update(
				[
					'customer_uuid' => $customer->uuid,
				],
				$customer_data
			);
			Logger::log()->info( 'Customer updated.' );
		} else {
			$customer = CMCustomer::create( $customer_data );
			Logger::log()->info( 'Customer created.' );
		}

		$memberships = WCMemberships::getMembershipsByCustomerID( $order->get_customer_id() );

		if ( $memberships ) {
			$memberships = WCMemberships::sortMembershipsByOrderDate( $memberships );

			$membership = $memberships[0];

			// @todo: maybe use updateCustomAttributes()
			$customer->addCustomAttributes(
				[
					'type'  => 'Integer',
					'key'   => 'id',
					'value' => $membership->get_id(),
				],
				[
					'type'  => 'String',
					'key'   => 'membership',
					'value' => $membership->get_plan()->get_name(),
				]
			);

			foreach ( $memberships as $membership ) {
				// Add tags to customer, such as memberships / plans etc.
				$customer->addTags( $membership->get_plan()->get_name() );
			}
		}

		Logger::log()->info( 'Customer created / retrieved.' );

		return $customer;
	}

	/**
	 * Function to create plan in ChartMogul.
	 *
	 * @param \WC_Product $product WooCommerce Product Object.
	 * @param \WC_Order   $order WooCommerce Order Object.
	 */
	private function create_plan( WC_Product $product, WC_Order $order ): object {

		// Retrieve Plan UUID if plan is already pushed in ChartMogul.
		$plan = ChartMogul\Plan::all( [
			'data_source_uuid' => $this->data_source_uuid,
			'external_id'      => $product->get_id(),
		] )->first();

		if ( ! $plan ) {
			// Sane defaults
			$interval_count = 10;
			$interval_unit  = 'year';
			if ( WCSubscriptions::getSubscriptionForOrder( $order->get_id() ) ) {
				// Subscriptions Plan
				$interval_count = (int) get_post_meta( $product->get_id(), '_subscription_period_interval', true );
				$interval_unit  = get_post_meta( $product->get_id(), '_subscription_period', true );
			} elseif ( has_term( [ 'subscriptions' ], 'product_cat', $product->get_id() ) ) {
				// Foundations Plan
				$interval_count = 10;
				$interval_unit  = 'year';
			} elseif ( has_term( [ 'minidegrees' ], 'product_cat', $product->get_id() ) ) {
				// Mini-degrees plan
				$interval_count = 1;
				$interval_unit  = 'year';
			}

			$plan = ChartMogul\Plan::create( [
				'data_source_uuid' => $this->data_source_uuid,
				'name'             => $product->get_name(),
				'interval_count'   => $interval_count,
				'interval_unit'    => $interval_unit,
				'external_id'      => $product->get_id(),
			] );

			Logger::log()->info( 'Plan Created.' );
		}

		Logger::log()->info( 'Plan Created / Retrieved Successfully.' );

		return $plan;
	}

	/**
	 * Function to create subscription in ChartMogul.
	 *
	 * @param string         $plan_id      ChartMogul Plan id.
	 * @param \WC_Order_item $order_item Order Item.
	 * @param \WC_Order      $order Current Order.
	 * @retun Object
	 */
	private function create_subscription(
		string $plan_id,
		WC_Order_item $order_item,
		WC_Order $order,
		$refund_type
	): object {

		$subscription = WCSubscriptions::getSubscriptionForOrder( $order->get_id() );

		$service_period_start = $subscription->get_date( 'start' );
		$service_period_end = $subscription->get_date( 'next_payment' )?: $subscription->get_date( 'end' );

		$subscription_data = [
			'subscription_external_id'     => $subscription->get_id(),
			'subscription_set_external_id' => $subscription->get_id(),
			'service_period_start'         => $service_period_start,
			'service_period_end'           => $service_period_end,
			'plan_uuid'                    => $plan_id,
			'type'                         => 'subscription',
		];

		if ( 'none' === $refund_type ) {
			$amounts = $this->get_payment_amount( $order_item->get_total_tax(), $order_item->get_total() );

			$subscription_data['quantity'] = $order_item->get_quantity();
		} else {
			$amounts = $this->get_refund_amount( $order_item->get_total_tax(), $order_item->get_total() );

			if ( 'past' === $refund_type ) {
				$subscription_data['prorated'] = true;
			}

			$subscription_data['quantity'] = $order_item->get_quantity();
		}

		$subscription_data['amount_in_cents']     = $amounts['amount_in_cents'];
		$subscription_data['tax_amount_in_cents'] = $amounts['tax_amount_in_cents'];

		Logger::log()->info( 'subscription created.' );

		return new ChartMogul\LineItems\Subscription( $subscription_data );
	}

	/**
	 * Function to create one time line item in ChartMogul.
	 *
	 * @param string                                                             $plan_id    ChartMogul Plan id.
	 * @param WC_Order_item|OrderRefund $order_item Order Item.
	 * @retun Object
	 */
	private function create_onetime_lineitem( string $plan_id, $order_item, $refund_type ) {

		Logger::log()->info(
			'create_onetime_lineitem ',
			[
				'order_item_or_refund_item_id' => $order_item->get_id(),
				'refund_type'                  => $refund_type,
			]
		);

		$amounts = $this->get_payment_amount( $order_item->get_total_tax(), $order_item->get_total() );

		$one_time_data = [
			'plan_uuid'           => $plan_id,
			'amount_in_cents'     => $amounts['amount_in_cents'],
			'tax_amount_in_cents' => $amounts['tax_amount_in_cents'],
		];

		if ( $order_item instanceof OrderRefund ) {
			$description  = '';
			$who_refunded = WPComponent::getUser( $order_item->get_refunded_by() );

			// wc_price('-' . $refund->get_amount(), array('currency' => $order->get_currency()))
			if ( $who_refunded->exists() ) {
				$description = sprintf(
					/* translators: 1: refund id 2: refund date 3: username */
					esc_html__( 'Refund #%1$s - %2$s by %3$s', 'woocommerce' ),
					esc_html( $order_item->get_id() ),
					esc_html( wc_format_datetime( $order_item->get_date_created(), get_option( 'date_format' ) . ', ' . get_option( 'time_format' ) ) ),
					sprintf(
						'<abbr class="refund_by" title="%1$s">%2$s</abbr>',
						/* translators: 1: ID who refunded */
						sprintf( esc_attr__( 'ID: %d', 'woocommerce' ), absint( $who_refunded->ID ) ),
						esc_html( $who_refunded->display_name )
					)
				);
			} else {
				$description = sprintf(
					/* translators: 1: refund id 2: refund date */
					esc_html__( 'Refund #%1$s - %2$s', 'woocommerce' ),
					esc_html( $refund->get_id() ),
					esc_html( wc_format_datetime( $order_item->get_date_created(), get_option( 'date_format' ) . ', ' . get_option( 'time_format' ) ) )
				);
			}

			if ( $order_item->get_reason() ) {
				$description .= esc_html( $order_item->get_reason() );
			}

			$one_time_data['description'] = $description;
		} else {

			$one_time_data['quantity']    = $order_item->get_quantity();
			$one_time_data['description'] = $order_item->get_name();
		}

		return new ChartMogul\LineItems\OneTime( $one_time_data );
	}

	/**
	 * Function to create invoice for payments in ChartMogul.
	 *
	 * @param Object    $customer     ChartMogul Customer.
	 * @param \WC_Order $order WooCommerce Order.
	 * @retun bool
	 */
	private function create_payment_invoice( object $customer, WC_Order $order, $refund_type ) {

		$line_items = $this->get_line_items( $order, $refund_type );

		if ( 0 === count( $line_items ) ) {
			Logger::log()->debug( 'no $line_items found, so bailing early.' );
			return true;
		}

		$paid_date = $order->get_date_paid()
			?: $order->get_date_created(); // $order->get_date_completed()

		$payment_status = 'failed';
		if ( in_array( $order->get_status(), [ 'completed', 'refunded' ] ) ) {
			$payment_status = 'successful';
		}

		Logger::log()->info( sprintf( 'payment_status %s.', $payment_status ) );

		$transaction = new ChartMogul\Transactions\Payment( [
			'date'   => $paid_date->date( 'Y-m-d H:i:s' ),
			'result' => $payment_status,
		] );

		$invoice = new ChartMogul\Invoice( [
			'external_id'          => $order->get_id(),
			'date'                 => $order->get_date_created()->date( 'Y-m-d H:i:s' ),
			'currency'             => $order->get_currency(),
			'due_date'             => $order->get_date_created()->date( 'Y-m-d H:i:s' ),
			'customer_external_id' => $order->get_user_id(),
			'line_items'           => $line_items,
			'transactions'         => [ $transaction ],
		] );

		Logger::log()->info( 'Payment Invoice Created Successfully.' );

		$customer_invoice_parameter = [
			'customer_uuid' => $customer->uuid,
			'invoices'      => [ $invoice ],
		];

		// Invoice already exists?.
		$invoice_exists = ChartMogul\Invoice::all([
			'external_id' => $order->get_id(),
			'page'        => 1,
			'per_page'    => 200,
		]);

		if ( 0 === $invoice_exists->total_pages ) {
			ChartMogul\CustomerInvoices::create( $customer_invoice_parameter );
		}

		return true;
	}

	/**
	 * Function to create invoice for refunds in ChartMogul.
	 *
	 * @param Object    $customer     ChartMogul Customer.
	 * @param \WC_Order $order WooCommerce Order.
	 * @retun bool
	 */
	private function create_refund_invoice( object $customer, WC_Order $order, $refund_type ): bool {
		$_order = $order;

		if ( 'none' === $refund_type ) {
			Logger::log()->info(
				'not creating refunds invoice, bailing early',
				[
					'order_id'    => $_order->get_id(),
					'refund_type' => $refund_type,
				]
			);

			return true;
		}

		Logger::log()->info(
			'creating refunds invoice',
			[
				'order_id'    => $_order->get_id(),
				'refund_type' => $refund_type,
			]
		);

		$line_items = $this->get_refund_line_items( $_order, $customer, $refund_type );

		if ( 0 === count( $line_items ) ) {
			Logger::log()->debug( 'no $line_items found, so bailing early.' );
			return true;
		}

		$refunds = $order->get_refunds();
		$order   = current( $refunds );

		$paid_date = $order->get_date_created()
			?: $order->get_date_paid(); // $order->get_date_completed()

		$payment_status = 'failed';
		if ( in_array( $order->get_status(), [ 'completed', 'refunded' ] ) ) {
			$payment_status = 'successful';
		}

		Logger::log()->info( sprintf( 'payment_status %s.', $payment_status ) );

		$transaction = new ChartMogul\Transactions\Refund( [
			'date'   => $paid_date->date( 'Y-m-d H:i:s' ),
			'result' => $payment_status,
		] );

		$invoice = new ChartMogul\Invoice( [
			'external_id'          => $order->get_id(),
			'date'                 => $order->get_date_created()->date( 'Y-m-d H:i:s' ),
			'currency'             => $order->get_currency(),
			'customer_external_id' => $order->get_user_id(),
			'line_items'           => $line_items,
			'transactions'         => [ $transaction ],
		] );

		Logger::log()->info( 'Invoice Created Successfully.' );

		$customer_invoice_parameter = [
			'customer_uuid' => $customer->uuid,
			'invoices'      => [ $invoice ],
		];

		// Invoice already exists?.
		$invoice_exists = ChartMogul\Invoice::all([
			'external_id' => $order->get_id(),
			'page'        => 1,
			'per_page'    => 200,
		]);

		if ( 0 === $invoice_exists->total_pages ) {
			ChartMogul\CustomerInvoices::create( $customer_invoice_parameter );
		}

		return true;
	}

	private function get_line_items( WC_Order $order, $refund_type ) {
		$order_items = $order
			? $order->get_items()
			: [];
		$line_items  = [];

		Logger::log()->info( sprintf( 'get_line_items from order ID: %s.', $order->get_id() ) );

		// Iterating through each "line" items in the order.
		foreach ( $order_items as $item ) {

			$product = $item->get_product();

			Logger::log()->info( sprintf( 'foreach $order_items product id: %s / product type: %s .', $product->get_id(), $product->get_type() ) );

			$plan = $this->create_plan( $product, $order );

			Logger::log()->info( sprintf( 'item product id: %s / item signup fee: %s / product signup fee: %s.', $item->get_product_id(), WC_Subscriptions_Order::get_sign_up_fee( $order ), WC_Subscriptions_Product::get_sign_up_fee( $product ) ) );

			$has_trial_signup = ( WC_Subscriptions_Order::get_sign_up_fee( $order ) > 0 );
			// $has_trial_signup = ( WC_Subscriptions_Order::get_sign_up_fee( $order ) === WC_Subscriptions_Product::get_sign_up_fee( $product ) );

			if ( WC_Subscriptions_Product::is_subscription( $product ) && ! $has_trial_signup ) {
				$line_items[] = $this->create_subscription( $plan->uuid, $item, $order, $refund_type );

				Logger::log()->info( 'create_plan & create_subscription is called.' );
			} else {
				$line_items[] = $this->create_onetime_lineitem( $plan->uuid, $item, $refund_type );

				Logger::log()->info( 'create_onetime_lineitem is called.' );
			}

		}

		return $line_items;
	}

	private function get_refund_line_items( WC_Order $order, $customer, $refund_type ) {
		$order_refunds = $order
			? $order->get_refunds()
			: [];
		$line_items    = [];

		$plan = $this->retrieve_plan( $order, $customer );

		Logger::log()->info( sprintf( 'get_refund_line_items from order ID: %s.', $order->get_id() ) );
		$subscription = WCSubscriptions::getSubscriptionForOrder( $order->get_id() );

		// Iterating through each "line" items in the order.
		foreach ( $order_refunds as $item ) {

			Logger::log()->info( sprintf( 'foreach $refund_items refund id: %s.', $item->get_id() ) );

			if ( $subscription ) {
				$line_items[] = $this->create_subscription( $plan->uuid, $item, $order, $refund_type );

				Logger::log()->info( 'create_plan & create_subscription is called.' );
			} else {
				$line_items[] = $this->create_onetime_lineitem( $plan->uuid, $item, $refund_type );

				Logger::log()->info( 'create_onetime_lineitem is called.' );
			}

		}

		return $line_items;
	}

	private function retrieve_plan( $order, $customer ) {

		$customer_invoices = ChartMogul\CustomerInvoices::all([
			'customer_uuid' => $customer->uuid,
		])->toArray();

		$invoice = array_filter( $customer_invoices['invoices'], static fn( $customer_invoice ) => $order->get_id() === absint( $customer_invoice['external_id'] ) );

		$invoice = current( $invoice );
		$invoice = current( $invoice['line_items'] );

		return (object) [
			'uuid' => $invoice['plan_uuid'],
		];
	}

	/**
	 * Export single subscription to ChartMogul.
	 *
	 * @param \WC_Order $order Order.
	 */
	private function exportOrderToChartMogul( WC_Order $order ): void {

		$customer = $this->createCustomer( $order );

		Logger::log()->info( sprintf( 'Exporting order id: %d, for customer uuid: %s', $order->get_id(), $customer->uuid ) );

		// make sure to check it before creating payment invoice.
		$refund_type = $this->getOrderRefundType( $customer, $order );

		$this->create_payment_invoice( $customer, $order, $refund_type );
		$this->create_refund_invoice( $customer, $order, $refund_type );

		$this->add_cli_log( $order->get_id(), 'order' );
	}

	/**
	 * Export subscription to ChartMogul.
	 *
	 * @param int $subscription_id Subscription ID.
	 */
	private function exportSubscriptionToChartMogul( int $subscription_id ): void {

		$subscription = wcs_get_subscription( $subscription_id );

		$orders = $subscription->get_related_orders();
		$order  = current( $orders );
		$order  = wc_get_order( $order );

		if ( ! $order ) {
			Logger::log()->critical( sprintf( 'No orders found, related to subscription %d', $subscription->get_id() ) );
			return;
		}

		$customer = $this->createCustomer( $order );

		if ( 'cancelled' === $subscription->get_status() ) {

			$chartmogul_subscription_data = CMComponent::getSubscription( $customer->uuid, $subscription );

			// In case subscription was not already created on ChartMogul, create it first before cancelling.
			if ( ! $chartmogul_subscription_data ) {
				$this->exportSubscriptionRelatedOrdersToChartMogul( $orders, $customer, $subscription_id );

				$chartmogul_subscription_data = CMComponent::getSubscription( $customer->uuid, $subscription );
			}

			if ( $chartmogul_subscription_data ) {
				$chartmogul_subscription = new ChartMogul\Subscription([
					'uuid' => $chartmogul_subscription_data->uuid,
				]);

				$chartmogul_subscription->cancel( $subscription->get_date( 'cancelled' ) );

				Logger::log()->info( 'Subscription cancelled.' );

				return;
			}
		}

		$this->exportSubscriptionRelatedOrdersToChartMogul( $orders, $customer, $subscription_id );
	}

	/**
	 * Export subscription's related orders to ChartMogul.
	 *
	 * @param array $orders WooCommerce Orders id array.
	 * @param CMCustomer $customer ChartMogul Customer.
	 * @param int $subscription_id Subscription ID.
	 * @return void
	 */
	private function exportSubscriptionRelatedOrdersToChartMogul( array $orders, CMCustomer $customer, int $subscription_id ): void {
		foreach ( $orders as $order_id ) {
			$order = wc_get_order( $order_id );

			// make sure to check it before creating payment invoice.
			$refund_type = $this->getOrderRefundType( $customer, $order );

			$this->create_payment_invoice( $customer, $order, $refund_type );
			$this->create_refund_invoice( $customer, $order, $refund_type );
		}

		$this->add_cli_log( $subscription_id );
	}

	/**
	 * Function to add CLI log.
	 *
	 * @param int $id Post ID (Subscription or Order).
	 */
	private function add_cli_log( int $id, string $log_type = 'subscription' ): void {

		$log_name = 'Subscription';
		if ( 'order' === $log_type ) {
			$log_name = 'Subscription';
		}

		if ( true === $this->dry_run ) {
			$cli_msg = sprintf(
			/* translators: #%d: Subscription / Order id */
				esc_html__( '%1$s #%2$d would be sent to ChartMogul', 'cxl' ),
				$log_name,
				esc_html( $id )
			);
		} else {
			$cli_msg = sprintf(
			/* translators: #%d: Subscription / Order id */
				esc_html__( '%1$s #%2$d sent to ChartMogul', 'cxl' ),
				$log_name,
				esc_html( $id )
			);
		}

		Logger::log()->info( $cli_msg );
	}

	/**
	 * Helper to convert payment amount to cents, including taxes.
	 */
	private function get_payment_amount( $order_item_total_tax, $order_item_total ): array {
		return [
			'tax_amount_in_cents' => (int) $order_item_total_tax * 100,
			'amount_in_cents'     => (int) $order_item_total * 100 + (int) $order_item_total_tax * 100,
		];
	}

	/**
	 * Helper to convert refund amount to cents, including taxes.
	 */
	private function get_refund_amount( $order_item_total_tax, $order_item_total ): array {
		return [
			'tax_amount_in_cents' => (int) $order_item_total_tax * 100,
			'amount_in_cents'     => (int) $order_item_total * 100 + (int) $order_item_total_tax * 100,
		];
	}

	/**
	 * Helper function to determine the refund type, based on which refund data will be sent to ChartMogul.
	 *
	 * @param object $customer
	 * @param WC_Order $order
	 *
	 * @return string
	 */
	private function getOrderRefundType( object $customer, WC_Order $order ): string {

		Logger::log()->info( 'Retrieve order refund type.' );

		$refund_type = 'none';
		if ( ! WCOrders::hasRefunds( $order ) ) {
			return $refund_type;
		}

		$customer_invoices = ChartMogul\CustomerInvoices::all([
			'customer_uuid' => $customer->uuid,
		])->toArray();

		$invoice = array_filter( $customer_invoices['invoices'], static fn( $customer_invoice ) => $order->get_id() === absint( $customer_invoice['external_id'] ) );

		$refund_type = 'past';

		// order was fully refunded & invoice was sent to chartmogul already.
		if ( 'refunded' === $order->get_status() && ! empty( $invoice ) ) {
			$refund_type = 'real-time';
		} elseif ( $invoice ) {
			$refund_type = 'partial';
		}

		return $refund_type;
	}

}
