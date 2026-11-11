<?php
/**
 * Environment tab: where AgentWelcome runs, and what uninstalling removes.
 *
 * @package AgentWelcome
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template-local variables.

$name        = $vars['name'];
$options     = $vars['options'];
$environment = $vars['environment'];
$locked      = $environment->urls_from_constant();
$ticked      = $environment->allowed_types();
?>
<section class="agentwelcome-card">
	<h2><?php esc_html_e( 'Where AgentWelcome runs', 'agentwelcome' ); ?></h2>
	<p class="agentwelcome-intro">
		<?php
		/* translators: %s: environment type such as production. */
		echo wp_kses( sprintf( __( 'This site reports the environment type %s. Production is always allowed. Tick the other environments that should serve Markdown too.', 'agentwelcome' ), '<code>' . esc_html( $environment->type() ) . '</code>' ), array( 'code' => array() ) );
		?>
	</p>
	<div class="agentwelcome-field">
		<span class="agentwelcome-label"><?php esc_html_e( 'Also run on', 'agentwelcome' ); ?></span>
		<div class="agentwelcome-types">
			<?php foreach ( $environment::OPTIONAL as $env_type ) : ?>
				<label class="agentwelcome-type">
					<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[allowed_environments][]" value="<?php echo esc_attr( $env_type ); ?>" <?php checked( in_array( $env_type, $ticked, true ) ); ?> />
					<span class="agentwelcome-type-name"><?php echo esc_html( ucfirst( $env_type ) ); ?></span>
				</label>
			<?php endforeach; ?>
		</div>
		<p class="description"><?php esc_html_e( 'Set the environment type with WP_ENVIRONMENT_TYPE in wp-config.php. Many hosts set it for you. When it is not set, WordPress treats the site as production.', 'agentwelcome' ); ?></p>
	</div>
	<div class="agentwelcome-field">
		<label for="agentwelcome-allowed-urls"><?php esc_html_e( 'Allowed URLs', 'agentwelcome' ); ?></label>
		<?php if ( $locked ) : ?>
			<p><em><?php esc_html_e( 'The allowed URLs are set with AGENTWELCOME_ALLOWED_URLS in wp-config.php.', 'agentwelcome' ); ?></em></p>
		<?php endif; ?>
		<textarea id="agentwelcome-allowed-urls" name="<?php echo esc_attr( $name ); ?>[allowed_urls]" rows="3" class="large-text code" placeholder="www.example.com&#10;*.example.com" <?php disabled( $locked ); ?>><?php echo esc_textarea( implode( "\n", $environment->allowed_urls() ) ); ?></textarea>
		<p class="description"><?php esc_html_e( 'Optional. One host per line, where * matches anything. When set, AgentWelcome only runs if this site\'s host matches one of them. Leave empty to allow any host.', 'agentwelcome' ); ?></p>
		<p class="description"><?php esc_html_e( 'Staging copies made by some hosts replace the domain inside the database, which can change this setting. To keep it safe, define AGENTWELCOME_ALLOWED_URLS in wp-config.php instead. Whatever you set, AgentWelcome also remembers the site address and pauses itself when it changes, which is what a copy or a move does. You then resume it from a banner. Use define( \'AGENTWELCOME_ENABLED\', false ); to switch the plugin off completely.', 'agentwelcome' ); ?></p>
	</div>
</section>

<section class="agentwelcome-card">
	<h2><?php esc_html_e( 'Data', 'agentwelcome' ); ?></h2>
	<p class="agentwelcome-intro"><?php esc_html_e( 'The stored Markdown and any saved HTTP Basic credentials are always removed when the plugin is deleted.', 'agentwelcome' ); ?></p>
	<div class="agentwelcome-field">
		<label>
			<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[delete_data]" value="1" <?php checked( (bool) $options->get( 'delete_data', false ) ); ?> />
			<?php esc_html_e( 'Delete all data on uninstall', 'agentwelcome' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Also removes these settings and the per-page choices made in the editor box.', 'agentwelcome' ); ?></p>
	</div>
</section>
