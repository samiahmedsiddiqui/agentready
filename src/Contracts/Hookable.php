<?php
/**
 * A component that attaches itself to WordPress hooks.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Contracts;

defined( 'ABSPATH' ) || exit;

interface Hookable {

	public function register(): void;
}
