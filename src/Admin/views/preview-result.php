<?php
/**
 * Preview result: the page and whether it is served.
 *
 * @package AgentReady
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template-local variables.

$found = $vars['post'];
?>
<section class="agentready-card">
	<h2><?php echo esc_html( $vars['title'] ); ?></h2>
	<dl class="agentready-result-grid">
		<dt><?php esc_html_e( 'Post', 'agentready' ); ?></dt>
		<dd>#<?php echo esc_html( (string) $found->ID ); ?> (<?php echo esc_html( $found->post_type ); ?>)</dd>
		<dt><?php esc_html_e( 'Status', 'agentready' ); ?></dt>
		<dd><?php echo esc_html( $vars['status'] ); ?></dd>
		<?php if ( $vars['served'] && null !== $vars['md_url'] ) : ?>
			<dt><?php esc_html_e( 'Markdown URL', 'agentready' ); ?></dt>
			<dd><a href="<?php echo esc_url( $vars['md_url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $vars['md_url'] ); ?></a></dd>
		<?php endif; ?>
	</dl>
</section>
