<?php
/**
 * Keeps the built llms.txt and llms-full.txt for a few minutes, so public requests do not rebuild them every time.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Index;

use AgentWelcome\Content\Description;
use AgentWelcome\Content\Noindex;
use AgentWelcome\Content\PostMeta;
use AgentWelcome\Contracts\Hookable;
use AgentWelcome\Settings\LlmsOptions;
use AgentWelcome\Settings\Options;

defined( 'ABSPATH' ) || exit;

/** Keeps the built llms.txt and llms-full.txt for a few minutes, so public requests do not rebuild them each time. */
final class OutputCache implements Hookable {

	/** Transient that holds llms.txt. */
	public const LLMS = 'agentwelcome_out_llms';
	/** Transient that holds llms-full.txt. */
	public const FULL = 'agentwelcome_out_full';

	/** Seconds a saved file is kept. */
	private const TTL = 300;
	/** Largest file that is kept, in bytes. Larger ones are built on every request. */
	private const MAX_BYTES = 2000000;

	/** Options whose change alters the files. */
	private const OPTIONS = array( Options::KEY, LlmsOptions::KEY, 'blogname', 'blogdescription', 'home', 'siteurl', 'permalink_structure' );

	/**
	 * Clears the saved files when a page, a field that feeds them or a related setting changes.
	 *
	 * @return void
	 */
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
	 * Returns a saved file, or builds and saves it.
	 *
	 * @param string             $key   Transient name, LLMS or FULL.
	 * @param callable(): string $build Builds the file when there is no saved copy.
	 * @return string The file.
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

	/**
	 * Clears both saved files.
	 *
	 * @return void
	 */
	public static function purge(): void {
		delete_transient( self::LLMS );
		delete_transient( self::FULL );
	}

	/**
	 * Clears the saved files when a meta value that feeds them changes.
	 *
	 * @param mixed $meta_id  Meta row ID.
	 * @param mixed $post_id  Page ID.
	 * @param mixed $meta_key Meta key that changed.
	 * @return void
	 */
	public function on_meta( mixed $meta_id, mixed $post_id, mixed $meta_key ): void {
		$keys = array_merge( Description::SEO_META_KEYS, Noindex::KEYS, array( PostMeta::EXCLUDE, PostMeta::DESCRIPTION ) );

		if ( is_string( $meta_key ) && in_array( $meta_key, $keys, true ) ) {
			self::purge();
		}
	}

	/**
	 * Clears the saved files when a related option changes.
	 *
	 * @param mixed $option Name of the option that changed.
	 * @return void
	 */
	public function on_option( mixed $option ): void {
		if ( is_string( $option ) && in_array( $option, self::OPTIONS, true ) ) {
			self::purge();
		}
	}
}
