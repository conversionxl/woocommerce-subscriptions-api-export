<?php
/**
 * Base service provider.
 *
 * This is the base service provider class. This is an abstract class that must
 * be extended to create new service providers for the application.
 *
 * @package   HybridCore
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2008 - 2019, Justin Tadlock
 * @link      https://themehybrid.com/hybrid-core
 * @license   http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

namespace CXL\WC\ChartMogul\Tools;

use Hybrid\Contracts\Core\Application;

/**
 * Service provider class.
 *
 * @since  5.0.0
 * @access public
 */
abstract class ServiceProvider {

	/**
	 * Application instance. Sub-classes should use this property to access
	 * the application (container) to add, remove, or resolve bindings.
	 *
	 * @since  5.0.0
	 * @access protected
	 * @var    Application
	 */
	protected $cxl_wc_chartmogul;

	/**
	 * Accepts the application and sets it to the `$app` property.
	 *
	 * @since  5.0.0
	 * @access public
	 * @param  Application  $cxl_wc_chartmogul
	 * @return void
	 */
	public function __construct( Application $cxl_wc_chartmogul ) {

		$this->cxl_wc_chartmogul = $cxl_wc_chartmogul;
	}

	/**
	 * Callback executed when the `Application` class registers providers.
	 *
	 * @since  5.0.0
	 * @access public
	 * @return void
	 */
	public function register() {}

	/**
	 * Callback executed after all the service providers have been registered.
	 * This is particularly useful for single-instance container objects that
	 * only need to be loaded once per page and need to be resolved early.
	 *
	 * @since  5.0.0
	 * @access public
	 * @return void
	 */
	public function boot() {}
}
