<?php
/**
 * The outcome of checking one page against its stored copy.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Verify;

use AgentReady\Cache\Entry;
use AgentReady\Rendering\GeneratedPage;

defined( 'ABSPATH' ) || exit;

/** Outcome of comparing a stored copy with what the page produces now. */
final class VerifyResult {

	/** The stored copy matches the page. */
	public const IN_SYNC = 'in_sync';
	/** The stored copy is different from the page. */
	public const DIFFERS = 'differs';
	/** The page has no stored copy yet. */
	public const NOT_STORED = 'not_stored';
	/** The page is not served, so there is nothing to compare. */
	public const NOT_SERVED = 'not_served';

	/**
	 * @param string                                                $state    One of the state constants.
	 * @param string                                                $reason   Why the page is not served, for NOT_SERVED.
	 * @param Entry|null                                            $entry    The stored copy, when there is one.
	 * @param GeneratedPage|null                                    $page     The Markdown the page produces now.
	 * @param array<int, array{type: string, text: string}>         $diff     Stored against fresh Markdown, line by line.
	 * @param int                                                   $changed  Number of changed lines in the diff.
	 * @param array{total: int, found: int, missing: string[]}|null $coverage Page text found in the Markdown.
	 * @param string[]                                              $excluded Text blocks removed by the exclusions.
	 */
	public function __construct(
		public string $state,
		public string $reason = '',
		public ?Entry $entry = null,
		public ?GeneratedPage $page = null,
		public array $diff = array(),
		public int $changed = 0,
		public ?array $coverage = null,
		public array $excluded = array()
	) {}
}
