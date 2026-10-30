<?php
/**
 * Sends the saved HTTP Basic credentials with WordPress's own background request, so scheduled work runs on a protected site.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Http;

use AgentReady\Contracts\Hookable;
use AgentReady\Settings\Options;

defined( 'ABSPATH' ) || exit;

final class CronAuth implements Hookable {

	public function __construct( private Options $options ) {}

	public function register(): void {
		add_filter( 'cron_request', array( $this, 'add_credentials' ) );
	}

	/**
	 * @param mixed $request The request WordPress is about to send to wp-cron.php.
	 * @return mixed
	 */
	public function add_credentials( mixed $request ): mixed {
		$auth = $this->options->http_auth();

		if ( null === $auth || ! is_array( $request ) || ! isset( $request['url'] ) || ! is_array( $request['args'] ?? null ) || ! $this->is_this_site( (string) $request['url'] ) ) {
			return $request;
		}

		$request['args']['headers']['Authorization'] = 'Basic ' . base64_encode( $auth['user'] . ':' . $auth['password'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required by the HTTP Basic scheme.

		return $request;
	}

	private function is_this_site( string $url ): bool {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );

		return '' !== $host && in_array( $host, array( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ), strtolower( (string) wp_parse_url( site_url(), PHP_URL_HOST ) ) ), true );
	}
}
