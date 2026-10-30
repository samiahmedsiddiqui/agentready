<?php
/**
 * Verify & Preview shell: title, notices, tabs and the active tab's content.
 *
 * @package AgentReady
 */

use AgentReady\Admin\View;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template-local variables.
?>
<div class="wrap agentready">
	<h1 class="agentready-title">
		<?php esc_html_e( 'Verify & Preview', 'agentready' ); ?>
		<span class="agentready-version">v<?php echo esc_html( AGENTREADY_VERSION ); ?></span>
	</h1>
	<p class="agentready-tagline"><?php esc_html_e( 'Check that the stored Markdown matches what a visitor sees, preview it, and refresh anything that is out of date.', 'agentready' ); ?></p>
	<?php View::render( 'notices', $vars['notices'] ); ?>
	<nav class="nav-tab-wrapper">
		<?php foreach ( $vars['tabs'] as $slug => $entry ) : ?>
			<a class="nav-tab <?php echo $slug === $vars['tab'] ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( $entry[1] ); ?>"><?php echo esc_html( $entry[0] ); ?></a>
		<?php endforeach; ?>
	</nav>
	<?php $vars['content'](); ?>
</div>
