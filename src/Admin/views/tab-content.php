<?php
/**
 * Content tab: post types, exclusions and the editor box.
 *
 * @package AgentWelcome
 */

use AgentWelcome\Admin\View;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template-local variables.

$name    = $vars['name'];
$options = $vars['options'];
?>
<section class="agentwelcome-card">
	<h2><?php esc_html_e( 'Exclusions', 'agentwelcome' ); ?></h2>
	<p class="agentwelcome-intro"><?php esc_html_e( 'Leave out whole post types, specific pages, or pages in a taxonomy term. You can also hide one page from its own editor screen.', 'agentwelcome' ); ?></p>
	<div class="agentwelcome-field">
		<span class="agentwelcome-label"><?php esc_html_e( 'Post types', 'agentwelcome' ); ?></span>
		<p class="description"><?php esc_html_e( 'Every public post type is served by default. Tick the ones to leave out.', 'agentwelcome' ); ?></p>
		<div class="agentwelcome-types">
			<?php foreach ( $vars['types'] as $item ) : ?>
				<label class="agentwelcome-type">
					<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[excluded_post_types][]" value="<?php echo esc_attr( $item['slug'] ); ?>" <?php checked( $item['excluded'] ); ?> />
					<span class="agentwelcome-type-name"><?php echo esc_html( $item['label'] ); ?></span>
					<span class="agentwelcome-type-meta"><?php echo esc_html( $item['published'] ); ?></span>
				</label>
			<?php endforeach; ?>
		</div>
	</div>
	<div class="agentwelcome-field">
		<label for="agentwelcome-excluded-posts"><?php esc_html_e( 'IDs and URL patterns', 'agentwelcome' ); ?></label>
		<textarea id="agentwelcome-excluded-posts" name="<?php echo esc_attr( $name ); ?>[excluded_posts]" rows="4" class="large-text code" placeholder="42&#10;checkout/*&#10;privacy"><?php echo esc_textarea( (string) $options->get( 'excluded_posts', '' ) ); ?></textarea>
		<p class="description"><?php esc_html_e( 'One per line. A number is a post ID. Anything else is a path where * matches anything.', 'agentwelcome' ); ?></p>
	</div>
	<?php View::render( 'partials/terms', $vars ); ?>
	<div class="agentwelcome-field">
		<label>
			<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[exclude_noindex]" value="1" <?php checked( (bool) $options->get( 'exclude_noindex', true ) ); ?> />
			<?php esc_html_e( 'Leave out pages set to noindex', 'agentwelcome' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Skips pages your SEO plugin marks as noindex, in llms.txt, llms-full.txt and as Markdown. Only the setting on the page itself is read.', 'agentwelcome' ); ?></p>
	</div>
</section>

<section class="agentwelcome-card">
	<h2><?php esc_html_e( 'Homepage', 'agentwelcome' ); ?></h2>
	<p class="agentwelcome-intro"><?php esc_html_e( 'Choose the Markdown URL of the homepage when a static page is set as the front page. Both URLs keep working. The one chosen here is listed in llms.txt and in the page head.', 'agentwelcome' ); ?></p>
	<div class="agentwelcome-field">
		<?php foreach ( $vars['home_urls'] as $file => $home_url ) : ?>
			<label>
				<input type="radio" name="<?php echo esc_attr( $name ); ?>[home_markdown_url]" value="<?php echo esc_attr( $file ); ?>" <?php checked( $file, (string) $options->get( 'home_markdown_url', 'index.md' ) ); ?> />
				<code><?php echo esc_html( $home_url ); ?></code>
				<?php if ( 'index.md' === $file ) : ?>
					<span class="description"><?php esc_html_e( '(default)', 'agentwelcome' ); ?></span>
				<?php endif; ?>
			</label><br />
		<?php endforeach; ?>
		<?php if ( $vars['home_collision'] ) : ?>
			<p class="description"><strong><?php esc_html_e( 'A page with the slug "index" exists, so /index.md would be ambiguous. /index.html.md is used until that page is renamed.', 'agentwelcome' ); ?></strong></p>
		<?php endif; ?>
	</div>
</section>

<section class="agentwelcome-card">
	<h2><?php esc_html_e( 'Editor', 'agentwelcome' ); ?></h2>
	<p class="agentwelcome-intro"><?php esc_html_e( 'Choose whether editors see AgentWelcome on the edit screen.', 'agentwelcome' ); ?></p>
	<div class="agentwelcome-field">
		<label>
			<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[show_meta_box]" value="1" <?php checked( (bool) $options->get( 'show_meta_box', true ) ); ?> />
			<?php esc_html_e( 'Show the AgentWelcome box on the edit screen', 'agentwelcome' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'The box lets editors hide a page, write its llms.txt description and refresh its Markdown. Hiding the box does not remove anything already saved on a page.', 'agentwelcome' ); ?></p>
	</div>
</section>
