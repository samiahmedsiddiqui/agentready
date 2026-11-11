<?php
/**
 * Tests for the capability that controls the llms.txt files.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Tests\Unit;

use AgentReady\Admin\Capabilities;
use PHPUnit\Framework\TestCase;

final class CapabilitiesTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['agentready_test_filters'] = array();
	}

	public function test_default_is_the_plugin_capability(): void {
		$this->assertSame( 'agentready_manage_files', Capabilities::files() );
	}

	public function test_a_filter_can_map_it_to_another_capability(): void {
		$GLOBALS['agentready_test_filters']['agentready_files_capability'] = static fn(): string => 'edit_pages';

		$this->assertSame( 'edit_pages', Capabilities::files() );
	}

	/**
	 * @dataProvider unusable_values
	 *
	 * @param mixed $value What the filter returns.
	 */
	public function test_an_unusable_filter_result_falls_back_to_the_default( mixed $value ): void {
		$GLOBALS['agentready_test_filters']['agentready_files_capability'] = static fn() => $value;

		$this->assertSame( 'agentready_manage_files', Capabilities::files() );
	}

	/**
	 * @return array<string, array{mixed}>
	 */
	public function unusable_values(): array {
		return array(
			'empty string' => array( '' ),
			'false'        => array( false ),
			'null'         => array( null ),
			'array'        => array( array( 'edit_pages' ) ),
		);
	}
}
