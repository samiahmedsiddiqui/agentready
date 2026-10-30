<?php
/**
 * Warnings shown above the tabs.
 *
 * @package AgentReady
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template-local variables.

if ( null !== $vars['inactive'] ) :
	?>
	<div class="notice notice-warning">
		<p>
			<strong><?php esc_html_e( 'AgentReady is inactive on this site.', 'agentready' ); ?></strong>
			<?php echo esc_html( $vars['inactive'] ); ?>
			<?php esc_html_e( 'Markdown pages and llms.txt return the normal 404 page, and no discovery tag is added. Verify & Preview still works.', 'agentready' ); ?>
		</p>
		<?php if ( '' !== $vars['resume_url'] ) : ?>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $vars['resume_url'] ); ?>"><?php esc_html_e( 'Resume on this address', 'agentready' ); ?></a>
				<span class="description"><?php esc_html_e( 'Press this if the address change is intended, for example after moving the site to a new domain.', 'agentready' ); ?></span>
			</p>
		<?php endif; ?>
	</div>
<?php endif; ?>
<?php if ( ! $vars['pretty'] ) : ?>
	<div class="notice notice-warning"><p>
		<?php
		echo wp_kses(
			sprintf(
				/* translators: %s: link to the Permalinks settings screen. */
				__( 'AgentReady needs pretty permalinks. Plain permalinks (such as <code>?p=123</code>) are not supported, so no Markdown pages or llms.txt are served. Choose another structure in %s.', 'agentready' ),
				'<a href="' . esc_url( admin_url( 'options-permalink.php' ) ) . '">' . esc_html__( 'Settings → Permalinks', 'agentready' ) . '</a>'
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
				? __( 'Yoast SEO\'s llms.txt feature is on. Yoast saves a physical llms.txt file, which the web server serves before WordPress runs, so AgentReady\'s llms.txt is not used while that file exists. Turn the feature off in the Yoast SEO settings to use AgentReady\'s.', 'agentready' )
				: __( 'Yoast SEO\'s llms.txt feature is on, but its file does not exist yet, so AgentReady answers /llms.txt for now. When Yoast creates the file, it takes over. Turn the feature off in the Yoast SEO settings to keep using AgentReady\'s.', 'agentready' )
		);
		?>
	</p></div>
<?php elseif ( $vars['llms_file'] ) : ?>
	<div class="notice notice-warning"><p><?php esc_html_e( 'A physical llms.txt file exists in the site root. The web server serves it before WordPress runs, so AgentReady cannot replace it. Delete or rename the file to use the generated one.', 'agentready' ); ?></p></div>
<?php endif; ?>
