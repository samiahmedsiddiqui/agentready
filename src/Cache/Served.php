<?php
/**
 * A stored copy chosen to answer a request, and how it was obtained.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Cache;

defined( 'ABSPATH' ) || exit;

/** A stored copy chosen to answer a request, with how it was obtained. */
final class Served {

	/** The copy was current and served as is. */
	public const HIT = 'HIT';
	/** An out-of-date copy was served while a refresh is queued. */
	public const STALE = 'STALE';
	/** No usable copy existed, so one was generated for this request. */
	public const MISS = 'MISS';

	/**
	 * @param Entry  $entry The copy to send.
	 * @param string $cache One of HIT, STALE or MISS, sent in the X-AgentWelcome-Cache header.
	 */
	public function __construct(
		public Entry $entry,
		public string $cache
	) {}
}
