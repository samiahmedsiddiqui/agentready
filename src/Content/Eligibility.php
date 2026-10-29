<?php
/**
 * Decides whether a post may be exposed as Markdown.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Content;

use AgentReady\Http\Urls;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Eligibility {

	public function __construct(
		private PostTypes $post_types,
		private Exclusions $exclusions,
		private Urls $urls
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

		if ( null === $this->urls->permalink( $post ) ) {
			return 'plain_permalink';
		}

		$excluded = $this->exclusions->matched( $post );

		if ( null !== $excluded ) {
			return $excluded;
		}

		return apply_filters( 'agentready_is_eligible', true, $post ) ? null : 'filtered';
	}

	public function describe( string $reason ): string {
		$labels = array(
			'not_published'      => __( 'The page is not published.', 'agentready' ),
			'password_protected' => __( 'The page is password protected.', 'agentready' ),
			'excluded_post_type' => __( 'Its post type is excluded in the settings.', 'agentready' ),
			'plain_permalink'    => __( 'It has no pretty permalink. Plain permalinks are not supported.', 'agentready' ),
			'excluded_page'      => __( 'The page is hidden from AgentReady in the editor.', 'agentready' ),
			'excluded_pattern'   => __( 'It matches an ID or URL pattern in the exclusion list.', 'agentready' ),
			'excluded_term'      => __( 'It belongs to an excluded taxonomy term.', 'agentready' ),
			'filtered'           => __( 'A filter excluded it.', 'agentready' ),
		);

		return $labels[ $reason ] ?? $reason;
	}
}
