<?php
/**
 * A stored Markdown copy of one page.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Cache;

defined( 'ABSPATH' ) || exit;

/** Stored Markdown of one page, as kept in the agentwelcome_pages table. */
final class Entry {

	/** The copy matches the page as it was last generated. */
	public const FRESH = 'fresh';
	/** The page changed since the copy was generated. */
	public const STALE = 'stale';

	/**
	 * @param int    $post_id           ID of the page the copy belongs to.
	 * @param string $markdown          Markdown including the front matter block.
	 * @param string $content_hash      Hash of the page content the copy was built from.
	 * @param string $source            Where the content came from: the rendered page or the post content.
	 * @param string $generator_version Plugin version that generated the copy.
	 * @param string $generated_at      GMT time of generation, in MySQL format.
	 * @param string $state             Either FRESH or STALE.
	 */
	public function __construct(
		public int $post_id,
		public string $markdown,
		public string $content_hash,
		public string $source,
		public string $generator_version,
		public string $generated_at,
		public string $state
	) {}

	/**
	 * Time the copy was generated.
	 *
	 * @return int Unix timestamp.
	 */
	public function timestamp(): int {
		return (int) strtotime( $this->generated_at . ' UTC' );
	}
}
