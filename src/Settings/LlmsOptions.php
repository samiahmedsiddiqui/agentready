<?php
/**
 * Settings of the llms.txt page: the file mode, the custom text, llms-full.txt and the robots.txt line.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Settings;

defined( 'ABSPATH' ) || exit;

/** Settings of the llms.txt page: how the file is built, the custom text, llms-full.txt and the robots.txt line. They are kept apart from the main settings. */
final class LlmsOptions {

	/** Option name. */
	public const KEY = 'agentready_llms';

	/** Mode: the file is generated from the pages. */
	public const AUTOMATIC = 'automatic';
	/** Mode: the file is the custom text as written. */
	public const CUSTOM = 'custom';
	/** Mode: the custom text, followed by the generated list of pages. */
	public const HYBRID = 'hybrid';

	/** Control characters that are removed from the custom text. */
	private const CONTROL_CHARS = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/';
	/** A first line that is a Markdown title, such as "# My site". */
	private const TITLE_LINE = '/\A#[ \t]+\S/';
	/** The address of a Markdown link. */
	private const LINK_URL = '#\]\(\s*<?(https?://[^)\s>]+)#i';

	/** Largest custom text that is saved, in bytes. */
	public const MAX_BYTES = 200000;
	/** Size above which the page warns that the text is large, in bytes. */
	public const LARGE_BYTES = 100000;

	/**
	 * Default values of the settings.
	 *
	 * @return array<string, mixed> Settings by name.
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
	 * Saved settings, filled in with the defaults.
	 *
	 * @return array<string, mixed> Settings by name.
	 */
	public function all(): array {
		$saved = get_option( self::KEY, array() );

		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	/**
	 * Current mode.
	 *
	 * @return string One of AUTOMATIC, CUSTOM or HYBRID.
	 */
	public function mode(): string {
		return self::clean_mode( $this->all()['mode'] );
	}

	/**
	 * Saved custom text.
	 *
	 * @return string The text, or an empty string.
	 */
	public function custom(): string {
		return (string) $this->all()['custom'];
	}

	/**
	 * Whether /llms-full.txt is served.
	 *
	 * @return bool True when it is turned on.
	 */
	public function full_enabled(): bool {
		return ! empty( $this->all()['full'] );
	}

	/**
	 * Whether the robots.txt comment lines are added.
	 *
	 * @return bool True when they are turned on.
	 */
	public function robots_line(): bool {
		return ! empty( $this->all()['robots_line'] );
	}

	/**
	 * Cleans submitted settings before they are saved.
	 *
	 * @param mixed $input Raw submitted value.
	 * @return array<string, mixed> Settings by name.
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

	/**
	 * Turns a submitted mode into a valid one.
	 *
	 * @param mixed $mode Submitted value.
	 * @return string One of AUTOMATIC, CUSTOM or HYBRID. Anything else gives AUTOMATIC.
	 */
	public static function clean_mode( mixed $mode ): string {
		return in_array( $mode, array( self::CUSTOM, self::HYBRID ), true ) ? (string) $mode : self::AUTOMATIC;
	}

	/**
	 * Unifies line endings, removes control characters and trims the ends.
	 *
	 * @param string $text Custom text.
	 * @return string Cleaned text.
	 */
	public static function normalise( string $text ): string {
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
		$text = (string) preg_replace( self::CONTROL_CHARS, '', $text );

		return trim( $text );
	}

	/**
	 * Looks for things in a custom text that are worth a note. They never block saving.
	 *
	 * @param string $text Custom text.
	 * @param string $host Host of this site, used to spot links to other sites.
	 * @return string[] Codes: no_title, external and large.
	 */
	public static function check( string $text, string $host ): array {
		$notes = array();

		if ( '' === $text ) {
			return $notes;
		}

		if ( 1 !== preg_match( self::TITLE_LINE, $text ) ) {
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

	/**
	 * Whether the text links to another site.
	 *
	 * @param string $text Custom text.
	 * @param string $host Host of this site.
	 * @return bool True when at least one link points elsewhere.
	 */
	private static function has_external_link( string $text, string $host ): bool {
		if ( '' === $host || false === preg_match_all( self::LINK_URL, $text, $found ) ) {
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
