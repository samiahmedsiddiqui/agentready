<?php
/**
 * Keeps stored Markdown in step with content and settings changes.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Cache;

use AgentWelcome\Content\PostMeta;
use AgentWelcome\Content\PostTypes;
use AgentWelcome\Contracts\Hookable;
use AgentWelcome\Settings\Options;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/** Marks stored copies out of date when pages or settings change. */
final class Invalidator implements Hookable {

	/** Settings that change how pages are rendered, so every copy must be rebuilt. */
	private const RENDER_KEYS = array( 'content_selector', 'strip_selectors', 'http_auth_enabled', 'http_auth_user', 'http_auth_password' );
	/** Settings that change which pages are served. */
	private const SCOPE_KEYS = array( 'excluded_post_types', 'excluded_posts', 'excluded_terms' );

	/**
	 * @param Store     $store      Stored copies.
	 * @param PageCache $pages      Queues refreshes.
	 * @param Batch     $batch      Runs refreshes and removals of many pages.
	 * @param PostTypes $post_types Post types that are served.
	 */
	public function __construct(
		private Store $store,
		private PageCache $pages,
		private Batch $batch,
		private PostTypes $post_types
	) {}

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'save_post', array( $this, 'on_save' ), 99, 2 );
		add_action( 'set_object_terms', array( $this, 'on_terms' ) );
		foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $hook ) {
			add_action( $hook, array( $this, 'on_meta' ), 10, 3 );
		}

		add_action( 'transition_post_status', array( $this, 'on_transition' ), 10, 3 );
		add_action( 'deleted_post', array( $this->store, 'delete' ) );
		add_action( 'update_option_' . Options::KEY, array( $this, 'on_settings_updated' ), 10, 2 );
		add_action( 'add_option_' . Options::KEY, array( $this, 'on_settings_added' ), 10, 2 );
	}

	/**
	 * Refreshes a page as if it had been saved.
	 *
	 * @param WP_Post $post The page.
	 * @return void
	 */
	public function refresh( WP_Post $post ): void {
		$this->touch( $post );
	}

	/**
	 * Handles a saved page.
	 *
	 * @param int     $post_id Page ID.
	 * @param WP_Post $post    The page.
	 * @return void
	 */
	public function on_save( int $post_id, WP_Post $post ): void {
		$this->touch( $post );
	}

	/**
	 * Handles a change of the per-page settings of this plugin.
	 *
	 * @param mixed $meta_id   Meta row ID.
	 * @param mixed $object_id Page ID.
	 * @param mixed $meta_key  Meta key that changed.
	 * @return void
	 */
	public function on_meta( mixed $meta_id, mixed $object_id, mixed $meta_key ): void {
		if ( ! in_array( $meta_key, array( PostMeta::EXCLUDE, PostMeta::DESCRIPTION ), true ) ) {
			return;
		}

		$post = get_post( (int) $object_id );

		if ( $post instanceof WP_Post ) {
			$this->touch( $post );
		}
	}

	/**
	 * Handles a change of the terms of a page.
	 *
	 * @param int $object_id Page ID.
	 * @return void
	 */
	public function on_terms( int $object_id ): void {
		$post = get_post( $object_id );

		if ( $post instanceof WP_Post ) {
			$this->touch( $post );
		}
	}

	/**
	 * Removes the copy of a page that stopped being published.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post       The page.
	 * @return void
	 */
	public function on_transition( string $new_status, string $old_status, WP_Post $post ): void {
		if ( 'publish' === $old_status && 'publish' !== $new_status ) {
			$this->store->delete( $post->ID );
		}
	}

	/**
	 * Handles a change of the plugin settings.
	 *
	 * @param mixed $previous Previous settings.
	 * @param mixed $current  New settings.
	 * @return void
	 */
	public function on_settings_updated( mixed $previous, mixed $current ): void {
		$this->compare( is_array( $previous ) ? $previous : array(), is_array( $current ) ? $current : array() );
	}

	/**
	 * Handles the first save of the plugin settings.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  New settings.
	 * @return void
	 */
	public function on_settings_added( string $option, mixed $value ): void {
		$this->compare( array(), is_array( $value ) ? $value : array() );
	}

	/**
	 * Marks the copy of a page out of date and queues a refresh, or removes the copy when the page is no longer served.
	 *
	 * @param WP_Post $post The page.
	 * @return void
	 */
	private function touch( WP_Post $post ): void {
		if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) || ! $this->post_types->is_enabled( $post->post_type ) ) {
			return;
		}

		if ( 'publish' !== $post->post_status || '' !== $post->post_password ) {
			$this->store->delete( $post->ID );
			return;
		}

		$this->store->mark_stale( $post->ID );
		$this->pages->schedule( $post->ID );
	}

	/**
	 * Starts the refresh or removal that a settings change calls for.
	 *
	 * @param array<string, mixed> $previous Previous settings.
	 * @param array<string, mixed> $current  New settings.
	 * @return void
	 */
	private function compare( array $previous, array $current ): void {
		$previous = array_merge( Options::defaults(), $previous );
		$current  = array_merge( Options::defaults(), $current );

		if ( $this->changed( $previous, $current, self::RENDER_KEYS ) ) {
			$this->batch->start( true );
		}

		if ( $this->changed( $previous, $current, self::SCOPE_KEYS ) ) {
			$this->batch->schedule_prune();
		}

		if ( $this->changed( $previous, $current, array( 'exclude_noindex' ) ) ) {
			if ( $current['exclude_noindex'] ) {
				$this->batch->schedule_prune();
			} else {
				$this->batch->start( false );
			}
		}
	}

	/**
	 * Whether any of the given settings differs.
	 *
	 * @param array<string, mixed> $previous Previous settings.
	 * @param array<string, mixed> $current  New settings.
	 * @param string[]             $keys     Settings to compare.
	 * @return bool True when at least one differs.
	 */
	private function changed( array $previous, array $current, array $keys ): bool {
		foreach ( $keys as $key ) {
			if ( $previous[ $key ] !== $current[ $key ] ) {
				return true;
			}
		}

		return false;
	}
}
