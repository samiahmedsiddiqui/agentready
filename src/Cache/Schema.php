<?php
/**
 * Creates and upgrades the pages table.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Cache;

use AgentReady\Contracts\Hookable;

defined( 'ABSPATH' ) || exit;

final class Schema implements Hookable {

	public const DB_VERSION = '1';
	public const OPTION     = 'agentready_db_version';

	public static function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'agentready_pages';
	}

	public function register(): void {
		add_filter( 'wpmu_drop_tables', array( $this, 'drop_with_site' ) );
		$this->maybe_upgrade();
	}

	/**
	 * @param string[] $tables Tables dropped with a deleted site.
	 * @return string[]
	 */
	public function drop_with_site( array $tables ): array {
		$tables[] = self::table();

		return $tables;
	}

	public function maybe_upgrade(): void {
		if ( self::DB_VERSION === get_option( self::OPTION ) ) {
			return;
		}

		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$collate = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$table} (
			post_id bigint(20) unsigned NOT NULL,
			markdown longtext NOT NULL,
			content_hash char(40) NOT NULL default '',
			source varchar(20) NOT NULL default 'loopback',
			generator_version varchar(32) NOT NULL default '',
			generated_at datetime NOT NULL,
			state varchar(10) NOT NULL default 'fresh',
			PRIMARY KEY  (post_id),
			KEY state (state)
			) {$collate};"
		);

		update_option( self::OPTION, self::DB_VERSION, true );
	}
}
