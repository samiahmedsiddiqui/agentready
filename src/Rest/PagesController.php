<?php
/**
 * REST routes behind the editor box buttons.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Rest;

use AgentReady\Cache\PageCache;
use AgentReady\Cache\PageStatus;
use AgentReady\Cache\Store;
use AgentReady\Content\Eligibility;
use AgentReady\Contracts\Hookable;
use AgentReady\Environment\Environment;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class PagesController implements Hookable {

	private const NAMESPACE = 'agentready/v1';

	public function __construct(
		private Store $store,
		private PageCache $pages,
		private PageStatus $status,
		private Eligibility $eligibility,
		private Environment $environment
	) {}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		$args = array(
			'id' => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
		);

		register_rest_route(
			self::NAMESPACE,
			'/pages/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'show' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => $args,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/pages/(?P<id>\d+)/regenerate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'regenerate' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => $args,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/pages/(?P<id>\d+)/cache',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'clear' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => $args,
			)
		);
	}

	public function can_edit( WP_REST_Request $request ): bool {
		return current_user_can( 'edit_post', (int) $request['id'] );
	}

	public function show( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post = $this->post( $request );

		return $post instanceof WP_Post ? $this->respond( $post ) : $post;
	}

	public function regenerate( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post = $this->post( $request );

		if ( ! $post instanceof WP_Post ) {
			return $post;
		}

		$reason = $this->environment->reason();

		if ( null !== $reason ) {
			return new WP_Error( 'agentready_inactive', $this->environment->describe( $reason ), array( 'status' => 409 ) );
		}

		$reason = $this->eligibility->reason( $post );

		if ( null !== $reason ) {
			return new WP_Error( 'agentready_not_served', $this->eligibility->describe( $reason ), array( 'status' => 409 ) );
		}

		if ( null === $this->pages->regenerate( $post ) ) {
			return new WP_Error( 'agentready_busy', __( 'This page is being generated right now. Try again in a few seconds.', 'agentready' ), array( 'status' => 409 ) );
		}

		return $this->respond( $post );
	}

	public function clear( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post = $this->post( $request );

		if ( ! $post instanceof WP_Post ) {
			return $post;
		}

		$this->store->delete( $post->ID );

		return $this->respond( $post );
	}

	private function post( WP_REST_Request $request ): WP_Post|WP_Error {
		$post = get_post( (int) $request['id'] );

		return $post instanceof WP_Post ? $post : new WP_Error( 'agentready_not_found', __( 'Page not found.', 'agentready' ), array( 'status' => 404 ) );
	}

	private function respond( WP_Post $post ): WP_REST_Response {
		return new WP_REST_Response( $this->status->for_post( $post ) );
	}
}
