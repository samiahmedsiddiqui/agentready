<?php
/**
 * All pages tab: scan every stored copy against a fresh build.
 *
 * @package AgentWelcome
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template-local variables.

$scan    = $vars['status'];
$running = $scan['running'];
$ran     = $scan['started'] > 0;
?>
<section class="agentwelcome-card" style="margin-top:20px" data-agentwelcome-scan data-running="<?php echo $running ? '1' : '0'; ?>" data-path="/agentwelcome/v1/scan">
	<h2><?php esc_html_e( 'Check all pages', 'agentwelcome' ); ?></h2>
	<p class="agentwelcome-intro"><?php esc_html_e( 'Reads every page that has a stored copy, builds fresh Markdown, and lists the pages that differ. The scan runs in the background and changes nothing until you press Update outdated.', 'agentwelcome' ); ?></p>
	<?php if ( $vars['cron_disabled'] ) : ?>
		<p class="description"><strong><?php esc_html_e( 'WP-Cron is disabled on this site.', 'agentwelcome' ); ?></strong> <?php esc_html_e( 'While this page stays open it runs the scan itself, a few pages at a time. If you close it, the scan pauses until you come back or a real cron job calls wp-cron.php.', 'agentwelcome' ); ?></p>
	<?php endif; ?>
	<form method="post" action="<?php echo esc_url( $vars['action'] ); ?>" class="agentwelcome-actions">
		<input type="hidden" name="action" value="agentwelcome_scan" />
		<?php wp_nonce_field( $vars['nonce'] ); ?>
		<?php if ( $running ) : ?>
			<button type="submit" name="scan_action" value="stop" class="button"><?php esc_html_e( 'Stop scan', 'agentwelcome' ); ?></button>
		<?php else : ?>
			<button type="submit" name="scan_action" value="start" class="button button-primary" <?php disabled( $vars['inactive'] ); ?>><?php echo esc_html( $ran ? __( 'Scan again', 'agentwelcome' ) : __( 'Start scan', 'agentwelcome' ) ); ?></button>
		<?php endif; ?>
	</form>
	<?php if ( $ran ) : ?>
		<ul class="agentwelcome-status" data-agentwelcome-scan-counts>
			<li><span class="dashicons dashicons-search"></span><span data-agentwelcome-count="checked"><?php echo esc_html( sprintf( /* translators: %s: number of pages. */ _n( '%s page checked', '%s pages checked', $scan['checked'], 'agentwelcome' ), number_format_i18n( $scan['checked'] ) ) ); ?></span></li>
			<li><span class="dashicons dashicons-yes-alt agentwelcome-ok"></span><span data-agentwelcome-count="in_sync"><?php echo esc_html( sprintf( /* translators: %s: number of pages. */ _n( '%s page in sync', '%s pages in sync', $scan['in_sync'], 'agentwelcome' ), number_format_i18n( $scan['in_sync'] ) ) ); ?></span></li>
			<li><span class="dashicons dashicons-update"></span><span data-agentwelcome-count="differs"><?php echo esc_html( sprintf( /* translators: %s: number of pages. */ _n( '%s page out of date', '%s pages out of date', count( $scan['differs'] ), 'agentwelcome' ), number_format_i18n( count( $scan['differs'] ) ) ) ); ?></span></li>
			<?php if ( ! $running ) : ?>
				<li><span class="dashicons dashicons-clock"></span><span><?php echo esc_html( sprintf( /* translators: %s: number of pages. */ _n( '%s page not stored yet', '%s pages not stored yet', $scan['not_stored'], 'agentwelcome' ), number_format_i18n( $scan['not_stored'] ) ) ); ?></span></li>
			<?php endif; ?>
			<?php if ( $scan['fallback'] > 0 ) : ?>
				<li><span class="dashicons dashicons-warning agentwelcome-warn"></span><span><?php echo esc_html( sprintf( /* translators: %s: number of pages. */ _n( '%s page could only be read from its post content', '%s pages could only be read from their post content', $scan['fallback'], 'agentwelcome' ), number_format_i18n( $scan['fallback'] ) ) ); ?></span></li>
			<?php endif; ?>
		</ul>
		<p class="description">
			<?php
			if ( $running ) {
				echo esc_html( sprintf( /* translators: %s: date and time. */ __( 'Running since %s. This page updates by itself.', 'agentwelcome' ), $vars['started'] ) );
			} else {
				echo esc_html( sprintf( /* translators: 1: start time, 2: end time. */ __( 'Started %1$s, finished %2$s.', 'agentwelcome' ), $vars['started'], $vars['finished'] ) );
			}
			?>
		</p>
	<?php endif; ?>
	<?php if ( $ran && ! $running ) : ?>
		<form method="post" action="<?php echo esc_url( $vars['action'] ); ?>" class="agentwelcome-actions">
			<input type="hidden" name="action" value="agentwelcome_scan" />
			<?php wp_nonce_field( $vars['nonce'] ); ?>
			<?php if ( ! empty( $vars['rows'] ) ) : ?>
				<button type="submit" name="scan_action" value="update_outdated" class="button" <?php disabled( $vars['inactive'] ); ?>><?php echo esc_html( sprintf( /* translators: %s: number of pages. */ __( 'Update outdated (%s)', 'agentwelcome' ), number_format_i18n( count( $vars['rows'] ) ) ) ); ?></button>
			<?php endif; ?>
			<?php if ( $scan['not_stored'] > 0 ) : ?>
				<button type="submit" name="scan_action" value="generate_missing" class="button" <?php disabled( $vars['inactive'] ); ?>><?php esc_html_e( 'Generate missing', 'agentwelcome' ); ?></button>
			<?php endif; ?>
		</form>
		<?php if ( $vars['cron_disabled'] ) : ?>
			<p class="description"><?php esc_html_e( 'Update outdated and Generate missing queue background work. With WP-Cron disabled it only runs when a real cron job calls wp-cron.php. To refresh a page right away, use Update stored copy on its Compare page, or Regenerate now in its editor box.', 'agentwelcome' ); ?></p>
		<?php endif; ?>
	<?php endif; ?>
</section>
<?php if ( ! $running && ! empty( $vars['rows'] ) ) : ?>
	<section class="agentwelcome-card">
		<h2><?php esc_html_e( 'Pages that differ', 'agentwelcome' ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr><th><?php esc_html_e( 'Page', 'agentwelcome' ); ?></th><th><?php esc_html_e( 'Lines that differ', 'agentwelcome' ); ?></th><th></th></tr>
			</thead>
			<tbody>
				<?php foreach ( $vars['rows'] as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['title'] ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $row['changed'] ) ); ?></td>
						<td><a href="<?php echo esc_url( $row['url'] ); ?>"><?php esc_html_e( 'Compare', 'agentwelcome' ); ?></a></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description"><?php esc_html_e( 'Pages with changing content, such as dates or rotating banners, can show up here on every scan. Compare shows exactly what differs.', 'agentwelcome' ); ?></p>
	</section>
<?php elseif ( $ran && ! $running ) : ?>
	<section class="agentwelcome-card"><p><?php esc_html_e( 'Every checked page matches its stored copy.', 'agentwelcome' ); ?></p></section>
<?php endif; ?>
