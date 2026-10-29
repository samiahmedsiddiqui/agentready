<?php
/**
 * Stores generated Markdown in the agentready_pages table.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Cache;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table.

final class WpdbStore implements Store {

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
	}

	public function delete( int $post_id ): void {
		global $wpdb;

		$wpdb->delete( Schema::table(), array( 'post_id' => $post_id ), array( '%d' ) );
	}

	public function mark_stale( int $post_id ): void {
		global $wpdb;

		$table = Schema::table();

		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET state = %s WHERE post_id = %d", Entry::STALE, $post_id ) );
	}

	public function mark_all_stale(): void {
		global $wpdb;

		$table = Schema::table();

		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET state = %s", Entry::STALE ) );
	}

	public function clear(): void {
		global $wpdb;

		$table = Schema::table();

		$wpdb->query( "DELETE FROM {$table}" );
	}

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

	public function ids( int $after, int $limit ): array {
		global $wpdb;

		$table = Schema::table();

		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$table} WHERE post_id > %d ORDER BY post_id ASC LIMIT %d", $after, $limit ) ) );
	}
}
