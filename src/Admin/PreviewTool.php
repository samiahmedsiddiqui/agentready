<?php
/**
 * Settings tab that shows eligibility and the generated Markdown for any page URL.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Admin;

use AgentReady\Content\Eligibility;
use AgentReady\Http\Urls;
use AgentReady\Rendering\Generator;
use AgentReady\Rendering\RenderResult;
use AgentReady\Rendering\Selector;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class PreviewTool {

	private const NONCE = 'agentready_preview';

	public function __construct(
		private Urls $urls,
		private Eligibility $eligibility,
		private Generator $generator
	) {}

	public function render(): void {
		$url      = '';
		$selector = '';
		$strip    = '';

		if ( isset( $_POST['agentready_preview_url'] ) ) {
			check_admin_referer( self::NONCE );
			$url      = trim( sanitize_text_field( wp_unslash( $_POST['agentready_preview_url'] ) ) );
			$selector = isset( $_POST['agentready_preview_selector'] ) ? sanitize_text_field( wp_unslash( $_POST['agentready_preview_selector'] ) ) : '';
			$strip    = isset( $_POST['agentready_preview_strip'] ) ? sanitize_textarea_field( wp_unslash( $_POST['agentready_preview_strip'] ) ) : '';
		}

		View::render(
			'preview',
			array(
				'nonce'    => self::NONCE,
				'action'   => add_query_arg(
					array(
						'page' => SettingsPage::SLUG,
						'tab'  => 'preview',
					),
					admin_url( 'options-general.php' )
				),
				'url'      => $url,
				'selector' => $selector,
				'strip'    => $strip,
			)
		);

		if ( '' !== $url ) {
			$this->result( $url, $selector, $strip );
		}
	}

	private function result( string $url, string $selector, string $strip ): void {
		$post = $this->urls->resolve_url( $url );

		if ( ! $post instanceof WP_Post ) {
			$this->notice( 'error', __( 'No page was found for that URL on this site.', 'agentready' ) );
			return;
		}

		$reason = $this->eligibility->reason( $post );
		$md_url = $this->urls->markdown_url( $post );

		View::render(
			'preview-result',
			array(
				'title'  => get_the_title( $post ),
				'post'   => $post,
				'status' => null === $reason ? __( 'Served as Markdown', 'agentready' ) : $this->eligibility->describe( $reason ),
				'served' => null === $reason,
				'md_url' => $md_url,
			)
		);

		if ( null !== $reason ) {
			return;
		}

		$selectors = new Selector();
		$overrides = array();
		$content   = implode( ', ', $selectors->parse_list( $selector )['valid'] );
		$extra     = $selectors->parse_list( $strip )['valid'];

		if ( '' !== $content ) {
			$overrides['selector'] = $content;
		}

		if ( ! empty( $extra ) ) {
			$overrides['strip'] = $extra;
		}

		if ( ! empty( $overrides ) ) {
			$this->notice( 'info', __( 'The selectors below were applied to this preview only.', 'agentready' ) );
		}

		$page = $this->generator->generate( $post, $overrides );

		if ( RenderResult::FALLBACK === $page->source ) {
			$this->notice( 'warning', '' === $page->notice ? __( 'Generated from the post content only.', 'agentready' ) : $page->notice );
		}

		View::render( 'preview-markdown', array( 'page' => $page ) );
	}

	private function notice( string $type, string $message ): void {
		printf( '<div class="notice notice-%1$s inline"><p>%2$s</p></div>', esc_attr( $type ), esc_html( $message ) );
	}
}
