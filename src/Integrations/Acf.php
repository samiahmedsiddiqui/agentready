<?php
/**
 * Refreshes every page when an ACF options page is saved, because options can show up anywhere.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Integrations;

use AgentReady\Cache\Batch;
use AgentReady\Contracts\Hookable;
use AgentReady\Environment\Environment;

defined( 'ABSPATH' ) || exit;

final class Acf implements Hookable {

	public function __construct(
		private Batch $batch,
		private Environment $environment
	) {}

	public function register(): void {
		add_action( 'acf/save_post', array( $this, 'on_save' ), 20 );
	}

	public function on_save( mixed $post_id ): void {
		if ( self::is_options_target( $post_id ) && $this->environment->is_active() ) {
			$this->batch->start( true );
		}
	}

	/**
	 * ACF saves options pages under a name such as "options", while posts, users, terms and widgets use numbers or a prefix.
	 */
	public static function is_options_target( mixed $post_id ): bool {
		if ( ! is_string( $post_id ) || '' === $post_id || is_numeric( $post_id ) ) {
			return false;
		}

		return 1 !== preg_match( '/^(new_post|user_|widget_|comment_)|_\d+$/', $post_id );
	}
}
