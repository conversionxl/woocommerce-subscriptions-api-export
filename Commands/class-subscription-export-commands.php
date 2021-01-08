<?php
/**
 * CLI commands for CXL
 */
namespace CXL_Upwork_01dd36a4283a21f14f;

class Subscription_Export_Commands extends \CLI_Command {

	/**
	 * Export subscription order to ChartMogul.
	 *
	 * [--id]
	 * : Subscription Order ID
	 *
	 * [--all]
	 * : To run script for all orders.
	 *
	 * ## EXAMPLES
	 *
	 *     # Export order to ChartMogul.
	 *     $ wp cxl shop-subscription-export-chartmogul --id=25
	 *     Order #25 sent to ChartMogul.
	 *
	 * @subcommand shop-subscription-export-chartmogul
	 */
	public function shop_subscription_chartmogul_export( $args, $assoc_args ): void {

		if ( class_exists( Subscription_Export_ChartMogul_Command::class ) ) {
			new Subscription_Export_ChartMogul_Command( $args, $assoc_args );
		} else {
			WP_CLI::warning( esc_html__( '`Subscription_Chartmogul_Export` class is not available.', 'cxl' ) );
		}
	}

}
