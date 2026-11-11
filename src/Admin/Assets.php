<?php
/**
 * Versions the admin CSS and JS by file modification time, so a changed file is never served from a stale cache.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Admin;

defined( 'ABSPATH' ) || exit;

/** Loads the stylesheet and gives the version used for the admin files. */
final class Assets {

	/**
	 * Version string for an asset, which changes when the file does.
	 *
	 * @param string $file Path of the file, relative to the plugin folder.
	 * @return string Plugin version and file modification time.
	 */
	public static function version( string $file ): string {
		$path = AGENTREADY_PATH . $file;

		return AGENTREADY_VERSION . '.' . ( file_exists( $path ) ? (int) filemtime( $path ) : 0 );
	}

	/**
	 * Adds the admin stylesheet.
	 *
	 * @return void
	 */
	public static function style(): void {
		wp_enqueue_style( 'agentready-admin', AGENTREADY_URL . 'assets/css/admin.css', array(), self::version( 'assets/css/admin.css' ) );
	}
}
