<?php

namespace CXL\WC\ChartMogul\AutomateWoo\Actions;

use AutomateWoo\Action;
use AutomateWoo\Clean;
use AutomateWoo\Fields\Text;
use CXL\WC\ChartMogul\ChartMogul\Component as CMComponent;
use CXL\WC\ChartMogul\Export\Component as ExportComponent;
use CXL\WC\ChartMogul\Tools\Logger\Component as Logger;

/**
 * Subscription: Export subscription status changed to ChartMogul.
 *
 * @since 2021.06.24
 * @package CXL\WC\ChartMogul\AutomateWoo\Actions
 */
class SubscriptionStatusChanged extends Action {

	/**
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
	 * @since 2021.06.24
	 * @var array<string>
	 */
	public $required_data_items = [ 'subscription' ];

	/**
	 * Action option: data source.
	 *
	 * @since 2021.06.23
	 */
	protected string $field_data_source = 'cm_data_source';

	/**
	 * Loads action's fields.
	 *
	 * @since 2021.06.23
	 */
	public function load_fields(): void { // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
		$this->add_field( $this->get_field_data_source() );
	}

	/**
	 * @since 2021.06.24
	 * @throws \CXL\WC\ChartMogul\AutomateWoo\Actions\Exception
	 */
	public function run(): void {
		$subscription = $this->workflow->data_layer()->get_subscription();

		if ( ! $subscription ) {
			$this->workflow->log_action_error( $this, 'Subscription not found.' );
		}

		Logger::log()->debug( 'Export initiated by AutomateWoo: Subscription status changed', [
			'subscription_id' => $subscription->get_id(),
		] );

		try {
			CMComponent::init();

			if ( 'pong!' !== CMComponent::ping() ) {
				Logger::log()->critical( 'No ping to ChartMogul!' );
				$this->workflow->log_action_error( $this, 'Exporting subscription status changed failed. Please verify action config.' );
				return;
			}

			$options = [];

			// Check subscription id.
			$options['subscription_id'] = absint( $subscription->get_id() );

			// Check data source.
			$data_source = Clean::string( $this->get_option( $this->field_data_source ) );
			if ( $data_source ) {
				$options['data_source']      = trim( strtolower( $data_source ) );
				$options['data_source_uuid'] = CMComponent::getDataSourceUUIDbyName( $options['data_source'] );
			}

			new ExportComponent( $options );

			$this->log_success();
			return;

		} catch ( \Throwable $e ) {
			Logger::log()->error( $e->getMessage() );
		}

		$this->workflow->log_action_error( $this, 'Exporting subscription status changed failed. Please verify action config.' );
	}

	/** @since 2021.06.24 */
	protected function load_admin_details(): void { // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
		$this->title = 'Export subscription status changed';
		$this->group = __( 'ChartMogul - Subscription', 'automatewoo' );
	}

	/**
	 * Logs success action note.
	 *
	 * @since 2021.06.24
	 */
	protected function log_success(): void { // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
		$this->workflow->log_action_note( $this, 'Subscription status changed exported.' );
	}

	/**
	 * Creates data source field.
	 *
	 * @since 2021.06.24
	 */
	protected function get_field_data_source(): Text { // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps

		return ( new Text() )
			->set_name( $this->field_data_source )
			->set_title( 'Data Source' )
			->set_description( 'Data Source for the export.' )
			->set_placeholder( 'e.g. staging-export-test' );
	}

}
