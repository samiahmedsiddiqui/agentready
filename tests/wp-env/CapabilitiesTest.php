<?php
/**
 * Tests for the capability that controls the llms.txt files, on a real WordPress roles table.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Tests\WpEnv;

use AgentWelcome\Admin\Capabilities;
use WP_UnitTestCase;

final class CapabilitiesTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		get_role( 'administrator' )->remove_cap( Capabilities::FILES );
		delete_option( Capabilities::GRANTED_OPTION );
	}

	public function test_administrators_get_the_capability_once(): void {
		( new Capabilities() )->grant_to_administrators();

		$this->assertTrue( get_role( 'administrator' )->has_cap( Capabilities::FILES ) );
		$this->assertSame( '1', get_option( Capabilities::GRANTED_OPTION ) );
	}

	public function test_a_removal_by_the_site_owner_is_not_undone(): void {
		$capabilities = new Capabilities();
		$capabilities->grant_to_administrators();

		get_role( 'administrator' )->remove_cap( Capabilities::FILES );
		$capabilities->grant_to_administrators();

		$this->assertFalse( get_role( 'administrator' )->has_cap( Capabilities::FILES ) );
	}

	public function test_other_roles_do_not_get_it(): void {
		( new Capabilities() )->grant_to_administrators();

		$this->assertFalse( get_role( 'editor' )->has_cap( Capabilities::FILES ) );
	}

	public function test_a_user_with_the_capability_can_use_it(): void {
		$user = self::factory()->user->create_and_get( array( 'role' => 'editor' ) );
		$user->add_cap( Capabilities::FILES );

		$this->assertTrue( user_can( $user, Capabilities::files() ) );
	}

	public function test_the_filter_maps_it_to_another_capability(): void {
		add_filter( 'agentwelcome_files_capability', static fn(): string => 'edit_pages' );

		$editor = self::factory()->user->create_and_get( array( 'role' => 'editor' ) );

		$this->assertTrue( user_can( $editor, Capabilities::files() ) );
	}
}
