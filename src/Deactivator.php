<?php
/**
 * Runs on plugin deactivation.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady;

defined( 'ABSPATH' ) || exit;

final class Deactivator {

	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
