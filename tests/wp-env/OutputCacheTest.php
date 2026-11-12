<?php
/**
 * Tests that the saved llms files are cleared by real WordPress events.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Tests\WpEnv;

use AgentWelcome\Index\OutputCache;
use WP_UnitTestCase;

final class OutputCacheTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		OutputCache::remember( OutputCache::LLMS, static fn(): string => 'saved llms' );
		OutputCache::remember( OutputCache::FULL, static fn(): string => 'saved full' );
	}

	private function saved(): bool {
		return false !== get_transient( OutputCache::LLMS ) || false !== get_transient( OutputCache::FULL );
	}

	public function test_the_files_are_saved(): void {
		$this->assertTrue( $this->saved() );
	}

	public function test_saving_a_page_clears_them(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$this->assertFalse( get_transient( OutputCache::LLMS ) );
		$this->assertFalse( get_transient( OutputCache::FULL ) );
		$this->assertNotSame( 0, $id );
	}

	public function test_changing_the_site_title_clears_them(): void {
		update_option( 'blogname', 'A new title ' . wp_generate_password( 6, false ) );

		$this->assertFalse( $this->saved() );
	}

	public function test_changing_an_seo_description_clears_them(): void {
		$id = self::factory()->post->create();
		OutputCache::remember( OutputCache::LLMS, static fn(): string => 'saved llms' );

		update_post_meta( $id, '_yoast_wpseo_metadesc', 'A description' );

		$this->assertFalse( get_transient( OutputCache::LLMS ) );
	}

	public function test_an_unrelated_meta_change_does_not_clear_them(): void {
		$id = self::factory()->post->create();
		OutputCache::remember( OutputCache::LLMS, static fn(): string => 'saved llms' );
		OutputCache::remember( OutputCache::FULL, static fn(): string => 'saved full' );

		update_post_meta( $id, '_thumbnail_id', '5' );

		$this->assertSame( 'saved llms', get_transient( OutputCache::LLMS ) );
		$this->assertSame( 'saved full', get_transient( OutputCache::FULL ) );
	}
}
