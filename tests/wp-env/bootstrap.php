<?php
/**
 * Bootstrap for the tests that run on a real WordPress and a real database inside wp-env.
 *
 * @package AgentWelcome
 */

$agentwelcome_tests_dir = getenv( 'WP_PHPUNIT__DIR' );

if ( ! $agentwelcome_tests_dir ) {
	$agentwelcome_tests_dir = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : '/wordpress-phpunit';
}

if ( getenv( 'WP_PHPUNIT__TESTS_CONFIG' ) && ! defined( 'WP_TESTS_CONFIG_FILE_PATH' ) ) {
	define( 'WP_TESTS_CONFIG_FILE_PATH', getenv( 'WP_PHPUNIT__TESTS_CONFIG' ) );
}

if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ? getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) : dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills' );
}

require_once $agentwelcome_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		require dirname( __DIR__, 2 ) . '/agentwelcome.php';
	}
);

require $agentwelcome_tests_dir . '/includes/bootstrap.php';
