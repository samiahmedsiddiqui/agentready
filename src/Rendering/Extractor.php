<?php
/**
 * Extracts the main content area and strips non-content markup.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Rendering;

defined( 'ABSPATH' ) || exit;

final class Extractor {

	public function extract( string $html, string $selector = 'main' ): string {
		return '';
	}
}
