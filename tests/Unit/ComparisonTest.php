<?php
/**
 * Tests for comparing Markdown copies and checking page text coverage.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Tests\Unit;

use AgentReady\Verify\Comparison;
use PHPUnit\Framework\TestCase;

final class ComparisonTest extends TestCase {

	public function test_normalise_ignores_line_endings_trailing_space_and_extra_blank_lines(): void {
		$this->assertSame(
			Comparison::normalise( "# Title  \r\n\r\n\r\n\r\nText\r\n" ),
			Comparison::normalise( "# Title\n\nText" )
		);
	}

	public function test_normalise_removes_ignored_patterns(): void {
		$this->assertSame(
			Comparison::normalise( 'Updated at 10:15', array( '/\d{2}:\d{2}/' ) ),
			Comparison::normalise( 'Updated at 22:40', array( '/\d{2}:\d{2}/' ) )
		);
	}

	public function test_identical_texts_have_no_changes(): void {
		$diff = Comparison::diff( "a\nb\nc", "a\nb\nc" );

		$this->assertSame( 0, Comparison::changed( $diff ) );
		$this->assertCount( 3, $diff );
	}

	public function test_diff_marks_added_removed_and_replaced_lines(): void {
		$diff = Comparison::diff( "one\ntwo\nthree", "one\n2\nthree\nfour" );

		$this->assertSame(
			array(
				array( 'same', 'one' ),
				array( 'del', 'two' ),
				array( 'add', '2' ),
				array( 'same', 'three' ),
				array( 'add', 'four' ),
			),
			array_map( static fn( array $line ): array => array( $line['type'], $line['text'] ), $diff )
		);
		$this->assertSame( 3, Comparison::changed( $diff ) );
	}

	public function test_diff_against_nothing(): void {
		$this->assertSame( 2, Comparison::changed( Comparison::diff( '', "a\nb" ) ) );
		$this->assertSame( 2, Comparison::changed( Comparison::diff( "a\nb", '' ) ) );
	}

	public function test_long_texts_fall_back_to_a_line_set_comparison(): void {
		$old = implode( "\n", array_map( static fn( int $i ): string => "line {$i}", range( 1, 600 ) ) );
		$new = $old . "\nextra line";

		$this->assertSame( 1, Comparison::changed( Comparison::diff( $old, $new ) ) );
	}

	public function test_condense_keeps_context_and_marks_gaps(): void {
		$old  = implode( "\n", range( 1, 20 ) );
		$new  = str_replace( "\n10\n", "\nten\n", $old );
		$rows = Comparison::condense( Comparison::diff( $old, $new ), 2 );
		$kind = array_map( static fn( array $row ): string => $row['type'], $rows );

		$this->assertSame( array( 'gap', 'same', 'same', 'del', 'add', 'same', 'same', 'gap' ), $kind );
		$this->assertSame( '7', $rows[0]['text'] );
	}

	public function test_coverage_finds_text_despite_markdown_syntax(): void {
		$markdown = "---\ntitle: \"Page\"\n---\n# Heading\n\n**Bold** and [a link](https://example.com/x) in a paragraph.\n\n- First item\n- Second item\n";
		$result   = Comparison::coverage( array( 'Heading', 'Bold and a link in a paragraph.', 'First item', 'Second item' ), $markdown );

		$this->assertSame( 4, $result['total'] );
		$this->assertSame( 4, $result['found'] );
		$this->assertSame( array(), $result['missing'] );
	}

	public function test_coverage_lists_missing_blocks_and_skips_tiny_ones(): void {
		$result = Comparison::coverage( array( 'Present text', 'Lost text here', 'ab' ), 'Present text only.' );

		$this->assertSame( 2, $result['total'] );
		$this->assertSame( 1, $result['found'] );
		$this->assertSame( array( 'Lost text here' ), $result['missing'] );
	}

	public function test_coverage_does_not_count_link_addresses_or_front_matter(): void {
		$markdown = "---\ndescription: \"Secret words\"\n---\n[click](https://example.com/hidden-words)\n";

		$this->assertSame( 2, count( Comparison::coverage( array( 'Secret words', 'hidden words' ), $markdown )['missing'] ) );
	}

	public function test_coverage_handles_accents_and_case(): void {
		$this->assertSame( array(), Comparison::coverage( array( 'Café Müller' ), 'Welcome to CAFÉ MÜLLER today' )['missing'] );
	}
}
