<?php
/**
 * Finds the posts that should have a stored copy.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Cache;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, WordPress.DB.PreparedSQLPlaceholders, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic IN list over core and plugin tables.

final class Candidates {

	/**
	 * @param string[] $types Enabled post types.
	 * @return int[] Published post IDs above $after.
	 */
	public function after( array $types, int $after, int $limit ): array {
		global $wpdb;

		if ( empty( $types ) ) {
			return array();
		}

		$in = implode( ', ', array_fill( 0, count( $types ), '%s' ) );

		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ( {$in} ) AND ID > %d ORDER BY ID ASC LIMIT %d", array_merge( $types, array( $after, $limit ) ) ) ) );
	}

	/**
	 * @param string[] $types Enabled post types.
	 * @return int[] Published, unprotected post IDs with no stored copy.
	 */
	public function missing( array $types, int $limit ): array {
		global $wpdb;

		if ( empty( $types ) ) {
			return array();
		}

		$table = Schema::table();
		$in    = implode( ', ', array_fill( 0, count( $types ), '%s' ) );

		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN {$table} c ON c.post_id = p.ID WHERE c.post_id IS NULL AND p.post_status = 'publish' AND p.post_password = '' AND p.post_type IN ( {$in} ) ORDER BY p.ID ASC LIMIT %d", array_merge( $types, array( $limit ) ) ) ) );
	}
}
