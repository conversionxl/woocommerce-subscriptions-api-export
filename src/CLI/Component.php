<?php
/**
 * CXL CLI component.
 *
 * Subscription export CLI commands for CXL.
 *
 * @package   CXL
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 */

namespace CXL\WC\ChartMogul\CLI;

use CXL\WC\ChartMogul\ChartMogul\Component as CMComponent;
use CXL\WC\ChartMogul\Export\Component as ExportComponent;
use CXL\WC\ChartMogul\Tools\Logger\Component as Logger;
use WP_CLI_Command;

/**
 * @inheritDoc
 * @since  2021.05.27
 */
class Component extends WP_CLI_Command {

	/**
	 * Export subscriptions / orders to ChartMogul.
	 *
	 * [--subscription-id]
	 * : Subscription ID.
	 *
	 * [--order-id]
	 * : Order ID.
	 *
	 * [--all-subscriptions]
	 * : To run script for all subscriptions.
	 *
	 * [--all-orders]
	 * : To run script for all orders.
	 *
	 * [--date-time-modifier]
	 * : A date/time string. Valid formats are explained in <a href="https://secure.php.net/manual/en/datetime.formats.php">Date and Time Formats</a>.
	 *
	 * [--data-source]
	 * : Run for specific data source, pass the datasource UUID from ChartMogul.
	 *
	 * [--create-data-source]
	 * : To create data source.
	 *
	 * ## EXAMPLES
	 *
	 *     # Export single subscription to ChartMogul.
	 *     $ wp cxl shop-export-to-chartmogul --subscription-id=25 --data-source=xxxxxx
	 *     Subscription #25 sent to ChartMogul.
	 *
	 *     # Export single subscription to ChartMogul.
	 *     $ wp cxl shop-export-to-chartmogul --order-id=25 --data-source=xxxxxx
	 *     Subscription ### sent to ChartMogul.
	 *
	 *     # Export all subscriptions to ChartMogul.
	 *     $ wp cxl shop-export-to-chartmogul --all-subscriptions --data-source=xxxxxx
	 *     Subscription #25 sent to ChartMogul.
	 *
	 *     # Export all orders to ChartMogul.
	 *     $ wp cxl shop-export-to-chartmogul --all-orders --data-source=xxxxxx
	 *     Order #25 sent to ChartMogul.
	 *
	 *     # Create Datasource in ChartMogul.
	 *     $ wp cxl shop-export-to-chartmogul --create-data-source=true --data-source=new-datasource
	 *     Success: Data source created successfully.
	 *
	 * @todo  Separate command for create data source.
	 * @todo  Separate command for create plan.
	 * @param array $args
	 * @param array $assoc_args
	 * @subcommand shop-export-to-chartmogul
	 */
	public function shopExportToChartMogul( array $args, array $assoc_args ): void {
		try {
			$ping = CMComponent::initialize_chartmogul();

			if ( 'pong!' !== $ping ) {
				Logger::log()->error( 'No ping to ChartMogul!' );
			}

			new ExportComponent( $this->parseCommandArgs( $args, $assoc_args ) );
		} catch ( \Throwable $e ) {
			Logger::log()->error( $e->getMessage() );
		}
	}

	/**
	 * Function to set command arguments.
	 *
	 * @param array $args       List of arguments pass with CLI command.
	 * @param array $assoc_args List of associative arguments pass with CLI command.
	 */
	private function parseCommandArgs( array $args, array $assoc_args ): array {
		$options = [];

		// Check script mode.
		if ( ! empty( $assoc_args['dry-run'] ) ) {
			$options['dry_run'] = true;
		}

		// Check all subscriptions parameter.
		if ( ! empty( $assoc_args['all-subscriptions'] ) ) {
			$options['fetch_all_subscriptions'] = true;
		}

		// Check all orders parameter.
		if ( ! empty( $assoc_args['all-orders'] ) ) {
			$options['fetch_all_orders'] = true;
		}

		// Check date time modifier.
		if ( ! empty( $assoc_args['date-time-modifier'] ) ) {
			$options['date_time_modifier'] = $assoc_args['date-time-modifier'];
		}

		// Check all parameter.
		if ( ! empty( $assoc_args['data-source'] ) ) {
			$options['data_source']      = trim( strtolower( $assoc_args['data-source'] ) );
			$options['data_source_uuid'] = CMComponent::getDataSourceUUIDbyName( $options['data_source'] );
		}

		// Check all parameter.
		if ( ! empty( $assoc_args['create-data-source'] ) ) {
			$options['create_data_source'] = true;

			if ( empty( $options['data_source'] ) ) {
				Logger::log()->error( 'Please pass data source name using --data-source' );
			}
		}

		// Check subscription id.
		if ( ! empty( $assoc_args['subscription-id'] ) ) {
			$options['subscription_id'] = absint( $assoc_args['subscription-id'] );
		}

		// Check order id.
		if ( ! empty( $assoc_args['order-id'] ) ) {
			$options['order_id'] = absint( $assoc_args['order-id'] );
		}

		return $options;
	}

}
