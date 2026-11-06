<?php
/**
 * Tests for the wording of the theme and plugin change notices.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Tests\Unit;

use AgentReady\Admin\Notices;
use AgentReady\Cache\Changes;
use PHPUnit\Framework\TestCase;

final class NoticesTest extends TestCase {

	public function test_theme_notice_promises_a_refresh_when_cron_runs(): void {
		$message = Notices::change_message( Changes::REFRESH, array( 'reason' => 'theme' ) );

		$this->assertStringContainsString( 'The active theme changed.', $message );
		$this->assertStringContainsString( 'is refreshing', $message );
	}

	public function test_theme_notice_says_nothing_runs_when_cron_is_disabled(): void {
		$message = Notices::change_message( Changes::REFRESH, array( 'reason' => 'customizer' ), true );

		$this->assertStringContainsString( 'The Customizer was saved.', $message );
		$this->assertStringContainsString( 'WP-Cron is disabled', $message );
		$this->assertStringNotContainsString( 'is refreshing', $message );
	}

	public function test_unknown_reason_gives_no_notice(): void {
		$this->assertSame( '', Notices::change_message( Changes::REFRESH, array( 'reason' => 'other' ), true ) );
	}
}
