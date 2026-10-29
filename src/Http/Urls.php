<?php
/**
 * Maps posts to Markdown URLs and request paths back to posts.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Http;

use AgentReady\Settings\Options;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Urls {

	public const ROUTE_LLMS = 'llms';
	public const ROUTE_PAGE = 'page';

	public const HOME_DEFAULT = 'index.html.md';
	public const HOME_SHORT   = 'index.md';

	public function __construct( private ?Options $options = null ) {}

	public static function has_pretty_permalinks(): bool {
		return '' !== (string) get_option( 'permalink_structure' );
	}

	/**
	 * @return array{type: string, path: string, home_alias: bool}|null
	 */
	public function parse_route( string $request ): ?array {
		$request = trim( $request, '/' );

		if ( 'llms.txt' === $request ) {
			return array(
				'type'       => self::ROUTE_LLMS,
				'path'       => '',
				'home_alias' => false,
			);
		}

		if ( ! str_ends_with( $request, '.md' ) ) {
			return null;
		}

		$path = substr( $request, 0, -3 );

		if ( 1 === preg_match( '#(^|/)index\.html$#', $path ) ) {
			return $this->route( (string) preg_replace( '#(^|/)index\.html$#', '', $path ), false );
		}

		if ( 1 === preg_match( '#(^|/)index$#', $path ) ) {
			return $this->route( (string) preg_replace( '#(^|/)index$#', '', $path ), 'index' === $path );
		}

		return '' === $path ? null : $this->route( $path, false );
	}

	/**
	 * @return array{type: string, path: string, home_alias: bool}
	 */
	private function route( string $path, bool $home_alias ): array {
		return array(
			'type'       => self::ROUTE_PAGE,
			'path'       => $home_alias ? 'index' : trim( $path, '/' ),
			'home_alias' => $home_alias,
		);
	}

	/**
	 * @param string $setting   The saved homepage URL choice.
	 * @param bool   $collision Whether a real page already uses the slug "index".
	 */
	public static function home_file_name( string $setting, bool $collision ): string {
		return self::HOME_SHORT === $setting && ! $collision ? self::HOME_SHORT : self::HOME_DEFAULT;
	}

	public function home_file(): string {
		$setting = null === $this->options ? self::HOME_DEFAULT : (string) $this->options->get( 'home_markdown_url', self::HOME_DEFAULT );

		return self::home_file_name( $setting, self::HOME_SHORT === $setting && $this->index_collision() );
	}

	/**
	 * Whether a page other than the homepage has the slug "index", which would make /index.md ambiguous.
	 */
	public function index_collision(): bool {
		$post  = $this->post_from_path( 'index', true );
		$front = $this->front_page();

		return $post instanceof WP_Post && ( ! $front instanceof WP_Post || $post->ID !== $front->ID );
	}

	public function permalink( WP_Post $post ): ?string {
		if ( ! self::has_pretty_permalinks() ) {
			return null;
		}

		$url = get_permalink( $post );

		if ( ! is_string( $url ) || '' === $url || false !== strpos( $url, '?' ) ) {
			return null;
		}

		return $url;
	}

	public function markdown_url( WP_Post $post ): ?string {
		$url = $this->permalink( $post );

		if ( null === $url ) {
			return null;
		}

		$front_page = $this->front_page();

		if ( $front_page instanceof WP_Post && $front_page->ID === $post->ID ) {
			return home_url( '/' . $this->home_file() );
		}

		return untrailingslashit( $url ) . '.md';
	}

	/**
	 * @param bool $strict Require the post's own permalink to match the path exactly.
	 */
	public function resolve( string $path, bool $home_alias = false, bool $strict = true ): ?WP_Post {
		if ( '' === $path ) {
			return $this->front_page();
		}

		$post = $this->post_from_path( $path, $strict );

		if ( null === $post && $home_alias ) {
			return $this->front_page();
		}

		return $post;
	}

	public function resolve_url( string $url ): ?WP_Post {
		$parts = wp_parse_url( $url );
		$home  = wp_parse_url( home_url() );

		if ( ! is_array( $parts ) || ! is_array( $home ) ) {
			return null;
		}

		// A path such as /about/ has no host and means this site.
		$home_host = $home['host'] ?? '';

		if ( isset( $parts['host'] ) && $home_host !== $parts['host'] ) {
			return null;
		}

		$path  = $this->relative_path( (string) ( $parts['path'] ?? '' ) );
		$route = $this->parse_route( $path );

		if ( null !== $route ) {
			return self::ROUTE_PAGE === $route['type'] ? $this->resolve( $route['path'], $route['home_alias'], false ) : null;
		}

		return $this->resolve( $path, false, false );
	}

	private function front_page(): ?WP_Post {
		if ( 'page' !== get_option( 'show_on_front' ) ) {
			return null;
		}

		$id = (int) get_option( 'page_on_front' );

		return $id > 0 ? get_post( $id ) : null;
	}

	private function post_from_path( string $path, bool $strict ): ?WP_Post {
		$post = null;
		// Plugins such as Custom Permalinks only understand a path here, so try that before the full URL.
		$id = url_to_postid( $path . '/' );

		if ( $id <= 0 ) {
			$id = url_to_postid( home_url( '/' . $path . '/' ) );
		}

		if ( $id > 0 ) {
			$post = get_post( $id );
		}

		if ( ! $post instanceof WP_Post ) {
			$post = get_page_by_path( $path );
		}

		$post = apply_filters( 'agentready_resolve_post', $post, $path );

		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		$permalink = $this->permalink( $post );

		if ( null === $permalink ) {
			return null;
		}

		if ( $strict && trim( $path, '/' ) !== $this->relative_path( (string) wp_parse_url( $permalink, PHP_URL_PATH ) ) ) {
			return null;
		}

		return $post;
	}

	/**
	 * @return string|null The Markdown URL path relative to the site, for example company/about-us.md.
	 */
	public function markdown_path( WP_Post $post ): ?string {
		$url = $this->markdown_url( $post );

		return null === $url ? null : $this->relative_path( (string) wp_parse_url( $url, PHP_URL_PATH ) );
	}

	private function relative_path( string $path ): string {
		$path = trim( $path, '/' );
		$home = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );

		if ( '' !== $home && ( $path === $home || 0 === strpos( $path, $home . '/' ) ) ) {
			$path = trim( substr( $path, strlen( $home ) ), '/' );
		}

		return $path;
	}
}
