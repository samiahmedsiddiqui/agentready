<?php
/**
 * The HTML captured for a post, and how it was obtained.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Rendering;

defined( 'ABSPATH' ) || exit;

final class RenderResult {

	public const LOOPBACK = 'loopback';
	public const FALLBACK = 'fallback';

	public function __construct(
		public string $html,
		public string $source,
		public string $notice = ''
	) {}
}
