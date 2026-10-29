<?php
/**
 * Sanitizes settings before they are saved.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Settings;

use AgentReady\Content\PostTypes;

defined( 'ABSPATH' ) || exit;

final class Sanitizer {

	public function __construct( private PostTypes $post_types, private Options $options ) {}

	/**
	 * @param mixed $input Raw submitted value.
	 * @return array<string, mixed>
	 */
	public function sanitize( mixed $input ): array {
		$input = is_array( $input ) ? $input : array();

		// WordPress runs the callback a second time on the first save, with the cleaned result.
		$cleaned = array_key_exists( 'excluded_post_types', $input ) && ! array_key_exists( 'enabled_post_types', $input );

		if ( $cleaned ) {
			$excluded = array_values( array_map( 'sanitize_key', (array) $input['excluded_post_types'] ) );
		} else {
			$enabled  = isset( $input['enabled_post_types'] ) && is_array( $input['enabled_post_types'] )
				? array_map( 'sanitize_key', $input['enabled_post_types'] )
				: array();
			$excluded = array_values( array_diff( $this->post_types->available(), $enabled ) );
		}

		$terms = isset( $input['excluded_terms'] ) && is_array( $input['excluded_terms'] )
			? array_values( array_filter( array_map( 'absint', $input['excluded_terms'] ) ) )
			: array();

		$selector = isset( $input['content_selector'] ) ? sanitize_text_field( $input['content_selector'] ) : '';

		$settings = array(
			'excluded_post_types' => $excluded,
			'excluded_posts'      => isset( $input['excluded_posts'] ) ? sanitize_textarea_field( $input['excluded_posts'] ) : '',
			'excluded_terms'      => $terms,
			'content_selector'    => '' === $selector ? Options::DEFAULT_SELECTOR : $selector,
			'http_auth_enabled'   => ! empty( $input['http_auth_enabled'] ),
		);

		return array_merge( $settings, $this->http_auth( $input, $cleaned ) );
	}

	/**
	 * @param array<string, mixed> $input   Raw submitted values.
	 * @param bool                 $cleaned Whether the input already went through this sanitizer.
	 * @return array<string, string>
	 */
	private function http_auth( array $input, bool $cleaned ): array {
		$current = $this->options->all();

		if ( $this->options->http_auth_from_constants() ) {
			return array(
				'http_auth_user'     => (string) $current['http_auth_user'],
				'http_auth_password' => (string) $current['http_auth_password'],
			);
		}

		$user = isset( $input['http_auth_user'] ) ? sanitize_text_field( $input['http_auth_user'] ) : '';

		if ( '' === $user ) {
			return array(
				'http_auth_user'     => '',
				'http_auth_password' => '',
			);
		}

		if ( $cleaned ) {
			$stored = isset( $input['http_auth_password'] ) ? (string) $input['http_auth_password'] : '';

			return array(
				'http_auth_user'     => $user,
				'http_auth_password' => 0 === strpos( $stored, 'v1:' ) ? $stored : (string) $current['http_auth_password'],
			);
		}

		$password = isset( $input['http_auth_password'] ) ? (string) preg_replace( '/[\x00-\x1F\x7F]/', '', (string) $input['http_auth_password'] ) : '';

		return array(
			'http_auth_user'     => $user,
			'http_auth_password' => '' === $password ? (string) $current['http_auth_password'] : Secret::encrypt( $password ),
		);
	}
}
