<?php
/**
 * Reads plugin settings with defaults applied.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Settings;

defined( 'ABSPATH' ) || exit;

final class Options {

	public const KEY = 'agentready_settings';

	public const DEFAULT_SELECTOR = 'main, [role="main"], #content, #main, .site-content, article';

	/**
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'excluded_post_types' => array(),
			'excluded_posts'      => '',
			'excluded_terms'      => array(),
			'content_selector'    => self::DEFAULT_SELECTOR,
			'strip_selectors'     => '',
			'http_auth_enabled'   => false,
			'http_auth_user'      => '',
			'http_auth_password'  => '',
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$saved = get_option( self::KEY, array() );

		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	public function get( string $key, mixed $fallback = null ): mixed {
		$all = $this->all();

		return $all[ $key ] ?? $fallback;
	}

	public function http_auth_from_constants(): bool {
		return defined( 'AGENTREADY_HTTP_AUTH_USER' ) && defined( 'AGENTREADY_HTTP_AUTH_PASSWORD' );
	}

	/**
	 * @return array{user: string, password: string}|null
	 */
	public function http_auth(): ?array {
		if ( ! $this->get( 'http_auth_enabled' ) ) {
			return null;
		}

		if ( $this->http_auth_from_constants() ) {
			$user     = (string) constant( 'AGENTREADY_HTTP_AUTH_USER' );
			$password = (string) constant( 'AGENTREADY_HTTP_AUTH_PASSWORD' );
		} else {
			$user     = (string) $this->get( 'http_auth_user', '' );
			$password = Secret::decrypt( (string) $this->get( 'http_auth_password', '' ) );
		}

		if ( '' === $user ) {
			return null;
		}

		return array(
			'user'     => $user,
			'password' => $password,
		);
	}
}
