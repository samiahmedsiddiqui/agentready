<?php
/**
 * Tests for the generation locks and the render slots, which rely on an atomic insert into the options table.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Tests\WpEnv;

use AgentWelcome\Cache\Lock;
use WP_UnitTestCase;

final class LockTest extends WP_UnitTestCase {

	private Lock $lock;

	public function set_up(): void {
		parent::set_up();

		$this->lock = new Lock();
	}

	public function test_a_lock_can_be_taken_once(): void {
		$this->assertTrue( $this->lock->acquire( 501 ) );
		$this->assertFalse( $this->lock->acquire( 501 ) );
	}

	public function test_a_lock_can_be_taken_again_after_it_is_released(): void {
		$this->assertTrue( $this->lock->acquire( 502 ) );

		$this->lock->release( 502 );

		$this->assertTrue( $this->lock->acquire( 502 ) );
	}

	public function test_locks_of_different_pages_do_not_block_each_other(): void {
		$this->assertTrue( $this->lock->acquire( 503 ) );
		$this->assertTrue( $this->lock->acquire( 504 ) );
	}

	public function test_a_lock_older_than_a_minute_is_taken_over(): void {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->options} ( option_name, option_value, autoload ) VALUES ( %s, %s, 'no' )",
				'agentwelcome_lock_505',
				(string) ( time() - 120 )
			)
		);

		$this->assertTrue( $this->lock->acquire( 505 ) );
		$this->assertFalse( $this->lock->acquire( 505 ) );
	}

	public function test_a_fresh_lock_is_not_taken_over(): void {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->options} ( option_name, option_value, autoload ) VALUES ( %s, %s, 'no' )",
				'agentwelcome_lock_506',
				(string) time()
			)
		);

		$this->assertFalse( $this->lock->acquire( 506 ) );
	}

	public function test_only_the_given_number_of_render_slots_can_be_taken(): void {
		$this->assertSame( 1, $this->lock->take_slot( 3 ) );
		$this->assertSame( 2, $this->lock->take_slot( 3 ) );
		$this->assertSame( 3, $this->lock->take_slot( 3 ) );
		$this->assertNull( $this->lock->take_slot( 3 ) );
	}

	public function test_a_released_slot_can_be_taken_again(): void {
		$this->lock->take_slot( 2 );
		$this->lock->take_slot( 2 );

		$this->lock->release_slot( 1 );

		$this->assertSame( 1, $this->lock->take_slot( 2 ) );
		$this->assertNull( $this->lock->take_slot( 2 ) );
	}
}
