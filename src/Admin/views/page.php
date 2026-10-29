<?php
/**
 * Settings screen shell: title, notices, tabs and the active tab's content.
 *
 * @package AgentReady
 */

use AgentReady\Admin\View;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template-local variables.

$on_preview = 'preview' === $vars['tab'];
?>
<div class="wrap agentready">
	<h1 class="agentready-title">
		<?php esc_html_e( 'AgentReady', 'agentready' ); ?>
		<span class="agentready-version">v<?php echo esc_html( AGENTREADY_VERSION ); ?></span>
	</h1>
	<p class="agentready-tagline"><?php esc_html_e( 'Serve an llms.txt index and a Markdown version of every page, so AI agents can read your content.', 'agentready' ); ?></p>
	<?php View::render( 'notices', $vars['notices'] ); ?>
	<nav class="nav-tab-wrapper">
		<?php foreach ( $vars['tabs'] as $slug => $label ) : ?>
			<a class="nav-tab <?php echo ! $on_preview && 'content' === $slug ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( $vars['settings_url'] . '#' . $slug ); ?>" data-agentready-tab="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?>
		<a class="nav-tab <?php echo $on_preview ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( $vars['preview_url'] ); ?>"><?php esc_html_e( 'Preview', 'agentready' ); ?></a>
	</nav>
	<?php $vars['content'](); ?>
</div>
