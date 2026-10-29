<?php
/**
 * One-line page description for llms.txt: override, then SEO plugin, then excerpt.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Content;

use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Description {

	public function get( WP_Post $post ): string {
		$override = trim( (string) get_post_meta( $post->ID, PostMeta::DESCRIPTION, true ) );

		if ( '' !== $override ) {
			return $override;
		}

		$fallback = wp_strip_all_tags( get_the_excerpt( $post ) );

		return (string) apply_filters( 'agentready_description', $fallback, $post );
	}
}
