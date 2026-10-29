<?php
/**
 * Adds the rel="alternate" Markdown link to the page head.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Http;

use AgentReady\Content\Eligibility;
use AgentReady\Contracts\Hookable;

defined( 'ABSPATH' ) || exit;

final class DiscoveryTags implements Hookable {

	public function __construct( private Eligibility $eligibility ) {}

	public function register(): void {}
}
