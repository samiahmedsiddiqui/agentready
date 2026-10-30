<?php
/**
 * Tests for how the llms.txt file is put together.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Tests\Unit;

use AgentReady\Index\LlmsTxt;
use PHPUnit\Framework\TestCase;

final class LlmsTxtTest extends TestCase {

	private const HEADING = array( '# Site', '', '> Tagline' );

	private const SECTIONS = array( '## Pages', '', '- [Home](https://example.com/index.md)', '', '## Posts', '', '- [Hi](https://example.com/hi.md)' );

	public function test_automatic_lists_pages_under_the_heading(): void {
		$this->assertSame(
			"# Site\n\n> Tagline\n\n## Pages\n\n- [Home](https://example.com/index.md)\n\n## Posts\n\n- [Hi](https://example.com/hi.md)\n",
			LlmsTxt::compose( 'automatic', '', self::HEADING, self::SECTIONS )
		);
	}

	public function test_automatic_without_pages_is_only_the_heading(): void {
		$this->assertSame( "# Site\n\n> Tagline\n", LlmsTxt::compose( 'automatic', '', self::HEADING, array() ) );
	}

	public function test_automatic_ignores_saved_custom_text(): void {
		$this->assertStringStartsWith( "# Site\n", LlmsTxt::compose( 'automatic', '# Mine', self::HEADING, self::SECTIONS ) );
	}

	public function test_custom_is_served_as_written(): void {
		$this->assertSame( "# Mine\n\nHello\n", LlmsTxt::compose( 'custom', "# Mine\n\nHello", self::HEADING, self::SECTIONS ) );
	}

	public function test_hybrid_puts_custom_text_before_the_pages(): void {
		$this->assertSame(
			"# Mine\n\n## Pages\n\n- [Home](https://example.com/index.md)\n\n## Posts\n\n- [Hi](https://example.com/hi.md)\n",
			LlmsTxt::compose( 'hybrid', '# Mine', self::HEADING, self::SECTIONS )
		);
	}

	public function test_hybrid_without_pages_is_only_the_custom_text(): void {
		$this->assertSame( "# Mine\n", LlmsTxt::compose( 'hybrid', '# Mine', self::HEADING, array() ) );
	}

	/**
	 * @dataProvider empty_custom
	 */
	public function test_empty_custom_text_falls_back_to_the_generated_file( string $mode ): void {
		$this->assertSame(
			LlmsTxt::compose( 'automatic', '', self::HEADING, self::SECTIONS ),
			LlmsTxt::compose( $mode, '', self::HEADING, self::SECTIONS )
		);
	}

	/**
	 * @return array<string, array{string}>
	 */
	public function empty_custom(): array {
		return array(
			'custom' => array( 'custom' ),
			'hybrid' => array( 'hybrid' ),
		);
	}
}
