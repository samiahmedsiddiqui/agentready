<?php
/**
 * Regenerates Markdown when an Elementor document is saved.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Integrations;

use AgentReady\Contracts\Hookable;

defined( 'ABSPATH' ) || exit;

final class Elementor implements Hookable {

	public function register(): void {}
}
