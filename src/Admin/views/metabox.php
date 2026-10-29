<?php
/**
 * Editor box.
 *
 * @package AgentReady
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template-local variables.

$edited = $vars['post'];
$state  = $vars['status'];
?>
<div class="agentready-box" data-agentready-box data-path="<?php echo esc_attr( '/agentready/v1/pages/' . $edited->ID ); ?>" data-working="<?php esc_attr_e( 'Working…', 'agentready' ); ?>" data-failed="<?php esc_attr_e( 'The request failed. Try again.', 'agentready' ); ?>">
	<p class="agentready-line" data-agentready-state="<?php echo esc_attr( $state['state'] ); ?>">
		<span class="agentready-dot" aria-hidden="true"></span>
		<span data-agentready-label><?php echo esc_html( $state['label'] ); ?></span>
	</p>
	<p class="description" data-agentready-detail><?php echo esc_html( $state['detail'] ); ?></p>
	<p class="agentready-box-actions">
		<button type="button" class="button" data-agentready-action="regenerate" <?php disabled( ! $state['can_regenerate'] ); ?>><?php esc_html_e( 'Regenerate now', 'agentready' ); ?></button>
		<button type="button" class="button" data-agentready-action="clear" <?php disabled( ! $state['can_clear'] ); ?>><?php esc_html_e( 'Clear', 'agentready' ); ?></button>
	</p>
	<p class="description"><?php esc_html_e( 'Save or update the page first. Regeneration reads the saved page.', 'agentready' ); ?></p>
</div>
<hr />
<p>
	<label>
		<input type="checkbox" name="agentready_exclude" value="1" <?php checked( $vars['excluded'] ); ?> />
		<?php esc_html_e( 'Hide this page from AgentReady', 'agentready' ); ?>
	</label>
</p>
<p>
	<label for="agentready_description"><?php esc_html_e( 'Description for llms.txt', 'agentready' ); ?></label>
	<textarea id="agentready_description" name="agentready_description" class="widefat" rows="3" maxlength="300"><?php echo esc_textarea( $vars['description'] ); ?></textarea>
</p>
