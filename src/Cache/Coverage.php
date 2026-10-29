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

final class Coverage {

	public const LIMIT = 500;

	public function __construct(
		private Store $store,
		private Candidates $candidates,
		private PostTypes $post_types,
		private Eligibility $eligibility
	) {}

	/**
	 * @return array{fresh: int, stale: int, missing: int, capped: bool}
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
