<?php
/**
 * Cache buttons outside the editor: settings page, post list row action and bulk action.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Admin;

use AgentReady\Cache\Batch;
use AgentReady\Cache\Changes;
use AgentReady\Cache\PageCache;
use AgentReady\Cache\Store;
use AgentReady\Content\Eligibility;
use AgentReady\Content\PostTypes;
use AgentReady\Contracts\Hookable;
use AgentReady\Environment\Environment;
use AgentReady\Environment\SiteAddress;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class CacheActions implements Hookable {

	private const BULK = 'agentready_regenerate';

	public function __construct(
		private Store $store,
		private PageCache $pages,
		private Batch $batch,
		private PostTypes $post_types,
		private Eligibility $eligibility,
		private Environment $environment,
		private Changes $changes
	) {}

	public function register(): void {
		add_action( 'admin_post_agentready_cache', array( $this, 'handle_settings' ) );
		add_action( 'admin_post_agentready_regenerate_post', array( $this, 'handle_row' ) );
		add_action( 'admin_post_agentready_resume', array( $this, 'handle_resume' ) );
		add_action( 'admin_post_agentready_dismiss', array( $this, 'handle_dismiss' ) );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_filter( 'page_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'register_bulk' ) );
		add_action( 'admin_notices', array( $this, 'notices' ) );
	}

	public function handle_settings(): void {
		check_admin_referer( 'agentready_cache' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'agentready' ), 403 );
		}

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

		wp_safe_redirect( add_query_arg( 'agentready_notice', $notice, admin_url( 'admin.php?page=' . SettingsPage::SLUG ) ) );
		exit;
	}

	public function handle_resume(): void {
		check_admin_referer( 'agentready_resume' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'agentready' ), 403 );
		}

		SiteAddress::remember();

		wp_safe_redirect( add_query_arg( 'agentready_notice', 'resumed', admin_url( 'admin.php?page=' . SettingsPage::SLUG ) ) );
		exit;
	}

	public function handle_dismiss(): void {
		check_admin_referer( 'agentready_dismiss' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'agentready' ), 403 );
		}

		$key = isset( $_GET['key'] ) ? sanitize_key( wp_unslash( $_GET['key'] ) ) : '';

		if ( in_array( $key, array( Changes::REFRESH, Changes::PLUGINS ), true ) ) {
			$this->changes->dismiss( $key );
		}

		$back = wp_get_referer();

		wp_safe_redirect( false === $back ? admin_url( 'admin.php?page=' . SettingsPage::SLUG ) : $back );
		exit;
	}

	public function handle_row(): void {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

		check_admin_referer( 'agentready_regenerate_' . $post_id );

		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'agentready' ), 403 );
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
		$return = isset( $_GET['agentready_return'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_GET['agentready_return'] ) ), '' ) : '';

		if ( '' !== $return ) {
			$back = $return;
		}

		wp_safe_redirect( add_query_arg( 'agentready_notice', $notice, false === $back ? admin_url( 'edit.php' ) : remove_query_arg( array( 'agentready_notice', 'agentready_count' ), $back ) ) );
		exit;
	}

	/**
	 * @param array<string, string> $actions Row actions.
	 * @return array<string, string>
	 */
	public function row_actions( array $actions, WP_Post $post ): array {
		if ( ! $this->environment->is_active() || ! current_user_can( 'edit_post', $post->ID ) || ! $this->eligibility->is_eligible( $post ) ) {
			return $actions;
		}

		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'agentready_regenerate_post',
					'post'   => $post->ID,
				),
				admin_url( 'admin-post.php' )
			),
			'agentready_regenerate_' . $post->ID
		);

		$actions['agentready_regenerate'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Regenerate Markdown', 'agentready' ) . '</a>';

		return $actions;
	}

	public function register_bulk(): void {
		foreach ( $this->post_types->enabled() as $type ) {
			add_filter( "bulk_actions-edit-{$type}", array( $this, 'bulk_actions' ) );
			add_filter( "handle_bulk_actions-edit-{$type}", array( $this, 'handle_bulk' ), 10, 3 );
		}
	}

	/**
	 * @param array<string, string> $actions Bulk actions.
	 * @return array<string, string>
	 */
	public function bulk_actions( array $actions ): array {
		if ( $this->environment->is_active() ) {
			$actions[ self::BULK ] = __( 'Regenerate Markdown', 'agentready' );
		}

		return $actions;
	}

	/**
	 * @param mixed $redirect Redirect URL.
	 * @param mixed $action   Bulk action name.
	 * @param mixed $ids      Selected post IDs.
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
				'agentready_notice' => 'queued',
				'agentready_count'  => count( $queued ),
			),
			$redirect
		);
	}

	public function notices(): void {
		$code = isset( $_GET['agentready_notice'] ) ? sanitize_key( wp_unslash( $_GET['agentready_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only message code.

		if ( '' === $code ) {
			return;
		}

		$count = isset( $_GET['agentready_count'] ) ? absint( $_GET['agentready_count'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only count.
		$map   = array(
			'regenerated'        => array( 'success', __( 'The Markdown was regenerated.', 'agentready' ) ),
			/* translators: %s: number of pages. */
			'queued'             => array( 'success', sprintf( _n( '%s page is queued for regeneration.', '%s pages are queued for regeneration.', $count, 'agentready' ), number_format_i18n( $count ) ) ),
			'regenerating'       => array( 'success', __( 'Every page is marked out of date and is being rebuilt in the background. Current copies keep being served meanwhile.', 'agentready' ) ),
			'cleared_rebuilding' => array( 'success', __( 'The stored Markdown was deleted. Pages are being rebuilt in the background.', 'agentready' ) ),
			'cleared'            => array( 'success', __( 'The stored Markdown was deleted.', 'agentready' ) ),
			'scan_started'       => array( 'success', __( 'The scan started. It runs in the background and this page updates by itself.', 'agentready' ) ),
			'scan_stopped'       => array( 'success', __( 'The scan was stopped.', 'agentready' ) ),
			'missing_started'    => array( 'success', __( 'Pages without a stored copy are being generated in the background.', 'agentready' ) ),
			'resumed'            => array( 'success', __( 'AgentReady is resumed on this address.', 'agentready' ) ),
			'busy'               => array( 'warning', __( 'This page is being generated right now. Try again in a few seconds.', 'agentready' ) ),
			'inactive'           => array( 'warning', __( 'AgentReady is inactive on this site, so nothing was regenerated.', 'agentready' ) ),
			'not_served'         => array( 'warning', __( 'This page is not served as Markdown, so nothing was regenerated.', 'agentready' ) ),
		);

		if ( ! isset( $map[ $code ] ) ) {
			return;
		}

		if ( $this->environment->cron_disabled() && in_array( $code, array( 'queued', 'regenerating', 'cleared_rebuilding', 'missing_started', 'scan_started' ), true ) ) {
			$map[ $code ][1] .= ' ' . __( 'WP-Cron is disabled on this site, so this waits for a real cron job that calls wp-cron.php.', 'agentready' );

			if ( 'scan_started' === $code ) {
				$map[ $code ][1] = __( 'The scan started. WP-Cron is disabled on this site, so this page runs it while it stays open.', 'agentready' );
			}
		}

		printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $map[ $code ][0] ), esc_html( $map[ $code ][1] ) );
	}
}
