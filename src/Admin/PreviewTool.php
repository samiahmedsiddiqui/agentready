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
			$url      = esc_url_raw( wp_unslash( $_POST['agentready_preview_url'] ) );
			$selector = isset( $_POST['agentready_preview_selector'] ) ? sanitize_text_field( wp_unslash( $_POST['agentready_preview_selector'] ) ) : '';
			$strip    = isset( $_POST['agentready_preview_strip'] ) ? sanitize_textarea_field( wp_unslash( $_POST['agentready_preview_strip'] ) ) : '';
		}

		$action = add_query_arg(
			array(
				'page' => SettingsPage::SLUG,
				'tab'  => 'preview',
			),
			admin_url( 'options-general.php' )
		);
		?>
		<p><?php esc_html_e( 'Enter the URL of a page on this site to see whether AgentReady serves it and what the Markdown looks like.', 'agentready' ); ?></p>
		<form method="post" action="<?php echo esc_url( $action ); ?>">
			<?php wp_nonce_field( self::NONCE ); ?>
			<input type="text" class="regular-text code" name="agentready_preview_url" value="<?php echo esc_attr( $url ); ?>" placeholder="<?php echo esc_attr( home_url( '/about/' ) ); ?>" />
			<?php submit_button( __( 'Preview', 'agentready' ), 'secondary', 'submit', false ); ?>
			<details style="margin-top:12px"<?php echo '' !== $selector || '' !== $strip ? ' open' : ''; ?>>
				<summary><?php esc_html_e( 'Try different selectors without saving', 'agentready' ); ?></summary>
				<p>
					<label for="agentready-preview-selector"><?php esc_html_e( 'Content selector', 'agentready' ); ?></label><br />
					<input type="text" id="agentready-preview-selector" class="large-text code" name="agentready_preview_selector" value="<?php echo esc_attr( $selector ); ?>" />
				</p>
				<p>
					<label for="agentready-preview-strip"><?php esc_html_e( 'Extra elements to exclude (one per line)', 'agentready' ); ?></label><br />
					<textarea id="agentready-preview-strip" class="large-text code" rows="3" name="agentready_preview_strip"><?php echo esc_textarea( $strip ); ?></textarea>
				</p>
				<p class="description"><?php esc_html_e( 'Leave blank to use the saved settings. Nothing here is saved.', 'agentready' ); ?></p>
			</details>
		</form>
		<?php

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
		?>
		<h2><?php echo esc_html( get_the_title( $post ) ); ?></h2>
		<table class="widefat striped" style="max-width:720px">
			<tr>
				<th scope="row"><?php esc_html_e( 'Post', 'agentready' ); ?></th>
				<td>#<?php echo esc_html( (string) $post->ID ); ?> (<?php echo esc_html( $post->post_type ); ?>)</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Status', 'agentready' ); ?></th>
				<td><?php echo esc_html( null === $reason ? __( 'Served as Markdown', 'agentready' ) : $this->eligibility->describe( $reason ) ); ?></td>
			</tr>
			<?php if ( null === $reason && null !== $md_url ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Markdown URL', 'agentready' ); ?></th>
				<td><a href="<?php echo esc_url( $md_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $md_url ); ?></a></td>
			</tr>
			<?php endif; ?>
		</table>
		<?php

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
		?>
		<p><strong><?php esc_html_e( 'Source:', 'agentready' ); ?></strong> <?php echo esc_html( RenderResult::LOOPBACK === $page->source ? __( 'Rendered page (loopback request)', 'agentready' ) : __( 'Post content (fallback)', 'agentready' ) ); ?></p>
		<textarea class="large-text code" rows="24" readonly><?php echo esc_textarea( $page->markdown ); ?></textarea>
		<?php
	}

	private function notice( string $type, string $message ): void {
		printf( '<div class="notice notice-%1$s inline"><p>%2$s</p></div>', esc_attr( $type ), esc_html( $message ) );
	}
}
