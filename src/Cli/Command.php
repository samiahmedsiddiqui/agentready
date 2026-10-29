<?php
/**
 * WP-CLI commands: wp agentready cache clear | regenerate | status.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Cli;

use AgentReady\Cache\Candidates;
use AgentReady\Cache\Coverage;
use AgentReady\Cache\PageCache;
use AgentReady\Cache\Store;
use AgentReady\Content\Eligibility;
use AgentReady\Content\PostTypes;
use AgentReady\Contracts\Hookable;
use AgentReady\Environment\Environment;
use WP_CLI;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Command implements Hookable {

	public function __construct(
		private Store $store,
		private PageCache $pages,
		private PostTypes $post_types,
		private Eligibility $eligibility,
		private Environment $environment,
		private Candidates $candidates,
		private Coverage $coverage
	) {}

	public function register(): void {
		WP_CLI::add_command( 'agentready cache', $this );
	}

	/**
	 * Deletes every stored Markdown copy.
	 *
	 * ## EXAMPLES
	 *
	 *     wp agentready cache clear
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
	 *     wp agentready cache regenerate 42 57
	 *     wp agentready cache regenerate --all
	 *
	 * @param string[]             $args       Post IDs.
	 * @param array<string, mixed> $assoc_args Named arguments.
	 */
	public function regenerate( array $args, array $assoc_args ): void {
		$reason = $this->environment->reason();

		if ( null !== $reason ) {
			WP_CLI::error( 'AgentReady is inactive here. ' . $this->environment->describe( $reason ) );
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
	 *     wp agentready cache status
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
	 * @return int[]
	 */
	private function all_ids(): array {
		$ids  = array();
		$last = 0;

		do {
			$chunk = $this->candidates->after( $this->post_types->enabled(), $last, 200 );
			$ids   = array_merge( $ids, $chunk );
			$last  = (int) end( $chunk );
		} while ( ! empty( $chunk ) );

		return $ids;
	}
}
