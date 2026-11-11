<?php
/**
 * The HTML captured for a post, and how it was obtained.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Rendering;

defined( 'ABSPATH' ) || exit;

/** HTML read for a page, and how it was obtained. */
final class RenderResult {

	/** The HTML is the rendered page, read through a request to the site itself. */
	public const LOOPBACK = 'loopback';
	/** The HTML was built from the post content because the rendered page could not be read. */
	public const FALLBACK = 'fallback';

	/**
	 * @param string $html   HTML of the page.
	 * @param string $source Either LOOPBACK or FALLBACK.
	 * @param string $notice A sentence for the admin when the fallback was used.
	 */
	public function __construct(
		public string $html,
		public string $source,
		public string $notice = ''
	) {}
}
