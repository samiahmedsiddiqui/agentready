<?php
/**
 * Tests for reading a noindex choice from SEO plugin meta, and for spotting ACF options saves.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Tests\Unit;

use AgentReady\Content\Noindex;
use AgentReady\Integrations\Acf;
use PHPUnit\Framework\TestCase;

final class NoindexTest extends TestCase {

	/**
	 * @dataProvider values
	 *
	 * @param array<string, mixed> $values Meta values by key.
	 */
	public function test_reads_noindex( array $values, bool $expected ): void {
		$this->assertSame( $expected, Noindex::from_meta( $values ) );
	}

	/**
	 * @return array<string, array{array<string, mixed>, bool}>
	 */
	public function values(): array {
		return array(
			'nothing saved'          => array( array(), false ),
			'empty values'           => array( array( Noindex::YOAST => '', Noindex::RANKMATH => '', Noindex::SEOPRESS => '', Noindex::TSF => '' ), false ),
			'yoast noindex'          => array( array( Noindex::YOAST => '1' ), true ),
			'yoast number noindex'   => array( array( Noindex::YOAST => 1 ), true ),
			'yoast index'            => array( array( Noindex::YOAST => '2' ), false ),
			'yoast default'          => array( array( Noindex::YOAST => '0' ), false ),
			'rank math noindex'      => array( array( Noindex::RANKMATH => array( 'noindex', 'nofollow' ) ), true ),
			'rank math index'        => array( array( Noindex::RANKMATH => array( 'index' ) ), false ),
			'rank math empty'        => array( array( Noindex::RANKMATH => array() ), false ),
			'rank math not a list'   => array( array( Noindex::RANKMATH => 'noindex' ), false ),
			'seopress noindex'       => array( array( Noindex::SEOPRESS => 'yes' ), true ),
			'seopress off'           => array( array( Noindex::SEOPRESS => '' ), false ),
			'tsf noindex'            => array( array( Noindex::TSF => '1' ), true ),
			'tsf index'              => array( array( Noindex::TSF => '-1' ), false ),
			'tsf default'            => array( array( Noindex::TSF => '0' ), false ),
			'one plugin is enough'   => array( array( Noindex::YOAST => '2', Noindex::SEOPRESS => 'yes' ), true ),
			'unknown key is ignored' => array( array( 'other_noindex' => '1' ), false ),
		);
	}

	/**
	 * @dataProvider targets
	 */
	public function test_spots_options_saves( mixed $id, bool $expected ): void {
		$this->assertSame( $expected, Acf::is_options_target( $id ) );
	}

	/**
	 * @return array<string, array{mixed, bool}>
	 */
	public function targets(): array {
		return array(
			'options'          => array( 'options', true ),
			'option'           => array( 'option', true ),
			'custom page name' => array( 'site_settings', true ),
			'language options' => array( 'options_fr', true ),
			'post number'      => array( 123, false ),
			'post string'      => array( '123', false ),
			'user'             => array( 'user_5', false ),
			'term'             => array( 'category_7', false ),
			'custom term'      => array( 'genre_12', false ),
			'widget'           => array( 'widget_text-3', false ),
			'comment'          => array( 'comment_9', false ),
			'new post'         => array( 'new_post', false ),
			'empty'            => array( '', false ),
			'not a string'     => array( null, false ),
		);
	}
}
