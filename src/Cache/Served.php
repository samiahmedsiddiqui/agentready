<?php
/**
 * A stored copy chosen to answer a request, and how it was obtained.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Cache;

defined( 'ABSPATH' ) || exit;

final class Served {

	public const HIT   = 'HIT';
	public const STALE = 'STALE';
	public const MISS  = 'MISS';

	public function __construct(
		public Entry $entry,
		public string $cache
	) {}
}
