<?php
/**
 * Per-page, ID/URL pattern and taxonomy term exclusions.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Content;

use AgentWelcome\Settings\Options;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/** Pages the site owner chose to leave out, plus pages an SEO plugin marks as noindex. */
final class Exclusions {

	/**
	 * Reads the noindex choice from SEO plugins.
	 *
	 * @var Noindex
	 */
	private Noindex $noindex;

	/**
	 * @param Options      $options Saved settings.
	 * @param Noindex|null $noindex Noindex reader. A new one is created when omitted.
	 */
	public function __construct( private Options $options, ?Noindex $noindex = null ) {
		$this->noindex = $noindex ?? new Noindex();
	}

	/**
	 * Finds the rule that leaves a page out.
	 *
	 * @param WP_Post $post The page.
	 * @return string|null A reason code, or null when no rule applies.
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

		if ( $this->options->get( 'exclude_noindex', true ) && $this->noindex->is_noindex( $post ) ) {
			return 'noindex';
		}

		return null;
	}

	/**
	 * Whether the page matches an ID or a URL pattern from the exclusion list.
	 *
	 * @param WP_Post $post The page.
	 * @return bool True when it matches.
	 */
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

	/**
	 * Whether the page has one of the excluded terms.
	 *
	 * @param WP_Post $post The page.
	 * @return bool True when it has.
	 */
	private function matches_term( WP_Post $post ): bool {
		$terms = array_map( 'absint', (array) $this->options->get( 'excluded_terms', array() ) );

		$taxonomies = get_object_taxonomies( $post->post_type );

		if ( empty( $terms ) || empty( $taxonomies ) ) {
			return false;
		}

		$assigned = wp_get_object_terms( $post->ID, $taxonomies, array( 'fields' => 'ids' ) );

		return ! is_wp_error( $assigned ) && array() !== array_intersect( $terms, array_map( 'intval', $assigned ) );
	}

	/**
	 * Path of the page address, without the folder of the site.
	 *
	 * @param WP_Post $post The page.
	 * @return string Path without slashes at the ends.
	 */
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
