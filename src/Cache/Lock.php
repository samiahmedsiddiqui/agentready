<?php
/**
 * Per-page generation lock, taken with an atomic insert into the options table.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Cache;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Locks must bypass the object cache.

final class Lock {

	private const TTL = 60;

	public function acquire( int $post_id ): bool {
		global $wpdb;

		$name = $this->name( $post_id );

		if ( $this->insert( $name ) ) {
			return true;
		}

		$held = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );

		if ( null === $held || time() - (int) $held <= self::TTL ) {
			return false;
		}

		$removed = $wpdb->delete(
			$wpdb->options,
			array(
				'option_name'  => $name,
				'option_value' => $held,
			)
		);

		if ( 1 !== $removed ) {
			return false;
		}

		return $this->insert( $name );
	}

	public function release( int $post_id ): void {
		global $wpdb;

		$wpdb->delete( $wpdb->options, array( 'option_name' => $this->name( $post_id ) ) );
	}

	private function insert( string $name ): bool {
		global $wpdb;

		return 1 === (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} ( option_name, option_value, autoload ) VALUES ( %s, %s, 'no' ) /* LOCK */", $name, (string) time() ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Core table name.
	}

	private function name( int $post_id ): string {
		return 'agentready_lock_' . $post_id;
	}
}
