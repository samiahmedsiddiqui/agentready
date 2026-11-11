<?php
/**
 * Editor box.
 *
 * @package AgentWelcome
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template-local variables.

$edited = $vars['post'];
$state  = $vars['status'];
?>
<div class="agentwelcome-box" data-agentwelcome-box data-path="<?php echo esc_attr( '/agentwelcome/v1/pages/' . $edited->ID ); ?>" data-working="<?php esc_attr_e( 'Working…', 'agentwelcome' ); ?>" data-failed="<?php esc_attr_e( 'The request failed. Try again.', 'agentwelcome' ); ?>">
	<p class="agentwelcome-line" data-agentwelcome-state="<?php echo esc_attr( $state['state'] ); ?>">
		<span class="agentwelcome-dot" aria-hidden="true"></span>
		<span data-agentwelcome-label><?php echo esc_html( $state['label'] ); ?></span>
	</p>
	<p class="description" data-agentwelcome-detail><?php echo esc_html( $state['detail'] ); ?></p>
	<p class="agentwelcome-box-actions">
		<button type="button" class="button" data-agentwelcome-action="regenerate" <?php disabled( ! $state['can_regenerate'] ); ?>><?php esc_html_e( 'Regenerate now', 'agentwelcome' ); ?></button>
		<button type="button" class="button" data-agentwelcome-action="clear" <?php disabled( ! $state['can_clear'] ); ?>><?php esc_html_e( 'Clear', 'agentwelcome' ); ?></button>
	</p>
	<p class="description"><?php esc_html_e( 'Save or update the page first. Regeneration reads the saved page.', 'agentwelcome' ); ?></p>
	<?php if ( '' !== $vars['verify_url'] && 'unavailable' !== $state['state'] ) : ?>
		<p><a href="<?php echo esc_url( $vars['verify_url'] ); ?>"><?php esc_html_e( 'Check against the page', 'agentwelcome' ); ?></a></p>
	<?php endif; ?>
</div>
<hr />
<p>
	<label>
		<input type="checkbox" name="agentwelcome_exclude" value="1" <?php checked( $vars['excluded'] ); ?> />
		<?php esc_html_e( 'Hide this page from AgentWelcome', 'agentwelcome' ); ?>
	</label>
</p>
<p>
	<label for="agentwelcome_description"><?php esc_html_e( 'Description for llms.txt', 'agentwelcome' ); ?></label>
	<textarea id="agentwelcome_description" name="agentwelcome_description" class="widefat" rows="3" maxlength="300"><?php echo esc_textarea( $vars['description'] ); ?></textarea>
	<span class="description"><?php esc_html_e( 'When empty, the SEO plugin\'s meta description is used, then the excerpt. The page content is never used.', 'agentwelcome' ); ?></span>
</p>
