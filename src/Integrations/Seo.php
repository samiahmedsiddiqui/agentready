<?php
/**
 * Refreshes a page when its SEO description or noindex setting changes.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Integrations;

use AgentWelcome\Cache\Invalidator;
use AgentWelcome\Content\Description;
use AgentWelcome\Content\Noindex;
use AgentWelcome\Contracts\Hookable;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/** Refreshes a page when its SEO description or noindex setting changes. */
final class Seo implements Hookable {

	/**
	 * @param Invalidator $invalidator Refreshes one page.
	 */
	public function __construct( private Invalidator $invalidator ) {}

	/**
	 * @return void
	 */
	public function register(): void {
		foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $hook ) {
			add_action( $hook, array( $this, 'on_meta' ), 10, 3 );
		}
	}

	/**
	 * Refreshes the page when the changed meta key is one that feeds the description or the noindex choice.
	 *
	 * @param mixed $meta_id   Meta row ID.
	 * @param mixed $object_id Page ID.
	 * @param mixed $meta_key  Meta key that changed.
	 * @return void
	 */
	public function on_meta( mixed $meta_id, mixed $object_id, mixed $meta_key ): void {
		if ( ! in_array( $meta_key, array_merge( Description::SEO_META_KEYS, Noindex::KEYS ), true ) ) {
			return;
		}

		$post = get_post( (int) $object_id );

		if ( $post instanceof WP_Post ) {
			$this->invalidator->refresh( $post );
		}
	}
}
