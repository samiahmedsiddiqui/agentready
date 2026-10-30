<?php
/**
 * Tests for collecting the text blocks of a page's content area.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Tests\Unit;

use AgentReady\Rendering\Extractor;
use AgentReady\Rendering\Selector;
use PHPUnit\Framework\TestCase;

final class TextBlocksTest extends TestCase {

	/**
	 * @return array{kept: string[], excluded: string[]}
	 */
	private function blocks( string $body, string $selector = 'main' ): array {
		return ( new Extractor( new Selector() ) )->text_blocks( '<html><body>' . $body . '</body></html>', $selector );
	}

	public function test_collects_headings_paragraphs_and_list_items(): void {
		$result = $this->blocks( '<main><h1>Title</h1><p>First <strong>bold</strong> text.</p><ul><li>One</li><li>Two <a href="/x">link</a></li></ul></main>' );

		$this->assertSame( array( 'Title', 'First bold text.', 'One', 'Two link' ), $result['kept'] );
		$this->assertSame( array(), $result['excluded'] );
	}

	public function test_keeps_text_next_to_nested_lists_and_in_plain_divs(): void {
		$result = $this->blocks( '<main><div>Loose words<ul><li>Inner item</li></ul>After list</div></main>' );

		$this->assertSame( array( 'Loose words', 'Inner item', 'After list' ), $result['kept'] );
	}

	public function test_skips_scripts_hidden_elements_and_form_controls(): void {
		$result = $this->blocks( '<main><p>Visible</p><script>var secret = 1;</script><p hidden>Hidden attr</p><p aria-hidden="true">Hidden aria</p><p style="display: none">Hidden style</p><button>Press me</button><p>Also visible</p></main>' );

		$this->assertSame( array( 'Visible', 'Also visible' ), $result['kept'] );
	}

	public function test_links_wrapping_blocks_are_not_flattened(): void {
		$result = $this->blocks( '<main><a href="/card"><h3>Card title</h3><p>Card text</p></a></main>' );

		$this->assertSame( array( 'Card title', 'Card text' ), $result['kept'] );
	}

	public function test_reports_text_removed_by_the_exclusions(): void {
		$result = $this->blocks( '<main><p>Keep this</p><nav><p>Site menu words</p></nav><aside><p>Related posts</p></aside></main>' );

		$this->assertSame( array( 'Keep this' ), $result['kept'] );
		$this->assertSame( array( 'Site menu words', 'Related posts' ), $result['excluded'] );
	}

	public function test_a_missing_selector_falls_back_to_the_body_and_empty_html_gives_nothing(): void {
		$this->assertSame( array( 'Only body text' ), $this->blocks( '<p>Only body text</p>', '#nope' )['kept'] );
		$this->assertSame( array(), ( new Extractor( new Selector() ) )->text_blocks( '', 'main' )['kept'] );
	}

	public function test_ignores_blocks_without_letters_or_digits(): void {
		$this->assertSame( array( 'Real text' ), $this->blocks( '<main><p>—</p><p>&nbsp;</p><p>Real text</p></main>' )['kept'] );
	}
}
