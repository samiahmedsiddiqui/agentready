<?php
/**
 * Captures the final front-end HTML of a post.
 *
 * Fetches the public page as a logged-out visitor so theme templates, ACF output,
 * page builders and shortcodes are all included. Falls back to the_content.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Rendering;

use AgentReady\Http\Urls;
use AgentReady\Settings\Options;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Renderer {

	private const MAX_REDIRECTS = 3;

	public function __construct(
		private Options $options,
		private Urls $urls
	) {}

	public function render( WP_Post $post ): RenderResult {
		$url = $this->urls->permalink( $post );

		if ( null === $url ) {
			return $this->fallback( $post, 'no_permalink' );
		}

		if ( ! apply_filters( 'agentready_use_loopback', true, $post ) ) {
			return $this->fallback( $post, '' );
		}

		$result = $this->loopback( $url );

		return $result instanceof RenderResult ? $result : $this->fallback( $post, $result );
	}

	private function loopback( string $url ): RenderResult|string {
		$auth = $this->options->http_auth();

		for ( $hop = 0; $hop <= self::MAX_REDIRECTS; $hop++ ) {
			if ( ! $this->is_same_site( $url ) ) {
				return 'redirect_off_site';
			}

			$response = wp_remote_get( add_query_arg( 'agentready_render', (string) wp_rand(), $url ), $this->request_args( $auth ) );

			if ( is_wp_error( $response ) ) {
				return 'request_failed:' . $response->get_error_message();
			}

			$code = (int) wp_remote_retrieve_response_code( $response );

			if ( in_array( $code, array( 301, 302, 303, 307, 308 ), true ) ) {
				$location = (string) wp_remote_retrieve_header( $response, 'location' );

				if ( '' === $location ) {
					return 'redirect_invalid';
				}

				$url = UrlResolver::resolve( $url, $location );
				continue;
			}

			if ( 401 === $code ) {
				return null === $auth ? 'http_auth_required' : 'http_auth_failed';
			}

			$body = (string) wp_remote_retrieve_body( $response );

			if ( 200 !== $code || '' === $body ) {
				return 'http_' . $code;
			}

			return new RenderResult( $body, RenderResult::LOOPBACK );
		}

		return 'too_many_redirects';
	}

	/**
	 * @param array{user: string, password: string}|null $auth HTTP Basic credentials.
	 * @return array<string, mixed>
	 */
	private function request_args( ?array $auth ): array {
		$headers = array(
			'Accept'              => 'text/html,application/xhtml+xml',
			'Cache-Control'       => 'no-cache',
			'X-AgentReady-Render' => '1',
		);

		if ( null !== $auth ) {
			$headers['Authorization'] = 'Basic ' . base64_encode( $auth['user'] . ':' . $auth['password'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required by the HTTP Basic scheme.
		}

		return array(
			'timeout'             => (int) apply_filters( 'agentready_loopback_timeout', 15 ),
			'redirection'         => 0,
			'sslverify'           => (bool) apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook.
			'limit_response_size' => 5 * MB_IN_BYTES,
			'cookies'             => array(),
			'user-agent'          => 'AgentReady/' . AGENTREADY_VERSION . '; ' . home_url( '/' ),
			'headers'             => $headers,
		);
	}

	private function is_same_site( string $url ): bool {
		$target = wp_parse_url( $url );
		$home   = wp_parse_url( home_url() );

		if ( ! is_array( $target ) || ! is_array( $home ) || ! in_array( $target['scheme'] ?? '', array( 'http', 'https' ), true ) ) {
			return false;
		}

		return strtolower( (string) ( $target['host'] ?? '' ) ) === strtolower( (string) ( $home['host'] ?? '' ) )
			&& ( $target['port'] ?? null ) === ( $home['port'] ?? null );
	}

	private function fallback( WP_Post $item, string $code ): RenderResult {
		global $post;

		$original = $post;
		$post     = $item; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Shortcodes read the global post.

		setup_postdata( $item );
		$content = apply_filters( 'the_content', $item->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook.
		wp_reset_postdata();

		$post = $original; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restores the global.

		return new RenderResult( '<main>' . $content . '</main>', RenderResult::FALLBACK, $this->notice( $code ) );
	}

	private function notice( string $code ): string {
		if ( '' === $code ) {
			return '';
		}

		if ( 0 === strpos( $code, 'request_failed:' ) ) {
			/* translators: %s: error message. */
			return sprintf( __( 'The page could not be fetched (%s). Using the post content instead.', 'agentready' ), substr( $code, 15 ) );
		}

		switch ( $code ) {
			case 'http_auth_required':
				return __( 'The site asked for HTTP Basic authentication. Enable it in the AgentReady settings. Using the post content instead.', 'agentready' );
			case 'http_auth_failed':
				return __( 'The HTTP Basic authentication credentials were rejected. Using the post content instead.', 'agentready' );
			case 'redirect_off_site':
				return __( 'The page redirected to a different site. Using the post content instead.', 'agentready' );
			case 'no_permalink':
				return __( 'The post has no pretty permalink. Using the post content instead.', 'agentready' );
			default:
				/* translators: %s: error code. */
				return sprintf( __( 'The page could not be fetched (%s). Using the post content instead.', 'agentready' ), $code );
		}
	}
}
