<?php
/**
 * Storage for generated Markdown.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Cache;

defined( 'ABSPATH' ) || exit;

/** Storage for the generated Markdown of pages. */
interface Store {

	/**
	 * Reads the stored copy of a page.
	 *
	 * @param int  $post_id       Page ID.
	 * @param bool $with_markdown False to skip loading the Markdown text, for callers that only need the state.
	 * @return Entry|null The copy, or null when none is stored.
	 */
	public function get( int $post_id, bool $with_markdown = true ): ?Entry;

	/**
	 * Stores a copy, replacing any earlier one for the same page.
	 *
	 * @param Entry $entry The copy to store.
	 * @return void
	 */
	public function save( Entry $entry ): void;

	/**
	 * Removes the stored copy of a page.
	 *
	 * @param int $post_id Page ID.
	 * @return void
	 */
	public function delete( int $post_id ): void;

	/**
	 * Marks the copy of one page as out of date.
	 *
	 * @param int $post_id Page ID.
	 * @return void
	 */
	public function mark_stale( int $post_id ): void;

	/**
	 * Marks every stored copy as out of date.
	 *
	 * @return void
	 */
	public function mark_all_stale(): void;

	/**
	 * Removes every stored copy.
	 *
	 * @return void
	 */
	public function clear(): void;

	/**
	 * Counts the stored copies by state.
	 *
	 * @return array{fresh: int, stale: int} Copies per state.
	 */
	public function counts(): array;

	/**
	 * Lists the pages that have a stored copy.
	 *
	 * @param int $after Only IDs above this one.
	 * @param int $limit Largest number of IDs to return.
	 * @return int[] Page IDs in ascending order.
	 */
	public function ids( int $after, int $limit ): array;
}
