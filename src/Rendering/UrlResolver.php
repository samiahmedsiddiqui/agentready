<?php
/**
 * Resolves relative URLs against a base URL and drops unsafe schemes.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Rendering;

defined( 'ABSPATH' ) || exit;

/** Turns the addresses found in a page into absolute ones. */
final class UrlResolver {

	/**
	 * Makes an address absolute. Only http, https, mailto and tel addresses are kept.
	 *
	 * @param string $base Address of the page the link was found on.
	 * @param string $url  Address found in the page.
	 * @return string The absolute address, or an empty string when it is empty or uses another scheme.
	 */
	public static function resolve( string $base, string $url ): string {
		if ( '' === $url ) {
			return '';
		}

		if ( preg_match( '#^([a-z][a-z0-9+.-]*):#i', $url, $scheme ) ) {
			return in_array( strtolower( $scheme[1] ), array( 'http', 'https', 'mailto', 'tel' ), true ) ? $url : '';
		}

		$parts = wp_parse_url( $base );

		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return $url;
		}

		$origin = $parts['scheme'] . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );

		if ( 0 === strpos( $url, '//' ) ) {
			return $parts['scheme'] . ':' . $url;
		}

		$path = $parts['path'] ?? '/';

		if ( '#' === $url[0] ) {
			return $origin . $path . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' ) . $url;
		}

		if ( '?' === $url[0] ) {
			return $origin . $path . $url;
		}

		if ( '/' !== $url[0] ) {
			$url = substr( $path, 0, (int) strrpos( $path, '/' ) + 1 ) . $url;
		}

		return $origin . self::normalize_path( $url );
	}

	/**
	 * Removes "." and ".." parts from the path of an address.
	 *
	 * @param string $url Path, with an optional query or fragment.
	 * @return string The path without those parts.
	 */
	private static function normalize_path( string $url ): string {
		$suffix = '';

		if ( preg_match( '/^([^?#]*)([?#].*)$/', $url, $match ) ) {
			$url    = $match[1];
			$suffix = $match[2];
		}

		$segments = array();

		foreach ( explode( '/', $url ) as $segment ) {
			if ( '..' === $segment ) {
				array_pop( $segments );
			} elseif ( '.' !== $segment ) {
				$segments[] = $segment;
			}
		}

		$path = implode( '/', $segments );

		return ( '/' === substr( $path, 0, 1 ) ? '' : '/' ) . $path . $suffix;
	}
}
