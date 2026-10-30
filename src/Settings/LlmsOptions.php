<?php
/**
 * Settings of the llms.txt page: the file mode, the custom text, llms-full.txt and the robots.txt line.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Settings;

defined( 'ABSPATH' ) || exit;

final class LlmsOptions {

	public const KEY = 'agentready_llms';

	public const AUTOMATIC = 'automatic';
	public const CUSTOM    = 'custom';
	public const HYBRID    = 'hybrid';

	public const MAX_BYTES   = 200000;
	public const LARGE_BYTES = 100000;

	/**
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'mode'        => self::AUTOMATIC,
			'custom'      => '',
			'full'        => false,
			'robots_line' => false,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$saved = get_option( self::KEY, array() );

		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	public function mode(): string {
		return self::clean_mode( $this->all()['mode'] );
	}

	public function custom(): string {
		return (string) $this->all()['custom'];
	}

	public function full_enabled(): bool {
		return ! empty( $this->all()['full'] );
	}

	public function robots_line(): bool {
		return ! empty( $this->all()['robots_line'] );
	}

	/**
	 * @param mixed $input Raw submitted value.
	 * @return array<string, mixed>
	 */
	public function sanitize( mixed $input ): array {
		$input = is_array( $input ) ? $input : array();
		$text  = isset( $input['custom'] ) && is_string( $input['custom'] ) ? $input['custom'] : '';

		return array(
			'mode'        => self::clean_mode( $input['mode'] ?? '' ),
			'custom'      => self::normalise( wp_check_invalid_utf8( substr( $text, 0, self::MAX_BYTES ), true ) ),
			'full'        => ! empty( $input['full'] ),
			'robots_line' => ! empty( $input['robots_line'] ),
		);
	}

	public static function clean_mode( mixed $mode ): string {
		return in_array( $mode, array( self::CUSTOM, self::HYBRID ), true ) ? (string) $mode : self::AUTOMATIC;
	}

	/**
	 * Unifies line endings, drops control characters and trims the ends.
	 */
	public static function normalise( string $text ): string {
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
		$text = (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text );

		return trim( $text );
	}

	/**
	 * Notes about a custom text. They never block saving.
	 *
	 * @param string $host The site host, used to spot links to other sites.
	 * @return string[] Codes: no_title, external, large.
	 */
	public static function check( string $text, string $host ): array {
		$notes = array();

		if ( '' === $text ) {
			return $notes;
		}

		if ( 1 !== preg_match( '/\A#[ \t]+\S/', $text ) ) {
			$notes[] = 'no_title';
		}

		if ( self::has_external_link( $text, $host ) ) {
			$notes[] = 'external';
		}

		if ( strlen( $text ) > self::LARGE_BYTES ) {
			$notes[] = 'large';
		}

		return $notes;
	}

	private static function has_external_link( string $text, string $host ): bool {
		if ( '' === $host || false === preg_match_all( '#\]\(\s*<?(https?://[^)\s>]+)#i', $text, $found ) ) {
			return false;
		}

		$own = (string) preg_replace( '/^www\./', '', strtolower( $host ) );

		foreach ( $found[1] as $url ) {
			$link = (string) preg_replace( '/^www\./', '', strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) );

			if ( '' !== $link && $link !== $own ) {
				return true;
			}
		}

		return false;
	}
}
