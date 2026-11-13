<?php
/**
 * REST route to read and change the llms.txt and llms-full.txt settings.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Rest;

use AgentWelcome\Admin\Capabilities;
use AgentWelcome\Contracts\Hookable;
use AgentWelcome\Settings\LlmsOptions;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/** REST route to read and change the mode and custom text of llms.txt and llms-full.txt, so a tool that signs in with an application password can manage them. */
final class LlmsController implements Hookable {

	/** Namespace of the plugin routes. */
	private const NAMESPACE = 'agentwelcome/v1';

	/** Fields that can be sent, with their types. */
	private const FIELDS = array(
		'mode'        => 'string',
		'custom'      => 'string',
		'full'        => 'boolean',
		'full_mode'   => 'string',
		'full_custom' => 'string',
		'robots_line' => 'boolean',
	);

	/**
	 * @param LlmsOptions $options Settings of the llms files.
	 */
	public function __construct(
		private LlmsOptions $options
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
		$args = array();

		foreach ( self::FIELDS as $name => $type ) {
			$args[ $name ] = array( 'type' => $type );
		}

		$args['mode']['enum']      = array( LlmsOptions::AUTOMATIC, LlmsOptions::CUSTOM, LlmsOptions::HYBRID );
		$args['full_mode']['enum'] = $args['mode']['enum'];

		register_rest_route(
			self::NAMESPACE,
			'/llms',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'show' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => $args,
				),
			)
		);
	}

	/**
	 * Whether the current user may edit the llms files, which is what the route requires.
	 *
	 * @return bool True when the user has the llms.txt capability.
	 */
	public function can_manage(): bool {
		return current_user_can( Capabilities::files() );
	}

	/**
	 * Returns the saved settings.
	 *
	 * @return WP_REST_Response The settings and any notes about the texts.
	 */
	public function show(): WP_REST_Response {
		return $this->response();
	}

	/**
	 * Saves the fields that were sent and keeps the others as they are.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response|WP_Error The saved settings, or an error when a text is larger than the limit. A longer text is refused, not cut.
	 */
	public function update( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		foreach ( array( 'custom', 'full_custom' ) as $name ) {
			if ( is_string( $request->get_param( $name ) ) && strlen( $request->get_param( $name ) ) > LlmsOptions::MAX_BYTES ) {
				return new WP_Error(
					'agentwelcome_too_large',
					/* translators: %s: Largest text that can be saved, in bytes. */
					sprintf( __( 'The text is larger than %s bytes.', 'agentwelcome' ), number_format_i18n( LlmsOptions::MAX_BYTES ) ),
					array( 'status' => 413 )
				);
			}
		}

		$input = $this->options->all();

		foreach ( array_keys( self::FIELDS ) as $name ) {
			if ( $request->has_param( $name ) ) {
				$input[ $name ] = $request->get_param( $name );
			}
		}

		update_option( LlmsOptions::KEY, $this->options->sanitize( $input ) );

		return $this->response();
	}

	/**
	 * Builds the response from the saved settings.
	 *
	 * @return WP_REST_Response The settings and any notes about the texts.
	 */
	private function response(): WP_REST_Response {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		return new WP_REST_Response(
			array(
				'mode'        => $this->options->mode(),
				'custom'      => $this->options->custom(),
				'full'        => $this->options->full_enabled(),
				'full_mode'   => $this->options->full_mode(),
				'full_custom' => $this->options->full_custom(),
				'robots_line' => $this->options->robots_line(),
				'notes'       => LlmsOptions::check( $this->options->custom(), $host ),
				'full_notes'  => LlmsOptions::check( $this->options->full_custom(), $host ),
				'max_bytes'   => LlmsOptions::MAX_BYTES,
			)
		);
	}
}
