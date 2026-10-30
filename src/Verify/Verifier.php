<?php
/**
 * Builds fresh Markdown for a page and compares it with the stored copy and the page text.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Verify;

use AgentReady\Cache\Store;
use AgentReady\Content\Eligibility;
use AgentReady\Rendering\Generator;
use AgentReady\Rendering\Renderer;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Verifier {

	public function __construct(
		private Store $store,
		private Generator $generator,
		private Renderer $renderer,
		private Eligibility $eligibility
	) {}

	/**
	 * @param array{selector?: string, strip?: string[]} $overrides Settings to use instead of the saved ones.
	 * @param bool                                       $coverage  Also check the page text against the Markdown.
	 */
	public function verify( WP_Post $post, array $overrides = array(), bool $coverage = true ): VerifyResult {
		$reason = $this->eligibility->reason( $post );

		if ( null !== $reason ) {
			return new VerifyResult( VerifyResult::NOT_SERVED, $this->eligibility->describe( $reason ) );
		}

		$render = $this->renderer->render( $post );
		$page   = $this->generator->build( $post, $render, $overrides );
		$entry  = $this->store->get( $post->ID );
		$blocks = $coverage ? $this->generator->text_blocks( $render, $overrides ) : null;
		$result = new VerifyResult(
			VerifyResult::NOT_STORED,
			'',
			$entry,
			$page,
			array(),
			0,
			null === $blocks ? null : Comparison::coverage( $blocks['kept'], $page->markdown ),
			null === $blocks ? array() : $blocks['excluded']
		);

		if ( null === $entry ) {
			return $result;
		}

		$ignore = array_map( 'strval', (array) apply_filters( 'agentready_compare_ignore', array(), $post ) );
		$old    = Comparison::normalise( $entry->markdown, $ignore );
		$new    = Comparison::normalise( $page->markdown, $ignore );

		$result->state = $old === $new ? VerifyResult::IN_SYNC : VerifyResult::DIFFERS;

		if ( VerifyResult::DIFFERS === $result->state ) {
			$result->diff    = Comparison::diff( $old, $new );
			$result->changed = Comparison::changed( $result->diff );
		}

		return $result;
	}
}
