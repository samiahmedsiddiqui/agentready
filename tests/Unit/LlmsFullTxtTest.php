<?php
/**
 * Tests for the pieces of llms-full.txt and the robots.txt lines.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Tests\Unit;

use AgentReady\Index\LlmsFullTxt;
use AgentReady\Robots\RobotsTxt;
use PHPUnit\Framework\TestCase;

final class LlmsFullTxtTest extends TestCase {

	public function test_strips_the_front_matter(): void {
		$markdown = "---\ntitle: \"About\"\nurl: \"https://example.com/about/\"\n---\n\n# About\n\nText\n";

		$this->assertSame( "# About\n\nText", LlmsFullTxt::strip_front_matter( $markdown ) );
	}

	public function test_keeps_a_rule_that_is_not_front_matter(): void {
		$this->assertSame( "Intro\n\n---\n\nMore", LlmsFullTxt::strip_front_matter( "Intro\n\n---\n\nMore\n" ) );
	}

	public function test_strips_only_the_first_block(): void {
		$markdown = "---\na: \"b\"\n---\nBody\n\n---\nnot: front\n---\nEnd";

		$this->assertSame( "Body\n\n---\nnot: front\n---\nEnd", LlmsFullTxt::strip_front_matter( $markdown ) );
	}

	public function test_block_has_title_source_and_body(): void {
		$this->assertSame(
			"\n---\n\n# About\n\nSource: https://example.com/about/\n\nText\n",
			LlmsFullTxt::block( 'About', 'https://example.com/about/', 'Text' )
		);
	}

	public function test_block_uses_the_address_without_a_title(): void {
		$this->assertStringContainsString( "# https://example.com/a/\n", LlmsFullTxt::block( '', 'https://example.com/a/', 'Text' ) );
	}

	public function test_robots_lines_for_llms_txt_only(): void {
		$this->assertSame( "\n# AgentReady\n# llms.txt: https://example.com/llms.txt\n", RobotsTxt::lines( 'https://example.com/llms.txt', null ) );
	}

	public function test_robots_lines_with_the_full_file(): void {
		$this->assertSame(
			"\n# AgentReady\n# llms.txt: https://example.com/llms.txt\n# llms-full.txt: https://example.com/llms-full.txt\n",
			RobotsTxt::lines( 'https://example.com/llms.txt', 'https://example.com/llms-full.txt' )
		);
	}
}
