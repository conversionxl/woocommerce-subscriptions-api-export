<?php
/**
 * CXL WC Chartmogul implementation
 *
 * @package CXL
 */

namespace CXL\WC\ChartMogul;

use CXL\WC\ChartMogul\Core\Application;

defined( 'ABSPATH' ) || exit;

/**
 * Main class
 */
final class Plugin {

	/**
	 * Singleton pattern
	 */
	private static ?Plugin $instance = null;

	/**
	 * A reference to the Application instance.
	 *
	 */
	public static ?Application $app = null;

	/**
	 * Constructor.
	 *
	 * @throws \Exception
	 */
	private function __construct() {

		// Run.
		add_action( 'plugins_loaded', [ $this, 'init' ], 3 );
	}

	/**
	 * Singleton pattern.
	 */
	public static function getInstance(): Plugin {

		if ( ! self::$instance ) {
			/** @var \CXL\WC\ChartMogul\Plugin $instance */
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function init(): void {

		/**
		 * ------------------------------------------------------------------------------
		 * Create a new application.
		 * ------------------------------------------------------------------------------
		 *
		 * Creates the one true instance of the Hybrid Core application. You may access
		 * this instance via the `\CXL\WC\ChartMogul\Plugin\App` static class
		 * after the application has booted.
		 *
		 */
		$app = new Application();

		/**
		 * ------------------------------------------------------------------------------
		 * Register service providers with the application.
		 * ------------------------------------------------------------------------------
		 *
		 * Before booting the application, add any service providers that are necessary
		 * for running the plugin. Service providers are essentially the backbone of the
		 * bootstrapping process.
		 */
		$app->provider(Provider::class);

		/**
		 * ------------------------------------------------------------------------------
		 * Perform bootstrap actions.
		 * ------------------------------------------------------------------------------
		 *
		 * Creates an action hook for parent/child themes (or even plugins) to hook into the
		 * bootstrapping process and add their own bindings before the app is booted by
		 * passing the application instance to the action callback.
		 *
		 */
		do_action('cxl/wc/chartmogul/bootstrap', $app);

		/**
		 * ------------------------------------------------------------------------------
		 * Bootstrap the application.
		 * ------------------------------------------------------------------------------
		 *
		 * Calls the application `boot()` method, which launches the application. Pat
		 * yourself on the back for a job well done.
		 *
		 */
		$app->boot();

		self::$app = $app;
	}

	/**
	 * Clone
	 */
	private function __clone() {}

}
