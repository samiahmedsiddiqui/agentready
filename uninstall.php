<?php
/**
 * Removes plugin data on uninstall.
 *
 * @package AgentReady
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Removes the stored Markdown and saved credentials, and the settings and per-page choices when the user asked for it.
 */
function agentready_uninstall_site(): void {
	global $wpdb;

	$settings = get_option( 'agentready_settings' );
	$delete   = is_array( $settings ) && ! empty( $settings['delete_data'] );

	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}agentready_pages" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table.
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'agentready_lock_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Locks are stored as options.

	delete_option( 'agentready_db_version' );
	delete_option( 'agentready_batch' );
	wp_unschedule_hook( 'agentready_generate_post' );
	wp_unschedule_hook( 'agentready_regenerate_batch' );
	wp_unschedule_hook( 'agentready_prune' );

	if ( $delete ) {
		delete_option( 'agentready_settings' );
		delete_post_meta_by_key( '_agentready_exclude' );
		delete_post_meta_by_key( '_agentready_description' );
	} elseif ( is_array( $settings ) ) {
		// Saved HTTP Basic credentials are secrets, so they never outlive the plugin.
		$settings['http_auth_enabled']  = false;
		$settings['http_auth_user']     = '';
		$settings['http_auth_password'] = '';

		update_option( 'agentready_settings', $settings );
	}
}

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $agentready_site_id ) {
		switch_to_blog( (int) $agentready_site_id );
		agentready_uninstall_site();
		restore_current_blog();
	}
} else {
	agentready_uninstall_site();
}
