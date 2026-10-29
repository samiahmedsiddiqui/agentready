<?php
/**
 * Sanitizes settings before they are saved.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Settings;

use AgentReady\Content\PostTypes;
use AgentReady\Environment\Environment;
use AgentReady\Http\Urls;
use AgentReady\Rendering\Selector;

defined( 'ABSPATH' ) || exit;

final class Sanitizer {

	/** @var array<string, mixed>|null */
	private ?array $last = null;

	public function __construct( private PostTypes $post_types, private Options $options ) {}

	/**
	 * @param mixed $input Raw submitted value.
	 * @return array<string, mixed>
	 */
	public function sanitize( mixed $input ): array {
		// WordPress calls the sanitizer a second time with its own output on the first save.
		if ( is_array( $input ) && null !== $this->last && $input === $this->last ) {
			return $input;
		}

		$input     = is_array( $input ) ? $input : array();
		$selectors = new Selector();
		$content   = $selectors->parse_list( isset( $input['content_selector'] ) ? sanitize_text_field( $input['content_selector'] ) : '' );
		$strip     = $selectors->parse_list( isset( $input['strip_selectors'] ) ? sanitize_textarea_field( $input['strip_selectors'] ) : '' );

		$this->report_invalid( array_merge( $content['invalid'], $strip['invalid'] ) );

		$post_types = isset( $input['excluded_post_types'] ) && is_array( $input['excluded_post_types'] )
			? array_values( array_intersect( $this->post_types->available(), array_map( 'sanitize_key', $input['excluded_post_types'] ) ) )
			: array();

		$terms = isset( $input['excluded_terms'] ) && is_array( $input['excluded_terms'] )
			? array_values( array_filter( array_map( 'absint', $input['excluded_terms'] ) ) )
			: array();

		$this->last = array_merge(
			array(
				'excluded_post_types'  => $post_types,
				'excluded_posts'       => isset( $input['excluded_posts'] ) ? sanitize_textarea_field( $input['excluded_posts'] ) : '',
				'excluded_terms'       => $terms,
				'content_selector'     => implode( ', ', $content['valid'] ),
				'strip_selectors'      => implode( "\n", $strip['valid'] ),
				'show_meta_box'        => ! empty( $input['show_meta_box'] ),
				'http_auth_enabled'    => ! empty( $input['http_auth_enabled'] ),
				'allowed_environments' => isset( $input['allowed_environments'] ) && is_array( $input['allowed_environments'] )
					? array_values( array_intersect( Environment::OPTIONAL, array_map( 'sanitize_key', $input['allowed_environments'] ) ) )
					: array(),
				'home_markdown_url'    => isset( $input['home_markdown_url'] ) && Urls::HOME_LONG === $input['home_markdown_url'] ? Urls::HOME_LONG : Urls::HOME_SHORT,
				'allowed_urls'         => $this->allowed_urls( $input ),
				'delete_data'          => ! empty( $input['delete_data'] ),
			),
			$this->http_auth( $input )
		);

		return $this->last;
	}

	/**
	 * @param array<string, mixed> $input Raw submitted values.
	 */
	private function allowed_urls( array $input ): string {
		if ( defined( 'AGENTREADY_ALLOWED_URLS' ) ) {
			return (string) $this->options->get( 'allowed_urls', '' );
		}

		return implode( "\n", Environment::parse_urls( isset( $input['allowed_urls'] ) ? sanitize_textarea_field( $input['allowed_urls'] ) : '' ) );
	}

	/**
	 * @param string[] $invalid Selectors that could not be used.
	 */
	private function report_invalid( array $invalid ): void {
		if ( empty( $invalid ) ) {
			return;
		}

		add_settings_error(
			Options::KEY,
			'agentready_invalid_selector',
			sprintf(
				/* translators: %s: list of CSS selectors. */
				__( 'These selectors are not supported and were ignored: %s', 'agentready' ),
				implode( ', ', $invalid )
			),
			'warning'
		);
	}

	/**
	 * @param array<string, mixed> $input Raw submitted values.
	 * @return array<string, string>
	 */
	private function http_auth( array $input ): array {
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

		$password = isset( $input['http_auth_password'] ) ? (string) preg_replace( '/[\x00-\x1F\x7F]/', '', (string) $input['http_auth_password'] ) : '';

		return array(
			'http_auth_user'     => $user,
			'http_auth_password' => '' === $password ? (string) $current['http_auth_password'] : Secret::encrypt( $password ),
		);
	}
}
