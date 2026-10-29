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

final class VerifyResult {

	public const IN_SYNC    = 'in_sync';
	public const DIFFERS    = 'differs';
	public const NOT_STORED = 'not_stored';
	public const NOT_SERVED = 'not_served';

	/**
	 * @param array<int, array{type: string, text: string}> $diff     Stored against fresh Markdown.
	 * @param array{total: int, found: int, missing: string[]}|null $coverage Page text found in the Markdown.
	 * @param string[]                                      $excluded Text blocks removed by the exclusions.
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
