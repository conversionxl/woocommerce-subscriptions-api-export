<?php
/**
 * Subscription export CLI commands for CXL.
 */

namespace CXL_Upwork_01dd36a4283a21f14f\Commands;

use WP_CLI_Command;
use WP_CLI;

/**
 * Main class.
 *
 * @psalm-suppress UndefinedClass
 */
class SubscriptionExportCommands extends WP_CLI_Command {

    /**
     * Export subscription order to ChartMogul.
     *
     * [--id]
     * : Subscription ID.
     *
     * [--all]
     * : To run script for all subscriptions.
     *
     * [--data-source]
     * : Run for specific data source, pass the datasource UUID from ChartMogul.
     *
     * [--create-data-source]
     * : To create data source.
     *
     * ## EXAMPLES
     *
     *     # Export single subscriptions to ChartMogul.
     *     $ wp cxl shop-subscription-export-chartmogul --id=25 --data-source=xxxxxx
     *     Subscription #25 sent to ChartMogul.
     *
     *     # Export all subscriptions to ChartMogul.
     *     $ wp cxl shop-subscription-export-chartmogul --all --data-source=xxxxxx
     *     Subscription #25 sent to ChartMogul.
     *
     *     # Create Datasource in ChartMogul.
     *     $ wp cxl shop-subscription-export-chartmogul --create-data-source=true --data-source=new-datasource
     *     Success: Data source created successfully.
     *
     * @todo  Separate command for create data source.
     * @param array $args
     * @param array $assoc_args
     * @subcommand shop-subscription-export-chartmogul
     */
    public function shop_subscription_chartmogul_export( array $args, array $assoc_args ): void {

        if ( class_exists( SubscriptionExportChartMogulCommand::class ) ) {
            new SubscriptionExportChartMogulCommand( $args, $assoc_args );
        } else {
            WP_CLI::warning( esc_html__( '`SubscriptionExportChartMogulCommand` class is not available.', 'cxl' ) );
        }
    }

}
