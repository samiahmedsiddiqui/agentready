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
		private Description $description
	) {}

	/**
	 * @param array{selector?: string, strip?: string[]} $overrides Settings to use instead of the saved ones.
	 */
	public function generate( WP_Post $post, array $overrides = array() ): GeneratedPage {
		return $this->build( $post, $this->renderer->render( $post ), $overrides );
	}

	/**
	 * @param array{selector?: string, strip?: string[]} $overrides Settings to use instead of the saved ones.
	 */
	public function content_selector( array $overrides = array() ): string {
		$selector = ! empty( $overrides['selector'] ) ? $overrides['selector'] : $this->options->content_selector();

		return '' === $selector ? Options::DEFAULT_SELECTOR : $selector;
	}

	/**
	 * @param array{selector?: string, strip?: string[]} $overrides Settings to use instead of the saved ones.
	 * @return array{kept: string[], excluded: string[]}
	 */
	public function text_blocks( RenderResult $render, array $overrides = array() ): array {
		$extractor = ! empty( $overrides['strip'] ) ? $this->extractor->with_extra( $overrides['strip'] ) : $this->extractor;

		return $extractor->text_blocks( $render->html, $this->content_selector( $overrides ) );
	}

	/**
	 * Builds the Markdown from HTML that was already fetched.
	 *
	 * @param array{selector?: string, strip?: string[]} $overrides Settings to use instead of the saved ones.
	 */
	public function build( WP_Post $post, RenderResult $render, array $overrides = array() ): GeneratedPage {
		$url       = (string) $this->urls->permalink( $post );
		$selector  = $this->content_selector( $overrides );
		$extractor = ! empty( $overrides['strip'] ) ? $this->extractor->with_extra( $overrides['strip'] ) : $this->extractor;
		$node      = $extractor->extract( $render->html, $selector, $url );
		$body      = null === $node ? '' : $this->converter->convert( $node );
		$title     = trim( html_entity_decode( wp_strip_all_tags( get_the_title( $post ) ), ENT_QUOTES, 'UTF-8' ) );

		if ( '' !== $title && ( null === $node || 0 === $node->getElementsByTagName( 'h1' )->length ) ) {
			$body = "# {$title}\n\n" . $body;
		}

		$front = $this->front_matter->render(
			array(
				'title'       => $title,
				'url'         => $url,
				'description' => $this->description->get( $post ),
				'type'        => $post->post_type,
				'modified'    => (string) get_post_modified_time( 'c', true, $post ),
				'language'    => str_replace( '_', '-', get_locale() ),
			)
		);

		$markdown = (string) apply_filters( 'agentready_markdown', $front . "\n" . trim( $body ) . "\n", $post );

		return new GeneratedPage( $markdown, $render->source, $render->notice );
	}
}
