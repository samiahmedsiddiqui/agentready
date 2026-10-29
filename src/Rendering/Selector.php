<?php
/**
 * Compiles a small CSS selector subset to XPath.
 *
 * Supports tag, #id, .class, [attr], [attr=v], [attr^=v], [attr$=v], [attr*=v],
 * compound selectors, descendant and child (>) combinators, and comma lists.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Rendering;

defined( 'ABSPATH' ) || exit;

final class Selector {

	/**
	 * @return string[] One XPath per valid selector in the list, in order.
	 */
	public function to_xpaths( string $selector ): array {
		$xpaths = array();
		$parts  = preg_split( '/,(?![^\[]*\])/', $selector );

		foreach ( false === $parts ? array() : $parts as $part ) {
			$xpath = $this->compile( trim( $part ) );

			if ( null !== $xpath ) {
				$xpaths[] = $xpath;
			}
		}

		return $xpaths;
	}

	private function compile( string $selector ): ?string {
		if ( '' === $selector ) {
			return null;
		}

		$pattern = '/\G\s*(>)?\s*((?:[a-zA-Z*][\w-]*)?(?:#[\w-]+|\.[\w-]+|\[[^\]]*\])*)/';
		$offset  = 0;
		$xpath   = '';
		$length  = strlen( $selector );

		while ( $offset < $length ) {
			if ( ! preg_match( $pattern, $selector, $match, 0, $offset ) || '' === $match[2] ) {
				return null;
			}

			$step = $this->compound( $match[2] );

			if ( null === $step ) {
				return null;
			}

			$xpath .= ( '' === $xpath || '' === $match[1] ? '//' : '/' ) . $step;

			$offset += strlen( $match[0] );

			while ( $offset < $length && ctype_space( $selector[ $offset ] ) ) {
				++$offset;
			}
		}

		return $xpath;
	}

	private function compound( string $compound ): ?string {
		if ( ! preg_match( '/^([a-zA-Z*][\w-]*)?(.*)$/', $compound, $match ) ) {
			return null;
		}

		$tag        = '' === $match[1] ? '*' : strtolower( $match[1] );
		$rest       = $match[2];
		$predicates = '';
		$attribute  = '/\G(?:#([\w-]+)|\.([\w-]+)|\[([\w:-]+)(?:([~^$*|]?=)\s*(?:"([^"]*)"|\'([^\']*)\'|([^\]]*)))?\])/';
		$offset     = 0;
		$length     = strlen( $rest );

		while ( $offset < $length ) {
			if ( ! preg_match( $attribute, $rest, $part, PREG_UNMATCHED_AS_NULL, $offset ) ) {
				return null;
			}

			if ( null !== $part[1] ) {
				$predicates .= '[@id=' . $this->literal( $part[1] ) . ']';
			} elseif ( null !== $part[2] ) {
				$predicates .= "[contains(concat(' ', normalize-space(@class), ' '), " . $this->literal( ' ' . $part[2] . ' ' ) . ')]';
			} else {
				$value       = $part[5] ?? $part[6] ?? $part[7] ?? '';
				$predicates .= $this->attribute_predicate( $part[3], $part[4] ?? '', trim( $value ) );
			}

			$offset += strlen( $part[0] );
		}

		return $tag . $predicates;
	}

	private function attribute_predicate( string $name, string $operator, string $value ): string {
		$attr = '@' . $name;
		$lit  = $this->literal( $value );

		switch ( $operator ) {
			case '=':
				return "[{$attr}={$lit}]";
			case '^=':
				return "[starts-with({$attr}, {$lit})]";
			case '$=':
				return "[substring({$attr}, string-length({$attr}) - string-length({$lit}) + 1) = {$lit}]";
			case '*=':
				return "[contains({$attr}, {$lit})]";
			default:
				return "[{$attr}]";
		}
	}

	private function literal( string $value ): string {
		if ( false === strpos( $value, "'" ) ) {
			return "'" . $value . "'";
		}

		if ( false === strpos( $value, '"' ) ) {
			return '"' . $value . '"';
		}

		$pieces = array_map(
			static function ( string $piece ): string {
				return "'" . $piece . "'";
			},
			explode( "'", $value )
		);

		return 'concat(' . implode( ', "\'", ', $pieces ) . ')';
	}
}
