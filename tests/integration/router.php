<?php
$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$file = __DIR__ . '/wp' . $path;
if ( '/' !== $path && is_file( $file ) && ! str_ends_with( $path, '.php' ) ) {
	return false;
}
if ( str_ends_with( $path, '.php' ) && is_file( $file ) ) {
	chdir( dirname( $file ) );
	$_SERVER['SCRIPT_NAME'] = $path;
	$_SERVER['SCRIPT_FILENAME'] = $file;
	require $file;
	return true;
}
chdir( __DIR__ . '/wp' );
unset( $_SERVER['PATH_INFO'], $_SERVER['ORIG_PATH_INFO'] );
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/wp/index.php';
require __DIR__ . '/wp/index.php';
