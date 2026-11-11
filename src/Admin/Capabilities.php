<?php
/**
 * The capability that controls who may manage the llms.txt files.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Admin;

use AgentReady\Contracts\Hookable;

defined( 'ABSPATH' ) || exit;

/**
 * Defines the capability for the llms.txt page and gives it to administrators once.
 */
final class Capabilities implements Hookable {

	/** Capability that allows editing llms.txt, llms-full.txt and the robots.txt line. */
	public const FILES = 'agentready_manage_files';

	/** Option that records that administrators were given the capability, so a later removal is respected. */
	public const GRANTED_OPTION = 'agentready_files_capability_granted';

	/**
	 * Gives administrators the capability on the first admin request after the plugin is installed. It runs before the menu is built, so the page shows at once.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'grant_to_administrators' ), 1 );
	}

	/**
	 * The capability that is checked for the llms.txt files. A filter can map it to another capability.
	 *
	 * @return string Capability name.
	 */
	public static function files(): string {
		$capability = apply_filters( 'agentready_files_capability', self::FILES );

		return is_string( $capability ) && '' !== $capability ? $capability : self::FILES;
	}

	/**
	 * Adds the capability to the administrator role, once, and refreshes the capabilities of the current user.
	 *
	 * @return void
	 */
	public function grant_to_administrators(): void {
		if ( false !== get_option( self::GRANTED_OPTION ) ) {
			return;
		}

		$role = get_role( 'administrator' );

		if ( null !== $role ) {
			$role->add_cap( self::FILES );
			wp_get_current_user()->get_role_caps();
		}

		update_option( self::GRANTED_OPTION, '1', true );
	}
}
