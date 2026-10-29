<?php
/**
 * Converts a DOM subtree to Markdown. Only text is emitted, never raw HTML.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Rendering;

use DOMElement;
use DOMNode;

defined( 'ABSPATH' ) || exit;

final class Converter {

	private const BREAK = "\x01";

	private const MAX_DEPTH = 100;

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

	public function convert( DOMNode $root ): string {
		return $this->tidy( $this->children( $root, 0, false ) );
	}

	private function tidy( string $markdown ): string {
		$markdown = str_replace( "\r", '', $markdown );
		$markdown = (string) preg_replace( '/[ \t]+\n/', "\n", $markdown );
		$markdown = str_replace( self::BREAK, '  ', $markdown );
		$markdown = (string) preg_replace( "/\n{3,}/", "\n\n", $markdown );
		$markdown = trim( $markdown );

		return '' === $markdown ? '' : $markdown . "\n";
	}

	private function children( DOMNode $node, int $depth, bool $pre ): string {
		$output = '';

		foreach ( $node->childNodes as $child ) {
			$output .= $this->node( $child, $depth + 1, $pre );
		}

		return $output;
	}

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

	private function element( DOMElement $element, int $depth, bool $pre ): string {
		$tag = strtolower( $element->nodeName );

		if ( 1 === preg_match( '/^h([1-6])$/', $tag, $level ) ) {
			$text = $this->inline( $element, $depth, $pre );

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

	private function block( string $content ): string {
		$content = trim( $content );

		return '' === $content ? '' : "\n\n" . $content . "\n\n";
	}

	private function inline( DOMNode $node, int $depth, bool $pre ): string {
		$text = str_replace( self::BREAK, ' ', $this->children( $node, $depth, $pre ) );

		return trim( (string) preg_replace( '/\s*\n\s*/', ' ', $text ) );
	}

	private function wrap( string $marker, string $content ): string {
		if ( ! preg_match( '/^(\s*)(.*?)(\s*)$/s', $content, $match ) || '' === $match[2] ) {
			return $content;
		}

		return $match[1] . $marker . $match[2] . $marker . $match[3];
	}

	private function text( string $value ): string {
		$text = (string) preg_replace( '/\s+/u', ' ', $value );

		if ( '' === trim( $text ) ) {
			return $text;
		}

		$text = $this->escape( $text );
		$text = (string) preg_replace( '/^(\s*)(#{1,6}(?=\s)|[>+-](?=\s))/', '$1\\\\$2', $text );

		return (string) preg_replace( '/^(\s*\d+)([.)])(?=\s)/', '$1\\\\$2', $text );
	}

	private function escape( string $text ): string {
		$text = str_replace(
			array( '\\', '`', '*', '[', ']', '<' ),
			array( '\\\\', '\\`', '\\*', '\\[', '\\]', '\\<' ),
			$text
		);

		return (string) preg_replace( '/(?<![\p{L}\p{N}])_|_(?![\p{L}\p{N}])/u', '\\_', $text );
	}

	private function fence( string $content, string $character ): string {
		$longest = 0;

		if ( preg_match_all( '/' . preg_quote( $character, '/' ) . '+/', $content, $runs ) ) {
			foreach ( $runs[0] as $run ) {
				$longest = max( $longest, strlen( $run ) );
			}
		}

		return str_repeat( $character, $longest + 1 );
	}

	private function inline_code( string $content ): string {
		$content = trim( (string) preg_replace( '/\s+/', ' ', $content ) );

		if ( '' === $content ) {
			return '';
		}

		$fence = $this->fence( $content, '`' );
		$pad   = ( '`' === $content[0] || '`' === substr( $content, -1 ) ) ? ' ' : '';

		return $fence . $pad . $content . $pad . $fence;
	}

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

	private function link( DOMElement $element, int $depth, bool $pre ): string {
		$text = $this->inline( $element, $depth, $pre );
		$href = trim( $element->getAttribute( 'href' ) );

		if ( '' === $text || '' === $href ) {
			return $text;
		}

		return '[' . $text . '](' . $this->destination( $href ) . ')';
	}

	private function destination( string $url ): string {
		return str_replace( array( ' ', '(', ')' ), array( '%20', '%28', '%29' ), $url );
	}

	private function image( DOMElement $element ): string {
		$src = trim( $element->getAttribute( 'src' ) );

		if ( '' === $src || '1' === $element->getAttribute( 'width' ) || '1' === $element->getAttribute( 'height' ) ) {
			return '';
		}

		$alt = trim( (string) preg_replace( '/\s+/', ' ', $element->getAttribute( 'alt' ) ) );
		$alt = str_replace( array( '[', ']' ), array( '\\[', '\\]' ), $alt );

		return '![' . $alt . '](' . $this->destination( $src ) . ')';
	}

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

	private function list_block( DOMElement $list, bool $ordered, int $depth ): string {
		$number = max( 1, (int) $list->getAttribute( 'start' ) );
		$items  = array();

		foreach ( $list->childNodes as $child ) {
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

				for ( $i = 1; $i < max( 1, (int) $cell->getAttribute( 'colspan' ) ); $i++ ) {
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

	private function closest_table( DOMNode $node ): ?DOMNode {
		$parent = $node->parentNode;

		while ( null !== $parent && 'table' !== strtolower( $parent->nodeName ) ) {
			$parent = $parent->parentNode;
		}

		return $parent;
	}
}
