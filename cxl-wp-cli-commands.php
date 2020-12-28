<?php
/**
 * Plugin Name:       CXL WP_CLI Commands
 * Description:       WP CLI commands for export subscription order to external API.
 * Version:           1.0.0
 * Tested up to:      5.5
 *
 * Text Domain: cxl
 *
 * @package cxl
 */

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

define( 'CXL_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

/**
 * Autoloader for cli commands class.
 *
 * @param string $class Class name to load file.
 *
 * @return void
 */
function cxl_autoloader( $class ) {

	$file_name = __DIR__ . '/Commands/' . cxl_cli_class_to_file_name( $class );

	if ( ! file_exists( $file_name ) ) {
		return;
	}

    require_once $file_name;

}
spl_autoload_register( 'cxl_autoloader' );

/**
 * Function to modify class name to file name.
 *
 * @param string $class Class name to modify.
 *
 * @return string
 */
function cxl_cli_class_to_file_name( $class ) {

	$new_class_name = 'class-';

	$new_class_name .= trim( str_replace( '_', '-', strtolower( $class ) ) );
	$new_class_name .= '.php';

	return $new_class_name;
}

WP_CLI::add_command( 'cxl', 'CXL_CLI' );
