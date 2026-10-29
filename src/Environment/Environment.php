<?php
/**
 * Decides whether AgentReady is active on this site and environment.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Environment;

use AgentReady\Settings\Options;

defined( 'ABSPATH' ) || exit;

final class Environment {

	public const PRODUCTION = 'production';
	public const OPTIONAL   = array( 'staging', 'development', 'local' );

	private bool $resolved = false;

	private ?string $reason = null;

	public function __construct( private Options $options ) {}

	public function type(): string {
		return wp_get_environment_type();
	}

	public function is_active(): bool {
		return null === $this->reason();
	}

	/**
	 * @return string|null Why the plugin is off, or null when it is active.
	 */
	public function reason(): ?string {
		if ( $this->resolved ) {
			return $this->reason;
		}

		$this->resolved = true;
		$this->reason   = $this->detect();

		return $this->reason;
	}

	public function describe( string $reason ): string {
		$labels = array(
			'disabled_constant'       => __( 'AGENTREADY_ENABLED is set to false in wp-config.php.', 'agentready' ),
			'environment_not_allowed' => sprintf(
				/* translators: %s: environment type such as staging. */
				__( 'The environment type is "%s", which is not allowed in the AgentReady settings.', 'agentready' ),
				$this->type()
			),
			'url_not_allowed'         => __( 'This site\'s address does not match the allowed URLs.', 'agentready' ),
		);

		return $labels[ $reason ] ?? $reason;
	}

	public function urls_from_constant(): bool {
		return defined( 'AGENTREADY_ALLOWED_URLS' );
	}

	/**
	 * @return string[]
	 */
	public function allowed_urls(): array {
		$raw = $this->urls_from_constant() ? constant( 'AGENTREADY_ALLOWED_URLS' ) : $this->options->get( 'allowed_urls', '' );

		return self::parse_urls( $raw );
	}

	/**
	 * @return string[]
	 */
	public function allowed_types(): array {
		return array_values( array_intersect( self::OPTIONAL, array_map( 'strval', (array) $this->options->get( 'allowed_environments', array() ) ) ) );
	}

	private function detect(): ?string {
		if ( defined( 'AGENTREADY_ENABLED' ) && false === filter_var( constant( 'AGENTREADY_ENABLED' ), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) ) {
			return 'disabled_constant';
		}

		if ( ! self::type_allowed( $this->type(), $this->allowed_types() ) ) {
			return 'environment_not_allowed';
		}

		$urls = $this->allowed_urls();
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		if ( array() !== $urls && ! self::host_matches( $host, $urls ) ) {
			return 'url_not_allowed';
		}

		return null;
	}

	/**
	 * @param string   $type    Environment type.
	 * @param string[] $allowed Optional types that are ticked in the settings.
	 */
	public static function type_allowed( string $type, array $allowed ): bool {
		return self::PRODUCTION === $type || in_array( $type, $allowed, true );
	}

	/**
	 * Turns a list of URLs or hosts (array, or text split by commas or whitespace) into unique host patterns.
	 *
	 * @param mixed $raw Raw value.
	 * @return string[]
	 */
	public static function parse_urls( mixed $raw ): array {
		$items = is_array( $raw ) ? $raw : preg_split( '/[\s,]+/', (string) $raw );
		$hosts = array();

		foreach ( (array) $items as $item ) {
			$host = strtolower( trim( (string) $item ) );
			$host = (string) preg_replace( '#^[a-z][a-z0-9+.-]*://#', '', $host );
			$host = (string) preg_replace( '#[/?\#].*$#', '', $host );
			$host = (string) preg_replace( '#^.*@#', '', $host );
			$host = (string) preg_replace( '#:\d+$#', '', $host );

			if ( '' !== $host && 1 === preg_match( '/^[a-z0-9*.-]+$/', $host ) ) {
				$hosts[ $host ] = $host;
			}
		}

		return array_values( $hosts );
	}

	/**
	 * @param string   $host     Site host.
	 * @param string[] $patterns Host patterns where * matches anything.
	 */
	public static function host_matches( string $host, array $patterns ): bool {
		$host = strtolower( $host );

		foreach ( $patterns as $pattern ) {
			if ( fnmatch( $pattern, $host ) ) {
				return true;
			}
		}

		return false;
	}
}
