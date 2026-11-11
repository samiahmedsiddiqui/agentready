<?php
/**
 * Creates and upgrades the pages table.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Cache;

use AgentWelcome\Contracts\Hookable;

defined( 'ABSPATH' ) || exit;

/** Creates and upgrades the table that holds the stored Markdown. */
final class Schema implements Hookable {

	/** Version of the table layout. Raise it when the columns change. */
	public const DB_VERSION = '1';
	/** Option that records the installed table version. */
	public const OPTION = 'agentwelcome_db_version';

	/**
	 * Name of the table, with the site prefix.
	 *
	 * @return string Table name.
	 */
	public static function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'agentwelcome_pages';
	}

	/**
	 * Adds the multisite table cleanup and creates the table when it is missing.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'wpmu_drop_tables', array( $this, 'drop_with_site' ) );
		$this->maybe_upgrade();
	}

	/**
	 * Adds the table to the ones WordPress drops when a multisite site is deleted.
	 *
	 * @param string[] $tables Tables dropped with the site.
	 * @return string[] The tables, with this one added.
	 */
	public function drop_with_site( array $tables ): array {
		$tables[] = self::table();

		return $tables;
	}

	/**
	 * Creates or updates the table when the installed version is older than DB_VERSION.
	 *
	 * @return void
	 */
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
