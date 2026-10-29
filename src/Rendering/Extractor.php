<?php
/**
 * Extracts the main content area from a page and strips non-content markup.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Rendering;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

defined( 'ABSPATH' ) || exit;

final class Extractor {

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

	private const LAZY_ATTRIBUTES = array( 'data-src', 'data-lazy-src', 'data-original' );

	/**
	 * @param string[] $strip CSS selectors for elements to remove.
	 */
	public function __construct(
		private Selector $selector,
		private array $strip = self::DEFAULT_STRIP
	) {}

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

	private function strip( DOMXPath $xpath, DOMElement $root ): void {
		$queries = array( './/comment()' );

		foreach ( $this->strip as $selector ) {
			foreach ( $this->selector->to_xpaths( $selector ) as $query ) {
				$queries[] = '.' . $query;
			}
		}

		foreach ( $queries as $query ) {
			$list = $xpath->query( $query, $root );

			if ( false === $list ) {
				continue;
			}

			foreach ( iterator_to_array( $list ) as $node ) {
				if ( $node->parentNode instanceof DOMNode ) {
					$node->parentNode->removeChild( $node );
				}
			}
		}
	}

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
