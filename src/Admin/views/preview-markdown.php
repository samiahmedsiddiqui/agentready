<?php
/**
 * Preview result: the generated Markdown.
 *
 * @package AgentReady
 */

use AgentReady\Rendering\RenderResult;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template-local variables.

$result = $vars['page'];
?>
<section class="agentready-card">
	<div class="agentready-toolbar">
		<span><strong><?php esc_html_e( 'Source:', 'agentready' ); ?></strong> <?php echo esc_html( RenderResult::LOOPBACK === $result->source ? __( 'Rendered page (loopback request)', 'agentready' ) : __( 'Post content (fallback)', 'agentready' ) ); ?></span>
		<button type="button" class="button" data-agentready-copy="agentready-markdown" data-copied="<?php esc_attr_e( 'Copied', 'agentready' ); ?>"><?php esc_html_e( 'Copy Markdown', 'agentready' ); ?></button>
	</div>
	<textarea id="agentready-markdown" class="agentready-markdown" rows="24" readonly><?php echo esc_textarea( $result->markdown ); ?></textarea>
</section>
