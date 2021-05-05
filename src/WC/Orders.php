<?php
/**
 * WooCommerce Orders Helper component.
 *
 * @package   CXL
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 */

namespace CXL\WC\ChartMogul\WC;

use DateTime;
use WC_Order;
use function wc_get_orders;

/**
 * @inheritDoc
 * @since  2021.06.16
 */
class Orders {

	/**
	 * Retrieve orders based on provided timeline.
	 *
	 * @return array
	 */
	public static function getOrdersByDateCreated( string $date_time_modifier ): array {

		$date = new DateTime();

		$args = [
			'date_created' => '>' . $date->modify( $date_time_modifier )->format( 'Y-m-d' ),
			'return'       => 'ids',
			'status'       => 'completed',
			'limit'        => -1,
			'type'         => 'shop_order', // ["shop_order","shop_order_refund"]
		];

		return wc_get_orders( $args );
	}

	/** Check if provided order has refunded items.
	 *
	 * @param WC_Order $order WC_Order Object
	 */
	public static function hasRefunds( WC_Order $order ): bool {
		return 0 !== count( $order->get_refunds() );
	}

}
