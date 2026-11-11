<?php
/**
 * Keeps Custom Permalinks away from AgentWelcome's own URLs.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Integrations;

use AgentWelcome\Contracts\Hookable;
use AgentWelcome\Http\Urls;

defined( 'ABSPATH' ) || exit;

/** Keeps the Custom Permalinks plugin from redirecting the Markdown addresses. */
final class CustomPermalinks implements Hookable {

	/**
	 * @param Urls $urls Reads the addresses.
	 */
	public function __construct( private Urls $urls ) {}

	/**
	 * @return void
	 */
	public function register(): void {
		add_filter( 'custom_permalinks_request_ignore', array( $this, 'ignore' ) );
	}

	/**
	 * Custom Permalinks matches a request by prefix, so /news/launch.md would be sent to /news/launch before AgentWelcome runs.
	 *
	 * @param mixed $request The requested path.
	 * @return mixed "__true" to make Custom Permalinks skip the request, otherwise the value unchanged.
	 */
	public function ignore( mixed $request ): mixed {
		return is_string( $request ) && null !== $this->urls->parse_route( $request ) ? '__true' : $request;
	}
}
