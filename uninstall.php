<?php
/**
 * Removes plugin data on uninstall.
 *
 * @package AgentWelcome
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Removes the llms.txt management capability from every role and from users who were given it directly.
 */
function agentwelcome_remove_files_capability(): void {
	foreach ( wp_roles()->role_objects as $role ) {
		$role->remove_cap( 'agentwelcome_manage_files' );
	}

	foreach ( get_users(
		array(
			'capability' => 'agentwelcome_manage_files',
			'fields'     => 'ID',
			'number'     => -1,
		)
	) as $user_id ) {
		$user = get_user_by( 'id', (int) $user_id );

		if ( $user instanceof WP_User ) {
			$user->remove_cap( 'agentwelcome_manage_files' );
		}
	}
}

/**
 * Removes the stored Markdown and saved credentials, and the settings, the llms.txt text and per-page choices when the user asked for it.
 */
function agentwelcome_uninstall_site(): void {
	global $wpdb;

	$settings = get_option( 'agentwelcome_settings' );
	$delete   = is_array( $settings ) && ! empty( $settings['delete_data'] );

	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}agentwelcome_pages" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table.
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'agentwelcome_lock_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Locks are stored as options.

	delete_option( 'agentwelcome_db_version' );
	delete_option( 'agentwelcome_site_fingerprint' );
	delete_option( 'agentwelcome_batch' );
	delete_option( 'agentwelcome_scan' );
	delete_option( 'agentwelcome_changes' );
	wp_unschedule_hook( 'agentwelcome_generate_post' );
	wp_unschedule_hook( 'agentwelcome_regenerate_batch' );
	wp_unschedule_hook( 'agentwelcome_prune' );
	wp_unschedule_hook( 'agentwelcome_scan_batch' );

	if ( $delete ) {
		delete_option( 'agentwelcome_settings' );
		delete_option( 'agentwelcome_llms' );
		delete_post_meta_by_key( '_agentwelcome_exclude' );
		delete_post_meta_by_key( '_agentwelcome_description' );
		delete_option( 'agentwelcome_files_capability_granted' );
		agentwelcome_remove_files_capability();
	} elseif ( is_array( $settings ) ) {
		// Saved HTTP Basic credentials are secrets, so they never outlive the plugin.
		$settings['http_auth_enabled']  = false;
		$settings['http_auth_user']     = '';
		$settings['http_auth_password'] = '';

		update_option( 'agentwelcome_settings', $settings );
	}
}

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $agentwelcome_site_id ) {
		switch_to_blog( (int) $agentwelcome_site_id );
		agentwelcome_uninstall_site();
		restore_current_blog();
	}
} else {
	agentwelcome_uninstall_site();
}
