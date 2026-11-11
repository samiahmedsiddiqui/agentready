<?php
/**
 * Adds the rel="alternate" Markdown link to the page head.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Http;

use AgentReady\Content\Eligibility;
use AgentReady\Contracts\Hookable;
use AgentReady\Environment\Environment;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/** Adds a link in the page head that points to the Markdown version of the page. */
final class DiscoveryTags implements Hookable {

	/**
	 * @param Eligibility $eligibility Decides whether a page is served.
	 * @param Urls        $urls        Builds the addresses.
	 * @param Environment $environment Tells whether the plugin is running on this site.
	 */
	public function __construct(
		private Eligibility $eligibility,
		private Urls $urls,
		private Environment $environment
	) {}

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_head', array( $this, 'render' ), 2 );
	}

	/**
	 * Prints the link tag on single pages that are served.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! is_singular() || ! $this->environment->is_active() ) {
			return;
		}

		$post = get_queried_object();

		if ( ! $post instanceof WP_Post || ! $this->eligibility->is_eligible( $post ) ) {
			return;
		}

		$url = $this->urls->markdown_url( $post );

		if ( null !== $url ) {
			printf( '<link rel="alternate" type="text/markdown" href="%s" />' . "\n", esc_url( $url ) );
		}
	}
}
