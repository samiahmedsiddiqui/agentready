<?php
/**
 * Content tab: post types, exclusions and the editor box.
 *
 * @package AgentReady
 */

use AgentReady\Admin\View;

defined( 'ABSPATH' ) || exit;

$name    = $vars['name'];
$options = $vars['options'];
?>
<section class="agentready-card">
	<h2><?php esc_html_e( 'Post types', 'agentready' ); ?></h2>
	<p class="agentready-intro"><?php esc_html_e( 'Every public post type is served by default. Tick the ones to leave out.', 'agentready' ); ?></p>
	<div class="agentready-types">
		<?php foreach ( $vars['types'] as $item ) : ?>
			<label class="agentready-type">
				<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[excluded_post_types][]" value="<?php echo esc_attr( $item['slug'] ); ?>" <?php checked( $item['excluded'] ); ?> />
				<span class="agentready-type-name"><?php echo esc_html( $item['label'] ); ?></span>
				<span class="agentready-type-meta"><?php echo esc_html( $item['published'] ); ?></span>
			</label>
		<?php endforeach; ?>
	</div>
</section>

<section class="agentready-card">
	<h2><?php esc_html_e( 'Exclusions', 'agentready' ); ?></h2>
	<p class="agentready-intro"><?php esc_html_e( 'Leave out specific pages. You can also hide one page from its own editor screen.', 'agentready' ); ?></p>
	<div class="agentready-field">
		<label for="agentready-excluded-posts"><?php esc_html_e( 'IDs and URL patterns', 'agentready' ); ?></label>
		<textarea id="agentready-excluded-posts" name="<?php echo esc_attr( $name ); ?>[excluded_posts]" rows="4" class="large-text code" placeholder="42&#10;checkout/*&#10;privacy"><?php echo esc_textarea( (string) $options->get( 'excluded_posts', '' ) ); ?></textarea>
		<p class="description"><?php esc_html_e( 'One per line. A number is a post ID. Anything else is a path where * matches anything.', 'agentready' ); ?></p>
	</div>
	<?php View::render( 'partials/terms', $vars ); ?>
</section>

<section class="agentready-card">
	<h2><?php esc_html_e( 'Editor', 'agentready' ); ?></h2>
	<p class="agentready-intro"><?php esc_html_e( 'Choose whether editors see AgentReady on the edit screen.', 'agentready' ); ?></p>
	<div class="agentready-field">
		<label>
			<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[show_meta_box]" value="1" <?php checked( (bool) $options->get( 'show_meta_box', true ) ); ?> />
			<?php esc_html_e( 'Show the AgentReady box on the edit screen', 'agentready' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'The box lets editors hide a page, write its llms.txt description and refresh its Markdown. Hiding the box does not remove anything already saved on a page.', 'agentready' ); ?></p>
	</div>
</section>
