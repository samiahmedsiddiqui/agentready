<?php
/**
 * Verify & Preview page: checks a page against its stored Markdown, and scans every stored page.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Admin;

use AgentReady\Cache\Batch;
use AgentReady\Cache\PageCache;
use AgentReady\Contracts\Hookable;
use AgentReady\Environment\Environment;
use AgentReady\Http\Urls;
use AgentReady\Rendering\RenderResult;
use AgentReady\Rendering\Selector;
use AgentReady\Verify\Scan;
use AgentReady\Verify\Verifier;
use AgentReady\Verify\VerifyResult;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/** The Verify and Preview page: compares one page with its stored copy, and scans all pages. */
final class VerifyPage implements Hookable {

	/** Page slug. */
	public const SLUG = 'agentready-verify';

	/** Nonce action of the check form. */
	private const NONCE = 'agentready_verify';
	/** Nonce action of the scan buttons. */
	private const SCAN_NONCE = 'agentready_scan';

	/**
	 * @param Urls        $urls        Finds the page for an address.
	 * @param Verifier    $verifier    Compares one page.
	 * @param Notices     $notices     Notices to show.
	 * @param Scan        $scan        Scan of all pages.
	 * @param Batch       $batch       Generates missing copies.
	 * @param PageCache   $pages       Queues refreshes.
	 * @param Environment $environment Tells whether the plugin is running on this site.
	 */
	public function __construct(
		private Urls $urls,
		private Verifier $verifier,
		private Notices $notices,
		private Scan $scan,
		private Batch $batch,
		private PageCache $pages,
		private Environment $environment
	) {}

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_agentready_scan', array( $this, 'handle_scan' ) );
	}

	/**
	 * Address of the page.
	 *
	 * @param array<string, string|int> $args Extra query arguments.
	 * @return string Admin address.
	 */
	public static function url( array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Loads the style and script on this page.
	 *
	 * @param string $hook Admin page hook.
	 * @return void
	 */
	public function enqueue( string $hook ): void {
		if ( 'agentready_page_' . self::SLUG !== $hook ) {
			return;
		}

		Assets::style();
		wp_enqueue_script( 'agentready-admin', AGENTREADY_URL . 'assets/js/admin.js', array(), Assets::version( 'assets/js/admin.js' ), true );
		wp_enqueue_script( 'agentready-verify', AGENTREADY_URL . 'assets/js/verify.js', array( 'wp-api-fetch' ), Assets::version( 'assets/js/verify.js' ), true );
	}

	/**
	 * Prints the page with the tab that was asked for.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab switch.
		$tab = 'all' === $tab ? 'all' : 'one';

		View::render(
			'verify-page',
			array(
				'tab'     => $tab,
				'tabs'    => array(
					'one' => array( __( 'One page', 'agentready' ), self::url() ),
					'all' => array( __( 'All pages', 'agentready' ), self::url( array( 'tab' => 'all' ) ) ),
				),
				'notices' => $this->notices->data(),
				'content' => 'all' === $tab ? array( $this, 'render_all' ) : array( $this, 'render_one' ),
			)
		);
	}

	/**
	 * Prints the tab that checks one page, and the result when an address was given.
	 *
	 * @return void
	 */
	public function render_one(): void {
		$url      = '';
		$selector = '';
		$strip    = '';

		if ( isset( $_POST['agentready_verify_url'] ) ) {
			check_admin_referer( self::NONCE );
			$url      = trim( sanitize_text_field( wp_unslash( $_POST['agentready_verify_url'] ) ) );
			$selector = isset( $_POST['agentready_verify_selector'] ) ? sanitize_text_field( wp_unslash( $_POST['agentready_verify_selector'] ) ) : '';
			$strip    = isset( $_POST['agentready_verify_strip'] ) ? sanitize_textarea_field( wp_unslash( $_POST['agentready_verify_strip'] ) ) : '';
		} elseif ( isset( $_GET['url'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check, nothing is changed.
			$url = trim( sanitize_text_field( wp_unslash( $_GET['url'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check, nothing is changed.
		}

		View::render(
			'verify',
			array(
				'nonce'       => self::NONCE,
				'action'      => self::url(),
				'url'         => $url,
				'selector'    => $selector,
				'strip'       => $strip,
				'placeholder' => home_url( '/' ),
			)
		);

		if ( '' !== $url ) {
			$this->result( $url, $selector, $strip );
		}
	}

	/**
	 * Prints the tab that scans every page.
	 *
	 * @return void
	 */
	public function render_all(): void {
		$status = $this->scan->status();
		$rows   = array();

		foreach ( $status['differs'] as $row ) {
			$rows[] = array(
				'title'   => $row['title'],
				'changed' => $row['changed'],
				'url'     => self::url( array( 'url' => (string) get_permalink( $row['id'] ) ) ),
			);
		}

		View::render(
			'scan',
			array(
				'status'        => $status,
				'rows'          => $rows,
				'inactive'      => null !== $this->environment->reason(),
				'cron_disabled' => $this->environment->cron_disabled(),
				'action'        => admin_url( 'admin-post.php' ),
				'nonce'         => self::SCAN_NONCE,
				'started'       => $status['started'] > 0 ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $status['started'] ) : '',
				'finished'      => $status['finished'] > 0 ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $status['finished'] ) : '',
			)
		);
	}

	/**
	 * Handles the scan buttons: start, stop, update the pages that differ, or generate the missing ones.
	 *
	 * @return void
	 */
	public function handle_scan(): void {
		check_admin_referer( self::SCAN_NONCE );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'agentready' ), 403 );
		}

		$action = isset( $_POST['scan_action'] ) ? sanitize_key( wp_unslash( $_POST['scan_action'] ) ) : '';
		$notice = 'unknown';
		$count  = 0;

		if ( 'stop' === $action ) {
			$this->scan->stop();
			$notice = 'scan_stopped';
		} elseif ( ! $this->environment->is_active() ) {
			$notice = 'inactive';
		} elseif ( 'start' === $action ) {
			$this->scan->start();
			$notice = 'scan_started';
		} elseif ( 'update_outdated' === $action ) {
			$ids = $this->scan->differing_ids();
			$this->pages->queue( $ids );
			$count  = count( $ids );
			$notice = 'queued';
		} elseif ( 'generate_missing' === $action ) {
			$this->batch->start( false );
			$notice = 'missing_started';
		}

		wp_safe_redirect(
			self::url(
				array(
					'tab'               => 'all',
					'agentready_notice' => $notice,
					'agentready_count'  => $count,
				)
			)
		);
		exit;
	}

	/**
	 * Checks the page at an address and prints the result.
	 *
	 * @param string $url      Address of the page.
	 * @param string $selector Content selectors to try for this check only.
	 * @param string $strip    Selectors of parts to remove for this check only.
	 * @return void
	 */
	private function result( string $url, string $selector, string $strip ): void {
		$post = $this->urls->resolve_url( $url );

		if ( ! $post instanceof WP_Post ) {
			$this->notice( 'error', __( 'No page was found for that URL on this site.', 'agentready' ) );
			return;
		}

		$selectors = new Selector();
		$overrides = array();
		$content   = implode( ', ', $selectors->parse_list( $selector )['valid'] );
		$extra     = $selectors->parse_list( $strip )['valid'];

		if ( '' !== $content ) {
			$overrides['selector'] = $content;
		}

		if ( ! empty( $extra ) ) {
			$overrides['strip'] = $extra;
		}

		if ( ! empty( $overrides ) ) {
			$this->notice( 'info', __( 'The selectors below were applied to this check only. The comparison shows what would change if you saved them.', 'agentready' ) );
		}

		$result = $this->verifier->verify( $post, $overrides );

		if ( null !== $result->page && RenderResult::FALLBACK === $result->page->source ) {
			$this->notice( 'warning', '' === $result->page->notice ? __( 'Generated from the post content only.', 'agentready' ) : $result->page->notice );
		}

		$permalink = (string) $this->urls->permalink( $post );

		View::render(
			'verify-result',
			array(
				'post'       => $post,
				'title'      => get_the_title( $post ),
				'md_url'     => $this->urls->markdown_url( $post ),
				'result'     => $result,
				'update_url' => VerifyResult::NOT_SERVED === $result->state ? '' : $this->update_url( $post, $permalink ),
				'stored_at'  => null === $result->entry ? '' : wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $result->entry->timestamp() ),
				'applied'    => ! empty( $overrides ),
			)
		);
	}

	/**
	 * Address that regenerates the stored copy of a page and comes back to the check.
	 *
	 * @param WP_Post $post      The page.
	 * @param string  $permalink Address of the page.
	 * @return string The address with a nonce, or an empty string when the plugin is off.
	 */
	private function update_url( WP_Post $post, string $permalink ): string {
		if ( ! $this->environment->is_active() ) {
			return '';
		}

		return wp_nonce_url(
			add_query_arg(
				array(
					'action'            => 'agentready_regenerate_post',
					'post'              => $post->ID,
					'agentready_return' => rawurlencode( self::url( array( 'url' => $permalink ) ) ),
				),
				admin_url( 'admin-post.php' )
			),
			'agentready_regenerate_' . $post->ID
		);
	}

	/**
	 * Prints a notice.
	 *
	 * @param string $type    Notice type: error, info or warning.
	 * @param string $message Text of the notice.
	 * @return void
	 */
	private function notice( string $type, string $message ): void {
		printf( '<div class="notice notice-%1$s inline"><p>%2$s</p></div>', esc_attr( $type ), esc_html( $message ) );
	}
}
