<?php
/**
 * Respects noindex and reuses descriptions from Yoast SEO and Rank Math.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Integrations;

use AgentReady\Contracts\Hookable;

defined( 'ABSPATH' ) || exit;

final class Seo implements Hookable {

	public function register(): void {}
}
