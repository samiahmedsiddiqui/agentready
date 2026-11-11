<?php
/**
 * Maps posts to Markdown URLs and request paths back to posts.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Http;

use AgentWelcome\Settings\Options;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/** Builds and reads the addresses of the Markdown pages and llms files. */
final class Urls {

	/** Route type of /llms.txt. */
	public const ROUTE_LLMS = 'llms';
	/** Route type of /llms-full.txt. */
	public const ROUTE_LLMS_FULL = 'llms_full';
	/** Route type of the Markdown version of a page. */
	public const ROUTE_PAGE = 'page';

	/** File name of the Markdown homepage that cannot clash with a page. */
	public const HOME_LONG = 'index.html.md';
	/** Shorter file name of the Markdown homepage. */
	public const HOME_SHORT = 'index.md';
	/** File name used unless the settings say otherwise. */
	public const HOME_DEFAULT = self::HOME_SHORT;

	/**
	 * @param Options|null $options Saved settings. Without them the default homepage file name is used.
	 */
	public function __construct( private ?Options $options = null ) {}

	/**
	 * Whether the site uses pretty permalinks. The Markdown addresses need them.
	 *
	 * @return bool True when they are on.
	 */
	public static function has_pretty_permalinks(): bool {
		return '' !== (string) get_option( 'permalink_structure' );
	}

	/**
	 * Works out what a request path asks for.
	 *
	 * @param string $request Request path.
	 * @return array{type: string, path: string, home_alias: bool}|null The route, or null when the path is not one of ours.
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

		if ( 'llms-full.txt' === $request ) {
			return array(
				'type'       => self::ROUTE_LLMS_FULL,
				'path'       => '',
				'home_alias' => false,
			);
		}

		if ( ! str_ends_with( $request, '.md' ) ) {
			return null;
		}

		$path = substr( $request, 0, -3 );

		if ( 1 === preg_match( '#(^|/)index\.html$#', $path ) ) {
			return $this->page_route( (string) preg_replace( '#(^|/)index\.html$#', '', $path ), false );
		}

		if ( 1 === preg_match( '#(^|/)index$#', $path ) ) {
			return $this->page_route( (string) preg_replace( '#(^|/)index$#', '', $path ), 'index' === $path );
		}

		return '' === $path ? null : $this->page_route( $path, false );
	}

	/**
	 * Builds the route of a page.
	 *
	 * @param string $path       Page path without the .md ending.
	 * @param bool   $home_alias True when the path was /index.md, which can also mean the homepage.
	 * @return array{type: string, path: string, home_alias: bool} The route.
	 */
	private function page_route( string $path, bool $home_alias ): array {
		return array(
			'type'       => self::ROUTE_PAGE,
			'path'       => $home_alias ? 'index' : trim( $path, '/' ),
			'home_alias' => $home_alias,
		);
	}

	/**
	 * Chooses the file name of the Markdown homepage.
	 *
	 * @param string $setting   The saved choice.
	 * @param bool   $collision Whether a real page already uses the slug "index".
	 * @return string File name.
	 */
	public static function home_file_name( string $setting, bool $collision ): string {
		return self::HOME_LONG === $setting || $collision ? self::HOME_LONG : self::HOME_SHORT;
	}

	/**
	 * File name of the Markdown homepage.
	 *
	 * @return string File name.
	 */
	public function home_file(): string {
		$setting = null === $this->options ? self::HOME_DEFAULT : (string) $this->options->get( 'home_markdown_url', self::HOME_DEFAULT );

		return self::home_file_name( $setting, self::HOME_LONG !== $setting && $this->index_collision() );
	}

	/**
	 * Whether a page other than the homepage has the slug "index", which would make /index.md ambiguous.
	 *
	 * @return bool True when one does.
	 */
	public function index_collision(): bool {
		$post  = $this->post_from_path( 'index', true );
		$front = $this->front_page();

		return $post instanceof WP_Post && ( ! $front instanceof WP_Post || $post->ID !== $front->ID );
	}

	/**
	 * Pretty address of a page.
	 *
	 * @param WP_Post $post The page.
	 * @return string|null The address, or null when the page has only a plain one.
	 */
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

	/**
	 * Address of the Markdown version of a page.
	 *
	 * @param WP_Post $post The page.
	 * @return string|null The address, or null when the page has no pretty address.
	 */
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
	 * Finds the page that a path belongs to.
	 *
	 * @param string $path       Page path without the .md ending.
	 * @param bool   $home_alias True to fall back to the homepage, for /index.md.
	 * @param bool   $strict     Require the page's own address to match the path exactly.
	 * @return WP_Post|null The page, or null when none matches.
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

	/**
	 * Finds the page that an address belongs to.
	 *
	 * @param string $url Full address or path, with or without .md.
	 * @return WP_Post|null The page, or null when none matches or it is on another site.
	 */
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

	/**
	 * The page that is set as the static homepage.
	 *
	 * @return WP_Post|null The page, or null when the homepage shows posts.
	 */
	private function front_page(): ?WP_Post {
		if ( 'page' !== get_option( 'show_on_front' ) ) {
			return null;
		}

		$id = (int) get_option( 'page_on_front' );

		return $id > 0 ? get_post( $id ) : null;
	}

	/**
	 * Looks up a page by its path.
	 *
	 * @param string $path   Page path.
	 * @param bool   $strict Require the page's own address to match the path exactly.
	 * @return WP_Post|null The page, or null when none matches.
	 */
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

		$post = apply_filters( 'agentwelcome_resolve_post', $post, $path );

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
	 * Path of the Markdown version of a page, relative to the site, such as company/about-us.md.
	 *
	 * @param WP_Post $post The page.
	 * @return string|null The path, or null when the page has no pretty address.
	 */
	public function markdown_path( WP_Post $post ): ?string {
		$url = $this->markdown_url( $post );

		return null === $url ? null : $this->relative_path( (string) wp_parse_url( $url, PHP_URL_PATH ) );
	}

	/**
	 * Removes the folder of the site from a path.
	 *
	 * @param string $path Path.
	 * @return string Path without the folder and slashes at the ends.
	 */
	private function relative_path( string $path ): string {
		$path = trim( $path, '/' );
		$home = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );

		if ( '' !== $home && ( $path === $home || 0 === strpos( $path, $home . '/' ) ) ) {
			$path = trim( substr( $path, strlen( $home ) ), '/' );
		}

		return $path;
	}
}
