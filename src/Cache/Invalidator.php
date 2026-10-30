<?php
/**
 * Keeps stored Markdown in step with content and settings changes.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Cache;

use AgentReady\Content\PostMeta;
use AgentReady\Content\PostTypes;
use AgentReady\Contracts\Hookable;
use AgentReady\Settings\Options;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Invalidator implements Hookable {

	private const RENDER_KEYS = array( 'content_selector', 'strip_selectors', 'http_auth_enabled', 'http_auth_user', 'http_auth_password' );
	private const SCOPE_KEYS  = array( 'excluded_post_types', 'excluded_posts', 'excluded_terms' );

	public function __construct(
		private Store $store,
		private PageCache $pages,
		private Batch $batch,
		private PostTypes $post_types
	) {}

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

	public function refresh( WP_Post $post ): void {
		$this->touch( $post );
	}

	public function on_save( int $post_id, WP_Post $post ): void {
		$this->touch( $post );
	}

	public function on_meta( mixed $meta_id, mixed $object_id, mixed $meta_key ): void {
		if ( ! in_array( $meta_key, array( PostMeta::EXCLUDE, PostMeta::DESCRIPTION ), true ) ) {
			return;
		}

		$post = get_post( (int) $object_id );

		if ( $post instanceof WP_Post ) {
			$this->touch( $post );
		}
	}

	public function on_terms( int $object_id ): void {
		$post = get_post( $object_id );

		if ( $post instanceof WP_Post ) {
			$this->touch( $post );
		}
	}

	public function on_transition( string $new_status, string $old_status, WP_Post $post ): void {
		if ( 'publish' === $old_status && 'publish' !== $new_status ) {
			$this->store->delete( $post->ID );
		}
	}

	/**
	 * @param mixed $previous Previous settings.
	 * @param mixed $current  New settings.
	 */
	public function on_settings_updated( mixed $previous, mixed $current ): void {
		$this->compare( is_array( $previous ) ? $previous : array(), is_array( $current ) ? $current : array() );
	}

	public function on_settings_added( string $option, mixed $value ): void {
		$this->compare( array(), is_array( $value ) ? $value : array() );
	}

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
	 * @param array<string, mixed> $previous Previous settings.
	 * @param array<string, mixed> $current  New settings.
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
	 * @param array<string, mixed> $previous Previous settings.
	 * @param array<string, mixed> $current  New settings.
	 * @param string[]             $keys     Keys to compare.
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
