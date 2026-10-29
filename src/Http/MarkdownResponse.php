<?php
/**
 * Sends Markdown with the correct headers (type, canonical, noindex, caching).
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Http;

defined( 'ABSPATH' ) || exit;

final class MarkdownResponse {

	public const MARKDOWN = 'text/markdown; charset=utf-8';
	public const TEXT     = 'text/plain; charset=utf-8';

	/**
	 * @param array<string, string> $extra Additional headers.
	 */
	public function send( string $body, string $content_type, string $canonical = '', int $modified = 0, array $extra = array() ): void {
		$etag    = '"' . md5( $body ) . '"';
		$headers = array(
			'Content-Type'           => $content_type,
			'X-Content-Type-Options' => 'nosniff',
			'X-Robots-Tag'           => 'noindex',
			'Cache-Control'          => 'public, max-age=300',
			'ETag'                   => $etag,
		) + $extra;

		if ( '' !== $canonical ) {
			$headers['Link'] = '<' . esc_url_raw( $canonical ) . '>; rel="canonical"';
		}

		if ( $modified > 0 ) {
			$headers['Last-Modified'] = gmdate( 'D, d M Y H:i:s', $modified ) . ' GMT';
		}

		$headers = (array) apply_filters( 'agentready_response_headers', $headers );

		if ( $this->is_not_modified( $etag, $modified ) ) {
			status_header( 304 );
			$this->send_headers( $headers );
			return;
		}

		status_header( 200 );
		$this->send_headers( $headers );

		if ( 'HEAD' !== $this->method() ) {
			echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Served as text, never HTML.
		}
	}

	public function unavailable(): void {
		status_header( 503 );
		$this->send_headers(
			array(
				'Content-Type'  => self::TEXT,
				'Retry-After'   => '10',
				'Cache-Control' => 'no-store',
				'X-Robots-Tag'  => 'noindex',
			)
		);

		if ( 'HEAD' !== $this->method() ) {
			echo esc_html__( 'The Markdown for this page is being generated. Try again in a few seconds.', 'agentready' ) . "\n";
		}
	}

	/**
	 * @param array<string, string> $headers Response headers.
	 */
	private function send_headers( array $headers ): void {
		foreach ( $headers as $name => $value ) {
			header( $name . ': ' . str_replace( array( "\r", "\n" ), '', (string) $value ) );
		}
	}

	private function method(): string {
		return isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
	}

	private function is_not_modified( string $etag, int $modified ): bool {
		if ( isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) {
			$sent = trim( sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) );

			return $sent === $etag || 'W/' . $etag === $sent;
		}

		if ( $modified > 0 && isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) {
			$since = strtotime( sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) );

			return false !== $since && $since >= $modified;
		}

		return false;
	}
}
