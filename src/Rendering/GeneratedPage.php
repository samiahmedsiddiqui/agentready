<?php
/**
 * A generated Markdown page and how its HTML was obtained.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Rendering;

defined( 'ABSPATH' ) || exit;

/** Markdown built for a page, with where its content came from. */
final class GeneratedPage {

	/**
	 * @param string $markdown Markdown including the front matter block.
	 * @param string $source   Where the content came from: a RenderResult constant.
	 * @param string $notice   A sentence for the admin when the rendered page could not be used.
	 */
	public function __construct(
		public string $markdown,
		public string $source,
		public string $notice = ''
	) {}
}
