<?php
/**
 * Top-level AgentWelcome menu with the Settings, llms.txt and Verify & Preview pages.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Admin;

use AgentWelcome\Contracts\Hookable;

defined( 'ABSPATH' ) || exit;

/** Adds the AgentWelcome menu and its pages, and the footer text on them. */
final class Menu implements Hookable {

	/** Profile of the author, linked in the footer. */
	private const AUTHOR_URL = 'https://www.linkedin.com/in/sami-ahmed-siddiqui/';

	/** Position of the menu in the admin sidebar. */
	private const POSITION = 81;

	/**
	 * @param SettingsPage $settings Settings page.
	 * @param LlmsPage     $llms     llms.txt page.
	 * @param VerifyPage   $verify   Verify and Preview page.
	 */
	public function __construct(
		private SettingsPage $settings,
		private LlmsPage $llms,
		private VerifyPage $verify
	) {}

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_pages' ) );
		add_filter( 'admin_footer_text', array( $this, 'footer_text' ), 11 );
		add_filter( 'update_footer', array( $this, 'footer_version' ), 11 );
	}

	/**
	 * Adds the menu. Administrators get all pages. A user who may only manage the llms.txt files gets a menu with that page alone.
	 *
	 * @return void
	 */
	public function add_pages(): void {
		if ( current_user_can( 'manage_options' ) ) {
			$this->add_all_pages();
		} elseif ( current_user_can( Capabilities::files() ) ) {
			$this->add_files_page_only();
		}
	}

	/**
	 * Adds the full menu with Settings, llms.txt and Verify & Preview.
	 *
	 * @return void
	 */
	private function add_all_pages(): void {
		add_menu_page(
			__( 'AgentWelcome', 'agentwelcome' ),
			__( 'AgentWelcome', 'agentwelcome' ),
			'manage_options',
			SettingsPage::SLUG,
			array( $this->settings, 'render' ),
			$this->icon(),
			self::POSITION
		);

		add_submenu_page( SettingsPage::SLUG, __( 'AgentWelcome Settings', 'agentwelcome' ), __( 'Settings', 'agentwelcome' ), 'manage_options', SettingsPage::SLUG, array( $this->settings, 'render' ) );
		add_submenu_page( SettingsPage::SLUG, __( 'llms.txt', 'agentwelcome' ), __( 'llms.txt', 'agentwelcome' ), Capabilities::files(), LlmsPage::SLUG, array( $this->llms, 'render' ) );
		add_submenu_page( SettingsPage::SLUG, __( 'Verify & Preview', 'agentwelcome' ), __( 'Verify & Preview', 'agentwelcome' ), 'manage_options', VerifyPage::SLUG, array( $this->verify, 'render' ) );
	}

	/**
	 * Adds a top-level menu with the llms.txt page alone.
	 *
	 * @return void
	 */
	private function add_files_page_only(): void {
		add_menu_page(
			__( 'llms.txt', 'agentwelcome' ),
			__( 'llms.txt', 'agentwelcome' ),
			Capabilities::files(),
			LlmsPage::SLUG,
			array( $this->llms, 'render' ),
			$this->icon(),
			self::POSITION
		);
	}

	/**
	 * Replaces the admin footer text on the plugin pages.
	 *
	 * @param mixed $text Current footer text.
	 * @return mixed The new text on the plugin pages, otherwise the text unchanged.
	 */
	public function footer_text( mixed $text ): mixed {
		if ( ! $this->is_plugin_screen() ) {
			return $text;
		}

		return wp_kses(
			sprintf(
				/* translators: %s: author name, linked to their profile. */
				__( 'Thank you for using AgentWelcome. Built by %s.', 'agentwelcome' ),
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

	/**
	 * Replaces the WordPress version in the footer on the plugin pages.
	 *
	 * @param mixed $text Current footer text.
	 * @return mixed The plugin version on the plugin pages, otherwise the text unchanged.
	 */
	public function footer_version( mixed $text ): mixed {
		if ( ! $this->is_plugin_screen() ) {
			return $text;
		}

		/* translators: %s: plugin version. */
		return esc_html( sprintf( __( 'AgentWelcome %s', 'agentwelcome' ), AGENTWELCOME_VERSION ) );
	}

	/**
	 * Whether the current admin screen is one of the plugin pages.
	 *
	 * @return bool True when it is.
	 */
	private function is_plugin_screen(): bool {
		$screen = get_current_screen();

		return $screen instanceof \WP_Screen && in_array( $screen->id, array( 'toplevel_page_' . SettingsPage::SLUG, 'toplevel_page_' . LlmsPage::SLUG, 'agentwelcome_page_' . LlmsPage::SLUG, 'agentwelcome_page_' . VerifyPage::SLUG ), true );
	}

	/**
	 * Menu icon: a sparkle, as an SVG that WordPress recolors to match the admin scheme.
	 *
	 * @return string Data URI of the icon.
	 */
	private function icon(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="#a7aaad" d="M8 5Q8 12 15 12Q8 12 8 19Q8 12 1 12Q8 12 8 5ZM15.5 1Q15.5 4.5 19 4.5Q15.5 4.5 15.5 8Q15.5 4.5 12 4.5Q15.5 4.5 15.5 1Z"/></svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required for a menu icon data URI.
	}
}
