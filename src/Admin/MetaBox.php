<?php
/**
 * Editor box: stored Markdown status and refresh, hide from AgentWelcome, and description override.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Admin;

use AgentWelcome\Cache\PageStatus;
use AgentWelcome\Content\PostMeta;
use AgentWelcome\Content\PostTypes;
use AgentWelcome\Contracts\Hookable;
use AgentWelcome\Settings\Options;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/** The AgentWelcome box on the edit screen: shows the state of the page and saves the hide and description choices. */
final class MetaBox implements Hookable {

	/** Nonce action of the box. */
	private const NONCE_ACTION = 'agentwelcome_save_post';
	/** Name of the nonce field. */
	private const NONCE_NAME = 'agentwelcome_nonce';

	/**
	 * @param PostTypes  $post_types Post types that are served.
	 * @param Options    $options    Saved settings.
	 * @param PageStatus $status     Describes the state of a page.
	 */
	public function __construct(
		private PostTypes $post_types,
		private Options $options,
		private PageStatus $status
	) {}

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add' ) );
		add_action( 'save_post', array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Adds the box to the post types that are served, unless the setting or a filter hides it.
	 *
	 * @return void
	 */
	public function add(): void {
		$setting = (bool) $this->options->get( 'show_meta_box', true );
		$types   = array_values(
			array_filter(
				$this->post_types->enabled(),
				static fn( string $type ): bool => (bool) apply_filters( 'agentwelcome_show_meta_box', $setting, $type )
			)
		);

		if ( empty( $types ) ) {
			return;
		}

		add_meta_box( 'agentwelcome', __( 'AgentWelcome', 'agentwelcome' ), array( $this, 'render' ), $types, 'side' );
	}

	/**
	 * Loads the box script on the edit screen.
	 *
	 * @param string $hook Admin page hook.
	 * @return void
	 */
	public function enqueue( string $hook ): void {
		if ( 'post.php' !== $hook ) {
			return;
		}

		Assets::style();
		wp_enqueue_script( 'agentwelcome-editor', AGENTWELCOME_URL . 'assets/js/editor.js', array( 'wp-api-fetch' ), Assets::version( 'assets/js/editor.js' ), true );
	}

	/**
	 * Prints the box.
	 *
	 * @param WP_Post $post The page being edited.
	 * @return void
	 */
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

	/**
	 * Saves the hide and description choices when the page is saved.
	 *
	 * @param int $post_id Page ID.
	 * @return void
	 */
	public function save( int $post_id ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$nonce = isset( $_POST[ self::NONCE_NAME ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( empty( $_POST['agentwelcome_exclude'] ) ) {
			delete_post_meta( $post_id, PostMeta::EXCLUDE );
		} else {
			update_post_meta( $post_id, PostMeta::EXCLUDE, 1 );
		}

		$description = isset( $_POST['agentwelcome_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['agentwelcome_description'] ) ) : '';

		if ( '' === $description ) {
			delete_post_meta( $post_id, PostMeta::DESCRIPTION );
		} else {
			update_post_meta( $post_id, PostMeta::DESCRIPTION, $description );
		}
	}
}
