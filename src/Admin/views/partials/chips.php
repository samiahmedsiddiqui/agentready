<?php
/**
 * Buttons that add a common selector to a field.
 *
 * @package AgentWelcome
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template-local variables.
?>
<div class="agentwelcome-chips">
	<span class="agentwelcome-chips-label"><?php esc_html_e( 'Common:', 'agentwelcome' ); ?></span>
	<?php foreach ( $vars['items'] as $item ) : ?>
		<button type="button" class="button button-small" data-agentwelcome-add="<?php echo esc_attr( $item ); ?>" data-target="<?php echo esc_attr( $vars['target'] ); ?>" data-separator="<?php echo esc_attr( $vars['mode'] ); ?>"><?php echo esc_html( $item ); ?></button>
	<?php endforeach; ?>
</div>
