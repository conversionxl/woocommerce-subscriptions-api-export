<?php
/**
 * Logger tool.
 */

namespace CXL\WC\ChartMogul\Tools\Logger;

use CXL\WC\ChartMogul\Plugin\App;
use MHCG\Monolog\Handler\WPCLIHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as MLogger;

/**
 * @inheritDoc
 * @since 2021.05.27
 */
class Component {

	/**
	 * @inheritDoc
	 *
	 * Usage:
	 * Component::log()->warning('This is a warning');
	 * Component::log()->error('An error has occurred');
	 * Component::log()->critical('This will report error and exit out');
	 * Component::log()->debug('Only shown when running wp with --debug');
	 * Component::log()->info('General logging - will not be shown when running wp with --quiet');
	 */
	public static function log() {

		$log_folder = rtrim( \WP_CONTENT_DIR, '\\/' ) . '/cxl-wc-chartmogul';
		$log_folder = rtrim( wp_normalize_path( $log_folder ), '/' );
		wp_mkdir_p( $log_folder );

		static::maybe_create_htaccess( $log_folder );

		$log_name = date( 'Y-m-d' ) . '.log';
		$log_file = $log_folder . '/' . $log_name;
		if ( ! file_exists( $log_file ) ) {
			file_put_contents( $log_file, '' );
		}

		$logger = new MLogger( $log_name );

		// create a log channel
		if ( App::resolve( 'cxl_is_wp_cli' ) ) {
			$logger->pushHandler( new WPCLIHandler( MLogger::DEBUG, true, true ) );
		} else {
			$logger->pushHandler( new StreamHandler( $log_file ) );
		}

		return $logger;
	}

	/**
	 * When the log root folder is inside WordPress content folder, the logs are going to be publicly accessible, and
	 * that is in best case a privacy leakage issue, in worst case a security threat.
	 * We try to write an .htaccess file to prevent access to them.
	 * This guarantees nothing, because .htaccess can be ignored depending web server in use and its configuration,
	 * but at least we tried.
	 * To configure a custom log folder outside content folder is also highly recommended in documentation.
	 */
	private static function maybe_create_htaccess( string $folder ): string {

		if (
			! $folder
			|| ! is_dir( $folder )
			|| ! is_writable( $folder )
			|| file_exists( "{$folder}/.htaccess" )
			|| ! defined( 'WP_CONTENT_DIR' )
		) {
			return $folder;
		}

		$target_dir  = realpath( $folder );
		$content_dir = realpath( \WP_CONTENT_DIR );

		// Sorry, we can't allow logs to be put straight in content folder. That's too dangerous.
		if ( $target_dir === $content_dir ) {
			$target_dir .= \DIRECTORY_SEPARATOR . 'cxl-wc-chartmogul';
		}

		// If target dir is outside content dir, its security is up to user.
		if ( strpos( $target_dir, $content_dir ) !== 0 ) {
			return $target_dir;
		}

		// Let's disable error reporting: too much file operations which might fail, nothing can log them, and package
		// is fully functional even if failing happens. Silence looks like best option here.
		set_error_handler( '__return_true' );

		$handle = fopen( "{$folder}/.htaccess", 'w' );

		if ( $handle && flock( $handle, \LOCK_EX ) ) {
			$htaccess = <<<'HTACCESS'
<IfModule mod_authz_core.c>
	Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
	Deny from all
</IfModule>
HTACCESS;

			if ( fwrite( $handle, $htaccess ) ) {
				flock( $handle, \LOCK_UN );
				chmod( "{$folder}/.htaccess", 0444 );
			}
		}

		fclose( $handle );

		restore_error_handler();

		return $target_dir;
	}

}
