<?php
/**
 * Decides whether a post may be exposed as Markdown.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Content;

use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Eligibility {

	public function __construct(
		private PostTypes $post_types,
		private Exclusions $exclusions
	) {}

	public function is_eligible( WP_Post $post ): bool {
		return null === $this->reason( $post );
	}

	/**
	 * @return string|null Why the post is not served, or null when it is eligible.
	 */
	public function reason( WP_Post $post ): ?string {
		if ( 'publish' !== $post->post_status ) {
			return 'not_published';
		}

		if ( '' !== $post->post_password ) {
			return 'password_protected';
		}

		if ( ! $this->post_types->is_enabled( $post->post_type ) ) {
			return 'excluded_post_type';
		}

		$excluded = $this->exclusions->matched( $post );

		if ( null !== $excluded ) {
			return $excluded;
		}

		return apply_filters( 'agentready_is_eligible', true, $post ) ? null : 'filtered';
	}
}
