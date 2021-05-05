<?php
/**
 * Plugin Name: WooCommerce Subscriptions external API export.
 * Version: 0.1.0
 */

use CXL\WC\ChartMogul\Plugin;

defined( 'ABSPATH' ) || exit;

define( 'CXL_WC_CHARTMOGUL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CXL_WC_CHARTMOGUL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CXL_WC_CHARTMOGUL_PLUGIN_FILE', __FILE__ );
define( 'CXL_WC_CHARTMOGUL_PLUGIN_BASE', plugin_basename( __FILE__ ) );

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
