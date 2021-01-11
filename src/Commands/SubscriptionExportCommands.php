<?php
/**
 * Subscription export CLI commands for CXL.
 */
namespace CXL_Upwork_01dd36a4283a21f14f\Commands;

use WP_CLI_Command;

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
     * : Run for specific data source.
     *
     * [--create-data-source]
     * : To create data source.
     *
     * ## EXAMPLES
     *
     *     # Export subscriptions to ChartMogul.
     *     $ wp cxl shop-subscription-export-chartmogul --id=25
     *     Subscription #25 sent to ChartMogul.
     *
     * @param array $args
     * @param array $assoc_args
     * @subcommand shop-subscription-export-chartmogul
     */
    public function shop_subscription_chartmogul_export( array $args, array $assoc_args ): void {

        if ( class_exists( SubscriptionExportChartMogulCommand::class ) ) {
            new SubscriptionExportChartMogulCommand( $args, $assoc_args );
        } else {
            WP_CLI::warning( esc_html__( SubscriptionExportChartMogulCommand::class . ' class is not available.', 'cxl' ) );
        }
    }

}
