<?php
/**
 * Warnings shown above the tabs.
 *
 * @package AgentWelcome
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template-local variables.

if ( null !== $vars['inactive'] ) :
	?>
	<div class="notice notice-warning">
		<p>
			<strong><?php esc_html_e( 'AgentWelcome is inactive on this site.', 'agentwelcome' ); ?></strong>
			<?php echo esc_html( $vars['inactive'] ); ?>
			<?php esc_html_e( 'Markdown pages and llms.txt return the normal 404 page, and no discovery tag is added. Verify & Preview still works.', 'agentwelcome' ); ?>
		</p>
		<?php if ( '' !== $vars['resume_url'] ) : ?>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $vars['resume_url'] ); ?>"><?php esc_html_e( 'Resume on this address', 'agentwelcome' ); ?></a>
				<span class="description"><?php esc_html_e( 'Press this if the address change is intended, for example after moving the site to a new domain.', 'agentwelcome' ); ?></span>
			</p>
		<?php endif; ?>
	</div>
<?php endif; ?>
<?php foreach ( $vars['changes'] as $change ) : ?>
	<div class="notice notice-info">
		<p>
			<?php echo esc_html( $change['message'] ); ?>
			<?php if ( '' !== $change['check_url'] ) : ?>
				<a href="<?php echo esc_url( $change['check_url'] ); ?>"><?php esc_html_e( 'Check all pages', 'agentwelcome' ); ?></a>
			<?php endif; ?>
			<a href="<?php echo esc_url( $change['dismiss_url'] ); ?>"><?php esc_html_e( 'Dismiss', 'agentwelcome' ); ?></a>
		</p>
	</div>
<?php endforeach; ?>
<?php if ( ! $vars['pretty'] ) : ?>
	<div class="notice notice-warning"><p>
		<?php
		echo wp_kses(
			sprintf(
				/* translators: %s: link to the Permalinks settings screen. */
				__( 'AgentWelcome needs pretty permalinks. Plain permalinks (such as <code>?p=123</code>) are not supported, so no Markdown pages or llms.txt are served. Choose another structure in %s.', 'agentwelcome' ),
				'<a href="' . esc_url( admin_url( 'options-permalink.php' ) ) . '">' . esc_html__( 'Settings → Permalinks', 'agentwelcome' ) . '</a>'
			),
			array(
				'code' => array(),
				'a'    => array( 'href' => array() ),
			)
		);
		?>
	</p></div>
<?php endif; ?>
<?php if ( $vars['yoast_llms'] ) : ?>
	<div class="notice notice-warning"><p>
		<?php
		echo esc_html(
			$vars['llms_file']
				? __( 'Yoast SEO\'s llms.txt feature is on. Yoast saves a physical llms.txt file, which the web server serves before WordPress runs, so AgentWelcome\'s llms.txt is not used while that file exists. Turn the feature off in the Yoast SEO settings to use AgentWelcome\'s.', 'agentwelcome' )
				: __( 'Yoast SEO\'s llms.txt feature is on, but its file does not exist yet, so AgentWelcome answers /llms.txt for now. When Yoast creates the file, it takes over. Turn the feature off in the Yoast SEO settings to keep using AgentWelcome\'s.', 'agentwelcome' )
		);
		?>
	</p></div>
<?php elseif ( $vars['llms_file'] ) : ?>
	<div class="notice notice-warning"><p><?php esc_html_e( 'A physical llms.txt file exists in the site root. The web server serves it before WordPress runs, so AgentWelcome cannot replace it. Delete or rename the file to use the generated one.', 'agentwelcome' ); ?></p></div>
<?php endif; ?>
<?php if ( $vars['full_file'] ) : ?>
	<div class="notice notice-warning"><p><?php esc_html_e( 'A physical llms-full.txt file exists in the site root. The web server serves it before WordPress runs, so AgentWelcome cannot replace it. Delete or rename the file to use the generated one.', 'agentwelcome' ); ?></p></div>
<?php endif; ?>
