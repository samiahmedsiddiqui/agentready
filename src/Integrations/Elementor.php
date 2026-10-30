<?php
/**
 * Refreshes a page when an Elementor document is saved, and every page when a saved template changes.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Integrations;

use AgentReady\Cache\Batch;
use AgentReady\Cache\Invalidator;
use AgentReady\Contracts\Hookable;
use AgentReady\Environment\Environment;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Elementor implements Hookable {

	private const TEMPLATE_TYPE = 'elementor_library';

	public function __construct(
		private Invalidator $invalidator,
		private Batch $batch,
		private Environment $environment
	) {}

	public function register(): void {
		add_action( 'elementor/document/after_save', array( $this, 'on_save' ), 20 );
	}

	public function on_save( mixed $document ): void {
		if ( ! is_object( $document ) || ! method_exists( $document, 'get_main_id' ) || ! $this->environment->is_active() ) {
			return;
		}

		$post = get_post( (int) $document->get_main_id() );

		if ( ! $post instanceof WP_Post ) {
			return;
		}

		if ( self::TEMPLATE_TYPE === $post->post_type ) {
			$this->batch->start( true );
			return;
		}

		$this->invalidator->refresh( $post );
	}
}
