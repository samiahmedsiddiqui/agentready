<?php
/**
 * Reads the per-page noindex choice of the active SEO plugin.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Content;

use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Noindex {

	public const YOAST    = '_yoast_wpseo_meta-robots-noindex';
	public const RANKMATH = 'rank_math_robots';
	public const SEOPRESS = '_seopress_robots_index';
	public const TSF      = '_genesis_noindex';

	public const KEYS = array( self::YOAST, self::RANKMATH, self::SEOPRESS, self::TSF );

	public function is_noindex( WP_Post $post ): bool {
		$values = array();

		foreach ( $this->active_keys() as $key ) {
			$values[ $key ] = get_post_meta( $post->ID, $key, true );
		}

		$noindex = self::from_meta( $values ) || $this->aioseo( $post );

		return (bool) apply_filters( 'agentready_is_noindex', $noindex, $post );
	}

	/**
	 * @param array<string, mixed> $values Saved meta values by key. Only keys of active plugins should be passed.
	 */
	public static function from_meta( array $values ): bool {
		$rank = $values[ self::RANKMATH ] ?? array();

		return '1' === (string) ( $values[ self::YOAST ] ?? '' )
			|| ( is_array( $rank ) && in_array( 'noindex', $rank, true ) )
			|| 'yes' === (string) ( $values[ self::SEOPRESS ] ?? '' )
			|| '1' === (string) ( $values[ self::TSF ] ?? '' );
	}

	/**
	 * Meta keys of the SEO plugins that are active, so leftovers of a removed plugin are ignored.
	 *
	 * @return string[]
	 */
	private function active_keys(): array {
		$active = array(
			self::YOAST    => defined( 'WPSEO_VERSION' ),
			self::RANKMATH => defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ),
			self::SEOPRESS => defined( 'SEOPRESS_VERSION' ),
			self::TSF      => defined( 'THE_SEO_FRAMEWORK_VERSION' ) || function_exists( 'the_seo_framework' ),
		);

		return array_keys( array_filter( (array) apply_filters( 'agentready_noindex_sources', $active ) ) );
	}

	private function aioseo( WP_Post $post ): bool {
		if ( ! function_exists( 'aioseo' ) || ! class_exists( '\AIOSEO\Plugin\Common\Models\Post' ) ) {
			return false;
		}

		try {
			$saved = \AIOSEO\Plugin\Common\Models\Post::getPost( $post->ID );

			return ! empty( $saved->robots_noindex ) && empty( $saved->robots_default );
		} catch ( \Throwable $error ) {
			return false;
		}
	}
}
