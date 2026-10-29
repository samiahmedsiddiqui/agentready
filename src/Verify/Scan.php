<?php
/**
 * Compares every stored page with a fresh build in the background, and reports what differs. Changes nothing.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Verify;

use AgentReady\Cache\Coverage;
use AgentReady\Cache\Store;
use AgentReady\Contracts\Hookable;
use AgentReady\Environment\Environment;
use AgentReady\Rendering\RenderResult;

defined( 'ABSPATH' ) || exit;

final class Scan implements Hookable {

	public const JOB    = 'agentready_scan_batch';
	public const OPTION = 'agentready_scan';

	private const SIZE   = 3;
	private const BUDGET = 20;
	private const LIMIT  = 500;

	public function __construct(
		private Store $store,
		private Verifier $verifier,
		private Environment $environment,
		private Coverage $coverage
	) {}

	public function register(): void {
		add_action( self::JOB, array( $this, 'run' ) );
	}

	/**
	 * @return array{running: bool, started: int, finished: int, last_id: int, checked: int, in_sync: int, skipped: int, fallback: int, not_stored: int, differs: array<int, array{id: int, title: string, changed: int}>}
	 */
	public function status(): array {
		$state = get_option( self::OPTION );

		return array_merge( self::blank(), is_array( $state ) ? $state : array() );
	}

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

	public function stop(): void {
		$state             = $this->status();
		$state['running']  = false;
		$state['finished'] = time();

		update_option( self::OPTION, $state, false );
		wp_unschedule_hook( self::JOB );
	}

	/**
	 * @return int[] IDs of the pages that differ.
	 */
	public function differing_ids(): array {
		return array_map( static fn( array $row ): int => $row['id'], $this->status()['differs'] );
	}

	public function run(): void {
		$state = $this->status();

		if ( ! $state['running'] ) {
			return;
		}

		if ( ! $this->environment->is_active() ) {
			$this->stop();
			return;
		}

		$ids     = $this->store->ids( $state['last_id'], self::SIZE );
		$started = microtime( true );
		$paused  = false;

		foreach ( $ids as $id ) {
			if ( microtime( true ) - $started > self::BUDGET ) {
				$paused = true;
				break;
			}

			$state            = $this->check( $id, $state );
			$state['last_id'] = $id;
			update_option( self::OPTION, $state, false );
		}

		if ( ! $paused && count( $ids ) < self::SIZE ) {
			$state['running']    = false;
			$state['finished']   = time();
			$state['not_stored'] = $this->coverage->summary()['missing'];
			update_option( self::OPTION, $state, false );
			return;
		}

		$this->schedule( 3 );
	}

	/**
	 * @param array<string, mixed> $state Scan state.
	 * @return array<string, mixed>
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

		if ( VerifyResult::DIFFERS === $result->state && count( $state['differs'] ) < self::LIMIT ) {
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

	private function schedule( int $delay ): void {
		if ( false === wp_next_scheduled( self::JOB ) ) {
			wp_schedule_single_event( time() + $delay, self::JOB );
		}
	}

	/**
	 * @return array{running: bool, started: int, finished: int, last_id: int, checked: int, in_sync: int, skipped: int, fallback: int, not_stored: int, differs: array<int, array{id: int, title: string, changed: int}>}
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
