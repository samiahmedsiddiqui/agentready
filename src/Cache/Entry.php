<?php
/**
 * A stored Markdown copy of one page.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Cache;

defined( 'ABSPATH' ) || exit;

final class Entry {

	public const FRESH = 'fresh';
	public const STALE = 'stale';

	public function __construct(
		public int $post_id,
		public string $markdown,
		public string $content_hash,
		public string $source,
		public string $generator_version,
		public string $generated_at,
		public string $state
	) {}

	public function timestamp(): int {
		return (int) strtotime( $this->generated_at . ' UTC' );
	}
}
