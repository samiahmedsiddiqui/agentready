<?php
/**
 * Environment tab: where AgentReady runs, and what uninstalling removes.
 *
 * @package AgentReady
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template-local variables.

$name        = $vars['name'];
$options     = $vars['options'];
$environment = $vars['environment'];
$locked      = $environment->urls_from_constant();
$ticked      = $environment->allowed_types();
?>
<section class="agentready-card">
	<h2><?php esc_html_e( 'Where AgentReady runs', 'agentready' ); ?></h2>
	<p class="agentready-intro">
		<?php
		/* translators: %s: environment type such as production. */
		echo wp_kses( sprintf( __( 'This site reports the environment type %s. Production is always allowed. Tick the other environments that should serve Markdown too.', 'agentready' ), '<code>' . esc_html( $environment->type() ) . '</code>' ), array( 'code' => array() ) );
		?>
	</p>
	<div class="agentready-field">
		<span class="agentready-label"><?php esc_html_e( 'Also run on', 'agentready' ); ?></span>
		<div class="agentready-types">
			<?php foreach ( $environment::OPTIONAL as $env_type ) : ?>
				<label class="agentready-type">
					<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[allowed_environments][]" value="<?php echo esc_attr( $env_type ); ?>" <?php checked( in_array( $env_type, $ticked, true ) ); ?> />
					<span class="agentready-type-name"><?php echo esc_html( ucfirst( $env_type ) ); ?></span>
				</label>
			<?php endforeach; ?>
		</div>
		<p class="description"><?php esc_html_e( 'Set the environment type with WP_ENVIRONMENT_TYPE in wp-config.php. Many hosts set it for you. When it is not set, WordPress treats the site as production.', 'agentready' ); ?></p>
	</div>
	<div class="agentready-field">
		<label for="agentready-allowed-urls"><?php esc_html_e( 'Allowed URLs', 'agentready' ); ?></label>
		<?php if ( $locked ) : ?>
			<p><em><?php esc_html_e( 'The allowed URLs are set with AGENTREADY_ALLOWED_URLS in wp-config.php.', 'agentready' ); ?></em></p>
		<?php endif; ?>
		<textarea id="agentready-allowed-urls" name="<?php echo esc_attr( $name ); ?>[allowed_urls]" rows="3" class="large-text code" placeholder="www.example.com&#10;*.example.com" <?php disabled( $locked ); ?>><?php echo esc_textarea( implode( "\n", $environment->allowed_urls() ) ); ?></textarea>
		<p class="description"><?php esc_html_e( 'Optional. One host per line, where * matches anything. When set, AgentReady only runs if this site\'s host matches one of them. Leave empty to allow any host.', 'agentready' ); ?></p>
		<p class="description"><?php esc_html_e( 'Staging copies made by some hosts replace the domain inside the database, which can change this setting. To keep it safe, define AGENTREADY_ALLOWED_URLS in wp-config.php instead. Whatever you set, AgentReady also remembers the site address and pauses itself when it changes, which is what a copy or a move does. You then resume it from a banner. Use define( \'AGENTREADY_ENABLED\', false ); to switch the plugin off completely.', 'agentready' ); ?></p>
	</div>
</section>

<section class="agentready-card">
	<h2><?php esc_html_e( 'Data', 'agentready' ); ?></h2>
	<p class="agentready-intro"><?php esc_html_e( 'The stored Markdown and any saved HTTP Basic credentials are always removed when the plugin is deleted.', 'agentready' ); ?></p>
	<div class="agentready-field">
		<label>
			<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[delete_data]" value="1" <?php checked( (bool) $options->get( 'delete_data', false ) ); ?> />
			<?php esc_html_e( 'Delete all data on uninstall', 'agentready' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Also removes these settings and the per-page choices made in the editor box.', 'agentready' ); ?></p>
	</div>
</section>
