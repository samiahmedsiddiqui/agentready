<?php
/**
 * REST route that reports the all-pages scan progress.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Rest;

use AgentReady\Contracts\Hookable;
use AgentReady\Verify\Scan;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class ScanController implements Hookable {

	public function __construct( private Scan $scan ) {}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		register_rest_route(
			'agentready/v1',
			'/scan',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'show' ),
				'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
			)
		);
	}

	public function show(): WP_REST_Response {
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
