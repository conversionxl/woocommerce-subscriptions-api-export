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

/**
 * Autoloader for cli commands class.
 *
 * @param string $class Class name to load file.
 *
 * @return void
 */
function cxl_autoloader( string $class ): void {

	$class     = str_replace( 'CXL_Upwork_01dd36a4283a21f14f\\', '', $class );
	$file_name = __DIR__ . '/Commands/' . cxl_cli_class_to_file_name( $class );

	if ( ! file_exists( $file_name ) ) {
		return;
	}

    require_once $file_name;

}
spl_autoload_register( '\CXL_Upwork_01dd36a4283a21f14f\cxl_autoloader' );

/**
 * Function to modify class name to file name.
 *
 * @param string $class Class name to modify.
 *
 * @return string
 */
function cxl_cli_class_to_file_name( string $class ): string {

	$new_class_name = 'class-';

	$new_class_name .= trim( str_replace( '_', '-', strtolower( $class ) ) );
	$new_class_name .= '.php';

	return $new_class_name;

}

WP_CLI::add_command( 'cxl', Subscription_Export_Commands::class );
