<?php
/**
 * Plugin Name: CXL Upwork 01dd36a4283a21f14f: WooCommerce Subscriptions external API export.
 * Version: 0.1.0
 */

namespace CXL_Upwork_01dd36a4283a21f14f;

use WP_CLI;

if ( ! class_exists( 'WP_CLI' ) ) {
    return;
}

require_once __DIR__ . '/vendor/autoload.php';

WP_CLI::add_command( 'cxl', Commands\SubscriptionExportCommands::class );
