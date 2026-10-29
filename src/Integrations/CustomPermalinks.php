<?php
/**
 * Keeps Custom Permalinks away from AgentReady's own URLs.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Integrations;

use AgentReady\Contracts\Hookable;
use AgentReady\Http\Urls;

defined( 'ABSPATH' ) || exit;

final class CustomPermalinks implements Hookable {

	public function __construct( private Urls $urls ) {}

	public function register(): void {
		add_filter( 'custom_permalinks_request_ignore', array( $this, 'ignore' ) );
	}

	/**
	 * Custom Permalinks matches a request by prefix, so /news/launch.md would be sent to /news/launch before AgentReady runs.
	 *
	 * @param mixed $request The requested path.
	 * @return mixed '__true' to make Custom Permalinks skip the request.
	 */
	public function ignore( mixed $request ): mixed {
		return is_string( $request ) && null !== $this->urls->parse_route( $request ) ? '__true' : $request;
	}
}
