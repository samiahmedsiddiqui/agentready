<?php
/**
 * The page for llms.txt: file mode and custom text, llms-full.txt, and the robots.txt line.
 *
 * @package AgentWelcome
 */

use AgentWelcome\Admin\View;
use AgentWelcome\Settings\LlmsOptions;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template-local variables.

$name       = $vars['name'];
$modes      = array(
	LlmsOptions::AUTOMATIC => array( __( 'Automatic', 'agentwelcome' ), __( 'AgentWelcome lists your pages for you. This is the default.', 'agentwelcome' ) ),
	LlmsOptions::CUSTOM    => array( __( 'Custom', 'agentwelcome' ), __( 'Serve exactly the text you write below.', 'agentwelcome' ) ),
	LlmsOptions::HYBRID    => array( __( 'Hybrid', 'agentwelcome' ), __( 'Your text first, then the list of pages that AgentWelcome builds.', 'agentwelcome' ) ),
);
$full_modes = array(
	LlmsOptions::AUTOMATIC => array( __( 'Automatic', 'agentwelcome' ), __( 'AgentWelcome joins the stored Markdown of your pages. This is the default.', 'agentwelcome' ) ),
	LlmsOptions::CUSTOM    => array( __( 'Custom', 'agentwelcome' ), __( 'Serve exactly the text you write below.', 'agentwelcome' ) ),
	LlmsOptions::HYBRID    => array( __( 'Hybrid', 'agentwelcome' ), __( 'Your text first, then the stored Markdown of your pages.', 'agentwelcome' ) ),
);
?>
<div class="wrap agentwelcome">
	<h1 class="agentwelcome-title">
		<?php esc_html_e( 'llms.txt', 'agentwelcome' ); ?>
		<span class="agentwelcome-version">v<?php echo esc_html( AGENTWELCOME_VERSION ); ?></span>
	</h1>
	<p class="agentwelcome-tagline"><?php esc_html_e( 'Choose what AI agents find at /llms.txt, add a single file with all your content, and point crawlers to it.', 'agentwelcome' ); ?></p>
	<?php View::render( 'notices', $vars['notices'] ); ?>
	<?php settings_errors(); ?>
	<form method="post" action="options.php" class="agentwelcome-llms">
		<?php settings_fields( $vars['group'] ); ?>
		<section class="agentwelcome-card">
			<h2><?php esc_html_e( 'The llms.txt file', 'agentwelcome' ); ?></h2>
			<p class="agentwelcome-intro">
				<?php if ( $vars['pretty'] ) : ?>
					<a href="<?php echo esc_url( $vars['llms_url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $vars['llms_url'] ); ?></a>
				<?php else : ?>
					<?php esc_html_e( 'Plain permalinks are on, so nothing is served. Switch to pretty permalinks first.', 'agentwelcome' ); ?>
				<?php endif; ?>
			</p>
			<?php
			View::render(
				'llms-editor',
				array(
					'name'       => $name,
					'mode_field' => 'mode',
					'text_field' => 'custom',
					'id'         => 'agentwelcome-llms-custom',
					'mode'       => $vars['mode'],
					'text'       => $vars['custom'],
					'notes'      => $vars['notes'],
					'modes'      => $modes,
					'max_bytes'  => $vars['max_bytes'],
					'help'       => __( 'Plain Markdown, served as text. In Hybrid mode, leave out the page list, because AgentWelcome adds it below your text. If this is empty, the generated file is served.', 'agentwelcome' ),
				)
			);
			?>
			<details>
				<summary><?php esc_html_e( 'What the file contains now (saved settings)', 'agentwelcome' ); ?></summary>
				<pre class="agentwelcome-code"><?php echo esc_html( $vars['preview'] ); ?></pre>
			</details>
		</section>

		<section class="agentwelcome-card">
			<h2><?php esc_html_e( 'llms-full.txt', 'agentwelcome' ); ?></h2>
			<p class="agentwelcome-intro"><?php esc_html_e( 'One file with the Markdown of every page, for agents that prefer a single download.', 'agentwelcome' ); ?></p>
			<div class="agentwelcome-field">
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[full]" value="1" <?php checked( $vars['full'] ); ?> />
					<?php esc_html_e( 'Serve /llms-full.txt', 'agentwelcome' ); ?>
				</label>
				<p class="description">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: number of pages. */
							_n( 'It is built from the stored copies, which today cover %s page.', 'It is built from the stored copies, which today cover %s pages.', $vars['stored'], 'agentwelcome' ),
							number_format_i18n( $vars['stored'] )
						)
					);
					?>
					<?php if ( $vars['missing'] > 0 ) : ?>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: number of pages. */
								_n( '%s more page has no stored copy yet and is left out.', '%s more pages have no stored copy yet and are left out.', $vars['missing'], 'agentwelcome' ),
								number_format_i18n( $vars['missing'] )
							)
						);
						?>
						<?php if ( '' !== $vars['verify_all_url'] ) : ?>
							<a href="<?php echo esc_url( $vars['verify_all_url'] ); ?>"><?php esc_html_e( 'Generate the missing ones', 'agentwelcome' ); ?></a>
						<?php endif; ?>
					<?php endif; ?>
					<?php esc_html_e( 'Large sites are cut off at 5 MB.', 'agentwelcome' ); ?>
				</p>
				<?php if ( $vars['full'] && $vars['pretty'] ) : ?>
					<p class="description"><a href="<?php echo esc_url( $vars['full_url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $vars['full_url'] ); ?></a></p>
				<?php endif; ?>
			</div>
			<?php
			View::render(
				'llms-editor',
				array(
					'name'       => $name,
					'mode_field' => 'full_mode',
					'text_field' => 'full_custom',
					'id'         => 'agentwelcome-llms-full-custom',
					'mode'       => $vars['full_mode'],
					'text'       => $vars['full_custom'],
					'notes'      => $vars['full_notes'],
					'modes'      => $full_modes,
					'max_bytes'  => $vars['max_bytes'],
					'help'       => __( 'Plain Markdown, served as text. In Hybrid mode, leave out the pages, because AgentWelcome adds them below your text. The file stops at 5 MB. If this is empty, the generated file is served.', 'agentwelcome' ),
				)
			);
			?>
		</section>

		<section class="agentwelcome-card">
			<h2><?php esc_html_e( 'robots.txt', 'agentwelcome' ); ?></h2>
			<p class="agentwelcome-intro"><?php esc_html_e( 'Help crawlers find the files. This only adds comment lines, so it never changes what crawlers may visit.', 'agentwelcome' ); ?></p>
			<div class="agentwelcome-field">
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[robots_line]" value="1" <?php checked( $vars['robots_line'] ); ?> />
					<?php esc_html_e( 'Mention llms.txt in robots.txt', 'agentwelcome' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Adds lines such as "# llms.txt: https://example.com/llms.txt". Skipped when the site asks search engines not to index it.', 'agentwelcome' ); ?></p>
				<?php if ( $vars['robots_file'] ) : ?>
					<div class="notice notice-warning inline"><p><?php esc_html_e( 'A real robots.txt file exists on the server, so WordPress does not generate one and this setting has no effect. Add the lines to that file yourself.', 'agentwelcome' ); ?></p></div>
				<?php endif; ?>
			</div>
		</section>
		<?php submit_button(); ?>
	</form>
</div>
