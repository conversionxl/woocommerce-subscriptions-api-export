<?php
/**
 * App service provider.
 *
 * Service providers are essentially the bootstrapping code for your plugin.
 * They allow you to add bindings to the container on registration and boot them
 * once everything has been registered.
 *
 * @package   CXL
 */

namespace CXL\WC\ChartMogul;

use CXL\WC\ChartMogul\AutomateWoo\Component as AWComponent;
use CXL\WC\ChartMogul\CLI\Component as CLIComponent;
use CXL\WC\ChartMogul\Query\Component as QueryComponent;
use CXL\WC\ChartMogul\Tools\ServiceProvider;
use WP_CLI;

/**
 * @inheritDoc
 * @since  2021.05.27
 */
class Provider extends ServiceProvider {

	/**
	 * @inheritDoc
	 * @since  2021.05.27
	 * @return void
	 */
	public function register() {

		$this->cxl_wc_chartmogul->instance( 'cxl_is_wp_cli', ( defined( 'WP_CLI' ) && \WP_CLI ) );

		if ( $this->cxl_wc_chartmogul->resolve( 'cxl_is_wp_cli' ) ) {
			WP_CLI::add_command( 'cxl', CLIComponent::class );
		}

		$this->cxl_wc_chartmogul->singleton( QueryComponent::class );
		$this->cxl_wc_chartmogul->singleton( AWComponent::class );
	}

	/**
	 * @inheritDoc
	 * @since  2021.05.27
	 * @return void
	 */
	public function boot() {

		$this->cxl_wc_chartmogul->resolve( QueryComponent::class )->boot();
		$this->cxl_wc_chartmogul->resolve( AWComponent::class )->boot();
	}

}
