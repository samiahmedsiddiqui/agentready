<?php
/**
 * REST route that reports the all-pages scan progress.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Rest;

use AgentWelcome\Contracts\Hookable;
use AgentWelcome\Environment\Environment;
use AgentWelcome\Verify\Scan;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/** REST route that reports the progress of the scan of all pages. */
final class ScanController implements Hookable {

	/**
	 * @param Scan        $scan        The scan.
	 * @param Environment $environment Tells whether WP-Cron is disabled.
	 */
	public function __construct(
		private Scan $scan,
		private Environment $environment
	) {}

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * Registers the route.
	 *
	 * @return void
	 */
	public function routes(): void {
		register_rest_route(
			'agentwelcome/v1',
			'/scan',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'show' ),
				'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
			)
		);
	}

	/**
	 * Reports the progress. Without WP-Cron each call also runs one small step of the scan.
	 *
	 * @return WP_REST_Response Whether it runs, and the counts so far.
	 */
	public function show(): WP_REST_Response {
		// Without WP-Cron, the open page drives the scan: each poll runs one small batch.
		if ( $this->environment->cron_disabled() && $this->scan->status()['running'] ) {
			$this->scan->run();
		}

		$status = $this->scan->status();

		return new WP_REST_Response(
			array(
				'running' => $status['running'],
				'checked' => $status['checked'],
				'in_sync' => $status['in_sync'],
				'differs' => count( $status['differs'] ),
			)
		);
	}
}
