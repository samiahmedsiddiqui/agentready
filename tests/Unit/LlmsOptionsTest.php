<?php
/**
 * Tests for the llms.txt page settings helpers.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Tests\Unit;

use AgentReady\Settings\LlmsOptions;
use PHPUnit\Framework\TestCase;

final class LlmsOptionsTest extends TestCase {

	/**
	 * @dataProvider modes
	 */
	public function test_cleans_mode( mixed $input, string $expected ): void {
		$this->assertSame( $expected, LlmsOptions::clean_mode( $input ) );
	}

	/**
	 * @return array<string, array{mixed, string}>
	 */
	public function modes(): array {
		return array(
			'custom'    => array( 'custom', 'custom' ),
			'hybrid'    => array( 'hybrid', 'hybrid' ),
			'automatic' => array( 'automatic', 'automatic' ),
			'unknown'   => array( 'full', 'automatic' ),
			'empty'     => array( '', 'automatic' ),
			'array'     => array( array( 'custom' ), 'automatic' ),
			'null'      => array( null, 'automatic' ),
		);
	}

	public function test_normalises_line_endings_and_control_characters(): void {
		$this->assertSame( "# Site\n\nLine\nTab\there", LlmsOptions::normalise( "  # Site\r\n\r\nLine\rTab\there\x00\x07\n " ) );
	}

	public function test_normalise_keeps_unicode(): void {
		$this->assertSame( 'Café — 日本語', LlmsOptions::normalise( 'Café — 日本語' ) );
	}

	public function test_empty_text_has_no_notes(): void {
		$this->assertSame( array(), LlmsOptions::check( '', 'example.com' ) );
	}

	public function test_good_text_has_no_notes(): void {
		$text = "# Site\n\n- [Home](https://www.example.com/index.md): Start";

		$this->assertSame( array(), LlmsOptions::check( $text, 'example.com' ) );
	}

	public function test_flags_missing_title(): void {
		$this->assertSame( array( 'no_title' ), LlmsOptions::check( 'Just words', 'example.com' ) );
		$this->assertSame( array( 'no_title' ), LlmsOptions::check( "#Nospace\n", 'example.com' ) );
	}

	public function test_flags_links_to_other_hosts(): void {
		$text = "# Site\n\n[Other](https://other.test/a.md)";

		$this->assertSame( array( 'external' ), LlmsOptions::check( $text, 'example.com' ) );
	}

	public function test_own_host_with_www_is_not_external(): void {
		$text = "# Site\n\n[Home](https://example.com/a.md) and [Again](<https://WWW.Example.com/b.md>)";

		$this->assertSame( array(), LlmsOptions::check( $text, 'www.example.com' ) );
	}

	public function test_flags_large_text(): void {
		$text = "# Site\n" . str_repeat( 'a', LlmsOptions::LARGE_BYTES );

		$this->assertSame( array( 'large' ), LlmsOptions::check( $text, 'example.com' ) );
	}
}
