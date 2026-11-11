<?php
/**
 * REST routes behind the editor box buttons.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Rest;

use AgentWelcome\Cache\PageCache;
use AgentWelcome\Cache\PageStatus;
use AgentWelcome\Cache\Store;
use AgentWelcome\Content\Eligibility;
use AgentWelcome\Contracts\Hookable;
use AgentWelcome\Environment\Environment;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/** REST routes for the state of one page and for regenerating or clearing its stored copy. Used by the editor box. */
final class PagesController implements Hookable {

	/** Namespace of the plugin routes. */
	private const NAMESPACE = 'agentwelcome/v1';

	/**
	 * @param Store       $store       Stored copies.
	 * @param PageCache   $pages       Generates the copies.
	 * @param PageStatus  $status      Describes the state of a page.
	 * @param Eligibility $eligibility Decides whether a page is served.
	 * @param Environment $environment Tells whether the plugin is running on this site.
	 */
	public function __construct(
		private Store $store,
		private PageCache $pages,
		private PageStatus $status,
		private Eligibility $eligibility,
		private Environment $environment
	) {}

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * Registers the routes.
	 *
	 * @return void
	 */
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

	/**
	 * Whether the current user may edit the page, which is what the routes require.
	 *
	 * @param WP_REST_Request $request The request, with the page ID.
	 * @return bool True when the user may edit the page.
	 */
	public function can_edit( WP_REST_Request $request ): bool {
		return current_user_can( 'edit_post', (int) $request['id'] );
	}

	/**
	 * Gives the state of a page.
	 *
	 * @param WP_REST_Request $request The request, with the page ID.
	 * @return WP_REST_Response|WP_Error The state, or an error when the page does not exist.
	 */
	public function show( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post = $this->post( $request );

		return $post instanceof WP_Post ? $this->respond( $post ) : $post;
	}

	/**
	 * Generates the stored copy of a page now.
	 *
	 * @param WP_REST_Request $request The request, with the page ID.
	 * @return WP_REST_Response|WP_Error The new state, or an error when the page is not served or is being generated.
	 */
	public function regenerate( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post = $this->post( $request );

		if ( ! $post instanceof WP_Post ) {
			return $post;
		}

		$reason = $this->environment->reason();

		if ( null !== $reason ) {
			return new WP_Error( 'agentwelcome_inactive', $this->environment->describe( $reason ), array( 'status' => 409 ) );
		}

		$reason = $this->eligibility->reason( $post );

		if ( null !== $reason ) {
			return new WP_Error( 'agentwelcome_not_served', $this->eligibility->describe( $reason ), array( 'status' => 409 ) );
		}

		if ( null === $this->pages->regenerate( $post ) ) {
			return new WP_Error( 'agentwelcome_busy', __( 'This page is being generated right now. Try again in a few seconds.', 'agentwelcome' ), array( 'status' => 409 ) );
		}

		return $this->respond( $post );
	}

	/**
	 * Deletes the stored copy of a page.
	 *
	 * @param WP_REST_Request $request The request, with the page ID.
	 * @return WP_REST_Response|WP_Error The new state, or an error when the page does not exist.
	 */
	public function clear( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post = $this->post( $request );

		if ( ! $post instanceof WP_Post ) {
			return $post;
		}

		$this->store->delete( $post->ID );

		return $this->respond( $post );
	}

	/**
	 * Finds the page of a request.
	 *
	 * @param WP_REST_Request $request The request, with the page ID.
	 * @return WP_Post|WP_Error The page, or a 404 error.
	 */
	private function post( WP_REST_Request $request ): WP_Post|WP_Error {
		$post = get_post( (int) $request['id'] );

		return $post instanceof WP_Post ? $post : new WP_Error( 'agentwelcome_not_found', __( 'Page not found.', 'agentwelcome' ), array( 'status' => 404 ) );
	}

	/**
	 * Builds the answer that describes a page.
	 *
	 * @param WP_Post $post The page.
	 * @return WP_REST_Response The state of the page.
	 */
	private function respond( WP_Post $post ): WP_REST_Response {
		return new WP_REST_Response( $this->status->for_post( $post ) );
	}
}
