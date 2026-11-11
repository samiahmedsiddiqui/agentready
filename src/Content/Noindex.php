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

/** Reads whether an SEO plugin marks a page as noindex. */
final class Noindex {

	/** Meta key of Yoast SEO. */
	public const YOAST = '_yoast_wpseo_meta-robots-noindex';
	/** Meta key of Rank Math. */
	public const RANKMATH = 'rank_math_robots';
	/** Meta key of SEOPress. */
	public const SEOPRESS = '_seopress_robots_index';
	/** Meta key of The SEO Framework. */
	public const TSF = '_genesis_noindex';

	/** Meta keys of all the supported plugins except All in One SEO, which keeps its own table. */
	public const KEYS = array( self::YOAST, self::RANKMATH, self::SEOPRESS, self::TSF );

	/**
	 * Whether an active SEO plugin marks the page as noindex.
	 *
	 * @param WP_Post $post The page.
	 * @return bool True when it does.
	 */
	public function is_noindex( WP_Post $post ): bool {
		$values = array();

		foreach ( $this->active_keys() as $key ) {
			$values[ $key ] = get_post_meta( $post->ID, $key, true );
		}

		$noindex = self::from_meta( $values ) || $this->aioseo( $post );

		return (bool) apply_filters( 'agentready_is_noindex', $noindex, $post );
	}

	/**
	 * Reads the choice from saved meta values.
	 *
	 * @param array<string, mixed> $values Saved values by meta key. Only keys of active plugins should be passed.
	 * @return bool True when a value means noindex.
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
	 * @return string[] Meta keys.
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

	/**
	 * Reads the choice from All in One SEO.
	 *
	 * @param WP_Post $post The page.
	 * @return bool True when it marks the page as noindex.
	 */
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
