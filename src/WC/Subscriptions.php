<?php
/**
 * WooCommerce Subscriptions Helper component.
 *
 * @package   CXL
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 */

namespace CXL\WC\ChartMogul\WC;

use WC_Subscription;
use function wcs_get_subscriptions_for_order;
use function wcs_order_contains_subscription;

/** @since  2021.06.16 */
class Subscriptions {

	/**
	 * Get the order's first related subscription.
	 *
	 * Orders can technically have multiple subscriptions this method returns only the one.
	 *
	 * @param \WC_Order|int $order An instance of a WC_Order object or the ID of an order
	 * @param array         $args A set of name value pairs to filter the returned value.
	 *            'subscriptions_per_page' The number of subscriptions to return. Default set to -1 to return all.
	 *            'offset' An optional number of subscription to displace or pass over. Default 0.
	 *            'orderby' The field which the subscriptions should be ordered by. Can be 'start_date', 'trial_end_date', 'end_date', 'status' or 'order_id'. Defaults to 'start_date'.
	 *            'order' The order of the values returned. Can be 'ASC' or 'DESC'. Defaults to 'DESC'
	 *            'customer_id' The user ID of a customer on the site.
	 *            'product_id' The post ID of a WC_Product_Subscription, WC_Product_Variable_Subscription or WC_Product_Subscription_Variation object
	 *            'order_id' The post ID of a shop_order post/WC_Order object which was used to create the subscription
	 *            'subscription_status' Any valid subscription status. Can be 'any', 'active', 'cancelled', 'on-hold', 'expired', 'pending' or 'trash'. Defaults to 'any'.
	 *            'order_type' Get subscriptions for the any order type in this array. Can include 'any', 'parent', 'renewal' or 'switch', defaults to parent.
	 * @since 2021.05.25
	 */
	public static function getSubscriptionForOrder( $order, array $args = [] ): ?WC_Subscription {
		$args = wp_parse_args(
			$args,
			[
				'subscriptions_per_page' => 1,
				'order_type'             => [ 'any' ],
				'subscription_status'    => [ 'any' ],
			]
		);

		if ( ! wcs_order_contains_subscription( $order, $args['order_type'] ) ) {
			return null;
		}

		return current( wcs_get_subscriptions_for_order( $order, $args ) );
	}

	/**
	 * Checks if subscription started with trial.
	 *
	 * @since 2020.05.05
	 * @param bool $is_active    True: check if trial is still active, false: if just existed.
	 */
	public static function hasTrial( WC_Subscription $subscription, bool $is_active = true ): bool {
		$trial_end = $subscription->get_time( 'trial_end' );

		// Check date if subscription is already cancelled.
		if ( 0 === $trial_end ) {
			$trial_end = wcs_date_to_time( $subscription->get_meta( 'trial_end_pre_cancellation' ) );
		}

		$has_trial = ( $trial_end > 0 );

		if ( $is_active ) {
			$has_trial = ( $trial_end > gmdate( 'U' ) );
		}

		return $has_trial;
	}

	/**
	 * A function for grabbing an array of all subscriptions.
	 */
	public static function getAllSubscriptions(): array {

		return wcs_get_subscriptions( [
			'subscriptions_per_page' => -1, // @todo batched processing, memory limit concerns.
		] );
	}

	/**
	 * Get customer subscription which started with trial by customer ID.
	 *
	 * @since 2021.06.29
	 */
	public static function getTrialSubscriptionByCustomerID( int $customer_id ): ?WC_Subscription {
		$subscriptions = wcs_get_users_subscriptions( $customer_id );

		// Include only subscriptions, which started as trial.
		$trial_subscriptions = array_filter( $subscriptions, [ self::class, 'hasTrial' ] );

		if ( 0 === count( $trial_subscriptions ) ) {
			return null;
		}

		// Subscriptions are ordered by start date descending.
		end( $trial_subscriptions );

		return current( $trial_subscriptions );
	}

	/**
	 * Get customer subscription which started with trial by customer ID.
	 *
	 * @since 2021.06.29
	 */
	public static function getTrialSubscriptionByCustomerID2( int $customer_id ): ?WC_Subscription {

		$trial_subscriptions = self::getCustomerTrialSubscriptions( $customer_id, [ 'active', 'on-hold' ] );

		// Remove trial subscriptions which were renewed early. We don't consider them as trial anymore.
		$trial_subscriptions = array_filter( $trial_subscriptions, [ self::class, 'subscriptionHasNoRenewal' ] );

		if ( 0 === count( $trial_subscriptions ) ) {
			return null;
		}

		// Subscriptions are ordered by start date descending.
		end( $trial_subscriptions );

		return current( $trial_subscriptions );
	}

	/**
	 * Gets trial subscriptions for given customer.
	 *
	 * @since 2021.06.29
	 * @param string|array $status
	 * @return array<\WC_Subscription>
	 */
	public static function getCustomerTrialSubscriptions( int $customer_id, $status = 'any' ): array {

		$subscription_query_args = [
			'customer_id'            => $customer_id,
			'subscription_status'    => $status,
			'subscriptions_per_page' => -1,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'             => [
				[
					'key'     => '_schedule_trial_end',
					'compare' => '!=',
					'value'   => 0,
				],
			],
		];

		return wcs_get_subscriptions( $subscription_query_args );
	}

	/**
	 * Checks if subscription has paid renewal order.
	 *
	 * @since 2021.06.29
	 */
	private function subscriptionHasNoRenewal( WC_Subscription $subscription ): bool {
		global $wpdb;

		$query = sprintf( "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->posts} p ON (pm.post_id=p.ID AND p.post_status IN (%s))
            WHERE meta_key IN ('_subscription_renewal', '_subscription_switch')
            AND meta_value=%d;",
			implode( ',', array_map( static fn( string $item ) => sprintf( "'wc-%s'", $item ), wc_get_is_paid_statuses() ) ),
			$subscription->get_id()
		);

		return 0 === (int) $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

}
