<?php
/**
 * Runs the pipeline: render, extract, convert, prepend front matter.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Rendering;

use AgentReady\Content\Description;
use AgentReady\Http\Urls;
use AgentReady\Settings\Options;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Generator {

	public function __construct(
		private Options $options,
		private Urls $urls,
		private Renderer $renderer,
		private Extractor $extractor,
		private Converter $converter,
		private FrontMatter $front_matter,
		private Description $description,
		private Summary $summary
	) {}

	/**
	 * @param array{selector?: string, strip?: string[]} $overrides Settings to use instead of the saved ones.
	 */
	public function generate( WP_Post $post, array $overrides = array() ): GeneratedPage {
		$render    = $this->renderer->render( $post );
		$url       = (string) $this->urls->permalink( $post );
		$selector  = ! empty( $overrides['selector'] ) ? $overrides['selector'] : $this->options->content_selector();
		$selector  = '' === $selector ? Options::DEFAULT_SELECTOR : $selector;
		$extractor = ! empty( $overrides['strip'] ) ? $this->extractor->with_extra( $overrides['strip'] ) : $this->extractor;
		$node      = $extractor->extract( $render->html, $selector, $url );
		$body      = null === $node ? '' : $this->converter->convert( $node );
		$summary   = $this->summary->from_markdown( $body );
		$title     = trim( html_entity_decode( wp_strip_all_tags( get_the_title( $post ) ), ENT_QUOTES, 'UTF-8' ) );

		if ( '' !== $title && ( null === $node || 0 === $node->getElementsByTagName( 'h1' )->length ) ) {
			$body = "# {$title}\n\n" . $body;
		}

		$front = $this->front_matter->render(
			array(
				'title'       => $title,
				'url'         => $url,
				'description' => $this->description_for( $post, $summary ),
				'type'        => $post->post_type,
				'modified'    => (string) get_post_modified_time( 'c', true, $post ),
				'language'    => str_replace( '_', '-', get_locale() ),
			)
		);

		$markdown = (string) apply_filters( 'agentready_markdown', $front . "\n" . trim( $body ) . "\n", $post );

		return new GeneratedPage( $markdown, $render->source, $render->notice );
	}

	private function description_for( WP_Post $post, string $summary ): string {
		$description = $this->description->get( $post );

		return '' !== $description ? $description : $summary;
	}
}
