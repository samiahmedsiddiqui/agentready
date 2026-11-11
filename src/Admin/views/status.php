<?php
/**
 * Status column: system info, served content, cache counts and cache buttons.
 *
 * @package AgentWelcome
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template-local variables.

$cache = $vars['cache'];
?>
<section class="agentwelcome-card">
	<h2><?php esc_html_e( 'Status', 'agentwelcome' ); ?></h2>
	<ul class="agentwelcome-status">
		<li>
			<?php if ( null === $vars['inactive'] ) : ?>
				<span class="dashicons dashicons-yes-alt agentwelcome-ok"></span>
				<span><?php esc_html_e( 'AgentWelcome is active.', 'agentwelcome' ); ?></span>
			<?php else : ?>
				<span class="dashicons dashicons-warning agentwelcome-warn"></span>
				<span><?php esc_html_e( 'AgentWelcome is inactive here. Nothing is served.', 'agentwelcome' ); ?></span>
			<?php endif; ?>
		</li>
		<li>
			<?php if ( $vars['pretty'] ) : ?>
				<span class="dashicons dashicons-yes-alt agentwelcome-ok"></span>
				<span><?php esc_html_e( 'Pretty permalinks are on.', 'agentwelcome' ); ?></span>
			<?php else : ?>
				<span class="dashicons dashicons-warning agentwelcome-warn"></span>
				<span><?php esc_html_e( 'Plain permalinks are on. Nothing is served.', 'agentwelcome' ); ?></span>
			<?php endif; ?>
		</li>
		<?php if ( $vars['pretty'] ) : ?>
			<li>
				<span class="dashicons dashicons-media-text"></span>
				<span><a href="<?php echo esc_url( home_url( '/llms.txt' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View llms.txt', 'agentwelcome' ); ?></a></span>
			</li>
		<?php endif; ?>
		<?php foreach ( $vars['types'] as $item ) : ?>
			<li>
				<span class="dashicons dashicons-admin-page"></span>
				<span><?php echo esc_html( $item['label'] . ': ' . $item['published'] ); ?></span>
			</li>
		<?php endforeach; ?>
	</ul>
</section>

<section class="agentwelcome-card">
	<h2><?php esc_html_e( 'Stored Markdown', 'agentwelcome' ); ?></h2>
	<ul class="agentwelcome-status">
		<li><span class="dashicons dashicons-yes-alt agentwelcome-ok"></span><span><?php echo esc_html( sprintf( /* translators: %s: number of pages. */ _n( '%s page up to date', '%s pages up to date', $cache['fresh'], 'agentwelcome' ), number_format_i18n( $cache['fresh'] ) ) ); ?></span></li>
		<li><span class="dashicons dashicons-update"></span><span><?php echo esc_html( sprintf( /* translators: %s: number of pages. */ _n( '%s page out of date', '%s pages out of date', $cache['stale'], 'agentwelcome' ), number_format_i18n( $cache['stale'] ) ) ); ?></span></li>
		<li><span class="dashicons dashicons-clock"></span><span><?php echo esc_html( sprintf( /* translators: %s: number of pages. */ _n( '%s page not generated yet', '%s pages not generated yet', $cache['missing'], 'agentwelcome' ), number_format_i18n( $cache['missing'] ) . ( $cache['capped'] ? '+' : '' ) ) ); ?></span></li>
		<?php if ( $vars['running'] ) : ?>
			<li><span class="dashicons dashicons-update-alt"></span><span><?php esc_html_e( 'A background refresh is running.', 'agentwelcome' ); ?></span></li>
		<?php endif; ?>
	</ul>
	<?php if ( $vars['cron_disabled'] ) : ?>
		<p class="description"><?php esc_html_e( 'WP-Cron is disabled on this site, so background refreshes only run if a real cron job calls wp-cron.php. Pages are still generated when they are first requested.', 'agentwelcome' ); ?></p>
	<?php endif; ?>
	<p class="description"><?php esc_html_e( 'Servers and CDNs may keep .md and llms.txt for a few minutes (AgentWelcome allows 5). If a change does not show yet, add a query string to the URL, such as ?t=123, to skip that cache.', 'agentwelcome' ); ?></p>
	<p class="agentwelcome-actions">
		<button type="submit" form="agentwelcome-cache-form" name="cache_action" value="regenerate_all" class="button" data-agentwelcome-confirm="<?php esc_attr_e( 'Mark every page as out of date and rebuild them in the background? Current copies keep being served meanwhile.', 'agentwelcome' ); ?>" <?php disabled( null !== $vars['inactive'] ); ?>><?php esc_html_e( 'Regenerate all', 'agentwelcome' ); ?></button>
		<button type="submit" form="agentwelcome-cache-form" name="cache_action" value="clear" class="button" data-agentwelcome-confirm="<?php esc_attr_e( 'Delete every stored copy? Pages are rebuilt in the background and when first requested.', 'agentwelcome' ); ?>"><?php esc_html_e( 'Clear cache', 'agentwelcome' ); ?></button>
	</p>
</section>

<section class="agentwelcome-card">
	<h2><?php esc_html_e( 'System', 'agentwelcome' ); ?></h2>
	<dl class="agentwelcome-system">
		<?php foreach ( $vars['system'] as $label => $value ) : ?>
			<dt><?php echo esc_html( $label ); ?></dt>
			<dd><?php echo esc_html( $value ); ?></dd>
		<?php endforeach; ?>
	</dl>
</section>

<section class="agentwelcome-card">
	<h2><?php esc_html_e( 'How it works', 'agentwelcome' ); ?></h2>
	<ol class="agentwelcome-steps">
		<li><?php echo esc_html( sprintf( /* translators: %s: homepage Markdown path. */ __( 'Add .md to a page URL, for example /about.md. The homepage is /%s.', 'agentwelcome' ), $vars['home_file'] ) ); ?></li>
		<li><?php esc_html_e( 'The page is read like a visitor sees it, so theme, ACF and builder output is included.', 'agentwelcome' ); ?></li>
		<li><?php esc_html_e( 'Navigation, scripts and forms are removed, and the rest becomes Markdown.', 'agentwelcome' ); ?></li>
		<li><?php esc_html_e( 'The result is stored and refreshed when you save the page.', 'agentwelcome' ); ?></li>
	</ol>
	<p><a class="button" href="<?php echo esc_url( $vars['verify_url'] ); ?>"><?php esc_html_e( 'Verify a page', 'agentwelcome' ); ?></a></p>
</section>
