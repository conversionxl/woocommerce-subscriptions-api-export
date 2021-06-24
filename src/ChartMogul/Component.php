<?php
/**
 * ChartMogul API component.
 *
 * @package   CXL
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 */

namespace CXL\WC\ChartMogul\ChartMogul;

use ChartMogul\Configuration as CMConfiguration;
use ChartMogul\Customer as CMCustomer;
use ChartMogul\DataSource as CMDataSource;
use ChartMogul\Ping as CMPing;
use ChartMogul\Subscription as CMSubscription;
use CXL\WC\ChartMogul\Tools\Logger\Component as Logger;
use Doctrine\Common\Collections\Criteria;
use WC_Subscription;

/** @since  2021.05.27 */
class Component {

	/**
	 * Option: Account Token.
	 *
	 * $ wp option add cxl_cm_account_token 900asdfasdc019adkjasdf09808
	 *
	 * @since 2021.06.22
	 */
	protected static string $option_account_token = 'cxl_cm_account_token';

	/**
	 * Option: Secret Key.
	 *
	 * $ wp option add cxl_cm_secret_key 900asdfasdc019adkjasdf09808
	 *
	 * @since 2021.06.22
	 */
	protected static string $option_secret_key = 'cxl_cm_secret_key';

	/**
	 * Function to initialize ChartMogul.
	 */
	public static function initialize_chartmogul(): string {

		$account_token = get_option( static::$option_account_token, '' );
		$secret_key    = get_option( static::$option_secret_key, '' );

		if ( ! $account_token || ! $secret_key ) {
			Logger::log()->critical( 'Setup ChartMogul account token and secret key.' );
			return '';
		}

		// @todo constant option name + `get_option()`.
		CMConfiguration::getDefaultConfiguration()
			->setAccountToken( $account_token )
			->setSecretKey( $secret_key );

		return CMPing::ping()->data;
	}

	/**
	 * Function to create data source in ChartMogul.
	 */
	public static function createDataSource( string $data_source_name ): void {

		Logger::log()->info( 'Creating Data source: ' . $data_source_name );

		// Data source already exists, bail early.
		if ( static::dataSourceExists( $data_source_name ) ) {
			return;
		}

		CMDataSource::create([
			'name' => $data_source_name,
		]);

		// @todo: need success helper
		Logger::log()->info( 'Data source created successfully.' );
	}

	/**
	 * Function to set UUID for provided Data Source name.
	 *
	 * @param string $data_source_name ChartMogul Data Source name.
	 * @return string|null UUID or null value.
	 */
	public static function getDataSourceUUIDbyName( string $data_source_name ): ?string {

		$data_source = static::getDataSource( $data_source_name );

		if ( $data_source ) {
			Logger::log()->info( 'Data source UUID: ' . $data_source->uuid );

			return $data_source->uuid;
		}

		return null;
	}

	/**
	 * Fetch subscription from ChartMogul matching the passed subscription id.
	 */
	public static function getSubscription( string $customer_uuid, WC_Subscription $subscription ): ?CMSubscription {

		try {
			$chartmogul_subscriptions = CMSubscription::all([
				'customer_uuid' => $customer_uuid,
			]);
		} catch ( \Throwable $e ) {
			Logger::log()->error( 'getSubscription failed. Reason: ' . $e->getMessage() );

			return null;
		}

		$cm_subscription = $chartmogul_subscriptions->matching( new Criteria( null, [ 'subscription_set_external_id' => $subscription->get_id() ] ) );
		// $cm_subscription = $chartmogul_subscriptions->filter( static fn( $chartmogul_subscription ) => absint( $subscription->get_id() ) === absint( $chartmogul_subscription->subscription_set_external_id ) );

		if ( ! $cm_subscription->isEmpty() ) {
			return $cm_subscription->first();
		}

		return null;
	}

	/**
	 *  Get customer from ChartMogul.
	 */
	public static function findCustomerByExternalId( string $data_source_uuid, string $external_id ): ?CMCustomer {

		return CMCustomer::findByExternalId( [
			'data_source_uuid' => $data_source_uuid,
			'external_id'      => $external_id,
		] );
	}

	/**
	 * Check if data source already exists.
	 *
	 * @param string $data_source_name ChartMogul Data Source name.
	 */
	public static function dataSourceExists( string $data_source_name ): bool {
		$data_source = static::getDataSource( $data_source_name );

		if ( $data_source ) {
			Logger::log()->info( 'Data source exists => Name: ' . $data_source->name );

			return true;
		}

		return false;
	}

	/**
	 * Get data source.
	 *
	 * @param string $data_source_name ChartMogul Data Source name.
	 */
	public static function getDataSource( string $data_source_name ): ?CMDataSource {
		$data_source = CMDataSource::all([
			'name' => $data_source_name,
		])->first();

		if ( $data_source ) {
			Logger::log()->info( 'Get Data source => Name: ' . $data_source->name );

			return $data_source;
		}

		return null;
	}

}
