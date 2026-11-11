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

/** Locks kept as rows in the options table, using an atomic insert so two requests cannot take the same one. */
final class Lock {

	/** Seconds after which a lock counts as abandoned and can be taken over. */
	private const TTL_SECONDS = 60;

	/**
	 * Takes the generation lock of a page.
	 *
	 * @param int $post_id Page ID.
	 * @return bool True when the lock was taken, false when another request holds it.
	 */
	public function acquire( int $post_id ): bool {
		return $this->take( $this->name( $post_id ) );
	}

	/**
	 * Takes one of a fixed number of render slots, so only a few pages are rendered at the same time.
	 *
	 * @param int $max Number of slots.
	 * @return int|null The slot number, or null when every slot is in use.
	 */
	public function take_slot( int $max ): ?int {
		for ( $slot = 1; $slot <= $max; $slot++ ) {
			if ( $this->take( 'agentready_slot_' . $slot ) ) {
				return $slot;
			}
		}

		return null;
	}

	/**
	 * Gives a render slot back.
	 *
	 * @param int $slot Slot number returned by take_slot().
	 * @return void
	 */
	public function release_slot( int $slot ): void {
		global $wpdb;

		$wpdb->delete( $wpdb->options, array( 'option_name' => 'agentready_slot_' . $slot ) );
	}

	/**
	 * Takes a lock by name. A lock older than TTL_SECONDS is treated as abandoned and replaced.
	 *
	 * @param string $name Option name of the lock.
	 * @return bool True when the lock was taken.
	 */
	private function take( string $name ): bool {
		global $wpdb;

		if ( $this->insert( $name ) ) {
			return true;
		}

		$held = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );

		if ( null === $held || time() - (int) $held <= self::TTL_SECONDS ) {
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

	/**
	 * Releases the generation lock of a page.
	 *
	 * @param int $post_id Page ID.
	 * @return void
	 */
	public function release( int $post_id ): void {
		global $wpdb;

		$wpdb->delete( $wpdb->options, array( 'option_name' => $this->name( $post_id ) ) );
	}

	/**
	 * Inserts the lock row if it does not exist yet.
	 *
	 * @param string $name Option name of the lock.
	 * @return bool True when this call created the row.
	 */
	private function insert( string $name ): bool {
		global $wpdb;

		return 1 === (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} ( option_name, option_value, autoload ) VALUES ( %s, %s, 'no' ) /* LOCK */", $name, (string) time() ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Core table name.
	}

	/**
	 * Option name of the generation lock of a page.
	 *
	 * @param int $post_id Page ID.
	 * @return string Option name.
	 */
	private function name( int $post_id ): string {
		return 'agentready_lock_' . $post_id;
	}
}
