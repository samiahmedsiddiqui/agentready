<?php
/**
 * Regenerates Markdown when ACF field values or options pages are saved.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Integrations;

use AgentReady\Contracts\Hookable;

defined( 'ABSPATH' ) || exit;

final class Acf implements Hookable {

	public function register(): void {}
}
