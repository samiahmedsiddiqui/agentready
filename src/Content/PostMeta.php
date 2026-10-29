<?php
/**
 * Post meta keys used for per-page controls.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Content;

defined( 'ABSPATH' ) || exit;

final class PostMeta {

	public const EXCLUDE     = '_agentready_exclude';
	public const DESCRIPTION = '_agentready_description';
}
