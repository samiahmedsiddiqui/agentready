<?php
/**
 * Serves stored Markdown and generates it on demand or in the background.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Cache;

use AgentWelcome\Content\Eligibility;
use AgentWelcome\Contracts\Hookable;
use AgentWelcome\Environment\Environment;
use AgentWelcome\Rendering\Generator;
use AgentWelcome\Rendering\RenderResult;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/** Serves the stored Markdown of a page, generating it when it is missing and queueing refreshes when it is out of date. */
final class PageCache implements Hookable {

	/** Cron hook that refreshes one page. */
	public const JOB = 'agentwelcome_generate_post';

	/** Seconds a request waits for another request that is generating the same page. */
	private const WAIT_SECONDS = 5;
	/** Microseconds between checks while waiting. */
	private const POLL_MICROSECONDS = 250000;
	/** Seconds before a refresh of an out-of-date page runs. */
	private const REFRESH_DELAY_SECONDS = 10;
	/** Seconds before another attempt to render a page that was built from the post content only. */
	private const FALLBACK_RETRY_DELAY = 300;
	/** Seconds between the refreshes of pages queued together. */
	private const QUEUE_SPACING_SECONDS = 5;
	/** Pages that may be rendered for visitors at the same time. */
	private const MAX_RENDERS = 3;

	/**
	 * @param Store       $store       Stored copies.
	 * @param Generator   $generator   Builds the Markdown of a page.
	 * @param Lock        $lock        Locks and render slots.
	 * @param Eligibility $eligibility Decides whether a page is served.
	 * @param Environment $environment Tells whether the plugin is running on this site.
	 */
	public function __construct(
		private Store $store,
		private Generator $generator,
		private Lock $lock,
		private Eligibility $eligibility,
		private Environment $environment
	) {}

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( self::JOB, array( $this, 'run_job' ) );
	}

	/**
	 * Gets the copy to serve for a page. A current copy is returned as is, an out-of-date one is returned and refreshed later, and a missing one is generated while the visitor waits.
	 *
	 * @param WP_Post $post The page.
	 * @return Served|null Null when the page is still being generated elsewhere after the wait, or when every render slot is busy.
	 */
	public function fetch( WP_Post $post ): ?Served {
		$entry = $this->store->get( $post->ID );
		$state = Freshness::classify( $entry, AGENTWELCOME_VERSION );

		if ( null !== $entry && Freshness::FRESH === $state ) {
			return new Served( $entry, Served::HIT );
		}

		if ( null !== $entry && Freshness::STALE === $state ) {
			$this->schedule( $post->ID, RenderResult::FALLBACK === $entry->source ? self::FALLBACK_RETRY_DELAY : self::REFRESH_DELAY_SECONDS );

			return new Served( $entry, Served::STALE );
		}

		$deadline = microtime( true ) + self::WAIT_SECONDS;

		do {
			$slot = $this->take_render_slot();

			if ( null === $slot ) {
				$this->schedule( $post->ID, self::REFRESH_DELAY_SECONDS );

				return null !== $entry ? new Served( $entry, Served::STALE ) : null;
			}

			try {
				$made = $this->regenerate( $post, false );
			} finally {
				$this->lock->release_slot( $slot );
			}

			if ( null !== $made ) {
				return new Served( $made, Served::MISS );
			}

			if ( null !== $entry ) {
				return new Served( $entry, Served::STALE );
			}

			usleep( self::POLL_MICROSECONDS );

			$current = $this->store->get( $post->ID );

			if ( null !== $current && Freshness::OUTDATED !== Freshness::classify( $current, AGENTWELCOME_VERSION ) ) {
				return new Served( $current, Served::HIT );
			}
		} while ( microtime( true ) < $deadline );

		return null;
	}

	/**
	 * Takes a slot for rendering a page for a visitor.
	 *
	 * @return int|null Slot number, or null when every slot is busy.
	 */
	private function take_render_slot(): ?int {
		return $this->lock->take_slot( max( 1, (int) apply_filters( 'agentwelcome_max_concurrent_renders', self::MAX_RENDERS ) ) );
	}

	/**
	 * Generates and stores the copy of a page.
	 *
	 * @param WP_Post $post  The page.
	 * @param bool    $force Rebuild even if another request just stored a current copy.
	 * @return Entry|null The new copy, or null when another request holds the lock.
	 */
	public function regenerate( WP_Post $post, bool $force = true ): ?Entry {
		if ( ! $this->lock->acquire( $post->ID ) ) {
			return null;
		}

		try {
			if ( ! $force ) {
				$current = $this->store->get( $post->ID );

				if ( null !== $current && Freshness::FRESH === Freshness::classify( $current, AGENTWELCOME_VERSION ) ) {
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

	/**
	 * Cron callback that refreshes one page, or removes its copy when it is no longer served.
	 *
	 * @param int $post_id Page ID.
	 * @return void
	 */
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

	/**
	 * Queues a refresh of a page unless one is already queued.
	 *
	 * @param int $post_id Page ID.
	 * @param int $delay   Seconds to wait.
	 * @return void
	 */
	public function schedule( int $post_id, int $delay = self::REFRESH_DELAY_SECONDS ): void {
		$args = array( $post_id );

		if ( ! $this->environment->is_active() || false !== wp_next_scheduled( self::JOB, $args ) ) {
			return;
		}

		wp_schedule_single_event( time() + $delay, self::JOB, $args );
	}

	/**
	 * Marks pages out of date and queues a refresh for each, spaced apart.
	 *
	 * @param int[] $post_ids Page IDs.
	 * @return void
	 */
	public function queue( array $post_ids ): void {
		foreach ( array_values( $post_ids ) as $index => $post_id ) {
			$this->store->mark_stale( $post_id );
			$this->schedule( $post_id, self::REFRESH_DELAY_SECONDS + $index * self::QUEUE_SPACING_SECONDS );
		}
	}

	/**
	 * Generates the copy of a page. A copy built without the rendered page is kept out of date so it is retried later.
	 *
	 * @param WP_Post $post The page.
	 * @return Entry The new copy.
	 */
	private function build( WP_Post $post ): Entry {
		$page     = $this->generator->generate( $post );
		$fallback = RenderResult::FALLBACK === $page->source;

		return new Entry(
			$post->ID,
			$page->markdown,
			sha1( $page->markdown ),
			$page->source,
			AGENTWELCOME_VERSION,
			gmdate( 'Y-m-d H:i:s' ),
			$fallback ? Entry::STALE : Entry::FRESH
		);
	}
}
