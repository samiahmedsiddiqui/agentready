<?php
/**
 * WP-CLI commands: wp agentready regenerate | flush.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Cli;

use AgentReady\Cache\Store;
use AgentReady\Contracts\Hookable;

defined( 'ABSPATH' ) || exit;

final class Command implements Hookable {

	public function __construct( private Store $store ) {}

	public function register(): void {}
}
