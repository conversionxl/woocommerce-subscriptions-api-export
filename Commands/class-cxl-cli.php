<?php
/**
 * CLI commands for CXL
 */
class CLX_CLI extends WP_CLI_Command {

	/**
	 * Export subscription order to chartmogul
	 *
	 * [id]
	 * : Subscription Order ID
	 *
	 * [--all]
	 * : To run script for all orders.
	 * 
	 * ## EXAMPLES
	 *
	 *     # Export order to ChartMogul.
	 *     $ wp cxl shop-subscription-export-chartmogul 185
	 *     Success: Deleted amazon order #185.
	 *
	 * @subcommand shop-subscription-export-chartmogul
	 */
	public function shop_subscription_chartmogul_export( $args, $assoc_args ) {

		if ( class_exists( 'Subscription_Chartmogul_Export' ) ) {
			new Subscription_Chartmogul_Export( $args, $assoc_args );
		} else {
			WP_CLI::warning( esc_html__( '`Subscription_Chartmogul_Export` class is not available.', 'cxl' ) );
		}
	}

}
