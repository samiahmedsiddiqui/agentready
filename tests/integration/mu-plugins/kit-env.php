<?php
// Test kit: reads wp-content/kit-env.json to define environment constants and a no-loopback switch.
$kit_file = WP_CONTENT_DIR . '/kit-env.json';
if ( is_readable( $kit_file ) ) {
	$kit = json_decode( (string) file_get_contents( $kit_file ), true );
	if ( isset( $kit['type'] ) ) {
		define( 'WP_ENVIRONMENT_TYPE', $kit['type'] );
	}
	if ( is_array( $kit ) && array_key_exists( 'enabled', $kit ) ) {
		define( 'AGENTWELCOME_ENABLED', $kit['enabled'] );
	}
	if ( isset( $kit['urls'] ) ) {
		define( 'AGENTWELCOME_ALLOWED_URLS', $kit['urls'] );
	}
	if ( ! empty( $kit['fake_seo'] ) ) {
		$kit_throw = 'throw' === $kit['fake_seo'];
		if ( ! function_exists( 'wpseo_replace_vars' ) ) {
			function wpseo_replace_vars( $text, $post ) {
				global $kit_throw;
				if ( $kit_throw ) {
					throw new RuntimeException( 'broken resolver' );
				}
				return str_replace( '%%sitename%%', 'Kit Site', $text );
			}
			function aioseo() {
				return (object) array( 'tags' => new class() {
					public function replaceTags( $text, $id ) {
						return str_replace( '#site_title', 'Kit Site', $text );
					}
				} );
			}
			function seopress_get_service( $name ) {
				return new class() {
					public function replace( $text, $context ) {
						return str_replace( '%%sitetitle%%', 'Kit Site', $text );
					}
				};
			}
			eval( 'namespace RankMath; class Helper { public static function replace_vars( $text, $post ) { return str_replace( "%sitename%", "Kit Site", $text ); } }' );
		}
	}
	if ( ! empty( $kit['volatile'] ) ) {
		add_filter( 'the_content', static fn( $content ) => $content . '<p>Rendered at ' . gmdate( 'H:i:s' ) . ' UTC.</p>' );
	}
	if ( ! empty( $kit['no_address_check'] ) ) {
		add_filter( 'agentwelcome_check_site_address', '__return_false' );
	}
	if ( ! empty( $kit['no_cron'] ) ) {
		define( 'DISABLE_WP_CRON', true );
	}
	if ( ! empty( $kit['compare_ignore'] ) ) {
		add_filter( 'agentwelcome_compare_ignore', static fn() => (array) $kit['compare_ignore'] );
	}
	if ( ! empty( $kit['llms_full_max'] ) ) {
		add_filter( 'agentwelcome_llms_full_max_bytes', static fn() => (int) $kit['llms_full_max'] );
	}
	if ( ! empty( $kit['noindex_all'] ) ) {
		add_filter( 'agentwelcome_noindex_sources', static fn( $sources ) => array_fill_keys( array_keys( $sources ), true ) );
	}
	if ( ! empty( $kit['files_cap'] ) ) {
		add_filter( 'agentwelcome_files_capability', static fn() => (string) $kit['files_cap'] );
	}
	if ( ! empty( $kit['no_loopback'] ) ) {
		add_filter( 'agentwelcome_use_loopback', '__return_false' );
	}
}
