<?php
/**
 * Tests for storing, reading and counting stored copies, on a real database.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Tests\WpEnv;

use AgentWelcome\Cache\Entry;
use AgentWelcome\Cache\WpdbStore;
use WP_UnitTestCase;

final class StoreTest extends WP_UnitTestCase {

	private WpdbStore $store;

	public function set_up(): void {
		parent::set_up();

		$this->store = new WpdbStore();
		$this->store->clear();
	}

	private function entry( int $post_id, string $markdown = "# Title\n", string $state = Entry::FRESH ): Entry {
		return new Entry( $post_id, $markdown, sha1( $markdown ), 'loopback', '1.0.0', gmdate( 'Y-m-d H:i:s' ), $state );
	}

	public function test_a_saved_copy_is_read_back_unchanged(): void {
		$this->store->save( $this->entry( 101, "# Hello\n\nA paragraph with a umlaut: \u{00fc}.\n" ) );

		$entry = $this->store->get( 101 );

		$this->assertInstanceOf( Entry::class, $entry );
		$this->assertSame( "# Hello\n\nA paragraph with a umlaut: \u{00fc}.\n", $entry->markdown );
		$this->assertSame( Entry::FRESH, $entry->state );
		$this->assertSame( 'loopback', $entry->source );
		$this->assertSame( '1.0.0', $entry->generator_version );
	}

	public function test_the_text_can_be_left_out_of_a_read(): void {
		$this->store->save( $this->entry( 102, "# Long text\n" ) );

		$entry = $this->store->get( 102, false );

		$this->assertSame( '', $entry->markdown );
		$this->assertSame( Entry::FRESH, $entry->state );
	}

	public function test_a_page_without_a_copy_gives_null(): void {
		$this->assertNull( $this->store->get( 999999 ) );
	}

	public function test_saving_again_replaces_the_copy(): void {
		$this->store->save( $this->entry( 103, "# One\n" ) );
		$this->store->save( $this->entry( 103, "# Two\n" ) );

		$this->assertSame( "# Two\n", $this->store->get( 103 )->markdown );
		$this->assertSame( 1, array_sum( $this->store->counts() ) );
	}

	public function test_marking_stale_changes_the_state_of_one_page_or_of_all(): void {
		$this->store->save( $this->entry( 104 ) );
		$this->store->save( $this->entry( 105 ) );

		$this->store->mark_stale( 104 );

		$this->assertSame( Entry::STALE, $this->store->get( 104 )->state );
		$this->assertSame( Entry::FRESH, $this->store->get( 105 )->state );

		$this->store->mark_all_stale();

		$this->assertSame( array( 'fresh' => 0, 'stale' => 2 ), $this->store->counts() );
	}

	public function test_counts_group_the_copies_by_state(): void {
		$this->store->save( $this->entry( 106 ) );
		$this->store->save( $this->entry( 107, "# A\n", Entry::STALE ) );
		$this->store->save( $this->entry( 108, "# B\n", Entry::STALE ) );

		$this->assertSame( array( 'fresh' => 1, 'stale' => 2 ), $this->store->counts() );
	}

	public function test_ids_come_in_order_and_can_be_paged(): void {
		foreach ( array( 30, 10, 20, 40 ) as $id ) {
			$this->store->save( $this->entry( $id ) );
		}

		$this->assertSame( array( 10, 20 ), $this->store->ids( 0, 2 ) );
		$this->assertSame( array( 30, 40 ), $this->store->ids( 20, 5 ) );
		$this->assertSame( array(), $this->store->ids( 40, 5 ) );
	}

	public function test_a_copy_can_be_deleted_and_the_table_cleared(): void {
		$this->store->save( $this->entry( 109 ) );
		$this->store->save( $this->entry( 110 ) );

		$this->store->delete( 109 );

		$this->assertNull( $this->store->get( 109 ) );
		$this->assertNotNull( $this->store->get( 110 ) );

		$this->store->clear();

		$this->assertSame( array( 'fresh' => 0, 'stale' => 0 ), $this->store->counts() );
	}
}
