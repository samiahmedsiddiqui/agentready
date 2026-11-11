<?php
/**
 * Counts of fresh, stale and not yet generated pages for the Status card.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Cache;

use AgentReady\Content\Eligibility;
use AgentReady\Content\PostTypes;

defined( 'ABSPATH' ) || exit;

/** Counts the pages by state of their stored copy, including pages that have none yet. */
final class Coverage {

	/** Most pages without a copy that are checked; above this the count is reported as a minimum. */
	public const LIMIT = 500;

	/**
	 * @param Store       $store       Stored copies.
	 * @param Candidates  $candidates  Source of pages with no copy.
	 * @param PostTypes   $post_types   Post types that are served.
	 * @param Eligibility $eligibility Decides whether a page is served.
	 */
	public function __construct(
		private Store $store,
		private Candidates $candidates,
		private PostTypes $post_types,
		private Eligibility $eligibility
	) {}

	/**
	 * Counts fresh, stale and missing copies.
	 *
	 * @return array{fresh: int, stale: int, missing: int, capped: bool} Counts, and whether the missing count hit LIMIT.
	 */
	public function summary(): array {
		$counts  = $this->store->counts();
		$ids     = $this->candidates->missing( $this->post_types->enabled(), self::LIMIT );
		$missing = 0;

		foreach ( $ids as $id ) {
			$post = get_post( $id );

			if ( $post instanceof \WP_Post && $this->eligibility->is_eligible( $post ) ) {
				++$missing;
			}
		}

		return array(
			'fresh'   => $counts['fresh'],
			'stale'   => $counts['stale'],
			'missing' => $missing,
			'capped'  => count( $ids ) >= self::LIMIT,
		);
	}
}
