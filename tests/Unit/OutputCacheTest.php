<?php
/**
 * Tests for the short-lived copy of llms.txt and llms-full.txt.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Tests\Unit;

use AgentWelcome\Index\OutputCache;
use PHPUnit\Framework\TestCase;

final class OutputCacheTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['agentwelcome_test_transients'] = array();
	}

	public function test_builds_once_and_reuses_the_saved_copy(): void {
		$calls = 0;
		$build = function () use ( &$calls ): string {
			++$calls;

			return 'text';
		};

		$this->assertSame( 'text', OutputCache::remember( OutputCache::LLMS, $build ) );
		$this->assertSame( 'text', OutputCache::remember( OutputCache::LLMS, $build ) );
		$this->assertSame( 1, $calls );
	}

	public function test_does_not_save_a_very_large_file(): void {
		$calls = 0;
		$build = function () use ( &$calls ): string {
			++$calls;

			return str_repeat( 'a', 2000001 );
		};

		OutputCache::remember( OutputCache::FULL, $build );
		OutputCache::remember( OutputCache::FULL, $build );

		$this->assertSame( 2, $calls );
	}

	public function test_purge_clears_both_files(): void {
		OutputCache::remember( OutputCache::LLMS, fn(): string => 'a' );
		OutputCache::remember( OutputCache::FULL, fn(): string => 'b' );

		OutputCache::purge();

		$this->assertSame( 'c', OutputCache::remember( OutputCache::LLMS, fn(): string => 'c' ) );
		$this->assertSame( 'd', OutputCache::remember( OutputCache::FULL, fn(): string => 'd' ) );
	}

	/**
	 * @dataProvider meta_keys
	 */
	public function test_meta_change_purges_only_for_keys_that_feed_the_files( string $key, bool $purged ): void {
		OutputCache::remember( OutputCache::LLMS, fn(): string => 'old' );

		( new OutputCache() )->on_meta( 1, 2, $key );

		$this->assertSame( $purged ? 'new' : 'old', OutputCache::remember( OutputCache::LLMS, fn(): string => 'new' ) );
	}

	/**
	 * @return array<string, array{string, bool}>
	 */
	public function meta_keys(): array {
		return array(
			'SEO description'  => array( '_yoast_wpseo_metadesc', true ),
			'noindex'          => array( '_genesis_noindex', true ),
			'hidden page'      => array( '_agentwelcome_exclude', true ),
			'own description'  => array( '_agentwelcome_description', true ),
			'unrelated'        => array( '_thumbnail_id', false ),
		);
	}

	/**
	 * @dataProvider options
	 */
	public function test_option_change_purges_only_for_options_that_feed_the_files( string $option, bool $purged ): void {
		OutputCache::remember( OutputCache::LLMS, fn(): string => 'old' );

		( new OutputCache() )->on_option( $option );

		$this->assertSame( $purged ? 'new' : 'old', OutputCache::remember( OutputCache::LLMS, fn(): string => 'new' ) );
	}

	/**
	 * @return array<string, array{string, bool}>
	 */
	public function options(): array {
		return array(
			'plugin settings' => array( 'agentwelcome_settings', true ),
			'llms page'       => array( 'agentwelcome_llms', true ),
			'site title'      => array( 'blogname', true ),
			'unrelated'       => array( 'posts_per_page', false ),
		);
	}
}
