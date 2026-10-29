<?php
/**
 * Captures the final front-end HTML of a post.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Rendering;

use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Renderer {

	public function render( WP_Post $post ): string {
		return '';
	}
}
