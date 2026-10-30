<?php
/**
 * Remembers the site address as a hash, so a copy or a move is noticed even when a host rewrites the database.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Environment;

defined( 'ABSPATH' ) || exit;

final class SiteAddress {

	public const OPTION = 'agentready_site_fingerprint';

	/**
	 * The host and path, without scheme, "www." or trailing slash, so http to https and www switches do not count.
	 */
	public static function normalise( string $url ): string {
		$parts = wp_parse_url( strtolower( trim( $url ) ) );
		$parts = is_array( $parts ) ? $parts : array();
		$host  = (string) preg_replace( '/^www\./', '', (string) ( $parts['host'] ?? '' ) );
		$port  = isset( $parts['port'] ) ? ':' . $parts['port'] : '';
		$path  = trim( (string) ( $parts['path'] ?? '' ), '/' );

		return $host . $port . ( '' === $path ? '' : '/' . $path );
	}

	public static function fingerprint( string $url ): string {
		return hash( 'sha256', self::normalise( $url ) );
	}

	/**
	 * Whether the saved fingerprint belongs to this address. The first call saves it.
	 */
	public static function matches(): bool {
		$saved = get_option( self::OPTION );

		if ( ! is_string( $saved ) || '' === $saved ) {
			self::remember();

			return true;
		}

		return hash_equals( $saved, self::fingerprint( home_url() ) );
	}

	public static function remember(): void {
		update_option( self::OPTION, self::fingerprint( home_url() ), true );
	}
}
