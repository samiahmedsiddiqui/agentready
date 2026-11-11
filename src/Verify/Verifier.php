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

/** Compares the stored copy of a page with the Markdown it would produce now. */
final class Verifier {

	/**
	 * @param Store       $store       Stored copies.
	 * @param Generator   $generator   Builds the Markdown of a page.
	 * @param Renderer    $renderer    Renders the page.
	 * @param Eligibility $eligibility Decides whether a page is served.
	 */
	public function __construct(
		private Store $store,
		private Generator $generator,
		private Renderer $renderer,
		private Eligibility $eligibility
	) {}

	/**
	 * Renders the page, builds its Markdown and compares it with the stored copy.
	 *
	 * @param WP_Post                                    $post      The page.
	 * @param array{selector?: string, strip?: string[]} $overrides Settings to use instead of the saved ones.
	 * @param bool                                       $coverage  Also check the page text against the Markdown.
	 * @return VerifyResult The outcome.
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

		$ignore      = array_map( 'strval', (array) apply_filters( 'agentready_compare_ignore', array(), $post ) );
		$stored_text = Comparison::normalise( $entry->markdown, $ignore );
		$fresh_text  = Comparison::normalise( $page->markdown, $ignore );

		$result->state = $stored_text === $fresh_text ? VerifyResult::IN_SYNC : VerifyResult::DIFFERS;

		if ( VerifyResult::DIFFERS === $result->state ) {
			$result->diff    = Comparison::diff( $stored_text, $fresh_text );
			$result->changed = Comparison::changed( $result->diff );
		}

		return $result;
	}
}
