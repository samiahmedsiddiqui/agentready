<?php
/**
 * The settings form: one form with a panel per tab, and the status column.
 *
 * @package AgentReady
 */

use AgentReady\Admin\View;

defined( 'ABSPATH' ) || exit;
?>
<form method="post" action="options.php" class="agentready-tabs" data-agentready-tabs>
	<?php settings_fields( $vars['group'] ); ?>
	<div class="agentready-grid">
		<div class="agentready-main">
			<div class="agentready-panel is-active" data-agentready-panel="content"><?php View::render( 'tab-content', $vars ); ?></div>
			<div class="agentready-panel" data-agentready-panel="rendering"><?php View::render( 'tab-rendering', $vars ); ?></div>
			<div class="agentready-panel" data-agentready-panel="environment"><?php View::render( 'tab-environment', $vars ); ?></div>
			<?php submit_button(); ?>
		</div>
		<aside class="agentready-side">
			<?php View::render( 'status', $vars['status'] ); ?>
		</aside>
	</div>
</form>
<form id="agentready-cache-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="agentready_cache" />
	<?php wp_nonce_field( 'agentready_cache' ); ?>
</form>
