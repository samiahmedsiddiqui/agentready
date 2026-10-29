<?php
/**
 * Builds the metadata block placed at the top of each Markdown page.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Rendering;

use WP_Post;

defined( 'ABSPATH' ) || exit;

final class FrontMatter {

	public function build( WP_Post $post ): string {
		return '';
	}
}
