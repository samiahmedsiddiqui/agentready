<?php
/**
 * Opt-in robots.txt allow rules for AI crawlers (GPTBot, ClaudeBot, PerplexityBot, Google-Extended).
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Robots;

use AgentReady\Contracts\Hookable;
use AgentReady\Settings\Options;

defined( 'ABSPATH' ) || exit;

final class RobotsTxt implements Hookable {

	public function __construct( private Options $options ) {}

	public function register(): void {}
}
