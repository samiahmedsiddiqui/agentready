<?php
/**
 * Post meta keys used for per-page controls.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Content;

defined( 'ABSPATH' ) || exit;

/** Meta keys this plugin saves on pages. */
final class PostMeta {

	/** Set when the page is hidden from AgentReady. */
	public const EXCLUDE = '_agentready_exclude';
	/** Description for llms.txt that is set in the editor box. */
	public const DESCRIPTION = '_agentready_description';
}
