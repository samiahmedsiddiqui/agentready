<?php
/**
 * Rewrite rules and request handling for /llms.txt, /llms-full.txt and .md URLs.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Http;

use AgentReady\Content\Eligibility;
use AgentReady\Contracts\Hookable;

defined( 'ABSPATH' ) || exit;

final class Router implements Hookable {

	public function __construct( private Eligibility $eligibility ) {}

	public function register(): void {}
}
