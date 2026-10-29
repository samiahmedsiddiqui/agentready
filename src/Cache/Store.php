<?php
/**
 * Storage for generated Markdown.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Cache;

defined( 'ABSPATH' ) || exit;

final class Store {

	public function get( int $post_id ): ?string {
		return null;
	}

	public function set( int $post_id, string $markdown ): void {}

	public function delete( int $post_id ): void {}

	public function flush(): void {}
}
