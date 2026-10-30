<?php
/**
 * The page for llms.txt: file mode and custom text, llms-full.txt, and the robots.txt line.
 *
 * @package AgentReady
 */

use AgentReady\Admin\View;
use AgentReady\Settings\LlmsOptions;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template-local variables.

$name  = $vars['name'];
$modes = array(
	LlmsOptions::AUTOMATIC => array( __( 'Automatic', 'agentready' ), __( 'AgentReady lists your pages for you. This is the default.', 'agentready' ) ),
	LlmsOptions::CUSTOM    => array( __( 'Custom', 'agentready' ), __( 'Serve exactly the text you write below.', 'agentready' ) ),
	LlmsOptions::HYBRID    => array( __( 'Hybrid', 'agentready' ), __( 'Your text first, then the list of pages that AgentReady builds.', 'agentready' ) ),
);
$notes = array(
	'no_title' => __( 'The text does not start with a title line such as "# My site". Most llms.txt files do.', 'agentready' ),
	'external' => __( 'The text links to other sites. That is allowed, but check that you mean to.', 'agentready' ),
	'large'    => __( 'The text is very large. Agents read the whole file, so shorter is better.', 'agentready' ),
);
?>
<div class="wrap agentready">
	<h1 class="agentready-title">
		<?php esc_html_e( 'llms.txt', 'agentready' ); ?>
		<span class="agentready-version">v<?php echo esc_html( AGENTREADY_VERSION ); ?></span>
	</h1>
	<p class="agentready-tagline"><?php esc_html_e( 'Choose what AI agents find at /llms.txt, add a single file with all your content, and point crawlers to it.', 'agentready' ); ?></p>
	<?php View::render( 'notices', $vars['notices'] ); ?>
	<?php settings_errors(); ?>
	<form method="post" action="options.php" class="agentready-llms">
		<?php settings_fields( $vars['group'] ); ?>
		<section class="agentready-card">
			<h2><?php esc_html_e( 'The llms.txt file', 'agentready' ); ?></h2>
			<p class="agentready-intro">
				<?php if ( $vars['pretty'] ) : ?>
					<a href="<?php echo esc_url( $vars['llms_url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $vars['llms_url'] ); ?></a>
				<?php else : ?>
					<?php esc_html_e( 'Plain permalinks are on, so nothing is served. Switch to pretty permalinks first.', 'agentready' ); ?>
				<?php endif; ?>
			</p>
			<div class="agentready-field">
				<span class="agentready-label"><?php esc_html_e( 'Mode', 'agentready' ); ?></span>
				<?php foreach ( $modes as $value => $mode_text ) : ?>
					<p>
						<label>
							<input type="radio" name="<?php echo esc_attr( $name ); ?>[mode]" value="<?php echo esc_attr( $value ); ?>" data-agentready-mode <?php checked( $vars['mode'], $value ); ?> />
							<strong><?php echo esc_html( $mode_text[0] ); ?></strong>
							<span class="description"> &mdash; <?php echo esc_html( $mode_text[1] ); ?></span>
						</label>
					</p>
				<?php endforeach; ?>
			</div>
			<div class="agentready-field" data-agentready-custom <?php echo LlmsOptions::AUTOMATIC === $vars['mode'] ? 'hidden' : ''; ?>>
				<label for="agentready-llms-custom"><?php esc_html_e( 'Your text', 'agentready' ); ?></label>
				<textarea id="agentready-llms-custom" name="<?php echo esc_attr( $name ); ?>[custom]" rows="14" class="large-text code" placeholder="# My site&#10;&#10;> One sentence about what the site is.&#10;&#10;## Docs&#10;&#10;- [Getting started](https://example.com/start.md): The basics"><?php echo esc_textarea( $vars['custom'] ); ?></textarea>
				<p>
					<button type="button" class="button" data-agentready-load="agentready-llms-custom" data-max="<?php echo esc_attr( (string) $vars['max_bytes'] ); ?>" data-too-big="<?php esc_attr_e( 'That file is too large.', 'agentready' ); ?>"><?php esc_html_e( 'Load from file', 'agentready' ); ?></button>
					<input type="file" accept=".txt,.md,text/plain,text/markdown" hidden data-agentready-file />
					<span class="description" data-agentready-count data-template="<?php /* translators: 1: used bytes, 2: maximum bytes. */ echo esc_attr( sprintf( __( '%1$s of %2$s bytes', 'agentready' ), '{used}', '{max}' ) ); ?>" data-max="<?php echo esc_attr( (string) $vars['max_bytes'] ); ?>"></span>
				</p>
				<p class="description">
					<?php esc_html_e( 'Plain Markdown, served as text. In Hybrid mode, leave out the page list, because AgentReady adds it below your text. If this is empty, the generated file is served.', 'agentready' ); ?>
				</p>
				<?php foreach ( $vars['notes'] as $code ) : ?>
					<?php if ( isset( $notes[ $code ] ) ) : ?>
						<div class="notice notice-info inline"><p><?php echo esc_html( $notes[ $code ] ); ?></p></div>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
			<details>
				<summary><?php esc_html_e( 'What the file contains now (saved settings)', 'agentready' ); ?></summary>
				<pre class="agentready-code"><?php echo esc_html( $vars['preview'] ); ?></pre>
			</details>
		</section>

		<section class="agentready-card">
			<h2><?php esc_html_e( 'llms-full.txt', 'agentready' ); ?></h2>
			<p class="agentready-intro"><?php esc_html_e( 'One file with the Markdown of every page, for agents that prefer a single download.', 'agentready' ); ?></p>
			<div class="agentready-field">
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[full]" value="1" <?php checked( $vars['full'] ); ?> />
					<?php esc_html_e( 'Serve /llms-full.txt', 'agentready' ); ?>
				</label>
				<p class="description">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: number of pages. */
							_n( 'It is built from the stored copies, which today cover %s page.', 'It is built from the stored copies, which today cover %s pages.', $vars['stored'], 'agentready' ),
							number_format_i18n( $vars['stored'] )
						)
					);
					?>
					<?php if ( $vars['missing'] > 0 ) : ?>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: number of pages. */
								_n( '%s more page has no stored copy yet and is left out.', '%s more pages have no stored copy yet and are left out.', $vars['missing'], 'agentready' ),
								number_format_i18n( $vars['missing'] )
							)
						);
						?>
						<a href="<?php echo esc_url( $vars['verify_all_url'] ); ?>"><?php esc_html_e( 'Generate the missing ones', 'agentready' ); ?></a>
					<?php endif; ?>
					<?php esc_html_e( 'Large sites are cut off at 5 MB.', 'agentready' ); ?>
				</p>
				<?php if ( $vars['full'] && $vars['pretty'] ) : ?>
					<p class="description"><a href="<?php echo esc_url( $vars['full_url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $vars['full_url'] ); ?></a></p>
				<?php endif; ?>
			</div>
		</section>

		<section class="agentready-card">
			<h2><?php esc_html_e( 'robots.txt', 'agentready' ); ?></h2>
			<p class="agentready-intro"><?php esc_html_e( 'Help crawlers find the files. This only adds comment lines, so it never changes what crawlers may visit.', 'agentready' ); ?></p>
			<div class="agentready-field">
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[robots_line]" value="1" <?php checked( $vars['robots_line'] ); ?> />
					<?php esc_html_e( 'Mention llms.txt in robots.txt', 'agentready' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Adds lines such as "# llms.txt: https://example.com/llms.txt". Skipped when the site asks search engines not to index it.', 'agentready' ); ?></p>
				<?php if ( $vars['robots_file'] ) : ?>
					<div class="notice notice-warning inline"><p><?php esc_html_e( 'A real robots.txt file exists on the server, so WordPress does not generate one and this setting has no effect. Add the lines to that file yourself.', 'agentready' ); ?></p></div>
				<?php endif; ?>
			</div>
		</section>
		<?php submit_button(); ?>
	</form>
</div>
