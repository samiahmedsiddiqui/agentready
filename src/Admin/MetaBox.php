<?php
/**
 * Editor box: stored Markdown status and refresh, hide from AgentReady, and description override.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Admin;

use AgentReady\Cache\PageStatus;
use AgentReady\Content\PostMeta;
use AgentReady\Content\PostTypes;
use AgentReady\Contracts\Hookable;
use AgentReady\Settings\Options;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class MetaBox implements Hookable {

	private const NONCE_ACTION = 'agentready_save_post';
	private const NONCE_NAME   = 'agentready_nonce';

	public function __construct(
		private PostTypes $post_types,
		private Options $options,
		private PageStatus $status
	) {}

	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add' ) );
		add_action( 'save_post', array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function add(): void {
		$setting = (bool) $this->options->get( 'show_meta_box', true );
		$types   = array_values(
			array_filter(
				$this->post_types->enabled(),
				static fn( string $type ): bool => (bool) apply_filters( 'agentready_show_meta_box', $setting, $type )
			)
		);

		if ( empty( $types ) ) {
			return;
		}

		add_meta_box( 'agentready', __( 'AgentReady', 'agentready' ), array( $this, 'render' ), $types, 'side' );
	}

	public function enqueue( string $hook ): void {
		if ( 'post.php' !== $hook ) {
			return;
		}

		Assets::style();
		wp_enqueue_script( 'agentready-editor', AGENTREADY_URL . 'assets/js/editor.js', array( 'wp-api-fetch' ), Assets::version( 'assets/js/editor.js' ), true );
	}

	public function render( WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		View::render(
			'metabox',
			array(
				'post'        => $post,
				'excluded'    => (bool) get_post_meta( $post->ID, PostMeta::EXCLUDE, true ),
				'description' => (string) get_post_meta( $post->ID, PostMeta::DESCRIPTION, true ),
				'status'      => $this->status->for_post( $post ),
				'verify_url'  => current_user_can( 'manage_options' ) ? VerifyPage::url( array( 'url' => (string) get_permalink( $post ) ) ) : '',
			)
		);
	}

	public function save( int $post_id ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$nonce = isset( $_POST[ self::NONCE_NAME ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( empty( $_POST['agentready_exclude'] ) ) {
			delete_post_meta( $post_id, PostMeta::EXCLUDE );
		} else {
			update_post_meta( $post_id, PostMeta::EXCLUDE, 1 );
		}

		$description = isset( $_POST['agentready_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['agentready_description'] ) ) : '';

		if ( '' === $description ) {
			delete_post_meta( $post_id, PostMeta::DESCRIPTION );
		} else {
			update_post_meta( $post_id, PostMeta::DESCRIPTION, $description );
		}
	}
}
