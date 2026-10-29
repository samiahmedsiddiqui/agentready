<?php
/**
 * Runs the pipeline: render, extract, convert, prepend front matter.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Rendering;

use AgentReady\Settings\Options;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Generator {

	public function __construct(
		private Options $options,
		private Renderer $renderer,
		private Extractor $extractor,
		private Converter $converter,
		private FrontMatter $front_matter
	) {}

	public function generate( WP_Post $post ): string {
		return '';
	}
}
