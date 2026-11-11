<?php
/**
 * Serves /llms.txt, /llms-full.txt and the .md version of pages, before WordPress runs its main query.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Http;

use AgentReady\Cache\PageCache;
use AgentReady\Content\Eligibility;
use AgentReady\Contracts\Hookable;
use AgentReady\Environment\Environment;
use AgentReady\Index\LlmsFullTxt;
use AgentReady\Index\LlmsTxt;
use AgentReady\Index\OutputCache;
use AgentReady\Settings\LlmsOptions;
use WP;

defined( 'ABSPATH' ) || exit;

/** Answers requests for the Markdown pages, llms.txt and llms-full.txt before WordPress loads a template. */
final class Router implements Hookable {

	/**
	 * @param Urls             $urls         Reads and builds the addresses.
	 * @param Eligibility      $eligibility  Decides whether a page is served.
	 * @param PageCache        $pages        Gives the stored copy of a page.
	 * @param Environment      $environment  Tells whether the plugin is running on this site.
	 * @param LlmsTxt          $llms         Builds llms.txt.
	 * @param LlmsFullTxt      $llms_full    Builds llms-full.txt.
	 * @param LlmsOptions      $llms_options Settings of the llms files.
	 * @param MarkdownResponse $response     Sends the answer.
	 */
	public function __construct(
		private Urls $urls,
		private Eligibility $eligibility,
		private PageCache $pages,
		private Environment $environment,
		private LlmsTxt $llms,
		private LlmsFullTxt $llms_full,
		private LlmsOptions $llms_options,
		private MarkdownResponse $response
	) {}

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'parse_request', array( $this, 'maybe_serve' ), 1 );
	}

	/**
	 * Serves the request when it is for one of the files of this plugin.
	 *
	 * @param WP $wp The WordPress request.
	 * @return void
	 */
	public function maybe_serve( WP $wp ): void {
		if ( ! Urls::has_pretty_permalinks() || ! $this->is_read_request() || ! $this->environment->is_active() ) {
			return;
		}

		$route = $this->urls->parse_route( (string) $wp->request );

		if ( null === $route ) {
			return;
		}

		if ( Urls::ROUTE_LLMS === $route['type'] ) {
			$this->serve_index( OutputCache::LLMS, fn(): string => $this->llms->build() );
		}

		if ( Urls::ROUTE_LLMS_FULL === $route['type'] ) {
			if ( ! $this->llms_options->full_enabled() ) {
				return;
			}

			$this->serve_index( OutputCache::FULL, fn(): string => $this->llms_full->build() );
		}

		$this->serve_page( $route, trim( (string) $wp->request, '/' ) );
	}

	/**
	 * Sends an llms file, from the saved copy when there is one, and stops.
	 *
	 * @param string             $key   Name of the saved copy.
	 * @param callable(): string $build Builds the file when there is no saved copy.
	 * @return never
	 */
	private function serve_index( string $key, callable $build ): void {
		$this->response->send( OutputCache::remember( $key, $build ), MarkdownResponse::TEXT );
		$this->terminate();
	}

	/**
	 * Sends the Markdown of a page, or redirects to its real address, or lets WordPress carry on.
	 *
	 * @param array{type: string, path: string, home_alias: bool} $route   The parsed route.
	 * @param string                                              $request The requested path.
	 * @return void
	 */
	private function serve_page( array $route, string $request ): void {
		$post = $this->urls->resolve( $route['path'], $route['home_alias'] );

		if ( null === $post ) {
			$this->redirect_to_canonical( $route, $request );
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
	 * Sends a request for a valid page under the wrong path (such as its original permalink) to the real Markdown address.
	 *
	 * @param array{type: string, path: string, home_alias: bool} $route   The parsed route.
	 * @param string                                              $request The requested path.
	 * @return void
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

	/**
	 * Whether the request only reads.
	 *
	 * @return bool True for GET and HEAD.
	 */
	private function is_read_request(): bool {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';

		return in_array( $method, array( 'GET', 'HEAD' ), true );
	}

	/**
	 * Ends the request.
	 *
	 * @return never
	 */
	private function terminate(): void {
		exit;
	}
}
