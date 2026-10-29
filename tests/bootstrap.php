<?php
/**
 * PHPUnit bootstrap for the unit tests, which run without WordPress.
 *
 * @package AgentReady
 */

define( 'ABSPATH', __DIR__ . '/' );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value, $flags = 0 ) {
		return json_encode( $value, $flags );
	}
}
