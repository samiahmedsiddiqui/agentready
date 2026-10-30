<?php
/**
 * Tests for telling one site address from another.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Tests\Unit;

use AgentReady\Environment\SiteAddress;
use PHPUnit\Framework\TestCase;

final class SiteAddressTest extends TestCase {

	/**
	 * @dataProvider same_addresses
	 */
	public function test_these_count_as_the_same_site( string $a, string $b ): void {
		$this->assertSame( SiteAddress::fingerprint( $a ), SiteAddress::fingerprint( $b ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public function same_addresses(): array {
		return array(
			'http and https'      => array( 'http://example.com', 'https://example.com' ),
			'www and bare'        => array( 'https://www.example.com', 'https://example.com' ),
			'trailing slash'      => array( 'https://example.com/', 'https://example.com' ),
			'letter case'         => array( 'https://Example.COM', 'https://example.com' ),
			'subdirectory slash'  => array( 'https://example.com/blog/', 'https://example.com/blog' ),
			'surrounding spaces'  => array( ' https://example.com ', 'https://example.com' ),
		);
	}

	/**
	 * @dataProvider other_addresses
	 */
	public function test_these_count_as_another_site( string $a, string $b ): void {
		$this->assertNotSame( SiteAddress::fingerprint( $a ), SiteAddress::fingerprint( $b ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public function other_addresses(): array {
		return array(
			'another domain'          => array( 'https://example.com', 'https://staging.example.com' ),
			'a host copy domain'      => array( 'https://example.com', 'https://example.hostcopy.net' ),
			'a subdirectory'          => array( 'https://example.com', 'https://example.com/blog' ),
			'another subdirectory'    => array( 'https://example.com/blog', 'https://example.com/news' ),
			'another port'            => array( 'http://localhost:8080', 'http://localhost:8899' ),
			'a different subdomain'   => array( 'https://shop.example.com', 'https://blog.example.com' ),
		);
	}

	public function test_the_fingerprint_is_a_hash_that_hides_the_address(): void {
		$fingerprint = SiteAddress::fingerprint( 'https://example.com' );

		$this->assertSame( 64, strlen( $fingerprint ) );
		$this->assertStringNotContainsString( 'example', $fingerprint );
	}

	public function test_normalise_keeps_host_port_and_path(): void {
		$this->assertSame( 'localhost:8899/blog', SiteAddress::normalise( 'http://www.localhost:8899/blog/' ) );
		$this->assertSame( '', SiteAddress::normalise( '' ) );
	}
}
