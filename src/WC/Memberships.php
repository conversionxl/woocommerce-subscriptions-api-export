<?php
/**
 * WooCommerce Memberships Helper component.
 *
 * @package   CXL
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 */

namespace CXL\WC\ChartMogul\WC;

use CXL\WC\ChartMogul\WP\Component as WPComponent;
use DateTime;
use WC_Memberships_User_Membership;

/**
 * @inheritDoc
 * @since  2021.06.16
 */
class Memberships {

	/**
	 * Retrieves user memberships for given customer ID.
	 *
	 * @since 2021.05.25
	 * @param int $customer_id woocommerce customer ID.
	 * @return array<\WC_Memberships_User_Membership>|array<\WC_Memberships_Integration_Subscriptions_User_Membership>|false array of User Memberships or false if not found.
	 */
	public static function getMembershipsByCustomerID( int $customer_id ) {
		$user = WPComponent::getUser( $customer_id );

		if ( ! $user ) {
			return false;
		}

		return wc_memberships_get_user_memberships( $user->ID, [ 'status' => [ 'active' ] ] );
	}

	/**
	 * @param array<\WC_Memberships_Integration_Subscriptions_User_Membership>|array<\WC_Memberships_User_Membership> $memberships
	 * @return array<\WC_Memberships_User_Membership>|array<\WC_Memberships_Integration_Subscriptions_User_Membership> sorted array of User Memberships.
	 * @throws \Exception
	 */
	public static function sortMembershipsByOrderDate( array $memberships ): array {

		// Sort memberships by start date.
		usort( $memberships, static function( WC_Memberships_User_Membership $um, WC_Memberships_User_Membership $vm ): int {

			$um_start_date = new DateTime( $um->get_start_date() );
			$vm_start_date = new DateTime( $vm->get_start_date() );

			if ( $um_start_date === $vm_start_date ) {
				return 0;
			}

			return $um_start_date > $vm_start_date
				? -1
				: 1;
		} );

		return $memberships;
	}

}
