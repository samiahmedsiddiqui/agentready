<?php
/**
 * Compares every stored page with a fresh build in the background, and reports what differs. Changes nothing.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Verify;

use AgentReady\Cache\Coverage;
use AgentReady\Cache\Lock;
use AgentReady\Cache\Store;
use AgentReady\Contracts\Hookable;
use AgentReady\Environment\Environment;
use AgentReady\Rendering\RenderResult;

defined( 'ABSPATH' ) || exit;

/** Checks every stored page against its page in small steps from WP-Cron, and reports which ones differ. It changes nothing. */
final class Scan implements Hookable {

	/** Cron hook of one step. */
	public const JOB = 'agentready_scan_batch';
	/** Option that holds the progress and result of the scan. */
	public const OPTION = 'agentready_scan';

	/** Pages checked in one step. */
	private const PAGES_PER_RUN = 3;
	/** Seconds after which a step stops and hands over to the next one. */
	private const SECONDS_PER_RUN = 20;
	/** Most differing pages that are listed. Further ones are still counted as checked. */
	private const MAX_LISTED_PAGES = 500;

	// A post ID never used, so the scan has its own lock.
	/** Lock ID used so only one step runs at a time. It is not a page ID. */
	private const LOCK_ID = 0;

	/**
	 * @param Store       $store       Stored copies.
	 * @param Verifier    $verifier    Compares one page.
	 * @param Environment $environment Tells whether the plugin is running on this site.
	 * @param Coverage    $coverage    Counts the pages with no stored copy.
	 * @param Lock        $lock        Keeps two steps from running together.
	 */
	public function __construct(
		private Store $store,
		private Verifier $verifier,
		private Environment $environment,
		private Coverage $coverage,
		private Lock $lock
	) {}

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( self::JOB, array( $this, 'run' ) );
	}

	/**
	 * Progress and result of the scan.
	 *
	 * @return array{running: bool, started: int, finished: int, last_id: int, checked: int, in_sync: int, skipped: int, fallback: int, not_stored: int, differs: array<int, array{id: int, title: string, changed: int}>} Saved state, with zeros for a scan that never ran.
	 */
	public function status(): array {
		$state = get_option( self::OPTION );

		return array_merge( self::blank(), is_array( $state ) ? $state : array() );
	}

	/**
	 * Starts a new scan.
	 *
	 * @return bool False when the plugin is not running on this site.
	 */
	public function start(): bool {
		if ( ! $this->environment->is_active() ) {
			return false;
		}

		update_option(
			self::OPTION,
			array_merge(
				self::blank(),
				array(
					'running' => true,
					'started' => time(),
				)
			),
			false
		);
		$this->schedule( 5 );

		return true;
	}

	/**
	 * Stops the scan and keeps the result so far.
	 *
	 * @return void
	 */
	public function stop(): void {
		$state             = $this->status();
		$state['running']  = false;
		$state['finished'] = time();

		update_option( self::OPTION, $state, false );
		wp_unschedule_hook( self::JOB );
	}

	/**
	 * IDs of the pages that differ.
	 *
	 * @return int[] Page IDs.
	 */
	public function differing_ids(): array {
		return array_map( static fn( array $row ): int => $row['id'], $this->status()['differs'] );
	}

	/**
	 * Runs one step, unless another step is running.
	 *
	 * @return void
	 */
	public function run(): void {
		if ( ! $this->lock->acquire( self::LOCK_ID ) ) {
			return;
		}

		try {
			$this->step();
		} finally {
			$this->lock->release( self::LOCK_ID );
		}
	}

	/**
	 * Checks the next few pages and queues the next step, or finishes the scan.
	 *
	 * @return void
	 */
	private function step(): void {
		$state = $this->status();

		if ( ! $state['running'] ) {
			return;
		}

		if ( ! $this->environment->is_active() ) {
			$this->stop();
			return;
		}

		$ids     = $this->store->ids( $state['last_id'], self::PAGES_PER_RUN );
		$started = microtime( true );
		$paused  = false;

		foreach ( $ids as $id ) {
			if ( microtime( true ) - $started > self::SECONDS_PER_RUN ) {
				$paused = true;
				break;
			}

			$state            = $this->check( $id, $state );
			$state['last_id'] = $id;
			update_option( self::OPTION, $state, false );
		}

		if ( ! $paused && count( $ids ) < self::PAGES_PER_RUN ) {
			$state['running']    = false;
			$state['finished']   = time();
			$state['not_stored'] = $this->coverage->summary()['missing'];
			update_option( self::OPTION, $state, false );
			return;
		}

		$this->schedule( 3 );
	}

	/**
	 * Checks one page and adds it to the counts.
	 *
	 * @param int                  $id    Page ID.
	 * @param array<string, mixed> $state Scan state.
	 * @return array<string, mixed> The state with the page counted.
	 */
	private function check( int $id, array $state ): array {
		$post = get_post( $id );

		if ( ! $post instanceof \WP_Post ) {
			++$state['skipped'];
			return $state;
		}

		$result = $this->verifier->verify( $post, array(), false );

		if ( VerifyResult::NOT_SERVED === $result->state ) {
			++$state['skipped'];
			return $state;
		}

		++$state['checked'];

		if ( null !== $result->page && RenderResult::FALLBACK === $result->page->source ) {
			++$state['fallback'];
		}

		if ( VerifyResult::DIFFERS === $result->state && count( $state['differs'] ) < self::MAX_LISTED_PAGES ) {
			$state['differs'][] = array(
				'id'      => $id,
				'title'   => wp_strip_all_tags( get_the_title( $post ) ),
				'changed' => $result->changed,
			);
		} elseif ( VerifyResult::IN_SYNC === $result->state ) {
			++$state['in_sync'];
		}

		return $state;
	}

	/**
	 * Queues the next step unless one is already queued.
	 *
	 * @param int $delay Seconds to wait.
	 * @return void
	 */
	private function schedule( int $delay ): void {
		if ( false === wp_next_scheduled( self::JOB ) ) {
			wp_schedule_single_event( time() + $delay, self::JOB );
		}
	}

	/**
	 * State of a scan that has not run.
	 *
	 * @return array{running: bool, started: int, finished: int, last_id: int, checked: int, in_sync: int, skipped: int, fallback: int, not_stored: int, differs: array<int, array{id: int, title: string, changed: int}>} Empty counts.
	 */
	private static function blank(): array {
		return array(
			'running'    => false,
			'started'    => 0,
			'finished'   => 0,
			'last_id'    => 0,
			'checked'    => 0,
			'in_sync'    => 0,
			'skipped'    => 0,
			'fallback'   => 0,
			'not_stored' => 0,
			'differs'    => array(),
		);
	}
}
