<?php
/**
 * WP-CLI commands: wp agentwelcome cache clear | regenerate | status.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Cli;

use AgentWelcome\Cache\Candidates;
use AgentWelcome\Cache\Coverage;
use AgentWelcome\Cache\PageCache;
use AgentWelcome\Cache\Store;
use AgentWelcome\Content\Eligibility;
use AgentWelcome\Content\PostTypes;
use AgentWelcome\Contracts\Hookable;
use AgentWelcome\Environment\Environment;
use WP_CLI;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/** WP-CLI commands to inspect, clear and rebuild the stored Markdown. Run them with: wp agentwelcome cache. */
final class Command implements Hookable {

	/** Page IDs read from the database at a time. */
	private const CHUNK_SIZE = 200;

	/**
	 * @param Store       $store       Stored copies.
	 * @param PageCache   $pages       Generates the copies.
	 * @param PostTypes   $post_types  Post types that are served.
	 * @param Eligibility $eligibility Decides whether a page is served.
	 * @param Environment $environment Tells whether the plugin is running on this site.
	 * @param Candidates  $candidates  Source of the pages to go through.
	 * @param Coverage    $coverage    Counts the pages by state.
	 */
	public function __construct(
		private Store $store,
		private PageCache $pages,
		private PostTypes $post_types,
		private Eligibility $eligibility,
		private Environment $environment,
		private Candidates $candidates,
		private Coverage $coverage
	) {}

	/**
	 * @return void
	 */
	public function register(): void {
		WP_CLI::add_command( 'agentwelcome cache', $this );
	}

	/**
	 * Deletes every stored Markdown copy.
	 *
	 * ## EXAMPLES
	 *
	 *     wp agentwelcome cache clear
	 *
	 * @param string[]             $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Named arguments.
	 */
	public function clear( array $args, array $assoc_args ): void {
		$this->store->clear();
		WP_CLI::success( 'Stored Markdown deleted. Pages are rebuilt when they are first requested.' );
	}

	/**
	 * Regenerates stored Markdown now.
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : One or more post IDs.
	 *
	 * [--all]
	 * : Regenerate every page that is served.
	 *
	 * ## EXAMPLES
	 *
	 *     wp agentwelcome cache regenerate 42 57
	 *     wp agentwelcome cache regenerate --all
	 *
	 * @param string[]             $args       Post IDs.
	 * @param array<string, mixed> $assoc_args Named arguments.
	 */
	public function regenerate( array $args, array $assoc_args ): void {
		$reason = $this->environment->reason();

		if ( null !== $reason ) {
			WP_CLI::error( 'AgentWelcome is inactive here. ' . $this->environment->describe( $reason ) );
		}

		$ids = ! empty( $assoc_args['all'] ) ? $this->all_ids() : array_values( array_unique( array_map( 'absint', $args ) ) );

		if ( empty( $ids ) ) {
			WP_CLI::error( 'Give one or more post IDs, or use --all.' );
		}

		$progress = \WP_CLI\Utils\make_progress_bar( 'Regenerating', count( $ids ) );
		$done     = 0;
		$skipped  = 0;

		foreach ( $ids as $id ) {
			$post = get_post( $id );

			if ( $post instanceof WP_Post && $this->eligibility->is_eligible( $post ) && null !== $this->pages->regenerate( $post ) ) {
				++$done;
			} else {
				WP_CLI::debug( "Skipped {$id}." );
				++$skipped;
			}

			$progress->tick();
		}

		$progress->finish();
		WP_CLI::success( "Regenerated {$done} page(s). Skipped {$skipped}." );
	}

	/**
	 * Shows how many pages are up to date, out of date and not generated yet.
	 *
	 * ## EXAMPLES
	 *
	 *     wp agentwelcome cache status
	 *
	 * @param string[]             $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Named arguments.
	 */
	public function status( array $args, array $assoc_args ): void {
		$summary = $this->coverage->summary();
		$reason  = $this->environment->reason();

		WP_CLI::log( 'Active: ' . ( null === $reason ? 'yes' : 'no (' . $this->environment->describe( $reason ) . ')' ) );
		WP_CLI::log( 'Up to date: ' . $summary['fresh'] );
		WP_CLI::log( 'Out of date: ' . $summary['stale'] );
		WP_CLI::log( 'Not generated yet: ' . $summary['missing'] . ( $summary['capped'] ? '+' : '' ) );
	}

	/**
	 * IDs of every page that is served.
	 *
	 * @return int[] Page IDs.
	 */
	private function all_ids(): array {
		$ids  = array();
		$last = 0;

		do {
			$chunk = $this->candidates->after( $this->post_types->enabled(), $last, self::CHUNK_SIZE );
			$ids   = array_merge( $ids, $chunk );
			$last  = (int) end( $chunk );
		} while ( ! empty( $chunk ) );

		return $ids;
	}
}
