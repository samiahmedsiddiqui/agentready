<?php
/**
 * Clears cached Markdown when content changes.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Cache;

use AgentReady\Contracts\Hookable;

defined( 'ABSPATH' ) || exit;

final class Invalidator implements Hookable {

	public function __construct( private Store $store ) {}

	public function register(): void {}
}
