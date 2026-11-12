<?php
/**
 * A mode choice and a text box for one of the llms files, with the load-from-file button and the notes.
 *
 * @package AgentWelcome
 */

use AgentWelcome\Settings\LlmsOptions;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template-local variables.

$notes = array(
	'no_title' => __( 'The text does not start with a title line such as "# My site". Most llms.txt files do.', 'agentwelcome' ),
	'external' => __( 'The text links to other sites. That is allowed, but check that you mean to.', 'agentwelcome' ),
	'large'    => __( 'The text is very large. Agents read the whole file, so shorter is better.', 'agentwelcome' ),
);
?>
<div data-agentwelcome-editor>
	<div class="agentwelcome-field">
		<span class="agentwelcome-label"><?php esc_html_e( 'Mode', 'agentwelcome' ); ?></span>
		<?php foreach ( $vars['modes'] as $value => $mode_text ) : ?>
			<p>
				<label>
					<input type="radio" name="<?php echo esc_attr( $vars['name'] . '[' . $vars['mode_field'] . ']' ); ?>" value="<?php echo esc_attr( $value ); ?>" data-agentwelcome-mode <?php checked( $vars['mode'], $value ); ?> />
					<strong><?php echo esc_html( $mode_text[0] ); ?></strong>
					<span class="description"> &mdash; <?php echo esc_html( $mode_text[1] ); ?></span>
				</label>
			</p>
		<?php endforeach; ?>
	</div>
	<div class="agentwelcome-field" data-agentwelcome-custom <?php echo LlmsOptions::AUTOMATIC === $vars['mode'] ? 'hidden' : ''; ?>>
		<label for="<?php echo esc_attr( $vars['id'] ); ?>"><?php esc_html_e( 'Your text', 'agentwelcome' ); ?></label>
		<textarea id="<?php echo esc_attr( $vars['id'] ); ?>" name="<?php echo esc_attr( $vars['name'] . '[' . $vars['text_field'] . ']' ); ?>" rows="14" class="large-text code" placeholder="# My site&#10;&#10;> One sentence about what the site is.&#10;&#10;## Docs&#10;&#10;- [Getting started](https://example.com/start.md): The basics"><?php echo esc_textarea( $vars['text'] ); ?></textarea>
		<p>
			<button type="button" class="button" data-agentwelcome-load="<?php echo esc_attr( $vars['id'] ); ?>" data-max="<?php echo esc_attr( (string) $vars['max_bytes'] ); ?>" data-too-big="<?php esc_attr_e( 'That file is too large.', 'agentwelcome' ); ?>"><?php esc_html_e( 'Load from file', 'agentwelcome' ); ?></button>
			<input type="file" accept=".txt,.md,text/plain,text/markdown" hidden data-agentwelcome-file />
			<span class="description" data-agentwelcome-count data-template="<?php /* translators: 1: used bytes, 2: maximum bytes. */ echo esc_attr( sprintf( __( '%1$s of %2$s bytes', 'agentwelcome' ), '{used}', '{max}' ) ); ?>" data-max="<?php echo esc_attr( (string) $vars['max_bytes'] ); ?>"></span>
		</p>
		<p class="description"><?php echo esc_html( $vars['help'] ); ?></p>
		<?php foreach ( $vars['notes'] as $code ) : ?>
			<?php if ( isset( $notes[ $code ] ) ) : ?>
				<div class="notice notice-info inline"><p><?php echo esc_html( $notes[ $code ] ); ?></p></div>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>
</div>
