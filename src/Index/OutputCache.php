<?php
/**
 * Keeps the built llms.txt and llms-full.txt for a few minutes, so public requests do not rebuild them every time.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Index;

use AgentReady\Content\Description;
use AgentReady\Content\Noindex;
use AgentReady\Content\PostMeta;
use AgentReady\Contracts\Hookable;
use AgentReady\Settings\LlmsOptions;
use AgentReady\Settings\Options;

defined( 'ABSPATH' ) || exit;

final class OutputCache implements Hookable {

	public const LLMS = 'agentready_out_llms';
	public const FULL = 'agentready_out_full';

	private const TTL       = 300;
	private const MAX_BYTES = 2000000;

	private const OPTIONS = array( Options::KEY, LlmsOptions::KEY, 'blogname', 'blogdescription', 'home', 'siteurl', 'permalink_structure' );

	public function register(): void {
		foreach ( array( 'save_post', 'deleted_post', 'trashed_post', 'untrashed_post' ) as $hook ) {
			add_action( $hook, array( self::class, 'purge' ) );
		}

		foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $hook ) {
			add_action( $hook, array( $this, 'on_meta' ), 10, 3 );
		}

		add_action( 'added_option', array( $this, 'on_option' ) );
		add_action( 'updated_option', array( $this, 'on_option' ) );
	}

	/**
	 * @param callable(): string $build Builds the file when there is no saved copy.
	 */
	public static function remember( string $key, callable $build ): string {
		$saved = get_transient( $key );

		if ( is_string( $saved ) ) {
			return $saved;
		}

		$text = $build();

		if ( strlen( $text ) <= self::MAX_BYTES ) {
			set_transient( $key, $text, self::TTL );
		}

		return $text;
	}

	public static function purge(): void {
		delete_transient( self::LLMS );
		delete_transient( self::FULL );
	}

	/**
	 * @param mixed $meta_key Meta key that changed.
	 */
	public function on_meta( mixed $meta_id, mixed $post_id, mixed $meta_key ): void {
		$keys = array_merge( Description::SEO_META_KEYS, Noindex::KEYS, array( PostMeta::EXCLUDE, PostMeta::DESCRIPTION ) );

		if ( is_string( $meta_key ) && in_array( $meta_key, $keys, true ) ) {
			self::purge();
		}
	}

	/**
	 * @param mixed $option Name of the option that changed.
	 */
	public function on_option( mixed $option ): void {
		if ( is_string( $option ) && in_array( $option, self::OPTIONS, true ) ) {
			self::purge();
		}
	}
}
