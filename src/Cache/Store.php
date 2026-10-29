<?php
/**
 * Storage for generated Markdown.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Cache;

defined( 'ABSPATH' ) || exit;

interface Store {

	public function get( int $post_id, bool $with_markdown = true ): ?Entry;

	public function save( Entry $entry ): void;

	public function delete( int $post_id ): void;

	public function mark_stale( int $post_id ): void;

	public function mark_all_stale(): void;

	public function clear(): void;

	/**
	 * @return array{fresh: int, stale: int}
	 */
	public function counts(): array;

	/**
	 * @return int[] Stored post IDs above $after, in order.
	 */
	public function ids( int $after, int $limit ): array;
}
