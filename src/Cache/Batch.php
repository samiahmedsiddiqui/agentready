<?php
/**
 * Warms the cache in small background batches, and removes copies of pages that are no longer served.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Cache;

use AgentWelcome\Content\Eligibility;
use AgentWelcome\Content\PostTypes;
use AgentWelcome\Contracts\Hookable;
use AgentWelcome\Environment\Environment;

defined( 'ABSPATH' ) || exit;

/** Works through all pages in small steps from WP-Cron, to refresh stored copies or to remove ones that are no longer served. */
final class Batch implements Hookable {

	/** Cron hook of the refresh run. */
	public const JOB = 'agentwelcome_regenerate_batch';
	/** Cron hook of the removal of copies that are no longer served. */
	public const PRUNE = 'agentwelcome_prune';
	/** Option that holds where the refresh stopped. It exists only while a refresh is running. */
	public const OPTION = 'agentwelcome_batch';

	/** Pages handled in one cron run. */
	private const PAGES_PER_RUN = 5;
	/** Seconds after which a run stops and hands over to the next one. */
	private const SECONDS_PER_RUN = 20;
	/** Stored copies checked at a time while removing. */
	private const PRUNE_CHUNK = 200;

	/**
	 * @param Store       $store       Stored copies.
	 * @param PageCache   $pages       Generates the copies.
	 * @param PostTypes   $post_types  Post types that are served.
	 * @param Eligibility $eligibility Decides whether a page is served.
	 * @param Environment $environment Tells whether the plugin is running on this site.
	 * @param Candidates  $candidates  Source of the pages to go through.
	 */
	public function __construct(
		private Store $store,
		private PageCache $pages,
		private PostTypes $post_types,
		private Eligibility $eligibility,
		private Environment $environment,
		private Candidates $candidates
	) {}

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( self::JOB, array( $this, 'run' ) );
		add_action( self::PRUNE, array( $this, 'prune' ) );
	}

	/**
	 * Starts a refresh of all pages.
	 *
	 * @param bool $mark_stale True to mark every copy out of date first, so the old ones are served until replaced.
	 * @return void
	 */
	public function start( bool $mark_stale ): void {
		if ( $mark_stale ) {
			$this->store->mark_all_stale();
		}

		update_option( self::OPTION, array( 'last_id' => 0 ), false );
		$this->schedule( 10 );
	}

	/**
	 * Whether a refresh is in progress.
	 *
	 * @return bool True while one is running.
	 */
	public function is_running(): bool {
		return false !== get_option( self::OPTION );
	}

	/**
	 * Queues the removal of stored copies of pages that are no longer served.
	 *
	 * @return void
	 */
	public function schedule_prune(): void {
		if ( false === wp_next_scheduled( self::PRUNE ) ) {
			wp_schedule_single_event( time() + 10, self::PRUNE );
		}
	}

	/**
	 * Handles one step of the refresh and queues the next until every page is done.
	 *
	 * @return void
	 */
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
		$ids     = $this->candidates->after( $this->post_types->enabled(), $last, self::PAGES_PER_RUN );
		$started = microtime( true );
		$paused  = false;

		foreach ( $ids as $id ) {
			if ( microtime( true ) - $started > self::SECONDS_PER_RUN ) {
				$paused = true;
				break;
			}

			$this->process( $id );
			$last = $id;
			update_option( self::OPTION, array( 'last_id' => $last ), false );
		}

		if ( ! $paused && count( $ids ) < self::PAGES_PER_RUN ) {
			delete_option( self::OPTION );
			return;
		}

		$this->schedule( 5 );
	}

	/**
	 * Deletes the stored copies of pages that are no longer served.
	 *
	 * @return void
	 */
	public function prune(): void {
		$last = 0;

		do {
			$ids   = $this->store->ids( $last, self::PRUNE_CHUNK );
			$count = count( $ids );

			foreach ( $ids as $id ) {
				$post = get_post( $id );

				if ( ! $post instanceof \WP_Post || ! $this->eligibility->is_eligible( $post ) ) {
					$this->store->delete( $id );
				}

				$last = $id;
			}
		} while ( self::PRUNE_CHUNK === $count );
	}

	/**
	 * Refreshes one page, or removes its copy when it is no longer served.
	 *
	 * @param int $id Page ID.
	 * @return void
	 */
	private function process( int $id ): void {
		$post = get_post( $id );

		if ( ! $post instanceof \WP_Post || ! $this->eligibility->is_eligible( $post ) ) {
			$this->store->delete( $id );
			return;
		}

		$entry = $this->store->get( $id, false );

		if ( Freshness::FRESH === Freshness::classify( $entry, AGENTWELCOME_VERSION ) ) {
			return;
		}

		$this->pages->regenerate( $post );
	}

	/**
	 * Queues the next run unless one is already queued.
	 *
	 * @param int $delay Seconds to wait.
	 * @return void
	 */
	private function schedule( int $delay ): void {
		if ( false === wp_next_scheduled( self::JOB ) ) {
			wp_schedule_single_event( time() + $delay, self::JOB );
		}
	}
}
