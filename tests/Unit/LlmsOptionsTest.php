<?php
/**
 * Tests for the llms.txt page settings helpers.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Tests\Unit;

use AgentWelcome\Settings\LlmsOptions;
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

	public function test_the_defaults_cover_both_files(): void {
		$defaults = LlmsOptions::defaults();

		$this->assertSame( LlmsOptions::AUTOMATIC, $defaults['mode'] );
		$this->assertSame( LlmsOptions::AUTOMATIC, $defaults['full_mode'] );
		$this->assertSame( '', $defaults['custom'] );
		$this->assertSame( '', $defaults['full_custom'] );
	}

	public function test_sanitize_cleans_the_text_and_mode_of_each_file(): void {
		$saved = ( new LlmsOptions() )->sanitize(
			array(
				'mode'        => 'hybrid',
				'custom'      => "# Short\r\n",
				'full_mode'   => 'custom',
				'full_custom' => "  # Everything\r\n\x01 ",
			)
		);

		$this->assertSame( 'hybrid', $saved['mode'] );
		$this->assertSame( '# Short', $saved['custom'] );
		$this->assertSame( 'custom', $saved['full_mode'] );
		$this->assertSame( '# Everything', $saved['full_custom'] );
	}

	public function test_sanitize_falls_back_for_a_bad_mode_and_a_bad_text(): void {
		$saved = ( new LlmsOptions() )->sanitize(
			array(
				'full_mode'   => 'something else',
				'full_custom' => array( 'not text' ),
			)
		);

		$this->assertSame( LlmsOptions::AUTOMATIC, $saved['full_mode'] );
		$this->assertSame( '', $saved['full_custom'] );
	}

	public function test_sanitize_cuts_a_long_text_at_the_limit(): void {
		$saved = ( new LlmsOptions() )->sanitize( array( 'full_custom' => str_repeat( 'a', LlmsOptions::MAX_BYTES + 500 ) ) );

		$this->assertSame( LlmsOptions::MAX_BYTES, strlen( $saved['full_custom'] ) );
	}
}
