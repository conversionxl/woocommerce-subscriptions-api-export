<?php
/**
 * AutomateWoo helper component.
 *
 * @package   CXL
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 */

namespace CXL\WC\ChartMogul\AutomateWoo;

use Hybrid\Contracts\Bootable;

/**
 * @inheritDoc
 * @since  2021.06.22
 */
class Component implements Bootable {

	/** @inheritDoc */
	public function boot() {

		add_filter( 'automatewoo/actions', static function( array $actions ): array {

			$actions['cxl_cm_export_new_subscription']            = Actions\SubscriptionCreated::class;
			$actions['cxl_cm_export_subscription_status_changed'] = Actions\SubscriptionStatusChanged::class;
			$actions['cxl_cm_export_cancelled_subscription']      = Actions\SubscriptionCancelled::class;
			$actions['cxl_cm_export_new_order']                   = Actions\OrderCreated::class;
			$actions['cxl_cm_export_order_status_changed']        = Actions\OrderStatusChanged::class;
			$actions['cxl_cm_export_new_customer']                = Actions\CustomerCreated::class;

			return $actions;
		} );
	}

}
