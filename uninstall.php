<?php
/**
 * Removes plugin data on uninstall.
 *
 * @package AgentReady
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'agentready_settings' );
delete_post_meta_by_key( '_agentready_exclude' );
delete_post_meta_by_key( '_agentready_description' );
