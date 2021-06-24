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
	 * Plugin dir path.
	 */
	public string $plugin_dir_path;

	/**
	 * Plugin dir url.
	 */
	public string $plugin_dir_url;

	/**
	 * Directory slug
	 */
	public string $slug;

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

		/**
		 * Provision plugin context info.
		 *
		 * @see https://developer.wordpress.org/reference/functions/plugin_dir_path/
		 * @see https://stackoverflow.com/questions/11094776/php-how-to-go-one-level-up-on-dirname-file
		 */
		$this->plugin_dir_path = trailingslashit( dirname( __DIR__, 1 ) );
		$this->plugin_dir_url  = plugin_dir_url( __FILE__ );
		$this->slug            = basename( $this->plugin_dir_path );

		// Load translations.
		add_action( 'plugins_loaded', [ $this, 'loadTextdomain' ], 1 );

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

	/**
	 * Load the plugin textdomain.
	 *
	 * @since  2021.01.28
	 * @access public
	 */
	public function loadTextdomain(): void {

		load_plugin_textdomain(
			'cxl-wc-chartmogul',
			false,
			trailingslashit( dirname( plugin_basename( \CXL_WC_CHARTMOGUL_PLUGIN_FILE ) ) ) . '/public/lang'
		);
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
