<?php
/**
 * Tests for the queries that find pages to go through and pages without a stored copy, on a real database.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Tests\WpEnv;

use AgentWelcome\Cache\Candidates;
use AgentWelcome\Cache\Entry;
use AgentWelcome\Cache\WpdbStore;
use WP_UnitTestCase;

final class CandidatesTest extends WP_UnitTestCase {

	private Candidates $candidates;

	/** @var int[] IDs of three published pages, in order. */
	private array $published = array();

	public function set_up(): void {
		parent::set_up();

		( new WpdbStore() )->clear();

		$this->candidates = new Candidates();

		for ( $i = 0; $i < 3; $i++ ) {
			$this->published[] = self::factory()->post->create(
				array(
					'post_type'   => 'page',
					'post_status' => 'publish',
				)
			);
		}

		self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'draft',
			)
		);
		self::factory()->post->create(
			array(
				'post_type'     => 'page',
				'post_status'   => 'publish',
				'post_password' => 'secret',
			)
		);
	}

	public function test_after_lists_published_pages_in_order(): void {
		$ids = $this->candidates->after( array( 'page' ), 0, 100 );

		foreach ( $this->published as $id ) {
			$this->assertContains( $id, $ids );
		}

		$this->assertSame( $ids, array_values( array_unique( $ids ) ) );
		$this->assertSame( $ids, $this->sorted( $ids ) );
	}

	public function test_after_leaves_out_drafts(): void {
		$ids = $this->candidates->after( array( 'page' ), 0, 100 );

		foreach ( $ids as $id ) {
			$this->assertSame( 'publish', get_post_status( $id ) );
		}
	}

	public function test_after_can_be_paged(): void {
		$first = $this->candidates->after( array( 'page' ), $this->published[0] - 1, 2 );

		$this->assertCount( 2, $first );
		$this->assertSame( $this->published[0], $first[0] );

		$next = $this->candidates->after( array( 'page' ), $first[1], 100 );

		$this->assertNotContains( $first[0], $next );
		$this->assertNotContains( $first[1], $next );
	}

	public function test_no_post_types_gives_no_pages(): void {
		$this->assertSame( array(), $this->candidates->after( array(), 0, 10 ) );
		$this->assertSame( array(), $this->candidates->missing( array(), 10 ) );
	}

	public function test_missing_lists_pages_without_a_copy_and_leaves_out_protected_ones(): void {
		$missing = $this->candidates->missing( array( 'page' ), 100 );

		foreach ( $this->published as $id ) {
			$this->assertContains( $id, $missing );
		}

		foreach ( $missing as $id ) {
			$this->assertSame( '', get_post( $id )->post_password );
		}
	}

	public function test_missing_leaves_out_pages_that_have_a_copy(): void {
		( new WpdbStore() )->save( new Entry( $this->published[1], "# Page\n", sha1( 'page' ), 'loopback', '1.0.0', gmdate( 'Y-m-d H:i:s' ), Entry::FRESH ) );

		$missing = $this->candidates->missing( array( 'page' ), 100 );

		$this->assertNotContains( $this->published[1], $missing );
		$this->assertContains( $this->published[0], $missing );
	}

	public function test_missing_respects_the_limit(): void {
		$this->assertCount( 2, $this->candidates->missing( array( 'page' ), 2 ) );
	}

	/**
	 * @param int[] $ids IDs.
	 * @return int[] The IDs in ascending order.
	 */
	private function sorted( array $ids ): array {
		sort( $ids );

		return $ids;
	}
}
