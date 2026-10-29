<?php
/**
 * Settings screen under Settings > AgentReady.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Admin;

use AgentReady\Content\PostTypes;
use AgentReady\Contracts\Hookable;
use AgentReady\Settings\Options;

defined( 'ABSPATH' ) || exit;

final class SettingsPage implements Hookable {

	public function __construct(
		private Options $options,
		private PostTypes $post_types
	) {}

	public function register(): void {}
}
