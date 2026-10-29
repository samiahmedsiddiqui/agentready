<?php
/**
 * Preview tab form.
 *
 * @package AgentReady
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template-local variables.

$selector = $vars['selector'];
$strip    = $vars['strip'];
?>
<section class="agentready-card" style="margin-top:20px">
	<h2><?php esc_html_e( 'Check a page', 'agentready' ); ?></h2>
	<p class="agentready-intro"><?php esc_html_e( 'Enter the URL or path of a page on this site to see whether it is served and what the Markdown looks like. The field starts with your homepage address.', 'agentready' ); ?></p>
	<form method="post" action="<?php echo esc_url( $vars['action'] ); ?>">
		<?php wp_nonce_field( $vars['nonce'] ); ?>
		<p>
			<input type="text" class="large-text code" name="agentready_preview_url" value="<?php echo esc_attr( $vars['url'] ); ?>" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>" />
		</p>
		<details class="agentready-details"<?php echo '' !== $selector || '' !== $strip ? ' open' : ''; ?>>
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
		<p><?php submit_button( __( 'Preview', 'agentready' ), 'primary', 'submit', false ); ?></p>
	</form>
</section>
