<?php
/**
 * Tests for the description fallback.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Tests\Unit;

use AgentReady\Rendering\Summary;
use PHPUnit\Framework\TestCase;

final class SummaryTest extends TestCase {

	public function test_uses_first_long_paragraph_without_markdown(): void {
		$markdown = "# Title\n\nShort.\n\n- item\n\nThis is a [long paragraph](https://x.test) with **bold** words that is longer than forty characters.\n";

		$this->assertSame( 'This is a long paragraph with bold words that is longer than forty characters.', ( new Summary() )->from_markdown( $markdown ) );
	}

	public function test_truncates_to_the_word_limit(): void {
		$markdown = 'one two three four five six seven eight nine ten eleven twelve';

		$this->assertSame( 'one two three four five six…', ( new Summary() )->from_markdown( $markdown, 6 ) );
	}

	public function test_skips_images_tables_quotes_and_code(): void {
		$markdown = "![x](https://x.test/a.png)\n\n| a | b |\n\n> a quote that is long enough to be a paragraph on its own\n\n```\ncode that is long enough to be a paragraph on its own\n```\n";

		$this->assertSame( '', ( new Summary() )->from_markdown( $markdown ) );
	}

	public function test_returns_empty_when_nothing_qualifies(): void {
		$this->assertSame( '', ( new Summary() )->from_markdown( "# Only a heading\n\nToo short.\n" ) );
	}
}
