<?php
/**
 * PSR-4 autoloader used when Composer's autoloader is not present.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady;

defined( 'ABSPATH' ) || exit;

/** Loads the plugin classes when Composer's autoloader is not installed. */
final class Autoloader {

	/**
	 * Registers an autoloader for a namespace.
	 *
	 * @param string $prefix   Namespace prefix, such as AgentReady\.
	 * @param string $base_dir Folder that holds the classes, with a trailing slash.
	 * @return void
	 */
	public static function register( string $prefix, string $base_dir ): void {
		spl_autoload_register(
			static function ( string $class_name ) use ( $prefix, $base_dir ): void {
				if ( 0 !== strpos( $class_name, $prefix ) ) {
					return;
				}

				$relative = substr( $class_name, strlen( $prefix ) );
				$file     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';

				if ( is_readable( $file ) ) {
					require_once $file;
				}
			}
		);
	}
}
