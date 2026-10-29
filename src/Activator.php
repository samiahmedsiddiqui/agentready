<?php
/**
 * Runs on plugin activation.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady;

defined( 'ABSPATH' ) || exit;

final class Activator {

	public static function activate(): void {
		update_option( 'agentready_flush_rewrite', 1 );
	}
}
