<?php

/**
 * Plugin bootstrap file.
 *
 * This file is used to create a new application instance and bind items to the
 * container. This is the heart of the application.
 *
 * @package   CXL
 */
use CXL\WC\ChartMogul\Core\Application;
use CXL\WC\ChartMogul\Provider;

// ------------------------------------------------------------------------------
// Create a new application.
// ------------------------------------------------------------------------------
//
// Creates the one true instance of the Hybrid Core application. You may access
// this instance via the `\CXL\WC\ChartMogul\Plugin\App` static class
// after the application has booted.
$cxl_wc_chartmogul = new Application();

// ------------------------------------------------------------------------------
// Register service providers with the application.
// ------------------------------------------------------------------------------
//
// Before booting the application, add any service providers that are necessary
// for running the plugin. Service providers are essentially the backbone of the
// bootstrapping process.
$cxl_wc_chartmogul->provider( Provider::class );

// ------------------------------------------------------------------------------
// Perform bootstrap actions.
// ------------------------------------------------------------------------------
//
// Creates an action hook for parent/child themes (or even plugins) to hook into the
// bootstrapping process and add their own bindings before the app is booted by
// passing the application instance to the action callback.
do_action( 'cxl/wc/chartmogul/bootstrap', $cxl_wc_chartmogul );

// ------------------------------------------------------------------------------
// Bootstrap the application.
// ------------------------------------------------------------------------------
//
// Calls the application `boot()` method, which launches the application. Pat
// yourself on the back for a job well done.
$cxl_wc_chartmogul->boot();

// Store app instance to globals.
$GLOBALS['cxl_wc_chartmogul_container'] = $cxl_wc_chartmogul;
