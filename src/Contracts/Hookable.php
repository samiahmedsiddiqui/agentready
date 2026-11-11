<?php
/**
 * A component that attaches itself to WordPress hooks.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Contracts;

defined( 'ABSPATH' ) || exit;

/** Part of the plugin that attaches its own WordPress hooks. */
interface Hookable {

	/**
	 * Adds the actions and filters this component needs.
	 *
	 * @return void
	 */
	public function register(): void;
}
