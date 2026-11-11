<?php
/**
 * Extracts the main content area from a page and strips non-content markup.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Rendering;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMNodeList;
use DOMXPath;

defined( 'ABSPATH' ) || exit;

/** Finds the main content in the HTML of a page and removes what should not be in the Markdown. */
final class Extractor {

	/** Selectors of elements that are removed from the content: scripts, navigation, forms, comments and hidden or screen-reader-only parts. */
	public const DEFAULT_STRIP = array(
		'script',
		'style',
		'noscript',
		'template',
		'object',
		'embed',
		'canvas',
		'svg',
		'nav',
		'aside',
		'form',
		'input',
		'select',
		'textarea',
		'[hidden]',
		'[aria-hidden="true"]',
		'.screen-reader-text',
		'.skip-link',
		'#comments',
		'.comments-area',
		'.wp-block-navigation',
		'.wp-block-search',
		'.sharedaddy',
	);

	/** XPath queries for page headers and footers, which cannot be written as the simple selectors. Headers and footers inside the content are kept. */
	public const STRIP_XPATHS = array(
		'.//*[@role="banner"]',
		'.//*[@role="contentinfo"]',
		'.//header[not(ancestor::article or ancestor::main or ancestor::section)]',
		'.//footer[not(ancestor::article or ancestor::main or ancestor::section)]',
	);

	/** Elements whose text is not page text. */
	private const SKIP_TAGS = array( 'script', 'style', 'noscript', 'template', 'svg', 'canvas', 'iframe', 'select', 'option', 'button', 'input', 'textarea', 'head' );

	/** Elements that stay inside a line of text. */
	private const INLINE_TAGS = array( 'a', 'abbr', 'b', 'bdi', 'bdo', 'cite', 'code', 'data', 'del', 'dfn', 'em', 'font', 'i', 'img', 'ins', 'kbd', 'label', 'mark', 'q', 's', 'samp', 'small', 'span', 'strong', 'sub', 'sup', 'time', 'u', 'var', 'wbr', 'br' );

	/** Attributes where lazy-loading scripts keep the real image address. */
	private const LAZY_ATTRIBUTES = array( 'data-src', 'data-lazy-src', 'data-original' );

	/**
	 * @param Selector $selector Converts selectors to XPath.
	 * @param string[] $strip    Selectors of elements to remove.
	 */
	public function __construct(
		private Selector $selector,
		private array $strip = self::DEFAULT_STRIP
	) {}

	/**
	 * Makes a copy that removes more elements.
	 *
	 * @param string[] $selectors Extra selectors of elements to remove.
	 * @return self The new extractor.
	 */
	public function with_extra( array $selectors ): self {
		return new self( $this->selector, array_merge( $this->strip, $selectors ) );
	}

	/**
	 * Finds the content area, removes what is left out and makes the addresses absolute.
	 *
	 * @param string $html     HTML of the page.
	 * @param string $selector Selectors of the content area, separated by commas.
	 * @param string $base_url Address of the page, for relative links.
	 * @return DOMElement|null The cleaned content area, or null when the HTML cannot be read or has no content.
	 */
	public function extract( string $html, string $selector, string $base_url ): ?DOMElement {
		$dom = $this->load( $html );

		if ( null === $dom ) {
			return null;
		}

		$xpath = new DOMXPath( $dom );
		$node  = $this->find( $xpath, $selector );

		if ( null === $node ) {
			return null;
		}

		$this->strip( $xpath, $node );
		$this->prepare_media( $xpath, $node );
		$this->absolutize( $xpath, $node, $base_url );

		return $node;
	}

	/**
	 * Text blocks of the content area, split into those kept and those removed by the exclusions.
	 *
	 * @param string $html     HTML of the page.
	 * @param string $selector Selectors of the content area, separated by commas.
	 * @return array{kept: string[], excluded: string[]} The blocks of text.
	 */
	public function text_blocks( string $html, string $selector ): array {
		$empty = array(
			'kept'     => array(),
			'excluded' => array(),
		);
		$dom   = $this->load( $html );

		if ( null === $dom ) {
			return $empty;
		}

		$xpath = new DOMXPath( $dom );
		$node  = $this->find( $xpath, $selector );

		if ( null === $node ) {
			return $empty;
		}

		$all_blocks = array();
		$this->collect( $node, $all_blocks );
		$this->strip( $xpath, $node );

		$kept = array();
		$this->collect( $node, $kept );

		$unmatched = array_count_values( $kept );
		$removed   = array();

		foreach ( $all_blocks as $block ) {
			if ( ! empty( $unmatched[ $block ] ) ) {
				--$unmatched[ $block ];
			} else {
				$removed[] = $block;
			}
		}

		return array(
			'kept'     => $kept,
			'excluded' => $removed,
		);
	}

	/**
	 * Collects the text blocks under a node, skipping hidden elements and elements that hold no page text.
	 *
	 * @param DOMNode  $node   Node to read.
	 * @param string[] $blocks Collected text blocks, added to by reference.
	 * @return void
	 */
	private function collect( DOMNode $node, array &$blocks ): void {
		$text = '';

		foreach ( $node->childNodes as $child ) {
			if ( XML_TEXT_NODE === $child->nodeType || XML_CDATA_SECTION_NODE === $child->nodeType ) {
				$text .= $child->nodeValue;
				continue;
			}

			if ( ! $child instanceof DOMElement ) {
				continue;
			}

			$tag = strtolower( $child->nodeName );

			if ( in_array( $tag, self::SKIP_TAGS, true ) || $this->is_hidden( $child ) ) {
				continue;
			}

			if ( 'br' === $tag ) {
				$text .= ' ';
				continue;
			}

			if ( in_array( $tag, self::INLINE_TAGS, true ) && $this->is_inline_only( $child ) ) {
				$text .= ' ' . $child->textContent . ' ';
				continue;
			}

			$this->add_block( $text, $blocks );
			$text = '';
			$this->collect( $child, $blocks );
		}

		$this->add_block( $text, $blocks );
	}

	/**
	 * Adds a block of text when it holds at least two letters or digits in a row.
	 *
	 * @param string   $text   Text of the block.
	 * @param string[] $blocks Collected text blocks, added to by reference.
	 * @return void
	 */
	private function add_block( string $text, array &$blocks ): void {
		$text = trim( (string) preg_replace( '/\s+/u', ' ', str_replace( "\xc2\xa0", ' ', $text ) ) );

		if ( 1 === preg_match( '/[\p{L}\p{N}]{2}/u', $text ) ) {
			$blocks[] = $text;
		}
	}

	/**
	 * Whether an element holds only elements that stay inside a line of text.
	 *
	 * @param DOMElement $element Element to check.
	 * @return bool True when it does.
	 */
	private function is_inline_only( DOMElement $element ): bool {
		foreach ( $element->getElementsByTagName( '*' ) as $descendant ) {
			if ( ! in_array( strtolower( $descendant->nodeName ), self::INLINE_TAGS, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether an element is hidden by an attribute or an inline style.
	 *
	 * @param DOMElement $element Element to check.
	 * @return bool True when it is hidden.
	 */
	private function is_hidden( DOMElement $element ): bool {
		return $element->hasAttribute( 'hidden' )
			|| 'true' === strtolower( $element->getAttribute( 'aria-hidden' ) )
			|| 1 === preg_match( '/display\s*:\s*none|visibility\s*:\s*hidden/i', $element->getAttribute( 'style' ) );
	}

	/**
	 * Parses HTML.
	 *
	 * @param string $html HTML of the page.
	 * @return DOMDocument|null The document, or null when the HTML is empty or cannot be parsed.
	 */
	private function load( string $html ): ?DOMDocument {
		if ( '' === trim( $html ) || ! class_exists( DOMDocument::class ) ) {
			return null;
		}

		$dom      = new DOMDocument();
		$previous = libxml_use_internal_errors( true );

		$loaded = $dom->loadHTML(
			'<?xml encoding="utf-8" ?>' . $html,
			LIBXML_NONET | LIBXML_COMPACT | LIBXML_NOWARNING | LIBXML_NOERROR
		);

		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded ) {
			return null;
		}

		foreach ( iterator_to_array( $dom->childNodes ) as $child ) {
			if ( XML_PI_NODE === $child->nodeType ) {
				$dom->removeChild( $child );
			}
		}

		return $dom;
	}

	/**
	 * Finds the first element that matches the selectors and has content. The page body is the last choice.
	 *
	 * @param DOMXPath $xpath    Query object for the document.
	 * @param string   $selector Selectors separated by commas.
	 * @return DOMElement|null The element, or null when none has content.
	 */
	private function find( DOMXPath $xpath, string $selector ): ?DOMElement {
		$queries   = $this->selector->to_xpaths( $selector );
		$queries[] = '//body';

		foreach ( $queries as $query ) {
			$list = $xpath->query( $query );

			if ( false === $list ) {
				continue;
			}

			foreach ( $list as $candidate ) {
				if ( $candidate instanceof DOMElement && $this->has_content( $candidate ) ) {
					return $candidate;
				}
			}
		}

		return null;
	}

	/**
	 * Whether an element has text, an image, a frame or media.
	 *
	 * @param DOMElement $element Element to check.
	 * @return bool True when it does.
	 */
	private function has_content( DOMElement $element ): bool {
		if ( '' !== trim( $element->textContent ) ) {
			return true;
		}

		foreach ( array( 'img', 'iframe', 'video', 'audio' ) as $tag ) {
			if ( $element->getElementsByTagName( $tag )->length > 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Removes comments, page headers and footers, and the elements that match the strip selectors.
	 *
	 * @param DOMXPath   $xpath Query object for the document.
	 * @param DOMElement $root  Content area.
	 * @return void
	 */
	private function strip( DOMXPath $xpath, DOMElement $root ): void {
		$relative = array_merge( array( './/comment()' ), self::STRIP_XPATHS );
		$document = array();

		foreach ( $this->strip as $selector ) {
			$document = array_merge( $document, $this->selector->to_xpaths( $selector ) );
		}

		foreach ( $relative as $query ) {
			$this->remove( $xpath->query( $query, $root ), $root );
		}

		foreach ( $document as $query ) {
			$this->remove( $xpath->query( $query ), $root );
		}
	}

	/**
	 * Removes nodes that sit inside the content area.
	 *
	 * @param DOMNodeList<DOMNode>|false $nodes Nodes to remove when they sit inside the root.
	 * @param DOMElement                 $root  Content area.
	 * @return void
	 */
	private function remove( $nodes, DOMElement $root ): void {
		if ( false === $nodes ) {
			return;
		}

		foreach ( iterator_to_array( $nodes ) as $node ) {
			if ( $node !== $root && $node->parentNode instanceof DOMNode && $this->is_inside( $node, $root ) ) {
				$node->parentNode->removeChild( $node );
			}
		}
	}

	/**
	 * Whether a node is inside the content area.
	 *
	 * @param DOMNode    $node Node to check.
	 * @param DOMElement $root Content area.
	 * @return bool True when it is a descendant of the root.
	 */
	private function is_inside( DOMNode $node, DOMElement $root ): bool {
		$parent = $node->parentNode;

		while ( null !== $parent ) {
			if ( $parent === $root ) {
				return true;
			}

			$parent = $parent->parentNode;
		}

		return false;
	}

	/**
	 * Copies the real image address from a lazy-loading attribute when the image has no usable address.
	 *
	 * @param DOMXPath   $xpath Query object for the document.
	 * @param DOMElement $root  Content area.
	 * @return void
	 */
	private function prepare_media( DOMXPath $xpath, DOMElement $root ): void {
		$images = $xpath->query( './/img', $root );

		foreach ( false === $images ? array() : $images as $image ) {
			if ( ! $image instanceof DOMElement ) {
				continue;
			}

			$src = trim( $image->getAttribute( 'src' ) );

			if ( '' !== $src && 0 !== strpos( $src, 'data:' ) ) {
				continue;
			}

			foreach ( self::LAZY_ATTRIBUTES as $attribute ) {
				$lazy = trim( $image->getAttribute( $attribute ) );

				if ( '' !== $lazy ) {
					$image->setAttribute( 'src', $lazy );
					break;
				}
			}
		}
	}

	/**
	 * Makes the links and image addresses absolute. Addresses with an unsupported scheme are removed.
	 *
	 * @param DOMXPath   $xpath    Query object for the document.
	 * @param DOMElement $root     Content area.
	 * @param string     $base_url Address of the page.
	 * @return void
	 */
	private function absolutize( DOMXPath $xpath, DOMElement $root, string $base_url ): void {
		$nodes = $xpath->query( './/*[@href or @src]', $root );

		foreach ( false === $nodes ? array() : $nodes as $node ) {
			if ( ! $node instanceof DOMElement ) {
				continue;
			}

			foreach ( array( 'href', 'src' ) as $attribute ) {
				if ( ! $node->hasAttribute( $attribute ) ) {
					continue;
				}

				$resolved = UrlResolver::resolve( $base_url, trim( $node->getAttribute( $attribute ) ) );

				if ( '' === $resolved ) {
					$node->removeAttribute( $attribute );
				} else {
					$node->setAttribute( $attribute, $resolved );
				}
			}
		}
	}
}
