<?php
/**
 * Notices a theme or plugin change, which can change how every page renders.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Cache;

use AgentWelcome\Contracts\Hookable;
use AgentWelcome\Environment\Environment;

defined( 'ABSPATH' ) || exit;

/** Notes theme and plugin changes that can alter how pages look, so the admin can show a notice and the stored copies get refreshed. */
final class Changes implements Hookable {

	/** Option that holds the notices that have not been dismissed. */
	public const OPTION = 'agentwelcome_changes';

	/** Notice key for a change that refreshed every page: a theme switch, a theme update or a Customizer save. */
	public const REFRESH = 'refresh';
	/** Notice key for the count of changed plugins. */
	public const PLUGINS = 'plugins';

	/**
	 * @param Batch       $batch       Runs the refresh of every page.
	 * @param Environment $environment Tells whether the plugin is running on this site.
	 */
	public function __construct(
		private Batch $batch,
		private Environment $environment
	) {}

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'after_switch_theme', array( $this, 'on_theme_switch' ) );
		add_action( 'customize_save_after', array( $this, 'on_customizer' ) );
		add_action( 'activated_plugin', array( $this, 'on_plugin' ) );
		add_action( 'deactivated_plugin', array( $this, 'on_plugin' ) );
		add_action( 'upgrader_process_complete', array( $this, 'on_upgrade' ), 10, 2 );
	}

	/**
	 * Refreshes every page after the active theme was switched.
	 *
	 * @return void
	 */
	public function on_theme_switch(): void {
		$this->refresh( 'theme' );
	}

	/**
	 * Refreshes every page after the Customizer was saved.
	 *
	 * @return void
	 */
	public function on_customizer(): void {
		$this->refresh( 'customizer' );
	}

	/**
	 * Counts a plugin that was activated or deactivated. This plugin itself is ignored.
	 *
	 * @param mixed $plugin Plugin file, relative to the plugins folder.
	 * @return void
	 */
	public function on_plugin( mixed $plugin ): void {
		if ( ! is_string( $plugin ) || plugin_basename( AGENTWELCOME_FILE ) === $plugin ) {
			return;
		}

		$this->note_plugins( 1 );
	}

	/**
	 * Handles finished updates: an update of the active theme refreshes every page, and updated plugins are counted.
	 *
	 * @param mixed $upgrader Upgrader instance.
	 * @param mixed $extra    What was upgraded.
	 * @return void
	 */
	public function on_upgrade( mixed $upgrader, mixed $extra ): void {
		if ( ! is_array( $extra ) || 'update' !== ( $extra['action'] ?? '' ) ) {
			return;
		}

		if ( 'theme' === ( $extra['type'] ?? '' ) ) {
			$themes = array_map( 'strval', (array) ( $extra['themes'] ?? array() ) );

			if ( array_intersect( $themes, array( get_stylesheet(), get_template() ) ) ) {
				$this->refresh( 'theme_update' );
			}

			return;
		}

		if ( 'plugin' === ( $extra['type'] ?? '' ) ) {
			$plugins = array_diff( array_map( 'strval', (array) ( $extra['plugins'] ?? array() ) ), array( plugin_basename( AGENTWELCOME_FILE ) ) );

			if ( array() !== $plugins ) {
				$this->note_plugins( count( $plugins ) );
			}
		}
	}

	/**
	 * Notices that have not been dismissed.
	 *
	 * @return array<string, array<string, mixed>> Notice details by key.
	 */
	public function pending(): array {
		$saved = get_option( self::OPTION, array() );

		return is_array( $saved ) ? $saved : array();
	}

	/**
	 * Removes a notice.
	 *
	 * @param string $key Notice key.
	 * @return void
	 */
	public function dismiss( string $key ): void {
		$saved = $this->pending();

		unset( $saved[ $key ] );
		update_option( self::OPTION, $saved, false );
	}

	/**
	 * Marks every page out of date, starts the background refresh and records the notice.
	 *
	 * @param string $reason What happened: theme, theme_update or customizer.
	 * @return void
	 */
	private function refresh( string $reason ): void {
		if ( ! $this->environment->is_active() ) {
			return;
		}

		$this->batch->start( true );
		$this->record( self::REFRESH, array( 'reason' => $reason ) );
	}

	/**
	 * Adds to the count of changed plugins.
	 *
	 * @param int $count Number of plugins to add.
	 * @return void
	 */
	private function note_plugins( int $count ): void {
		if ( ! $this->environment->is_active() ) {
			return;
		}

		$saved = $this->pending();
		$total = isset( $saved[ self::PLUGINS ]['count'] ) ? (int) $saved[ self::PLUGINS ]['count'] : 0;

		$this->record( self::PLUGINS, array( 'count' => $total + $count ) );
	}

	/**
	 * Saves a notice, replacing any earlier one with the same key.
	 *
	 * @param string               $key  Notice key.
	 * @param array<string, mixed> $data Details of the change.
	 * @return void
	 */
	private function record( string $key, array $data ): void {
		$saved         = $this->pending();
		$saved[ $key ] = $data + array( 'at' => time() );

		update_option( self::OPTION, $saved, false );
	}
}
