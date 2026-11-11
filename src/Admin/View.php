<?php
/**
 * Loads admin templates from the views folder.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Admin;

defined( 'ABSPATH' ) || exit;

/** Loads the admin templates. */
final class View {

	/**
	 * Includes a template. The template reads its values from $vars.
	 *
	 * @param string               $name Template file name without .php, from the views folder.
	 * @param array<string, mixed> $vars Values the template reads as $vars.
	 * @return void
	 */
	public static function render( string $name, array $vars = array() ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Templates read $vars.
		$file = AGENTWELCOME_PATH . 'src/Admin/views/' . $name . '.php';

		if ( is_readable( $file ) ) {
			include $file;
		}
	}
}
