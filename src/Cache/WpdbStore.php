<?php
/**
 * Stores generated Markdown in the agentwelcome_pages table.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Cache;

use AgentWelcome\Index\OutputCache;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table.

/** Store backed by the agentwelcome_pages table. The cached llms files are cleared whenever stored copies change. */
final class WpdbStore implements Store {

	/**
	 * @param int  $post_id       Page ID.
	 * @param bool $with_markdown False to leave the Markdown text out of the query.
	 * @return Entry|null The stored copy, or null when none exists.
	 */
	public function get( int $post_id, bool $with_markdown = true ): ?Entry {
		global $wpdb;

		$table   = Schema::table();
		$columns = $with_markdown ? '*' : "post_id, '' AS markdown, content_hash, source, generator_version, generated_at, state";
		$row     = $wpdb->get_row( $wpdb->prepare( "SELECT {$columns} FROM {$table} WHERE post_id = %d", $post_id ), ARRAY_A );

		if ( ! is_array( $row ) ) {
			return null;
		}

		return new Entry(
			(int) $row['post_id'],
			(string) $row['markdown'],
			(string) $row['content_hash'],
			(string) $row['source'],
			(string) $row['generator_version'],
			(string) $row['generated_at'],
			(string) $row['state']
		);
	}

	/**
	 * @param Entry $entry Copy to store, replacing any earlier one.
	 * @return void
	 */
	public function save( Entry $entry ): void {
		global $wpdb;

		$wpdb->replace(
			Schema::table(),
			array(
				'post_id'           => $entry->post_id,
				'markdown'          => $entry->markdown,
				'content_hash'      => $entry->content_hash,
				'source'            => $entry->source,
				'generator_version' => $entry->generator_version,
				'generated_at'      => $entry->generated_at,
				'state'             => $entry->state,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		OutputCache::purge();
	}

	/**
	 * @param int $post_id Page ID.
	 * @return void
	 */
	public function delete( int $post_id ): void {
		global $wpdb;

		$wpdb->delete( Schema::table(), array( 'post_id' => $post_id ), array( '%d' ) );
		OutputCache::purge();
	}

	/**
	 * @param int $post_id Page ID.
	 * @return void
	 */
	public function mark_stale( int $post_id ): void {
		global $wpdb;

		$table = Schema::table();

		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET state = %s WHERE post_id = %d", Entry::STALE, $post_id ) );
	}

	/**
	 * @return void
	 */
	public function mark_all_stale(): void {
		global $wpdb;

		$table = Schema::table();

		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET state = %s", Entry::STALE ) );
	}

	/**
	 * @return void
	 */
	public function clear(): void {
		global $wpdb;

		$table = Schema::table();

		$wpdb->query( "DELETE FROM {$table}" );
		OutputCache::purge();
	}

	/**
	 * @return array{fresh: int, stale: int} Copies per state.
	 */
	public function counts(): array {
		global $wpdb;

		$table  = Schema::table();
		$rows   = (array) $wpdb->get_results( "SELECT state, COUNT(*) AS total FROM {$table} GROUP BY state", ARRAY_A );
		$counts = array(
			'fresh' => 0,
			'stale' => 0,
		);

		foreach ( $rows as $row ) {
			$counts[ Entry::STALE === $row['state'] ? 'stale' : 'fresh' ] += (int) $row['total'];
		}

		return $counts;
	}

	/**
	 * @param int $after Only IDs above this one.
	 * @param int $limit Largest number of IDs to return.
	 * @return int[] Page IDs in ascending order.
	 */
	public function ids( int $after, int $limit ): array {
		global $wpdb;

		$table = Schema::table();

		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$table} WHERE post_id > %d ORDER BY post_id ASC LIMIT %d", $after, $limit ) ) );
	}
}
