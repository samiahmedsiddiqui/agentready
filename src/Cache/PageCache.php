<?php
/**
 * Serves stored Markdown and generates it on demand or in the background.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Cache;

use AgentReady\Content\Eligibility;
use AgentReady\Contracts\Hookable;
use AgentReady\Environment\Environment;
use AgentReady\Rendering\Generator;
use AgentReady\Rendering\RenderResult;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class PageCache implements Hookable {

	public const JOB = 'agentready_generate_post';

	private const WAIT_SECONDS   = 5;
	private const POLL_MICRO     = 250000;
	private const REFRESH_DELAY  = 10;
	private const FALLBACK_DELAY = 300;
	private const QUEUE_SPACING  = 5;

	public function __construct(
		private Store $store,
		private Generator $generator,
		private Lock $lock,
		private Eligibility $eligibility,
		private Environment $environment
	) {}

	public function register(): void {
		add_action( self::JOB, array( $this, 'run_job' ) );
	}

	/**
	 * @return Served|null Null when another request is still generating and the wait ran out.
	 */
	public function fetch( WP_Post $post ): ?Served {
		$entry = $this->store->get( $post->ID );
		$state = Freshness::classify( $entry, AGENTREADY_VERSION );

		if ( null !== $entry && Freshness::FRESH === $state ) {
			return new Served( $entry, Served::HIT );
		}

		if ( null !== $entry && Freshness::STALE === $state ) {
			$this->schedule( $post->ID, RenderResult::FALLBACK === $entry->source ? self::FALLBACK_DELAY : self::REFRESH_DELAY );

			return new Served( $entry, Served::STALE );
		}

		$deadline = microtime( true ) + self::WAIT_SECONDS;

		do {
			$made = $this->regenerate( $post, false );

			if ( null !== $made ) {
				return new Served( $made, Served::MISS );
			}

			if ( null !== $entry ) {
				return new Served( $entry, Served::STALE );
			}

			usleep( self::POLL_MICRO );

			$current = $this->store->get( $post->ID );

			if ( null !== $current && Freshness::OUTDATED !== Freshness::classify( $current, AGENTREADY_VERSION ) ) {
				return new Served( $current, Served::HIT );
			}
		} while ( microtime( true ) < $deadline );

		return null;
	}

	/**
	 * @param bool $force Rebuild even if another request just stored a current copy.
	 * @return Entry|null Null when another request holds the lock.
	 */
	public function regenerate( WP_Post $post, bool $force = true ): ?Entry {
		if ( ! $this->lock->acquire( $post->ID ) ) {
			return null;
		}

		try {
			if ( ! $force ) {
				$current = $this->store->get( $post->ID );

				if ( null !== $current && Freshness::FRESH === Freshness::classify( $current, AGENTREADY_VERSION ) ) {
					return $current;
				}
			}

			$entry = $this->build( $post );
			$this->store->save( $entry );

			return $entry;
		} finally {
			$this->lock->release( $post->ID );
		}
	}

	public function run_job( int $post_id ): void {
		if ( ! $this->environment->is_active() ) {
			return;
		}

		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || ! $this->eligibility->is_eligible( $post ) ) {
			$this->store->delete( $post_id );
			return;
		}

		$this->regenerate( $post );
	}

	public function schedule( int $post_id, int $delay = self::REFRESH_DELAY ): void {
		$args = array( $post_id );

		if ( ! $this->environment->is_active() || false !== wp_next_scheduled( self::JOB, $args ) ) {
			return;
		}

		wp_schedule_single_event( time() + $delay, self::JOB, $args );
	}

	/**
	 * Marks the pages stale and queues a background refresh for each.
	 *
	 * @param int[] $post_ids Post IDs.
	 */
	public function queue( array $post_ids ): void {
		foreach ( array_values( $post_ids ) as $index => $post_id ) {
			$this->store->mark_stale( $post_id );
			$this->schedule( $post_id, self::REFRESH_DELAY + $index * self::QUEUE_SPACING );
		}
	}

	private function build( WP_Post $post ): Entry {
		$page     = $this->generator->generate( $post );
		$fallback = RenderResult::FALLBACK === $page->source;

		return new Entry(
			$post->ID,
			$page->markdown,
			sha1( $page->markdown ),
			$page->source,
			AGENTREADY_VERSION,
			gmdate( 'Y-m-d H:i:s' ),
			$fallback ? Entry::STALE : Entry::FRESH
		);
	}
}
