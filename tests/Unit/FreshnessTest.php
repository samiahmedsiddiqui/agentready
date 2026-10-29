<?php
/**
 * Tests for classifying stored copies.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Tests\Unit;

use AgentReady\Cache\Entry;
use AgentReady\Cache\Freshness;
use PHPUnit\Framework\TestCase;

final class FreshnessTest extends TestCase {

	private function entry( string $version, string $state ): Entry {
		return new Entry( 1, '# Page', sha1( '# Page' ), 'loopback', $version, '2026-01-02 03:04:05', $state );
	}

	public function test_no_entry_is_missing(): void {
		$this->assertSame( Freshness::MISSING, Freshness::classify( null, '1.0.0' ) );
	}

	public function test_current_fresh_entry_is_fresh(): void {
		$this->assertSame( Freshness::FRESH, Freshness::classify( $this->entry( '1.0.0', Entry::FRESH ), '1.0.0' ) );
	}

	public function test_stale_entry_is_stale(): void {
		$this->assertSame( Freshness::STALE, Freshness::classify( $this->entry( '1.0.0', Entry::STALE ), '1.0.0' ) );
	}

	public function test_older_plugin_version_is_outdated_even_when_stale(): void {
		$this->assertSame( Freshness::OUTDATED, Freshness::classify( $this->entry( '0.9.0', Entry::FRESH ), '1.0.0' ) );
		$this->assertSame( Freshness::OUTDATED, Freshness::classify( $this->entry( '0.9.0', Entry::STALE ), '1.0.0' ) );
	}

	public function test_timestamp_reads_the_stored_utc_time(): void {
		$this->assertSame( 1767323045, $this->entry( '1.0.0', Entry::FRESH )->timestamp() );
	}
}
