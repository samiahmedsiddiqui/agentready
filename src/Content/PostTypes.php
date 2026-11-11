<?php
/**
 * Resolves which post types are served as Markdown.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Content;

use AgentReady\Settings\Options;

defined( 'ABSPATH' ) || exit;

/** Post types that get a Markdown version. */
final class PostTypes {

	/**
	 * @param Options $options Saved settings.
	 */
	public function __construct( private Options $options ) {}

	/**
	 * Public post types, except attachments.
	 *
	 * @return string[] Post type names.
	 */
	public function available(): array {
		$types = get_post_types( array( 'public' => true ) );
		unset( $types['attachment'] );

		return array_values( $types );
	}

	/**
	 * Post types that were turned off in the settings.
	 *
	 * @return string[] Post type names.
	 */
	public function excluded(): array {
		return (array) $this->options->get( 'excluded_post_types', array() );
	}

	/**
	 * Post types that are served.
	 *
	 * @return string[] Post type names.
	 */
	public function enabled(): array {
		$excluded = array_map( 'strval', (array) apply_filters( 'agentready_excluded_post_types', $this->excluded() ) );

		return array_values( array_diff( $this->available(), $excluded ) );
	}

	/**
	 * Whether a post type is served.
	 *
	 * @param string $post_type Post type name.
	 * @return bool True when it is.
	 */
	public function is_enabled( string $post_type ): bool {
		return in_array( $post_type, $this->enabled(), true );
	}
}
