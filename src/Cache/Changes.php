<?php
/**
 * Notices a theme or plugin change, which can change how every page renders.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Cache;

use AgentReady\Contracts\Hookable;
use AgentReady\Environment\Environment;

defined( 'ABSPATH' ) || exit;

final class Changes implements Hookable {

	public const OPTION = 'agentready_changes';

	public const REFRESH = 'refresh';
	public const PLUGINS = 'plugins';

	public function __construct(
		private Batch $batch,
		private Environment $environment
	) {}

	public function register(): void {
		add_action( 'after_switch_theme', array( $this, 'on_theme_switch' ) );
		add_action( 'customize_save_after', array( $this, 'on_customizer' ) );
		add_action( 'activated_plugin', array( $this, 'on_plugin' ) );
		add_action( 'deactivated_plugin', array( $this, 'on_plugin' ) );
		add_action( 'upgrader_process_complete', array( $this, 'on_upgrade' ), 10, 2 );
	}

	public function on_theme_switch(): void {
		$this->refresh( 'theme' );
	}

	public function on_customizer(): void {
		$this->refresh( 'customizer' );
	}

	public function on_plugin( mixed $plugin ): void {
		if ( ! is_string( $plugin ) || plugin_basename( AGENTREADY_FILE ) === $plugin ) {
			return;
		}

		$this->note_plugins( 1 );
	}

	/**
	 * @param mixed $upgrader Upgrader instance.
	 * @param mixed $extra    What was upgraded.
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
			$plugins = array_diff( array_map( 'strval', (array) ( $extra['plugins'] ?? array() ) ), array( plugin_basename( AGENTREADY_FILE ) ) );

			if ( array() !== $plugins ) {
				$this->note_plugins( count( $plugins ) );
			}
		}
	}

	/**
	 * @return array<string, array<string, mixed>> Notices not yet dismissed, by key.
	 */
	public function pending(): array {
		$saved = get_option( self::OPTION, array() );

		return is_array( $saved ) ? $saved : array();
	}

	public function dismiss( string $key ): void {
		$saved = $this->pending();

		unset( $saved[ $key ] );
		update_option( self::OPTION, $saved, false );
	}

	private function refresh( string $reason ): void {
		if ( ! $this->environment->is_active() ) {
			return;
		}

		$this->batch->start( true );
		$this->record( self::REFRESH, array( 'reason' => $reason ) );
	}

	private function note_plugins( int $count ): void {
		if ( ! $this->environment->is_active() ) {
			return;
		}

		$saved = $this->pending();
		$total = isset( $saved[ self::PLUGINS ]['count'] ) ? (int) $saved[ self::PLUGINS ]['count'] : 0;

		$this->record( self::PLUGINS, array( 'count' => $total + $count ) );
	}

	/**
	 * @param array<string, mixed> $data Details of the change.
	 */
	private function record( string $key, array $data ): void {
		$saved         = $this->pending();
		$saved[ $key ] = $data + array( 'at' => time() );

		update_option( self::OPTION, $saved, false );
	}
}
