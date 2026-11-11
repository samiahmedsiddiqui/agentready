<?php
/**
 * Remembers the site address as a hash, so a copy or a move is noticed even when a host rewrites the database.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Environment;

defined( 'ABSPATH' ) || exit;

/** Remembers the address the plugin was set up on, so a copy or a move of the site is noticed. */
final class SiteAddress {

	/** Option that holds the hash of the saved address. */
	public const OPTION = 'agentwelcome_site_fingerprint';

	/**
	 * Reduces an address to its host, port and path, so a change of scheme, "www." or a trailing slash does not count as a move.
	 *
	 * @param string $url Site address.
	 * @return string Host, port and path.
	 */
	public static function normalise( string $url ): string {
		$parts = wp_parse_url( strtolower( trim( $url ) ) );
		$parts = is_array( $parts ) ? $parts : array();
		$host  = (string) preg_replace( '/^www\./', '', (string) ( $parts['host'] ?? '' ) );
		$port  = isset( $parts['port'] ) ? ':' . $parts['port'] : '';
		$path  = trim( (string) ( $parts['path'] ?? '' ), '/' );

		return $host . $port . ( '' === $path ? '' : '/' . $path );
	}

	/**
	 * Hash of an address, as saved.
	 *
	 * @param string $url Site address.
	 * @return string Hash.
	 */
	public static function fingerprint( string $url ): string {
		return hash( 'sha256', self::normalise( $url ) );
	}

	/**
	 * Whether the saved hash belongs to this address. The first call saves it.
	 *
	 * @return bool True when it matches or none was saved yet.
	 */
	public static function matches(): bool {
		$saved = get_option( self::OPTION );

		if ( ! is_string( $saved ) || '' === $saved ) {
			self::remember();

			return true;
		}

		return hash_equals( $saved, self::fingerprint( home_url() ) );
	}

	/**
	 * Saves the hash of the current address.
	 *
	 * @return void
	 */
	public static function remember(): void {
		update_option( self::OPTION, self::fingerprint( home_url() ), true );
	}
}
