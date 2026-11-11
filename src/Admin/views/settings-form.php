<?php
/**
 * The settings form: one form with a panel per tab, and the status column.
 *
 * @package AgentWelcome
 */

use AgentWelcome\Admin\View;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template-local variables.
?>
<form method="post" action="options.php" class="agentwelcome-tabs" data-agentwelcome-tabs>
	<?php settings_fields( $vars['group'] ); ?>
	<div class="agentwelcome-grid">
		<div class="agentwelcome-main">
			<div class="agentwelcome-panel is-active" data-agentwelcome-panel="content"><?php View::render( 'tab-content', $vars ); ?></div>
			<div class="agentwelcome-panel" data-agentwelcome-panel="rendering"><?php View::render( 'tab-rendering', $vars ); ?></div>
			<div class="agentwelcome-panel" data-agentwelcome-panel="environment"><?php View::render( 'tab-environment', $vars ); ?></div>
			<?php submit_button(); ?>
		</div>
		<aside class="agentwelcome-side">
			<?php View::render( 'status', $vars['status'] ); ?>
		</aside>
	</div>
</form>
<form id="agentwelcome-cache-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="agentwelcome_cache" />
	<?php wp_nonce_field( 'agentwelcome_cache' ); ?>
</form>
