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

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

$GLOBALS['agentready_test_transients'] = array();

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $name ) {
		return $GLOBALS['agentready_test_transients'][ $name ] ?? false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $name, $value, $expiration = 0 ) {
		$GLOBALS['agentready_test_transients'][ $name ] = $value;

		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $name ) {
		unset( $GLOBALS['agentready_test_transients'][ $name ] );

		return true;
	}
}
