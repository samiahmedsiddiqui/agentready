<?php
/**
 * Refreshes a page when its SEO description or noindex setting changes.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Integrations;

use AgentReady\Cache\Invalidator;
use AgentReady\Content\Description;
use AgentReady\Content\Noindex;
use AgentReady\Contracts\Hookable;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Seo implements Hookable {

	public function __construct( private Invalidator $invalidator ) {}

	public function register(): void {
		foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $hook ) {
			add_action( $hook, array( $this, 'on_meta' ), 10, 3 );
		}
	}

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
