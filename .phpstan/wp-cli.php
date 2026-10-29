<?php
/**
 * Minimal WP-CLI declarations for static analysis.
 *
 * @package AgentReady
 */

namespace {
	class WP_CLI {
		public static function add_command( string $name, $callable, array $args = array() ): bool {}
		public static function success( string $message ): void {}
		public static function error( $message, $exit = true ): void {}
		public static function warning( string $message ): void {}
		public static function log( string $message ): void {}
		public static function debug( string $message, $group = false ): void {}
	}
}

namespace WP_CLI\Utils {
	function make_progress_bar( string $message, int $count, int $interval = 100 ) {}
}
