<?php
/**
 * Converts a DOM subtree to Markdown. Only text is emitted, never raw HTML.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Rendering;

use DOMElement;
use DOMNode;

defined( 'ABSPATH' ) || exit;

/** Converts a DOM subtree to Markdown. Only text is written, never raw HTML. */
final class Converter {

	/** The start of a Markdown block: heading, list item, quote, table row, code fence or image. */
	private const BLOCK_START = '/^(#|[-*+] |\d+[.)] |>|\||`{3}|!\[)/';

	/** A block that is only an image. */
	private const STANDALONE_IMAGE = '/^!\[([^\]]*)\]\([^)]*\)$/';

	/** Heading, quote and bullet markers at the start of plain text. */
	private const BLOCK_MARKER_ESCAPE = '/^(\s*)(#{1,6}(?=\s)|[>+-](?=\s))/';

	/** A number followed by . or ) at the start of plain text. */
	private const NUMBER_MARKER_ESCAPE = '/^(\s*\d+)([.)])(?=\s)/';

	/** An underscore at the edge of a word. */
	private const EDGE_UNDERSCORE = '/(?<![\p{L}\p{N}])_|_(?![\p{L}\p{N}])/u';

	/** Marker for a hard line break. It becomes two spaces after the text is tidied. */
	private const BREAK = "\x01";

	/** Deepest nesting that is converted. Deeper content is written as escaped text, so a hostile page cannot exhaust the stack. */
	private const MAX_DEPTH = 100;

	/** Symbols that icon fonts and lists use on their own line. They are joined to the text after them. */
	private const ICONS = array( '✓', '✔', '✗', '✘', '✕', '✖', '×', '•', '●', '◦', '▪', '★', '☆', '✦', '→', '←', '↗', '➜', '✅', '❌', '⭐' );

	/** Elements that start a new block of text. */
	private const CONTAINERS = array(
		'html',
		'body',
		'div',
		'section',
		'article',
		'main',
		'header',
		'footer',
		'figure',
		'address',
		'center',
		'hgroup',
		'fieldset',
		'legend',
		'dialog',
		'menu',
		'nav',
		'aside',
		'form',
		'p',
		'figcaption',
		'caption',
	);

	/**
	 * Converts the content of a node to Markdown.
	 *
	 * @param DOMNode $root Node whose content is converted.
	 * @return string The Markdown, ending with a line break, or an empty string when there is no text.
	 */
	public function convert( DOMNode $root ): string {
		return $this->tidy( $this->children( $root, 0, false ) );
	}

	/**
	 * Cleans the converted text: removes trailing spaces, turns break markers into line breaks and polishes the blocks.
	 *
	 * @param string $markdown Converted text.
	 * @return string Final Markdown, or an empty string.
	 */
	private function tidy( string $markdown ): string {
		$markdown = str_replace( "\r", '', $markdown );
		$markdown = (string) preg_replace( '/[ \t]+\n/', "\n", $markdown );
		$markdown = str_replace( self::BREAK, '  ', $markdown );
		$markdown = trim( $this->polish( trim( $markdown ) ) );

		return '' === $markdown ? '' : $markdown . "\n";
	}

	/**
	 * Converts the child nodes of a node.
	 *
	 * @param DOMNode $node  Parent node.
	 * @param int     $depth Nesting depth of the parent.
	 * @param bool    $pre   True inside preformatted text, where whitespace is kept.
	 * @return string Markdown of the children.
	 */
	private function children( DOMNode $node, int $depth, bool $pre ): string {
		$output = '';

		foreach ( $node->childNodes as $child ) {
			$output .= $this->node( $child, $depth + 1, $pre );
		}

		return $output;
	}

	/**
	 * Converts one node.
	 *
	 * @param DOMNode $node  Node to convert.
	 * @param int     $depth Nesting depth.
	 * @param bool    $pre   True inside preformatted text, where whitespace is kept.
	 * @return string Markdown of the node.
	 */
	private function node( DOMNode $node, int $depth, bool $pre ): string {
		if ( $depth > self::MAX_DEPTH ) {
			return $this->escape( (string) $node->textContent );
		}

		if ( XML_TEXT_NODE === $node->nodeType || XML_CDATA_SECTION_NODE === $node->nodeType ) {
			return $pre ? (string) $node->nodeValue : $this->text( (string) $node->nodeValue );
		}

		if ( $node instanceof DOMElement ) {
			return $this->element( $node, $depth, $pre );
		}

		return '';
	}

	/**
	 * Converts one element according to its tag.
	 *
	 * @param DOMElement $element Element to convert.
	 * @param int        $depth   Nesting depth.
	 * @param bool       $pre     True inside preformatted text, where whitespace is kept.
	 * @return string Markdown of the element.
	 */
	private function element( DOMElement $element, int $depth, bool $pre ): string {
		$tag = strtolower( $element->nodeName );

		if ( 1 === preg_match( '/^h([1-6])$/', $tag, $level ) ) {
			$text = (string) preg_replace( '/^\*{1,4}([^*]+)\*{1,4}$/', '$1', $this->inline( $element, $depth, $pre ) );

			return '' === $text ? '' : "\n\n" . str_repeat( '#', (int) $level[1] ) . ' ' . $text . "\n\n";
		}

		switch ( $tag ) {
			case 'br':
				return self::BREAK . "\n";
			case 'hr':
				return "\n\n---\n\n";
			case 'strong':
			case 'b':
				return $this->wrap( '**', $this->children( $element, $depth, $pre ) );
			case 'em':
			case 'i':
				return $this->wrap( '*', $this->children( $element, $depth, $pre ) );
			case 'del':
			case 's':
			case 'strike':
				return $this->wrap( '~~', $this->children( $element, $depth, $pre ) );
			case 'sup':
				return $this->superscript( trim( $this->inline( $element, $depth, $pre ) ) );
			case 'q':
				return '"' . trim( $this->children( $element, $depth, $pre ) ) . '"';
			case 'code':
				return $this->inline_code( $element->textContent );
			case 'pre':
				return $this->code_block( $element );
			case 'a':
				return $this->link( $element, $depth, $pre );
			case 'img':
				return $this->image( $element );
			case 'ul':
			case 'ol':
				return $this->list_block( $element, 'ol' === $tag, $depth );
			case 'blockquote':
				return $this->quote( $element, $depth );
			case 'table':
				return $this->table( $element, $depth );
			case 'dt':
				return $this->block( $this->wrap( '**', $this->inline( $element, $depth, $pre ) ) );
			case 'dd':
				return $this->block( $this->inline( $element, $depth, $pre ) );
			case 'summary':
				return $this->block( $this->wrap( '**', $this->inline( $element, $depth, $pre ) ) );
			case 'iframe':
				return $this->embed( $element, 'Embedded content' );
			case 'video':
				return $this->embed( $element, 'Video' );
			case 'audio':
				return $this->embed( $element, 'Audio' );
		}

		if ( in_array( $tag, self::CONTAINERS, true ) || in_array( $tag, array( 'dl', 'details' ), true ) ) {
			return $this->block( $this->children( $element, $depth, $pre ) );
		}

		return $this->children( $element, $depth, $pre );
	}

	/**
	 * Sets text apart as its own block.
	 *
	 * @param string $content Markdown.
	 * @return string The text between blank lines, or an empty string when it is empty.
	 */
	private function block( string $content ): string {
		$content = trim( $content );

		return '' === $content ? '' : "\n\n" . $content . "\n\n";
	}

	/**
	 * Converts the content of a node to a single line of text.
	 *
	 * @param DOMNode $node  Node to convert.
	 * @param int     $depth Nesting depth.
	 * @param bool    $pre   True inside preformatted text, where whitespace is kept.
	 * @return string One line of Markdown.
	 */
	private function inline( DOMNode $node, int $depth, bool $pre ): string {
		$text = str_replace( self::BREAK, ' ', $this->children( $node, $depth, $pre ) );

		return trim( (string) preg_replace( '/\s*\n\s*/', ' ', $text ) );
	}

	/**
	 * Writes superscript text. Short marks such as footnote numbers stay attached; longer text goes in brackets.
	 *
	 * @param string $text Text of the superscript.
	 * @return string Markdown for it.
	 */
	private function superscript( string $text ): string {
		if ( '' === $text || 1 === preg_match( '/^[™®©]+$/u', $text ) ) {
			return $text;
		}

		return 1 === preg_match( '/^[\w*†‡§]{1,4}$/u', $text ) ? '^' . $text : ' (' . $text . ')';
	}

	/**
	 * Joins lone icon blocks to the text after them and drops images whose alt text repeats a neighbouring block.
	 *
	 * @param string $markdown Converted text.
	 * @return string Polished text.
	 */
	private function polish( string $markdown ): string {
		$blocks = $this->blocks( $markdown );
		$kept   = array();
		$count  = count( $blocks );

		for ( $i = 0; $i < $count; $i++ ) {
			$block = $blocks[ $i ];

			if ( in_array( $block, self::ICONS, true ) && $i + 1 < $count && $this->is_plain( $blocks[ $i + 1 ] ) ) {
				$blocks[ $i + 1 ] = $block . ' ' . $blocks[ $i + 1 ];
				continue;
			}

			$alt = $this->standalone_alt( $block );

			if ( null !== $alt && $this->repeats_neighbour( $alt, $kept, $blocks, $i ) ) {
				continue;
			}

			$kept[] = $block;
		}

		return implode( "\n\n", $kept );
	}

	/**
	 * Whether an image alt text only repeats the block before it or the block after it.
	 *
	 * @param string   $alt    Alt text of the image.
	 * @param string[] $kept   Blocks kept so far.
	 * @param string[] $blocks All blocks.
	 * @param int      $index  Position of the image block in the list of all blocks.
	 * @return bool True when it repeats a neighbour.
	 */
	private function repeats_neighbour( string $alt, array $kept, array $blocks, int $index ): bool {
		return $this->same_text( $alt, (string) end( $kept ) ) || ( $index + 1 < count( $blocks ) && $this->same_text( $alt, $blocks[ $index + 1 ] ) );
	}

	/**
	 * Splits Markdown into blocks at blank lines, keeping fenced code together.
	 *
	 * @param string $markdown Converted text.
	 * @return string[] The blocks.
	 */
	private function blocks( string $markdown ): array {
		$blocks  = array();
		$current = array();
		$fence   = '';

		foreach ( explode( "\n", $markdown ) as $line ) {
			if ( '' === $fence && 1 === preg_match( '/^(`{3,})/', $line, $open ) ) {
				$fence = $open[1];
			} elseif ( '' !== $fence && trim( $line ) === $fence ) {
				$fence = '';
			} elseif ( '' === $fence && '' === trim( $line ) ) {
				if ( ! empty( $current ) ) {
					$blocks[] = implode( "\n", $current );
					$current  = array();
				}
				continue;
			}

			$current[] = $line;
		}

		if ( ! empty( $current ) ) {
			$blocks[] = implode( "\n", $current );
		}

		return $blocks;
	}

	/**
	 * Whether a block is plain text, not a heading, list, quote, table, code or image.
	 *
	 * @param string $block A block.
	 * @return bool True when it is plain text.
	 */
	private function is_plain( string $block ): bool {
		return 0 === preg_match( self::BLOCK_START, $block ) && ! in_array( $block, self::ICONS, true );
	}

	/**
	 * Alt text of a block that is only an image.
	 *
	 * @param string $block A block.
	 * @return string|null The alt text, or null when the block is not an image or has none.
	 */
	private function standalone_alt( string $block ): ?string {
		return 1 === preg_match( self::STANDALONE_IMAGE, $block, $match ) && '' !== trim( $match[1] ) ? $match[1] : null;
	}

	/**
	 * Whether two texts are the same once markup and case are ignored.
	 *
	 * @param string $first  First text.
	 * @param string $second Second text.
	 * @return bool True when they match and are not empty.
	 */
	private function same_text( string $first, string $second ): bool {
		return $this->plain( $first ) === $this->plain( $second ) && '' !== $this->plain( $first );
	}

	/**
	 * Reduces text to lower case words without heading marks or emphasis.
	 *
	 * @param string $text Text.
	 * @return string The reduced text.
	 */
	private function plain( string $text ): string {
		$text = (string) preg_replace( '/^#+\s+/', '', trim( $text ) );
		$text = (string) preg_replace( '/[\\*_`]/', '', $text );

		return strtolower( trim( (string) preg_replace( '/\s+/u', ' ', $text ) ) );
	}

	/**
	 * Wraps text in a marker, keeping the spaces outside. Text that is already wrapped is left as it is.
	 *
	 * @param string $marker  Marker such as ** or *.
	 * @param string $content Text to wrap.
	 * @return string The wrapped text.
	 */
	private function wrap( string $marker, string $content ): string {
		if ( ! preg_match( '/^(\s*)(.*?)(\s*)$/s', $content, $match ) || '' === $match[2] ) {
			return $content;
		}

		// Already wrapped by the same marker, for example <strong><strong>text</strong></strong>.
		$quoted = preg_quote( $marker, '/' );

		if ( 1 === preg_match( '/^' . $quoted . '(?!\*).*(?<!\*)' . $quoted . '$/s', $match[2] ) ) {
			return $content;
		}

		return $match[1] . $marker . $match[2] . $marker . $match[3];
	}

	/**
	 * Converts a text node. Whitespace is collapsed and characters that Markdown would read as markup are escaped.
	 *
	 * @param string $value Text of the node.
	 * @return string Escaped text.
	 */
	private function text( string $value ): string {
		$text = (string) preg_replace( '/\s+/u', ' ', $value );

		if ( '' === trim( $text ) ) {
			return $text;
		}

		$text = $this->escape( $text );
		$text = (string) preg_replace( self::BLOCK_MARKER_ESCAPE, '$1\\\\$2', $text );

		return (string) preg_replace( self::NUMBER_MARKER_ESCAPE, '$1\\\\$2', $text );
	}

	/**
	 * Escapes characters that Markdown would read as markup.
	 *
	 * @param string $text Plain text.
	 * @return string Escaped text.
	 */
	private function escape( string $text ): string {
		$text = str_replace(
			array( '\\', '`', '*', '[', ']', '<' ),
			array( '\\\\', '\\`', '\\*', '\\[', '\\]', '\\<' ),
			$text
		);

		return (string) preg_replace( self::EDGE_UNDERSCORE, '\\_', $text );
	}

	/**
	 * Builds a fence that is longer than any run of the character inside the content.
	 *
	 * @param string $content   Text to fence.
	 * @param string $character Fence character, such as a backtick.
	 * @return string The fence.
	 */
	private function fence( string $content, string $character ): string {
		$longest = 0;

		if ( preg_match_all( '/' . preg_quote( $character, '/' ) . '+/', $content, $runs ) ) {
			foreach ( $runs[0] as $run ) {
				$longest = max( $longest, strlen( $run ) );
			}
		}

		return str_repeat( $character, $longest + 1 );
	}

	/**
	 * Writes inline code, with a fence long enough for any backticks inside.
	 *
	 * @param string $content Code text.
	 * @return string Markdown, or an empty string when the code is empty.
	 */
	private function inline_code( string $content ): string {
		$content = trim( (string) preg_replace( '/\s+/', ' ', $content ) );

		if ( '' === $content ) {
			return '';
		}

		$fence = $this->fence( $content, '`' );
		$pad   = ( '`' === $content[0] || '`' === substr( $content, -1 ) ) ? ' ' : '';

		return $fence . $pad . $content . $pad . $fence;
	}

	/**
	 * Writes a fenced code block, with the language when one can be found.
	 *
	 * @param DOMElement $pre The pre element.
	 * @return string Markdown, or an empty string when the code is empty.
	 */
	private function code_block( DOMElement $pre ): string {
		$code = ltrim( $pre->textContent, "\n" );
		$code = rtrim( $code );

		if ( '' === trim( $code ) ) {
			return '';
		}

		$fence = $this->fence( $code, '`' );
		$fence = strlen( $fence ) < 3 ? '```' : $fence;

		return "\n\n" . $fence . $this->language( $pre ) . "\n" . $code . "\n" . $fence . "\n\n";
	}

	/**
	 * Finds the language of a code block from its attributes or class.
	 *
	 * @param DOMElement $pre The pre element.
	 * @return string Lower case language name, or an empty string.
	 */
	private function language( DOMElement $pre ): string {
		$candidates = array( $pre );

		foreach ( $pre->getElementsByTagName( 'code' ) as $code ) {
			$candidates[] = $code;
			break;
		}

		foreach ( $candidates as $candidate ) {
			foreach ( array( 'data-enlighter-language', 'data-lang', 'data-language' ) as $attribute ) {
				$value = trim( $candidate->getAttribute( $attribute ) );

				if ( 1 === preg_match( '/^[\w+#.-]+$/', $value ) ) {
					return strtolower( $value );
				}
			}

			if ( 1 === preg_match( '/(?:^|\s)(?:language|lang)-([\w+#.-]+)/', $candidate->getAttribute( 'class' ), $match ) ) {
				return strtolower( $match[1] );
			}
		}

		return '';
	}

	/**
	 * Writes a link. Without text or an address, only the text is written.
	 *
	 * @param DOMElement $element The a element.
	 * @param int        $depth   Nesting depth.
	 * @param bool       $pre     True inside preformatted text, where whitespace is kept.
	 * @return string Markdown of the link.
	 */
	private function link( DOMElement $element, int $depth, bool $pre ): string {
		$text = $this->inline( $element, $depth, $pre );
		$href = trim( $element->getAttribute( 'href' ) );

		if ( '' === $text || '' === $href ) {
			return $text;
		}

		return '[' . $text . '](' . $this->destination( $href ) . ')';
	}

	/**
	 * Escapes characters that would end a link address.
	 *
	 * @param string $url Address.
	 * @return string The address.
	 */
	private function destination( string $url ): string {
		return str_replace( array( ' ', '(', ')' ), array( '%20', '%28', '%29' ), $url );
	}

	/**
	 * Writes an image. Tracking pixels and images that only repeat nearby text are left out.
	 *
	 * @param DOMElement $element The img element.
	 * @return string Markdown, or an empty string when the image is left out.
	 */
	private function image( DOMElement $element ): string {
		$src = trim( $element->getAttribute( 'src' ) );

		if ( '' === $src || '1' === $element->getAttribute( 'width' ) || '1' === $element->getAttribute( 'height' ) ) {
			return '';
		}

		$alt = trim( (string) preg_replace( '/\s+/', ' ', $element->getAttribute( 'alt' ) ) );

		if ( $this->repeats_parent_text( $element, $alt ) ) {
			return '';
		}

		$alt = str_replace( array( '[', ']' ), array( '\\[', '\\]' ), $alt );

		return '![' . $alt . '](' . $this->destination( $src ) . ')';
	}

	/**
	 * Whether the nearest ancestor that has text, within a few levels, is a short label that already says the alt text.
	 *
	 * @param DOMElement $image Image element.
	 * @param string     $alt   Alt text, cleaned.
	 * @return bool True when the alt text adds nothing.
	 */
	private function repeats_parent_text( DOMElement $image, string $alt ): bool {
		if ( '' === $alt ) {
			return false;
		}

		$ancestor = $image->parentNode;

		for ( $level = 0; $level < 4 && $ancestor instanceof DOMElement; $level++ ) {
			if ( in_array( strtolower( $ancestor->nodeName ), array( 'figure', 'body', 'main', 'article', 'section' ), true ) ) {
				return false;
			}

			$text = $this->plain( $ancestor->textContent );

			if ( '' !== $text ) {
				return strlen( $text ) <= 80 && str_contains( $text, $this->plain( $alt ) );
			}

			$ancestor = $ancestor->parentNode;
		}

		return false;
	}

	/**
	 * Writes a link for an embedded frame, video or audio.
	 *
	 * @param DOMElement $element Element with the source address.
	 * @param string     $label   Link text used when the element has no title.
	 * @return string Markdown, or an empty string when there is no address.
	 */
	private function embed( DOMElement $element, string $label ): string {
		$src = trim( $element->getAttribute( 'src' ) );

		if ( '' === $src ) {
			foreach ( $element->getElementsByTagName( 'source' ) as $source ) {
				$src = trim( $source->getAttribute( 'src' ) );

				if ( '' !== $src ) {
					break;
				}
			}
		}

		if ( '' === $src ) {
			return '';
		}

		$title = trim( $element->getAttribute( 'title' ) );

		return $this->block( '[' . ( '' === $title ? $label : $this->escape( $title ) ) . '](' . $this->destination( $src ) . ')' );
	}

	/**
	 * Writes a block quote.
	 *
	 * @param DOMElement $element The blockquote element.
	 * @param int        $depth   Nesting depth.
	 * @return string Markdown, or an empty string when the quote is empty.
	 */
	private function quote( DOMElement $element, int $depth ): string {
		$content = trim( $this->children( $element, $depth, false ) );

		if ( '' === $content ) {
			return '';
		}

		$lines = array_map(
			static function ( string $line ): string {
				return '' === trim( $line ) ? '>' : '> ' . $line;
			},
			explode( "\n", $content )
		);

		return "\n\n" . implode( "\n", $lines ) . "\n\n";
	}

	/**
	 * Writes a bulleted or numbered list.
	 *
	 * @param DOMElement $node    The ul or ol element.
	 * @param bool       $ordered True for a numbered list.
	 * @param int        $depth   Nesting depth.
	 * @return string Markdown, or an empty string when the list is empty.
	 */
	private function list_block( DOMElement $node, bool $ordered, int $depth ): string {
		$number = max( 1, (int) $node->getAttribute( 'start' ) );
		$items  = array();

		foreach ( $node->childNodes as $child ) {
			if ( ! $child instanceof DOMElement ) {
				continue;
			}

			$content = trim( $this->children( $child, $depth, false ) );

			if ( '' === $content ) {
				continue;
			}

			$marker = $ordered ? $number . '. ' : '- ';

			++$number;
			$indent = str_repeat( ' ', strlen( $marker ) );
			$lines  = str_replace( "\n", "\n" . $indent, (string) preg_replace( "/\n{2,}/", "\n", $content ) );

			$items[] = $marker . $lines;
		}

		return empty( $items ) ? '' : "\n\n" . implode( "\n", $items ) . "\n\n";
	}

	/**
	 * Writes a table. The first row becomes the header.
	 *
	 * @param DOMElement $table The table element.
	 * @param int        $depth Nesting depth.
	 * @return string Markdown, or only the caption when the table has no rows.
	 */
	private function table( DOMElement $table, int $depth ): string {
		$rows    = array();
		$caption = '';

		foreach ( $table->getElementsByTagName( 'caption' ) as $node ) {
			$caption = $this->block( $this->inline( $node, $depth, false ) );
			break;
		}

		foreach ( $table->getElementsByTagName( 'tr' ) as $tr ) {
			if ( $this->closest_table( $tr ) !== $table ) {
				continue;
			}

			$cells = array();

			foreach ( $tr->childNodes as $cell ) {
				if ( ! $cell instanceof DOMElement || ! in_array( strtolower( $cell->nodeName ), array( 'td', 'th' ), true ) ) {
					continue;
				}

				$cells[] = str_replace( '|', '\\|', $this->inline( $cell, $depth, false ) );

				$span = max( 1, (int) $cell->getAttribute( 'colspan' ) );

				for ( $i = 1; $i < $span; $i++ ) {
					$cells[] = '';
				}
			}

			if ( ! empty( $cells ) ) {
				$rows[] = $cells;
			}
		}

		if ( empty( $rows ) ) {
			return $caption;
		}

		$columns = max( array_map( 'count', $rows ) );
		$lines   = array();

		foreach ( $rows as $index => $cells ) {
			$cells   = array_pad( $cells, $columns, '' );
			$lines[] = '| ' . implode( ' | ', $cells ) . ' |';

			if ( 0 === $index ) {
				$lines[] = '|' . str_repeat( ' --- |', $columns );
			}
		}

		return $caption . "\n\n" . implode( "\n", $lines ) . "\n\n";
	}

	/**
	 * Finds the table that contains a node.
	 *
	 * @param DOMNode $node Node inside a table.
	 * @return DOMNode|null The nearest table ancestor, or null.
	 */
	private function closest_table( DOMNode $node ): ?DOMNode {
		$parent = $node->parentNode;

		while ( null !== $parent && 'table' !== strtolower( $parent->nodeName ) ) {
			$parent = $parent->parentNode;
		}

		return $parent;
	}
}
