<?php
/**
 * Tests for request routing and URL resolving.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Tests\Unit;

use AgentReady\Http\Urls;
use AgentReady\Rendering\UrlResolver;
use PHPUnit\Framework\TestCase;

final class UrlsTest extends TestCase {

	/**
	 * @dataProvider routes
	 *
	 * @param array{type: string, path: string, home_alias: bool}|null $expected Expected route.
	 */
	public function test_parses_route( string $request, ?array $expected ): void {
		$this->assertSame( $expected, ( new Urls() )->parse_route( $request ) );
	}

	/**
	 * @return array<string, array{string, array{type: string, path: string, home_alias: bool}|null}>
	 */
	public function routes(): array {
		$page = static function ( string $path, bool $alias = false ): array {
			return array(
				'type'       => Urls::ROUTE_PAGE,
				'path'       => $path,
				'home_alias' => $alias,
			);
		};

		return array(
			'llms.txt'             => array(
				'llms.txt',
				array(
					'type'       => Urls::ROUTE_LLMS,
					'path'       => '',
					'home_alias' => false,
				),
			),
			'page'                 => array( 'about.md', $page( 'about' ) ),
			'nested page'          => array( 'services/web.md', $page( 'services/web' ) ),
			'slashes trimmed'      => array( '/about.md/', $page( 'about' ) ),
			'home index.html.md'   => array( 'index.html.md', $page( '' ) ),
			'home index.md'        => array( 'index.md', $page( 'index', true ) ),
			'directory index.html' => array( 'about/index.html.md', $page( 'about' ) ),
			'directory index'      => array( 'about/index.md', $page( 'about' ) ),
			'html permalink'       => array( 'legacy.html.md', $page( 'legacy.html' ) ),
			'plain page'           => array( 'about', null ),
			'bare extension'       => array( '.md', null ),
			'other file'           => array( 'readme.txt', null ),
			'not built yet'        => array( 'llms-full.txt', null ),
		);
	}

	/**
	 * @dataProvider urls
	 */
	public function test_resolves_urls( string $url, string $expected ): void {
		$this->assertSame( $expected, UrlResolver::resolve( 'https://example.com/a/b/', $url ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public function urls(): array {
		return array(
			'root relative'     => array( '/x', 'https://example.com/x' ),
			'parent'            => array( '../c', 'https://example.com/a/c' ),
			'sibling'           => array( 'c', 'https://example.com/a/b/c' ),
			'dot segment'       => array( './d?x=1#h', 'https://example.com/a/b/d?x=1#h' ),
			'fragment'          => array( '#f', 'https://example.com/a/b/#f' ),
			'query'             => array( '?q=1', 'https://example.com/a/b/?q=1' ),
			'protocol relative' => array( '//cdn.test/x', 'https://cdn.test/x' ),
			'absolute'          => array( 'https://other.test/p', 'https://other.test/p' ),
			'mailto'            => array( 'mailto:a@b.co', 'mailto:a@b.co' ),
			'javascript'        => array( 'javascript:alert(1)', '' ),
			'data'              => array( 'data:text/html,x', '' ),
			'empty'             => array( '', '' ),
		);
	}
}
