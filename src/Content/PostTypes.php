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

final class PostTypes {

	public function __construct( private Options $options ) {}

	/**
	 * @return string[]
	 */
	public function available(): array {
		$types = get_post_types( array( 'public' => true ) );
		unset( $types['attachment'] );

		return array_values( $types );
	}

	/**
	 * @return string[]
	 */
	public function excluded(): array {
		return (array) $this->options->get( 'excluded_post_types', array() );
	}

	/**
	 * @return string[]
	 */
	public function enabled(): array {
		return array_values( array_diff( $this->available(), $this->excluded() ) );
	}

	public function is_enabled( string $post_type ): bool {
		return in_array( $post_type, $this->enabled(), true );
	}
}
