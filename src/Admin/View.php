<?php
/**
 * Loads admin templates from the views folder.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Admin;

defined( 'ABSPATH' ) || exit;

final class View {

	/**
	 * @param array<string, mixed> $vars Values the template reads as $vars.
	 */
	public static function render( string $name, array $vars = array() ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Templates read $vars.
		$file = AGENTREADY_PATH . 'src/Admin/views/' . $name . '.php';

		if ( is_readable( $file ) ) {
			include $file;
		}
	}
}
