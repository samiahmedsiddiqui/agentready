<?php
/**
 * Top-level AgentReady menu with the Settings and Verify & Preview pages.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Admin;

use AgentReady\Contracts\Hookable;

defined( 'ABSPATH' ) || exit;

final class Menu implements Hookable {

	private const AUTHOR_URL = 'https://www.linkedin.com/in/sami-ahmed-siddiqui/';

	private const POSITION = 81;

	public function __construct(
		private SettingsPage $settings,
		private VerifyPage $verify
	) {}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_pages' ) );
		add_filter( 'admin_footer_text', array( $this, 'footer_text' ), 11 );
		add_filter( 'update_footer', array( $this, 'footer_version' ), 11 );
	}

	public function add_pages(): void {
		add_menu_page(
			__( 'AgentReady', 'agentready' ),
			__( 'AgentReady', 'agentready' ),
			'manage_options',
			SettingsPage::SLUG,
			array( $this->settings, 'render' ),
			$this->icon(),
			self::POSITION
		);

		add_submenu_page( SettingsPage::SLUG, __( 'AgentReady Settings', 'agentready' ), __( 'Settings', 'agentready' ), 'manage_options', SettingsPage::SLUG, array( $this->settings, 'render' ) );
		add_submenu_page( SettingsPage::SLUG, __( 'Verify & Preview', 'agentready' ), __( 'Verify & Preview', 'agentready' ), 'manage_options', VerifyPage::SLUG, array( $this->verify, 'render' ) );
	}

	public function footer_text( mixed $text ): mixed {
		if ( ! $this->is_plugin_screen() ) {
			return $text;
		}

		return wp_kses(
			sprintf(
				/* translators: %s: author name, linked to their profile. */
				__( 'Thank you for using AgentReady. Built by %s.', 'agentready' ),
				'<a href="' . esc_url( self::AUTHOR_URL ) . '" target="_blank" rel="noopener noreferrer">Sami Ahmed Siddiqui</a>'
			),
			array(
				'a' => array(
					'href'   => array(),
					'target' => array(),
					'rel'    => array(),
				),
			)
		);
	}

	public function footer_version( mixed $text ): mixed {
		if ( ! $this->is_plugin_screen() ) {
			return $text;
		}

		/* translators: %s: plugin version. */
		return esc_html( sprintf( __( 'AgentReady %s', 'agentready' ), AGENTREADY_VERSION ) );
	}

	private function is_plugin_screen(): bool {
		$screen = get_current_screen();

		return $screen instanceof \WP_Screen && in_array( $screen->id, array( 'toplevel_page_' . SettingsPage::SLUG, 'agentready_page_' . VerifyPage::SLUG ), true );
	}

	/**
	 * A sparkle, the common symbol for AI, as an SVG that WordPress recolors to match the admin scheme.
	 */
	private function icon(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="#a7aaad" d="M8 5Q8 12 15 12Q8 12 8 19Q8 12 1 12Q8 12 8 5ZM15.5 1Q15.5 4.5 19 4.5Q15.5 4.5 15.5 8Q15.5 4.5 12 4.5Q15.5 4.5 15.5 1Z"/></svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required for a menu icon data URI.
	}
}
