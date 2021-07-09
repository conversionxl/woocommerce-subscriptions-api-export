<?php
/**
 * WordPress helper component.
 *
 * @package   CXL
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 */

namespace CXL\WC\ChartMogul\WP;

use WP_User;
use function get_user_by;

/** @since  2021.06.16 */
class Component {

	/**
	 * Gets the object of the user the profile field belongs to.
	 *
	 * @since 1.19.0
	 * @param string|int $user_id User ID
	 */
	public static function getUser( $user_id ): ?WP_User {

		$user = get_user_by( 'id', $user_id );

		return $user instanceof WP_User
			? $user
			: null;
	}

}
