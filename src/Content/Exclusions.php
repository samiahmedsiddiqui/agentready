<?php
/**
 * Per-page, ID/URL pattern and taxonomy term exclusions.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Content;

use AgentReady\Settings\Options;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Exclusions {

	public function __construct( private Options $options ) {}

	/**
	 * @return string|null Reason code, or null when nothing excludes the post.
	 */
	public function matched( WP_Post $post ): ?string {
		if ( get_post_meta( $post->ID, PostMeta::EXCLUDE, true ) ) {
			return 'excluded_page';
		}

		if ( $this->matches_pattern( $post ) ) {
			return 'excluded_pattern';
		}

		if ( $this->matches_term( $post ) ) {
			return 'excluded_term';
		}

		return null;
	}

	private function matches_pattern( WP_Post $post ): bool {
		$lines = preg_split( '/[\r\n,]+/', (string) $this->options->get( 'excluded_posts', '' ) );
		$path  = $this->relative_path( $post );

		foreach ( (array) $lines as $line ) {
			$line = trim( $line );

			if ( '' === $line ) {
				continue;
			}

			if ( ctype_digit( $line ) ) {
				if ( (int) $line === $post->ID ) {
					return true;
				}
				continue;
			}

			if ( fnmatch( trim( $line, '/' ), $path ) ) {
				return true;
			}
		}

		return false;
	}

	private function matches_term( WP_Post $post ): bool {
		$terms = array_map( 'absint', (array) $this->options->get( 'excluded_terms', array() ) );

		return ! empty( $terms ) && has_term( $terms, '', $post );
	}

	private function relative_path( WP_Post $post ): string {
		$path = (string) wp_parse_url( get_permalink( $post ), PHP_URL_PATH );
		$home = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
		$path = trim( $path, '/' );

		if ( '' !== $home && 0 === strpos( $path, $home ) ) {
			$path = ltrim( substr( $path, strlen( $home ) ), '/' );
		}

		return $path;
	}
}
