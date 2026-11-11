<?php
/**
 * Cache buttons outside the editor: settings page, post list row action and bulk action.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Admin;

use AgentWelcome\Cache\Batch;
use AgentWelcome\Cache\Changes;
use AgentWelcome\Cache\PageCache;
use AgentWelcome\Cache\Store;
use AgentWelcome\Content\Eligibility;
use AgentWelcome\Content\PostTypes;
use AgentWelcome\Contracts\Hookable;
use AgentWelcome\Environment\Environment;
use AgentWelcome\Environment\SiteAddress;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/** Handles the buttons and links that clear or rebuild the stored Markdown: the settings buttons, the row action and the bulk action. */
final class CacheActions implements Hookable {

	/** Name of the bulk action. */
	private const BULK = 'agentwelcome_regenerate';

	/**
	 * @param Store       $store       Stored copies.
	 * @param PageCache   $pages       Generates the copies.
	 * @param Batch       $batch       Refreshes many pages.
	 * @param PostTypes   $post_types  Post types that are served.
	 * @param Eligibility $eligibility Decides whether a page is served.
	 * @param Environment $environment Tells whether the plugin is running on this site.
	 * @param Changes     $changes     Theme and plugin changes.
	 */
	public function __construct(
		private Store $store,
		private PageCache $pages,
		private Batch $batch,
		private PostTypes $post_types,
		private Eligibility $eligibility,
		private Environment $environment,
		private Changes $changes
	) {}

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_agentwelcome_cache', array( $this, 'handle_settings' ) );
		add_action( 'admin_post_agentwelcome_regenerate_post', array( $this, 'handle_row' ) );
		add_action( 'admin_post_agentwelcome_resume', array( $this, 'handle_resume' ) );
		add_action( 'admin_post_agentwelcome_dismiss', array( $this, 'handle_dismiss' ) );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_filter( 'page_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'register_bulk' ) );
		add_action( 'admin_notices', array( $this, 'notices' ) );
	}

	/**
	 * Handles the Regenerate all and Clear cache buttons.
	 *
	 * @return void
	 */
	public function handle_settings(): void {
		check_admin_referer( 'agentwelcome_cache' );

		$this->require_manage_options();

		$action = isset( $_POST['cache_action'] ) ? sanitize_key( wp_unslash( $_POST['cache_action'] ) ) : '';
		$notice = 'unknown';

		if ( 'clear' === $action ) {
			$this->store->clear();
			$notice = $this->environment->is_active() ? 'cleared_rebuilding' : 'cleared';

			if ( $this->environment->is_active() ) {
				$this->batch->start( false );
			}
		} elseif ( 'regenerate_all' === $action ) {
			$notice = 'inactive';

			if ( $this->environment->is_active() ) {
				$this->batch->start( true );
				$notice = 'regenerating';
			}
		}

		wp_safe_redirect( add_query_arg( 'agentwelcome_notice', $notice, admin_url( 'admin.php?page=' . SettingsPage::SLUG ) ) );
		exit;
	}

	/**
	 * Handles the Resume button, which accepts the current site address.
	 *
	 * @return void
	 */
	public function handle_resume(): void {
		check_admin_referer( 'agentwelcome_resume' );

		$this->require_manage_options();

		SiteAddress::remember();

		wp_safe_redirect( add_query_arg( 'agentwelcome_notice', 'resumed', admin_url( 'admin.php?page=' . SettingsPage::SLUG ) ) );
		exit;
	}

	/**
	 * Handles the Dismiss link of a change notice.
	 *
	 * @return void
	 */
	public function handle_dismiss(): void {
		check_admin_referer( 'agentwelcome_dismiss' );

		$this->require_manage_options();

		$key = isset( $_GET['key'] ) ? sanitize_key( wp_unslash( $_GET['key'] ) ) : '';

		if ( in_array( $key, array( Changes::REFRESH, Changes::PLUGINS ), true ) ) {
			$this->changes->dismiss( $key );
		}

		$back = wp_get_referer();

		wp_safe_redirect( false === $back ? admin_url( 'admin.php?page=' . SettingsPage::SLUG ) : $back );
		exit;
	}

	/**
	 * Handles the Regenerate Markdown link of one page.
	 *
	 * @return void
	 */
	public function handle_row(): void {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

		check_admin_referer( 'agentwelcome_regenerate_' . $post_id );

		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'agentwelcome' ), 403 );
		}

		$notice = 'regenerated';

		if ( ! $this->environment->is_active() ) {
			$notice = 'inactive';
		} elseif ( ! $this->eligibility->is_eligible( $post ) ) {
			$notice = 'not_served';
		} elseif ( null === $this->pages->regenerate( $post ) ) {
			$notice = 'busy';
		}

		$back   = wp_get_referer();
		$return = isset( $_GET['agentwelcome_return'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_GET['agentwelcome_return'] ) ), '' ) : '';

		if ( '' !== $return ) {
			$back = $return;
		}

		wp_safe_redirect( add_query_arg( 'agentwelcome_notice', $notice, false === $back ? admin_url( 'edit.php' ) : remove_query_arg( array( 'agentwelcome_notice', 'agentwelcome_count' ), $back ) ) );
		exit;
	}

	/**
	 * Stops the request when the user may not manage options.
	 *
	 * @return void
	 */
	private function require_manage_options(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'agentwelcome' ), 403 );
		}
	}

	/**
	 * Adds Regenerate Markdown to the row actions of a served page.
	 *
	 * @param array<string, string> $actions Row actions.
	 * @param WP_Post               $post    The page.
	 * @return array<string, string> The actions.
	 */
	public function row_actions( array $actions, WP_Post $post ): array {
		if ( ! $this->environment->is_active() || ! current_user_can( 'edit_post', $post->ID ) || ! $this->eligibility->is_eligible( $post ) ) {
			return $actions;
		}

		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'agentwelcome_regenerate_post',
					'post'   => $post->ID,
				),
				admin_url( 'admin-post.php' )
			),
			'agentwelcome_regenerate_' . $post->ID
		);

		$actions['agentwelcome_regenerate'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Regenerate Markdown', 'agentwelcome' ) . '</a>';

		return $actions;
	}

	/**
	 * Adds the bulk action to the lists of the served post types.
	 *
	 * @return void
	 */
	public function register_bulk(): void {
		foreach ( $this->post_types->enabled() as $type ) {
			add_filter( "bulk_actions-edit-{$type}", array( $this, 'bulk_actions' ) );
			add_filter( "handle_bulk_actions-edit-{$type}", array( $this, 'handle_bulk' ), 10, 3 );
		}
	}

	/**
	 * Adds Regenerate Markdown to the bulk actions.
	 *
	 * @param array<string, string> $actions Bulk actions.
	 * @return array<string, string> The actions.
	 */
	public function bulk_actions( array $actions ): array {
		if ( $this->environment->is_active() ) {
			$actions[ self::BULK ] = __( 'Regenerate Markdown', 'agentwelcome' );
		}

		return $actions;
	}

	/**
	 * Queues a refresh for the selected pages that are served.
	 *
	 * @param mixed $redirect Redirect address.
	 * @param mixed $action   Bulk action name.
	 * @param mixed $ids      Selected page IDs.
	 * @return mixed The address to go to, with the result added.
	 */
	public function handle_bulk( mixed $redirect, mixed $action, mixed $ids ): mixed {
		if ( self::BULK !== $action || ! is_string( $redirect ) || ! $this->environment->is_active() ) {
			return $redirect;
		}

		$queued = array();

		foreach ( (array) $ids as $id ) {
			$post = get_post( (int) $id );

			if ( $post instanceof WP_Post && current_user_can( 'edit_post', $post->ID ) && $this->eligibility->is_eligible( $post ) ) {
				$queued[] = $post->ID;
			}
		}

		$this->pages->queue( $queued );

		return add_query_arg(
			array(
				'agentwelcome_notice' => 'queued',
				'agentwelcome_count'  => count( $queued ),
			),
			$redirect
		);
	}

	/**
	 * Prints the result of the last action.
	 *
	 * @return void
	 */
	public function notices(): void {
		$code = isset( $_GET['agentwelcome_notice'] ) ? sanitize_key( wp_unslash( $_GET['agentwelcome_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only message code.

		if ( '' === $code ) {
			return;
		}

		$count = isset( $_GET['agentwelcome_count'] ) ? absint( $_GET['agentwelcome_count'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only count.
		$map   = array(
			'regenerated'        => array( 'success', __( 'The Markdown was regenerated.', 'agentwelcome' ) ),
			/* translators: %s: number of pages. */
			'queued'             => array( 'success', sprintf( _n( '%s page is queued for regeneration.', '%s pages are queued for regeneration.', $count, 'agentwelcome' ), number_format_i18n( $count ) ) ),
			'regenerating'       => array( 'success', __( 'Every page is marked out of date and is being rebuilt in the background. Current copies keep being served meanwhile.', 'agentwelcome' ) ),
			'cleared_rebuilding' => array( 'success', __( 'The stored Markdown was deleted. Pages are being rebuilt in the background.', 'agentwelcome' ) ),
			'cleared'            => array( 'success', __( 'The stored Markdown was deleted.', 'agentwelcome' ) ),
			'scan_started'       => array( 'success', __( 'The scan started. It runs in the background and this page updates by itself.', 'agentwelcome' ) ),
			'scan_stopped'       => array( 'success', __( 'The scan was stopped.', 'agentwelcome' ) ),
			'missing_started'    => array( 'success', __( 'Pages without a stored copy are being generated in the background.', 'agentwelcome' ) ),
			'resumed'            => array( 'success', __( 'AgentWelcome is resumed on this address.', 'agentwelcome' ) ),
			'busy'               => array( 'warning', __( 'This page is being generated right now. Try again in a few seconds.', 'agentwelcome' ) ),
			'inactive'           => array( 'warning', __( 'AgentWelcome is inactive on this site, so nothing was regenerated.', 'agentwelcome' ) ),
			'not_served'         => array( 'warning', __( 'This page is not served as Markdown, so nothing was regenerated.', 'agentwelcome' ) ),
		);

		if ( ! isset( $map[ $code ] ) ) {
			return;
		}

		if ( $this->environment->cron_disabled() && in_array( $code, array( 'queued', 'regenerating', 'cleared_rebuilding', 'missing_started', 'scan_started' ), true ) ) {
			$map[ $code ][1] .= ' ' . __( 'WP-Cron is disabled on this site, so this waits for a real cron job that calls wp-cron.php.', 'agentwelcome' );

			if ( 'scan_started' === $code ) {
				$map[ $code ][1] = __( 'The scan started. WP-Cron is disabled on this site, so this page runs it while it stays open.', 'agentwelcome' );
			}
		}

		printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $map[ $code ][0] ), esc_html( $map[ $code ][1] ) );
	}
}
