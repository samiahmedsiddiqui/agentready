<?php
/**
 * Verify form for one page.
 *
 * @package AgentWelcome
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template-local variables.

$selector = $vars['selector'];
$strip    = $vars['strip'];
?>
<section class="agentwelcome-card" style="margin-top:20px">
	<h2><?php esc_html_e( 'Check a page', 'agentwelcome' ); ?></h2>
	<p class="agentwelcome-intro"><?php esc_html_e( 'Enter the URL or path of a page on this site. AgentWelcome reads the page now, builds the Markdown, and compares it with the stored copy and with the text on the page. The field starts with your homepage address.', 'agentwelcome' ); ?></p>
	<form method="post" action="<?php echo esc_url( $vars['action'] ); ?>">
		<?php wp_nonce_field( $vars['nonce'] ); ?>
		<p>
			<input type="text" class="large-text code" name="agentwelcome_verify_url" value="<?php echo esc_attr( $vars['url'] ); ?>" placeholder="<?php echo esc_attr( $vars['placeholder'] ); ?>" />
		</p>
		<details class="agentwelcome-details"<?php echo '' !== $selector || '' !== $strip ? ' open' : ''; ?>>
			<summary><?php esc_html_e( 'Try different selectors without saving', 'agentwelcome' ); ?></summary>
			<p>
				<label for="agentwelcome-verify-selector"><?php esc_html_e( 'Content selector', 'agentwelcome' ); ?></label><br />
				<input type="text" id="agentwelcome-verify-selector" class="large-text code" name="agentwelcome_verify_selector" value="<?php echo esc_attr( $selector ); ?>" />
			</p>
			<p>
				<label for="agentwelcome-verify-strip"><?php esc_html_e( 'Extra elements to exclude (one per line)', 'agentwelcome' ); ?></label><br />
				<textarea id="agentwelcome-verify-strip" class="large-text code" rows="3" name="agentwelcome_verify_strip"><?php echo esc_textarea( $strip ); ?></textarea>
			</p>
			<p class="description"><?php esc_html_e( 'Leave blank to use the saved settings. Nothing here is saved.', 'agentwelcome' ); ?></p>
		</details>
		<p><?php submit_button( __( 'Check page', 'agentwelcome' ), 'primary', 'submit', false ); ?></p>
	</form>
</section>
