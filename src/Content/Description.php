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

final class Description {

	private const SEO_META_KEYS = array( '_yoast_wpseo_metadesc', 'rank_math_description', '_aioseo_description', '_seopress_titles_desc', '_genesis_description' );

	public function get( WP_Post $post ): string {
		$override = trim( (string) get_post_meta( $post->ID, PostMeta::DESCRIPTION, true ) );

		if ( '' !== $override ) {
			return $override;
		}

		$text = $this->seo( $post );
		$text = '' !== $text ? $text : $this->clean( $post->post_excerpt );

		return (string) apply_filters( 'agentready_description', $text, $post );
	}

	private function seo( WP_Post $post ): string {
		$keys = (array) apply_filters( 'agentready_meta_description_keys', self::SEO_META_KEYS, $post );

		foreach ( $keys as $key ) {
			$value = $this->clean( (string) get_post_meta( $post->ID, (string) $key, true ), $post );

			if ( '' !== $value ) {
				return $value;
			}
		}

		return '';
	}

	private function clean( string $text, ?WP_Post $post = null ): string {
		if ( null !== $post && function_exists( 'wpseo_replace_vars' ) ) {
			$text = (string) wpseo_replace_vars( $text, $post );
		}

		// Unresolved template variables such as %%sitename%% or %title%.
		$text = (string) preg_replace( '/%{1,2}[a-z_]+(?:\([^)]*\))?%{1,2}/i', '', $text );
		$text = wp_strip_all_tags( strip_shortcodes( $text ) );
		$text = (string) preg_replace( '/\s+/', ' ', html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) );

		return wp_trim_words( trim( $text ), 30 );
	}
}
