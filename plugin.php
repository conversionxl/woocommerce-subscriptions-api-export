<?php

/**
 * CXL ChartMogul Export Plugin.
 *
 * @package CXL
 *
 * Plugin Name: WooCommerce Subscriptions external API export
 * Plugin URI: https://cxl.com/
 * Description: ChartMogul integration to export WooCommerce Orders, Subscription & Customers etc.
 * Author: Leho Kraav
 * Author URI: https://cxl.com
 * Version: 2021.06.24
 */

use CXL\WC\ChartMogul\Plugin;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Returns plugin instance.
 *
 * @since 2021.05.27
 */
function cxl_wc_chartmogul(): Plugin {

	return Plugin::getInstance();

}

add_action( 'cxl_common_lib_loaded', 'cxl_wc_chartmogul', 0 );
