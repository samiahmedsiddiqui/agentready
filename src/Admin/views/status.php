<?php
/**
 * Status column: system info, served content, cache counts and cache buttons.
 *
 * @package AgentReady
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template-local variables.

$cache = $vars['cache'];
?>
<section class="agentready-card">
	<h2><?php esc_html_e( 'Status', 'agentready' ); ?></h2>
	<ul class="agentready-status">
		<li>
			<?php if ( null === $vars['inactive'] ) : ?>
				<span class="dashicons dashicons-yes-alt agentready-ok"></span>
				<span><?php esc_html_e( 'AgentReady is active.', 'agentready' ); ?></span>
			<?php else : ?>
				<span class="dashicons dashicons-warning agentready-warn"></span>
				<span><?php esc_html_e( 'AgentReady is inactive here. Nothing is served.', 'agentready' ); ?></span>
			<?php endif; ?>
		</li>
		<li>
			<?php if ( $vars['pretty'] ) : ?>
				<span class="dashicons dashicons-yes-alt agentready-ok"></span>
				<span><?php esc_html_e( 'Pretty permalinks are on.', 'agentready' ); ?></span>
			<?php else : ?>
				<span class="dashicons dashicons-warning agentready-warn"></span>
				<span><?php esc_html_e( 'Plain permalinks are on. Nothing is served.', 'agentready' ); ?></span>
			<?php endif; ?>
		</li>
		<?php if ( $vars['pretty'] ) : ?>
			<li>
				<span class="dashicons dashicons-media-text"></span>
				<span><a href="<?php echo esc_url( home_url( '/llms.txt' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View llms.txt', 'agentready' ); ?></a></span>
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

<section class="agentready-card">
	<h2><?php esc_html_e( 'Stored Markdown', 'agentready' ); ?></h2>
	<ul class="agentready-status">
		<li><span class="dashicons dashicons-yes-alt agentready-ok"></span><span><?php echo esc_html( sprintf( /* translators: %s: number of pages. */ _n( '%s page up to date', '%s pages up to date', $cache['fresh'], 'agentready' ), number_format_i18n( $cache['fresh'] ) ) ); ?></span></li>
		<li><span class="dashicons dashicons-update"></span><span><?php echo esc_html( sprintf( /* translators: %s: number of pages. */ _n( '%s page out of date', '%s pages out of date', $cache['stale'], 'agentready' ), number_format_i18n( $cache['stale'] ) ) ); ?></span></li>
		<li><span class="dashicons dashicons-clock"></span><span><?php echo esc_html( sprintf( /* translators: %s: number of pages. */ _n( '%s page not generated yet', '%s pages not generated yet', $cache['missing'], 'agentready' ), number_format_i18n( $cache['missing'] ) . ( $cache['capped'] ? '+' : '' ) ) ); ?></span></li>
		<?php if ( $vars['running'] ) : ?>
			<li><span class="dashicons dashicons-update-alt"></span><span><?php esc_html_e( 'A background refresh is running.', 'agentready' ); ?></span></li>
		<?php endif; ?>
	</ul>
	<?php if ( $vars['cron_disabled'] ) : ?>
		<p class="description"><?php esc_html_e( 'WP-Cron is disabled on this site, so background refreshes only run if a real cron job calls wp-cron.php. Pages are still generated when they are first requested.', 'agentready' ); ?></p>
	<?php endif; ?>
	<p class="description"><?php esc_html_e( 'Servers and CDNs may keep .md and llms.txt for a few minutes (AgentReady allows 5). If a change does not show yet, add a query string to the URL, such as ?t=123, to skip that cache.', 'agentready' ); ?></p>
	<p class="agentready-actions">
		<button type="submit" form="agentready-cache-form" name="cache_action" value="regenerate_all" class="button" data-agentready-confirm="<?php esc_attr_e( 'Mark every page as out of date and rebuild them in the background? Current copies keep being served meanwhile.', 'agentready' ); ?>" <?php disabled( null !== $vars['inactive'] ); ?>><?php esc_html_e( 'Regenerate all', 'agentready' ); ?></button>
		<button type="submit" form="agentready-cache-form" name="cache_action" value="clear" class="button" data-agentready-confirm="<?php esc_attr_e( 'Delete every stored copy? Pages are rebuilt in the background and when first requested.', 'agentready' ); ?>"><?php esc_html_e( 'Clear cache', 'agentready' ); ?></button>
	</p>
</section>

<section class="agentready-card">
	<h2><?php esc_html_e( 'System', 'agentready' ); ?></h2>
	<dl class="agentready-system">
		<?php foreach ( $vars['system'] as $label => $value ) : ?>
			<dt><?php echo esc_html( $label ); ?></dt>
			<dd><?php echo esc_html( $value ); ?></dd>
		<?php endforeach; ?>
	</dl>
</section>

<section class="agentready-card">
	<h2><?php esc_html_e( 'How it works', 'agentready' ); ?></h2>
	<ol class="agentready-steps">
		<li><?php echo esc_html( sprintf( /* translators: %s: homepage Markdown path. */ __( 'Add .md to a page URL, for example /about.md. The homepage is /%s.', 'agentready' ), $vars['home_file'] ) ); ?></li>
		<li><?php esc_html_e( 'The page is read like a visitor sees it, so theme, ACF and builder output is included.', 'agentready' ); ?></li>
		<li><?php esc_html_e( 'Navigation, scripts and forms are removed, and the rest becomes Markdown.', 'agentready' ); ?></li>
		<li><?php esc_html_e( 'The result is stored and refreshed when you save the page.', 'agentready' ); ?></li>
	</ol>
	<p><a class="button" href="<?php echo esc_url( $vars['preview_url'] ); ?>"><?php esc_html_e( 'Try the preview', 'agentready' ); ?></a></p>
</section>
