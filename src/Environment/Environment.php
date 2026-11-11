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

/** Decides whether the plugin runs on this site, based on the environment type, the allowed addresses and the site address check. */
final class Environment {

	/** Environment type that is always allowed. */
	public const PRODUCTION = 'production';
	/** Environment types that run only when ticked in the settings. */
	public const OPTIONAL = array( 'staging', 'development', 'local' );

	/**
	 * Whether the reason has been worked out for this request.
	 *
	 * @var bool
	 */
	private bool $resolved = false;

	/**
	 * Why the plugin is off, or null when it is on. Worked out once per request.
	 *
	 * @var string|null
	 */
	private ?string $reason = null;

	/**
	 * @param Options $options Saved settings.
	 */
	public function __construct( private Options $options ) {}

	/**
	 * Whether WP-Cron is turned off in wp-config.php.
	 *
	 * @return bool True when DISABLE_WP_CRON is true.
	 */
	public function cron_disabled(): bool {
		return defined( 'DISABLE_WP_CRON' ) && (bool) constant( 'DISABLE_WP_CRON' );
	}

	/**
	 * Environment type of the site.
	 *
	 * @return string One of production, staging, development or local.
	 */
	public function type(): string {
		return wp_get_environment_type();
	}

	/**
	 * Whether the plugin runs on this site.
	 *
	 * @return bool True when it does.
	 */
	public function is_active(): bool {
		return null === $this->reason();
	}

	/**
	 * Why the plugin is off.
	 *
	 * @return string|null A reason code, or null when the plugin is on.
	 */
	public function reason(): ?string {
		if ( $this->resolved ) {
			return $this->reason;
		}

		$this->resolved = true;
		$this->reason   = $this->detect();

		return $this->reason;
	}

	/**
	 * Turns a reason code into a sentence.
	 *
	 * @param string $reason Code from reason().
	 * @return string Sentence for people, or the code when it is unknown.
	 */
	public function describe( string $reason ): string {
		$labels = array(
			'disabled_constant'       => __( 'AGENTREADY_ENABLED is set to false in wp-config.php.', 'agentready' ),
			'environment_not_allowed' => sprintf(
				/* translators: %s: environment type such as staging. */
				__( 'The environment type is "%s", which is not allowed in the AgentReady settings.', 'agentready' ),
				$this->type()
			),
			'url_not_allowed'         => __( 'This site\'s address does not match the allowed URLs.', 'agentready' ),
			'site_address_changed'    => __( 'The site address changed since AgentReady was set up. This looks like a copy or a move, so AgentReady is paused.', 'agentready' ),
		);

		return $labels[ $reason ] ?? $reason;
	}

	/**
	 * Whether the allowed addresses are set in wp-config.php.
	 *
	 * @return bool True when AGENTREADY_ALLOWED_URLS is defined.
	 */
	public function urls_from_constant(): bool {
		return defined( 'AGENTREADY_ALLOWED_URLS' );
	}

	/**
	 * Addresses the plugin may run on. An empty list means any address.
	 *
	 * @return string[] Host patterns.
	 */
	public function allowed_urls(): array {
		$raw = $this->urls_from_constant() ? constant( 'AGENTREADY_ALLOWED_URLS' ) : $this->options->get( 'allowed_urls', '' );

		return self::parse_urls( $raw );
	}

	/**
	 * Optional environment types that were ticked in the settings.
	 *
	 * @return string[] Environment types.
	 */
	public function allowed_types(): array {
		return array_values( array_intersect( self::OPTIONAL, array_map( 'strval', (array) $this->options->get( 'allowed_environments', array() ) ) ) );
	}

	/**
	 * Works out why the plugin is off.
	 *
	 * @return string|null A reason code, or null when the plugin is on.
	 */
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

		// An allowed-URLs constant is an explicit choice, so the automatic copy check steps aside.
		if ( ! $this->urls_from_constant() && (bool) apply_filters( 'agentready_check_site_address', true ) && ! SiteAddress::matches() ) {
			return 'site_address_changed';
		}

		return null;
	}

	/**
	 * Whether the plugin may run on an environment type.
	 *
	 * @param string   $type    Environment type.
	 * @param string[] $allowed Optional types that are ticked in the settings.
	 * @return bool True when production, or when the type is ticked.
	 */
	public static function type_allowed( string $type, array $allowed ): bool {
		return self::PRODUCTION === $type || in_array( $type, $allowed, true );
	}

	/**
	 * Turns a list of addresses or hosts into unique host patterns.
	 *
	 * @param mixed $raw An array, or text split by commas or whitespace.
	 * @return string[] Host patterns.
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
	 * Whether a host matches one of the patterns.
	 *
	 * @param string   $host     Site host.
	 * @param string[] $patterns Host patterns, where * matches anything.
	 * @return bool True when one matches.
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
