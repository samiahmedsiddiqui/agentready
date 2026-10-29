<?php
/**
 * Tests for the environment gate's pure helpers.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Tests\Unit;

use AgentReady\Environment\Environment;
use PHPUnit\Framework\TestCase;

final class EnvironmentTest extends TestCase {

	public function test_production_is_always_allowed(): void {
		$this->assertTrue( Environment::type_allowed( 'production', array() ) );
	}

	public function test_other_types_need_to_be_ticked(): void {
		$this->assertFalse( Environment::type_allowed( 'staging', array() ) );
		$this->assertFalse( Environment::type_allowed( 'staging', array( 'development' ) ) );
		$this->assertTrue( Environment::type_allowed( 'staging', array( 'staging', 'local' ) ) );
	}

	public function test_parse_urls_reduces_entries_to_hosts(): void {
		$raw = "https://Example.com/path?x=1\nwww.example.com:8080, user@staging.example.com\n*.example.org";

		$this->assertSame(
			array( 'example.com', 'www.example.com', 'staging.example.com', '*.example.org' ),
			Environment::parse_urls( $raw )
		);
	}

	public function test_parse_urls_accepts_arrays_and_removes_duplicates_and_junk(): void {
		$this->assertSame(
			array( 'a.com', 'b.com' ),
			Environment::parse_urls( array( 'A.com', 'https://a.com/', 'b.com', 'bad host!', '' ) )
		);
	}

	public function test_parse_urls_returns_nothing_for_empty_input(): void {
		$this->assertSame( array(), Environment::parse_urls( '' ) );
		$this->assertSame( array(), Environment::parse_urls( null ) );
	}

	public function test_host_matches_exact_and_wildcard_hosts(): void {
		$patterns = array( 'www.example.com', '*.example.org' );

		$this->assertTrue( Environment::host_matches( 'www.example.com', $patterns ) );
		$this->assertTrue( Environment::host_matches( 'WWW.Example.com', $patterns ) );
		$this->assertTrue( Environment::host_matches( 'shop.example.org', $patterns ) );
		$this->assertFalse( Environment::host_matches( 'example.org', $patterns ) );
		$this->assertFalse( Environment::host_matches( 'staging.example.com', $patterns ) );
		$this->assertFalse( Environment::host_matches( 'www.example.com.evil.net', $patterns ) );
	}
}
