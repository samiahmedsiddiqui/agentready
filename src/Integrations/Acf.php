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

/** Refreshes every page when an ACF options page is saved, because its fields can appear on any page. */
final class Acf implements Hookable {

	/** ACF save targets that are not an options page: new posts, users, widgets, comments, and posts or terms by ID. */
	private const NOT_OPTIONS_TARGET = '/^(new_post|user_|widget_|comment_)|_\d+$/';

	/**
	 * @param Batch       $batch       Runs the refresh of every page.
	 * @param Environment $environment Tells whether the plugin is running on this site.
	 */
	public function __construct(
		private Batch $batch,
		private Environment $environment
	) {}

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'acf/save_post', array( $this, 'on_save' ), 20 );
	}

	/**
	 * Starts the refresh when the saved target is an options page.
	 *
	 * @param mixed $post_id ACF save target.
	 * @return void
	 */
	public function on_save( mixed $post_id ): void {
		if ( self::is_options_target( $post_id ) && $this->environment->is_active() ) {
			$this->batch->start( true );
		}
	}

	/**
	 * Whether an ACF save target is an options page. ACF saves options pages under a name such as "options", while posts, users, terms and widgets use numbers or a prefix.
	 *
	 * @param mixed $post_id ACF save target.
	 * @return bool True for an options page.
	 */
	public static function is_options_target( mixed $post_id ): bool {
		if ( ! is_string( $post_id ) || '' === $post_id || is_numeric( $post_id ) ) {
			return false;
		}

		return 1 !== preg_match( self::NOT_OPTIONS_TARGET, $post_id );
	}
}
