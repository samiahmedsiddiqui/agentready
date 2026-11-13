<?php
add_action( 'init', function () {
	register_post_type( 'book', array( 'label' => 'Books', 'public' => true, 'has_archive' => true, 'supports' => array( 'title', 'editor' ), 'rewrite' => array( 'slug' => 'book' ) ) );
} );
if ( 'cli' !== PHP_SAPI && file_exists( WP_CONTENT_DIR . '/basic-auth.flag' ) ) {
	$user = $_SERVER['PHP_AUTH_USER'] ?? '';
	$pass = $_SERVER['PHP_AUTH_PW'] ?? '';
	if ( 'site' !== $user || 'secret' !== $pass ) {
		header( 'WWW-Authenticate: Basic realm="Staging"' );
		header( 'HTTP/1.1 401 Unauthorized' );
		exit( 'Authentication required' );
	}
}
add_filter( 'wp_is_application_passwords_available', '__return_true' );
