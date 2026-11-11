<?php
/**
 * Tests for the pieces of llms-full.txt and the robots.txt lines.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Tests\Unit;

use AgentWelcome\Index\LlmsFullTxt;
use AgentWelcome\Robots\RobotsTxt;
use AgentWelcome\Settings\LlmsOptions;
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

	public function test_block_puts_the_source_under_the_pages_own_heading(): void {
		$this->assertSame(
			"\n---\n\n# About us\n\nSource: https://example.com/about/\n\nText\n\nMore\n",
			LlmsFullTxt::block( 'About', 'https://example.com/about/', "# About us\n\nText\n\nMore" )
		);
	}

	public function test_block_does_not_mistake_a_subheading_for_the_title(): void {
		$this->assertStringStartsWith( "\n---\n\n# About\n\nSource: https://example.com/about/\n\n## Part\n", LlmsFullTxt::block( 'About', 'https://example.com/about/', "## Part\nText" ) );
	}

	public function test_block_with_only_a_heading(): void {
		$this->assertSame( "\n---\n\n# Solo\n\nSource: https://example.com/s/\n", LlmsFullTxt::block( 'Solo', 'https://example.com/s/', '# Solo' ) );
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
		$this->assertSame( "\n# AgentWelcome\n# llms.txt: https://example.com/llms.txt\n", RobotsTxt::lines( 'https://example.com/llms.txt', null ) );
	}

	public function test_robots_lines_with_the_full_file(): void {
		$this->assertSame(
			"\n# AgentWelcome\n# llms.txt: https://example.com/llms.txt\n# llms-full.txt: https://example.com/llms-full.txt\n",
			RobotsTxt::lines( 'https://example.com/llms.txt', 'https://example.com/llms-full.txt' )
		);
	}

	public function test_a_custom_text_is_served_alone(): void {
		$this->assertTrue( LlmsFullTxt::is_custom_only( LlmsOptions::CUSTOM, '# Mine' ) );
	}

	public function test_an_empty_custom_text_falls_back_to_the_generated_file(): void {
		$this->assertFalse( LlmsFullTxt::is_custom_only( LlmsOptions::CUSTOM, '' ) );
	}

	public function test_the_other_modes_are_not_custom_only(): void {
		$this->assertFalse( LlmsFullTxt::is_custom_only( LlmsOptions::AUTOMATIC, '# Mine' ) );
		$this->assertFalse( LlmsFullTxt::is_custom_only( LlmsOptions::HYBRID, '# Mine' ) );
	}

	public function test_the_generated_file_starts_with_the_site_heading(): void {
		$this->assertSame( "# My site\n", LlmsFullTxt::start( LlmsOptions::AUTOMATIC, '# Mine', 'My site' ) );
	}

	public function test_hybrid_starts_with_the_custom_text(): void {
		$this->assertSame( "# Mine\n\nIntro\n", LlmsFullTxt::start( LlmsOptions::HYBRID, "# Mine\n\nIntro", 'My site' ) );
	}

	public function test_hybrid_without_a_text_starts_with_the_site_heading(): void {
		$this->assertSame( "# My site\n", LlmsFullTxt::start( LlmsOptions::HYBRID, '', 'My site' ) );
	}
}
