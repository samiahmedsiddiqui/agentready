<?php
/**
 * Settings screen shell: title, notices, tabs and the active tab's content.
 *
 * @package AgentReady
 */

use AgentReady\Admin\View;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template-local variables.

$on_preview = false;
?>
<div class="wrap agentready">
	<h1 class="agentready-title">
		<?php esc_html_e( 'AgentReady', 'agentready' ); ?>
		<span class="agentready-version">v<?php echo esc_html( AGENTREADY_VERSION ); ?></span>
	</h1>
	<p class="agentready-tagline"><?php esc_html_e( 'Serve an llms.txt index and a Markdown version of every page, so AI agents can read your content.', 'agentready' ); ?></p>
	<?php View::render( 'notices', $vars['notices'] ); ?>
	<?php
	if ( ! empty( $vars['after_notices'] ) ) {
		$vars['after_notices']();
	}
	?>
	<nav class="nav-tab-wrapper">
		<?php foreach ( $vars['tabs'] as $slug => $label ) : ?>
			<a class="nav-tab <?php echo 'content' === $slug ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( $vars['settings_url'] . '#' . $slug ); ?>" data-agentready-tab="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?>
	</nav>
	<?php $vars['content'](); ?>
</div>
