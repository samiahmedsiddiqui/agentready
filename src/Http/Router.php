<?php
/**
 * Serves /llms.txt and the .md version of pages, before WordPress runs its main query.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Http;

use AgentReady\Cache\PageCache;
use AgentReady\Content\Eligibility;
use AgentReady\Contracts\Hookable;
use AgentReady\Environment\Environment;
use AgentReady\Index\LlmsTxt;
use WP;

defined( 'ABSPATH' ) || exit;

final class Router implements Hookable {

	public function __construct(
		private Urls $urls,
		private Eligibility $eligibility,
		private PageCache $pages,
		private Environment $environment,
		private LlmsTxt $llms,
		private MarkdownResponse $response
	) {}

	public function register(): void {
		add_action( 'parse_request', array( $this, 'maybe_serve' ), 1 );
	}

	public function maybe_serve( WP $wp ): void {
		if ( ! Urls::has_pretty_permalinks() || ! $this->is_read_request() || ! $this->environment->is_active() ) {
			return;
		}

		$route = $this->urls->parse_route( (string) $wp->request );

		if ( null === $route ) {
			return;
		}

		if ( Urls::ROUTE_LLMS === $route['type'] ) {
			$this->response->send( $this->llms->build(), MarkdownResponse::TEXT );
			$this->terminate();
		}

		$post = $this->urls->resolve( $route['path'], $route['home_alias'] );

		if ( null === $post ) {
			$this->redirect_to_canonical( $route, trim( (string) $wp->request, '/' ) );
			return;
		}

		if ( ! $this->eligibility->is_eligible( $post ) ) {
			return;
		}

		$served = $this->pages->fetch( $post );

		if ( null === $served ) {
			$this->response->unavailable();
			$this->terminate();
		}

		$this->response->send(
			$served->entry->markdown,
			(string) apply_filters( 'agentready_content_type', MarkdownResponse::MARKDOWN, $post ),
			(string) $this->urls->permalink( $post ),
			(int) strtotime( $post->post_modified_gmt . ' UTC' ),
			array( 'X-AgentReady-Cache' => $served->cache )
		);

		$this->terminate();
	}

	/**
	 * Sends a request for a valid page under the wrong path (such as its original permalink) to the real Markdown URL.
	 *
	 * @param array{type: string, path: string, home_alias: bool} $route   The parsed route.
	 * @param string                                              $request The requested path.
	 */
	private function redirect_to_canonical( array $route, string $request ): void {
		$post = $this->urls->resolve( $route['path'], $route['home_alias'], false );

		if ( null === $post || ! $this->eligibility->is_eligible( $post ) ) {
			return;
		}

		$target = $this->urls->markdown_url( $post );

		if ( null === $target || $this->urls->markdown_path( $post ) === $request ) {
			return;
		}

		wp_safe_redirect( $target, 301 );
		$this->terminate();
	}

	private function is_read_request(): bool {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';

		return in_array( $method, array( 'GET', 'HEAD' ), true );
	}

	/**
	 * @return never
	 */
	private function terminate(): void {
		exit;
	}
}
