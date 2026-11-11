<?php
/**
 * Post meta keys used for per-page controls.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Content;

defined( 'ABSPATH' ) || exit;

/** Meta keys this plugin saves on pages. */
final class PostMeta {

	/** Set when the page is hidden from AgentWelcome. */
	public const EXCLUDE = '_agentwelcome_exclude';
	/** Description for llms.txt that is set in the editor box. */
	public const DESCRIPTION = '_agentwelcome_description';
}
