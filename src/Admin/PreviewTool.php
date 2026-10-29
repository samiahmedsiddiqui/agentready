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
		$url = '';

		if ( isset( $_POST['agentready_preview_url'] ) ) {
			check_admin_referer( self::NONCE );
			$url = esc_url_raw( wp_unslash( $_POST['agentready_preview_url'] ) );
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
		</form>
		<?php

		if ( '' !== $url ) {
			$this->result( $url );
		}
	}

	private function result( string $url ): void {
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

		$page = $this->generator->generate( $post );

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
