<?php
/**
 * Tests for the table that holds the stored Markdown, on a real database.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Tests\WpEnv;

use AgentWelcome\Cache\Schema;
use WP_UnitTestCase;

final class SchemaTest extends WP_UnitTestCase {

	public function test_the_table_has_the_expected_columns(): void {
		global $wpdb;

		$columns = $wpdb->get_col( 'SHOW COLUMNS FROM ' . Schema::table() );

		$this->assertSame( array( 'post_id', 'markdown', 'content_hash', 'source', 'generator_version', 'generated_at', 'state' ), $columns );
	}

	public function test_the_primary_key_is_the_page_id(): void {
		global $wpdb;

		$keys = $wpdb->get_results( 'SHOW INDEX FROM ' . Schema::table() . " WHERE Key_name = 'PRIMARY'", ARRAY_A );

		$this->assertCount( 1, $keys );
		$this->assertSame( 'post_id', $keys[0]['Column_name'] );
	}

	public function test_the_installed_version_is_recorded(): void {
		$this->assertSame( Schema::DB_VERSION, get_option( Schema::OPTION ) );
	}

	public function test_running_the_upgrade_again_changes_nothing(): void {
		global $wpdb;

		( new Schema() )->maybe_upgrade();

		$this->assertSame( Schema::table(), $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', Schema::table() ) ) );
	}

	public function test_the_table_is_dropped_with_a_deleted_site(): void {
		$tables = ( new Schema() )->drop_with_site( array( 'wp_2_posts' ) );

		$this->assertContains( Schema::table(), $tables );
	}
}
