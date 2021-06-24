<?php
/**
 * CXL Query component.
 *
 * Subscription export URL Query commands for CXL.
 *
 * @package   CXL
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 */

namespace CXL\WC\ChartMogul\Query;

use CXL\WC\ChartMogul\ChartMogul\Component as CMComponent;
use CXL\WC\ChartMogul\Export\Component as ExportComponent;
use CXL\WC\ChartMogul\Tools\Logger\Component as Logger;
use Hybrid\Contracts\Bootable;

/** @since  2021.06.15 */
class Component implements Bootable {

	/** @inheritDoc */
	public function boot() {
		add_filter( 'query_vars', [ $this, 'addQueryArgs' ], 10 );
		add_action( 'wp', [ $this, 'shopExportToChartMogul' ], 10 );
	}

	/**
	 * Add query args to WP_Query.
	 *
	 * @since 2021.06.15
	 * @see https://developer.wordpress.org/reference/functions/get_query_var/
	 */
	public function addQueryArgs( array $args ): array {

		foreach (
			[ 'shop-export-to-chartmogul', 'subscription-id', 'order-id', 'all-subscriptions', 'all-orders', 'date-time-modifier', 'data-source', 'create-data-source' ]
			as $arg
		) {
			$args[] = $arg;
		}

		return $args;
	}

	/**
	 * Export subscriptions / orders to ChartMogul.
	 *
	 * subscription-id=sid
	 * Subscription ID.
	 *
	 * order-id=oid
	 * Order ID.
	 *
	 * all-subscriptions=true
	 * To run script for all subscriptions.
	 *
	 * all-orders=true
	 * To run script for all orders.
	 *
	 * date-time-modifier=-1 month
	 * A date/time string. Valid formats are explained in <a href="https://secure.php.net/manual/en/datetime.formats.php">Date and Time Formats</a>.
	 *
	 * data-source=some-data-source
	 * Run for specific data source, pass the datasource UUID from ChartMogul.
	 *
	 * create-data-source=true
	 * To create data source.
	 *
	 * ## EXAMPLES
	 *
	 *     # Export single subscription to ChartMogul.
	 *     URL: url?shop-export-to-chartmogul=true&subscription-id=25&data-source=xxxxxx
	 *     Subscription #25 sent to ChartMogul.
	 *
	 *     # Export single subscription to ChartMogul.
	 *     URL: url?shop-export-to-chartmogul=true&order-id=25&data-source=xxxxxx
	 *     Subscription ### sent to ChartMogul.
	 *
	 *     # Export all subscriptions to ChartMogul.
	 *     URL: url?shop-export-to-chartmogul=true&all-subscriptions=true&data-source=xxxxxx
	 *     Subscription #25 sent to ChartMogul.
	 *
	 *     # Export all orders to ChartMogul.
	 *     URL: url?shop-export-to-chartmogul=true&all-orders=true&data-source=xxxxxx
	 *     Order #25 sent to ChartMogul.
	 *
	 *     # Create Datasource in ChartMogul.
	 *     URL: url?shop-export-to-chartmogul=true&create-data-source=true&data-source=new-datasource
	 *     Success: Data source created successfully.
	 */
	public function shopExportToChartMogul(): void {

		if ( ! get_query_var( 'shop-export-to-chartmogul', false ) ) {
			Logger::log()->debug( 'Bailing!. No export query string found.' );
			return;
		}

		Logger::log()->debug( 'Export initiated by Query string' );

		try {
			$ping = CMComponent::initialize_chartmogul();

			if ( 'pong!' !== $ping ) {
				Logger::log()->error( 'No ping to Chartmogul!' );
			}

			new ExportComponent( $this->parseQueryArgs() );
		} catch ( \Throwable $e ) {
			Logger::log()->error( $e->getMessage() );
		}
	}

	/**
	 * Fetch query params from WP_Query and execute.
	 *
	 * @since 2021.06.15
	 * @see https://developer.wordpress.org/reference/functions/get_query_var/
	 */
	public function parseQueryArgs(): array {

		$options = [];

		// Check script mode.
		if ( get_query_var( 'dry-run', false ) ) {
			$options['dry_run'] = true;
		}

		// Check all subscriptions parameter.
		if ( get_query_var( 'all-subscriptions', false ) ) {
			$options['fetch_all_subscriptions'] = true;
		}

		// Check all orders parameter.
		if ( get_query_var( 'all-orders', false ) ) {
			$options['fetch_all_orders'] = true;
		}

		// Check date time modifier.
		if ( $modifier = get_query_var( 'date-time-modifier', '-1 month' ) ) {
			$options['date_time_modifier'] = $modifier;
		}

		// Check all parameter.
		if ( $data_source = get_query_var( 'data-source', null ) ) {
			$options['data_source']      = trim( strtolower( $data_source ) );
			$options['data_source_uuid'] = CMComponent::getDataSourceUUIDbyName( $options['data_source'] );
		}

		// Check create-data-source parameter.
		if ( get_query_var( 'create-data-source', false ) ) {
			$options['create_data_source'] = true;

			if ( empty( $options['data_source'] ) ) {
				Logger::log()->error( 'Please pass data source name using --data-source' );
			}
		}

		// Check subscription id.
		if ( $subscription_id = get_query_var( 'subscription-id', null ) ) {
			$options['subscription_id'] = absint( $subscription_id );
		}

		// Check order id.
		if ( $order_id = get_query_var( 'order-id', null ) ) {
			$options['order_id'] = absint( $order_id );
		}

		return $options;
	}

}
