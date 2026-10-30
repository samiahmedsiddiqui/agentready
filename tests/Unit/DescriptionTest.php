<?php
/**
 * Tests for spotting template tags left in an SEO description.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Tests\Unit;

use AgentReady\Content\Description;
use PHPUnit\Framework\TestCase;

final class DescriptionTest extends TestCase {

	/**
	 * @dataProvider texts
	 */
	public function test_detects_unresolved_tags( string $text, bool $expected ): void {
		$this->assertSame( $expected, Description::has_unresolved_tags( $text ) );
	}

	/**
	 * @return array<string, array{string, bool}>
	 */
	public function texts(): array {
		return array(
			'plain text'                    => array( 'Welcome to our contact page.', false ),
			'Yoast variable'                => array( 'Welcome to %%sitename%%.', true ),
			'Yoast variable with a filter'  => array( '%%title%% %%sep%% %%sitename%%', true ),
			'Rank Math variable'            => array( 'Welcome to %sitename%.', true ),
			'Rank Math variable with args'  => array( '%count(varname)%', true ),
			'AIOSEO tag'                    => array( 'Welcome to #site_title.', true ),
			'AIOSEO separator tag'          => array( '#post_title #separator_sa #site_title', true ),
			'a percentage is not a tag'     => array( 'Save 50% today and 30% more.', false ),
			'a hashtag is not a tag'        => array( 'Follow us #AI for news.', false ),
			'a number sign is not a tag'    => array( 'We are #1 in speed.', false ),
			'empty'                         => array( '', false ),
		);
	}
}
