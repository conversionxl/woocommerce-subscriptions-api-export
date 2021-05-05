<?php
/**
 * CXL WC Chartmogul implementation
 *
 * @package CXL
 */

namespace CXL\WC\ChartMogul;

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
		// ------------------------------------------------------------------------------
		// Bootstrap the plugin.
		// ------------------------------------------------------------------------------
		//
		// Load the bootstrap files. Note that autoloading should happen first so that
		// any classes/functions are available that we might need.

		/** @psalm-suppress UnresolvableInclude */
		require_once $this->plugin_dir_path . 'src/bootstrap-plugin.php';
	}

	/**
	 * Clone
	 */
	private function __clone() {}

}
