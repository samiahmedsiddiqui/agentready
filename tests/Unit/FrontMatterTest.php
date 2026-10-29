<?php
/**
 * Tests for the front matter block.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Tests\Unit;

use AgentReady\Rendering\FrontMatter;
use PHPUnit\Framework\TestCase;

final class FrontMatterTest extends TestCase {

	public function test_renders_quoted_values_and_skips_empty(): void {
		$output = ( new FrontMatter() )->render(
			array(
				'title'       => 'A "quoted" title',
				'url'         => 'https://example.com/a/',
				'description' => '',
				'language'    => 'en-US',
			)
		);

		$this->assertSame( "---\ntitle: \"A \\\"quoted\\\" title\"\nurl: \"https://example.com/a/\"\nlanguage: \"en-US\"\n---\n", $output );
	}
}
