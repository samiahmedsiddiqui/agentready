<?php
/**
 * Settings tab that shows the generated Markdown and eligibility for any page URL.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Admin;

use AgentReady\Content\Eligibility;
use AgentReady\Contracts\Hookable;
use AgentReady\Rendering\Generator;

defined( 'ABSPATH' ) || exit;

final class PreviewTool implements Hookable {

	public function __construct(
		private Eligibility $eligibility,
		private Generator $generator
	) {}

	public function register(): void {}
}
