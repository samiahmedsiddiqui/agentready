<?php
/**
 * Reads plugin settings with defaults applied.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Settings;

defined( 'ABSPATH' ) || exit;

/** Main settings of the plugin, saved as one option. */
final class Options {

	/** Option name. */
	public const KEY = 'agentready_settings';

	/** Selectors tried, in order, to find the main content of a page. An empty setting means these are used. */
	public const DEFAULT_SELECTOR = 'main, [role="main"], #content, #main, .site-content, article';

	/**
	 * Default values of the settings.
	 *
	 * @return array<string, mixed> Settings by name.
	 */
	public static function defaults(): array {
		return array(
			'excluded_post_types'  => array(),
			'excluded_posts'       => '',
			'excluded_terms'       => array(),
			'exclude_noindex'      => true,
			'content_selector'     => '',
			'strip_selectors'      => '',
			'show_meta_box'        => true,
			'http_auth_enabled'    => false,
			'http_auth_user'       => '',
			'http_auth_password'   => '',
			'home_markdown_url'    => 'index.md',
			'allowed_environments' => array(),
			'allowed_urls'         => '',
			'delete_data'          => false,
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
	 * Reads one setting.
	 *
	 * @param string $key      Setting name.
	 * @param mixed  $fallback Value to return when the setting does not exist.
	 * @return mixed The saved value, or the fallback.
	 */
	public function get( string $key, mixed $fallback = null ): mixed {
		$all = $this->all();

		return $all[ $key ] ?? $fallback;
	}

	/**
	 * Selector that was set for the main content.
	 *
	 * @return string The saved selector, or an empty string to detect it automatically.
	 */
	public function content_selector(): string {
		$selector = trim( (string) $this->get( 'content_selector', '' ) );

		return self::DEFAULT_SELECTOR === $selector ? '' : $selector;
	}

	/**
	 * Whether the HTTP Basic credentials are set in wp-config.php.
	 *
	 * @return bool True when both constants are defined.
	 */
	public function http_auth_from_constants(): bool {
		return defined( 'AGENTREADY_HTTP_AUTH_USER' ) && defined( 'AGENTREADY_HTTP_AUTH_PASSWORD' );
	}

	/**
	 * Credentials for a site that is protected with HTTP Basic authentication.
	 *
	 * @return array{user: string, password: string}|null The credentials, or null when the option is off or no user is set.
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
