<?php
/**
 * Sends Markdown with the correct headers (type, canonical, noindex, caching).
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Http;

defined( 'ABSPATH' ) || exit;

final class MarkdownResponse {

	public function send( string $markdown, string $canonical_url, int $modified_gmt ): void {}
}
