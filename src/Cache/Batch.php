<?php
/**
 * Warms the cache in small background batches, and removes copies of pages that are no longer served.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Cache;

use AgentReady\Content\Eligibility;
use AgentReady\Content\PostTypes;
use AgentReady\Contracts\Hookable;
use AgentReady\Environment\Environment;

defined( 'ABSPATH' ) || exit;

final class Batch implements Hookable {

	public const JOB    = 'agentready_regenerate_batch';
	public const PRUNE  = 'agentready_prune';
	public const OPTION = 'agentready_batch';

	private const SIZE   = 5;
	private const BUDGET = 20;
	private const CHUNK  = 200;

	public function __construct(
		private Store $store,
		private PageCache $pages,
		private PostTypes $post_types,
		private Eligibility $eligibility,
		private Environment $environment,
		private Candidates $candidates
	) {}

	public function register(): void {
		add_action( self::JOB, array( $this, 'run' ) );
		add_action( self::PRUNE, array( $this, 'prune' ) );
	}

	public function start( bool $mark_stale ): void {
		if ( $mark_stale ) {
			$this->store->mark_all_stale();
		}

		update_option( self::OPTION, array( 'last_id' => 0 ), false );
		$this->schedule( 10 );
	}

	public function is_running(): bool {
		return false !== get_option( self::OPTION );
	}

	public function schedule_prune(): void {
		if ( false === wp_next_scheduled( self::PRUNE ) ) {
			wp_schedule_single_event( time() + 10, self::PRUNE );
		}
	}

	public function run(): void {
		$state = get_option( self::OPTION );

		if ( ! is_array( $state ) ) {
			return;
		}

		if ( ! $this->environment->is_active() ) {
			delete_option( self::OPTION );
			return;
		}

		$last    = (int) ( $state['last_id'] ?? 0 );
		$ids     = $this->candidates->after( $this->post_types->enabled(), $last, self::SIZE );
		$started = microtime( true );
		$paused  = false;

		foreach ( $ids as $id ) {
			if ( microtime( true ) - $started > self::BUDGET ) {
				$paused = true;
				break;
			}

			$this->process( $id );
			$last = $id;
			update_option( self::OPTION, array( 'last_id' => $last ), false );
		}

		if ( ! $paused && count( $ids ) < self::SIZE ) {
			delete_option( self::OPTION );
			return;
		}

		$this->schedule( 5 );
	}

	public function prune(): void {
		$last = 0;

		do {
			$ids   = $this->store->ids( $last, self::CHUNK );
			$count = count( $ids );

			foreach ( $ids as $id ) {
				$post = get_post( $id );

				if ( ! $post instanceof \WP_Post || ! $this->eligibility->is_eligible( $post ) ) {
					$this->store->delete( $id );
				}

				$last = $id;
			}
		} while ( self::CHUNK === $count );
	}

	private function process( int $id ): void {
		$post = get_post( $id );

		if ( ! $post instanceof \WP_Post || ! $this->eligibility->is_eligible( $post ) ) {
			$this->store->delete( $id );
			return;
		}

		$entry = $this->store->get( $id, false );

		if ( Freshness::FRESH === Freshness::classify( $entry, AGENTREADY_VERSION ) ) {
			return;
		}

		$this->pages->regenerate( $post );
	}

	private function schedule( int $delay ): void {
		if ( false === wp_next_scheduled( self::JOB ) ) {
			wp_schedule_single_event( time() + $delay, self::JOB );
		}
	}
}
