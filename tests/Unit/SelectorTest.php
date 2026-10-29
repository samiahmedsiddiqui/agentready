<?php
/**
 * Tests for the CSS to XPath compiler.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Tests\Unit;

use AgentReady\Rendering\Selector;
use PHPUnit\Framework\TestCase;

final class SelectorTest extends TestCase {

	/**
	 * @dataProvider selectors
	 *
	 * @param string[] $expected XPath list.
	 */
	public function test_compiles( string $selector, array $expected ): void {
		$this->assertSame( $expected, ( new Selector() )->to_xpaths( $selector ) );
	}

	/**
	 * @return array<string, array{string, string[]}>
	 */
	public function selectors(): array {
		return array(
			'tag'                  => array( 'main', array( '//main' ) ),
			'uppercase tag'        => array( 'MAIN', array( '//main' ) ),
			'id'                   => array( '#content', array( "//*[@id='content']" ) ),
			'tag and id'           => array( 'div#content', array( "//div[@id='content']" ) ),
			'class'                => array( '.site-content', array( "//*[contains(concat(' ', normalize-space(@class), ' '), ' site-content ')]" ) ),
			'attribute value'      => array( '[role="main"]', array( "//*[@role='main']" ) ),
			'attribute exists'     => array( '[hidden]', array( '//*[@hidden]' ) ),
			'attribute prefix'     => array( 'a[href^="/x"]', array( "//a[starts-with(@href, '/x')]" ) ),
			'descendant'           => array( '.a article', array( "//*[contains(concat(' ', normalize-space(@class), ' '), ' a ')]//article" ) ),
			'child'                => array( 'ul > li', array( '//ul/li' ) ),
			'child without spaces' => array( 'ul>li', array( '//ul/li' ) ),
			'list keeps order'     => array( 'main, [role="main"], #content', array( '//main', "//*[@role='main']", "//*[@id='content']" ) ),
			'comma inside value'   => array( '[data-x="a,b"], p', array( "//*[@data-x='a,b']", '//p' ) ),
			'unsupported skipped'  => array( 'p:first-child, main', array( '//main' ) ),
			'empty'                => array( '', array() ),
		);
	}
}
