<?php
/**
 * One-line page description for llms.txt: editor override, then SEO meta description, then the excerpt. Never the page content.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Content;

use WP_Post;

defined( 'ABSPATH' ) || exit;

/** Works out the short description of a page for llms.txt and the front matter. */
final class Description {

	/** An SEO plugin template tag such as %%sitename%% or #post_title. */
	private const TEMPLATE_TAG = '/%{1,2}[a-z_]+(?:\([^)]*\))?%{1,2}|#[a-z]+_[a-z_]+/i';

	/** Meta keys where SEO plugins keep the description of a page. */
	public const SEO_META_KEYS = array( '_yoast_wpseo_metadesc', 'rank_math_description', '_aioseo_description', '_seopress_titles_desc', '_genesis_description' );

	/**
	 * Whether the text still holds template tags such as %%sitename%%, %title% or #site_title.
	 *
	 * @param string $text Description text.
	 * @return bool True when a tag is left.
	 */
	public static function has_unresolved_tags( string $text ): bool {
		return 1 === preg_match( self::TEMPLATE_TAG, $text );
	}

	/**
	 * Finds the description of a page: the one set in the editor box, then the SEO plugin description, then the excerpt.
	 *
	 * @param WP_Post $post The page.
	 * @return string The description, or an empty string when there is none.
	 */
	public function get( WP_Post $post ): string {
		$override = trim( (string) get_post_meta( $post->ID, PostMeta::DESCRIPTION, true ) );

		if ( '' !== $override ) {
			return $override;
		}

		$text = $this->seo( $post );
		$text = '' !== $text ? $text : $this->clean( $post->post_excerpt );

		return (string) apply_filters( 'agentready_description', $text, $post );
	}

	/**
	 * Reads the description saved by an SEO plugin. A text whose tags cannot be resolved is skipped.
	 *
	 * @param WP_Post $post The page.
	 * @return string The description, or an empty string.
	 */
	private function seo( WP_Post $post ): string {
		$keys = (array) apply_filters( 'agentready_meta_description_keys', self::SEO_META_KEYS, $post );

		foreach ( $keys as $key ) {
			$raw = trim( (string) get_post_meta( $post->ID, (string) $key, true ) );

			if ( '' === $raw ) {
				continue;
			}

			$text = $this->resolve( $raw, $post, (string) $key );

			// A description that still has tags would print them, or leave gaps, so the next source is used.
			if ( self::has_unresolved_tags( $text ) ) {
				continue;
			}

			$text = $this->clean( $text );

			if ( '' !== $text ) {
				return $text;
			}
		}

		return '';
	}

	/**
	 * Lets the SEO plugin that wrote the text fill in its own template tags.
	 *
	 * @param string  $text Description text.
	 * @param WP_Post $post The page.
	 * @param string  $key  Meta key the text came from.
	 * @return string Text with the tags filled in.
	 */
	private function resolve( string $text, WP_Post $post, string $key ): string {
		if ( ! self::has_unresolved_tags( $text ) ) {
			return $text;
		}

		try {
			switch ( $key ) {
				case '_yoast_wpseo_metadesc':
					return function_exists( 'wpseo_replace_vars' ) ? (string) wpseo_replace_vars( $text, $post ) : $text;

				case '_aioseo_description':
					$api = function_exists( 'aioseo' ) ? aioseo() : null;

					return is_object( $api ) && isset( $api->tags ) && is_callable( array( $api->tags, 'replaceTags' ) ) ? (string) call_user_func( array( $api->tags, 'replaceTags' ), $text, $post->ID ) : $text;

				case 'rank_math_description':
					return is_callable( array( '\RankMath\Helper', 'replace_vars' ) ) ? (string) call_user_func( array( '\RankMath\Helper', 'replace_vars' ), $text, $post ) : $text;

				case '_seopress_titles_desc':
					$service = function_exists( 'seopress_get_service' ) ? seopress_get_service( 'TagsToString' ) : null;

					return is_object( $service ) && is_callable( array( $service, 'replace' ) ) ? (string) call_user_func( array( $service, 'replace' ), $text, array( 'post' => $post ) ) : $text;
			}
		} catch ( \Throwable $error ) {
			return $text;
		}

		return $text;
	}

	/**
	 * Turns an excerpt into plain text of at most 30 words.
	 *
	 * @param string $text Excerpt.
	 * @return string Plain text.
	 */
	private function clean( string $text ): string {
		$text = wp_strip_all_tags( strip_shortcodes( $text ) );
		$text = (string) preg_replace( '/\s+/', ' ', html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) );

		return wp_trim_words( trim( $text ), 30 );
	}
}
