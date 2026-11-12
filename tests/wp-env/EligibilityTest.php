<?php
/**
 * Tests for which pages are served, with real posts, real permalinks and real post meta.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Tests\WpEnv;

use AgentWelcome\Content\Eligibility;
use AgentWelcome\Content\Exclusions;
use AgentWelcome\Content\PostMeta;
use AgentWelcome\Content\PostTypes;
use AgentWelcome\Http\Urls;
use AgentWelcome\Settings\Options;
use WP_UnitTestCase;

final class EligibilityTest extends WP_UnitTestCase {

	private Eligibility $eligibility;

	public function set_up(): void {
		parent::set_up();

		$this->set_permalink_structure( '/%postname%/' );

		$options           = new Options();
		$this->eligibility = new Eligibility( new PostTypes( $options ), new Exclusions( $options ), new Urls( $options ) );
	}

	/**
	 * @param array<string, mixed> $args Post arguments.
	 */
	private function page( array $args = array() ): \WP_Post {
		$id = self::factory()->post->create( array_merge( array( 'post_type' => 'page', 'post_status' => 'publish' ), $args ) );

		return get_post( $id );
	}

	public function test_a_published_page_is_served(): void {
		$this->assertNull( $this->eligibility->reason( $this->page() ) );
	}

	public function test_a_draft_is_not_served(): void {
		$this->assertSame( 'not_published', $this->eligibility->reason( $this->page( array( 'post_status' => 'draft' ) ) ) );
	}

	public function test_a_password_protected_page_is_not_served(): void {
		$this->assertSame( 'password_protected', $this->eligibility->reason( $this->page( array( 'post_password' => 'secret' ) ) ) );
	}

	public function test_a_page_hidden_in_the_editor_is_not_served(): void {
		$page = $this->page();
		update_post_meta( $page->ID, PostMeta::EXCLUDE, 1 );

		$this->assertSame( 'excluded_page', $this->eligibility->reason( $page ) );
	}

	public function test_a_page_is_not_served_with_plain_permalinks(): void {
		$page = $this->page();
		$this->set_permalink_structure( '' );

		$this->assertSame( 'plain_permalink', $this->eligibility->reason( $page ) );
	}

	public function test_a_page_that_matches_an_id_in_the_exclusion_list_is_not_served(): void {
		$page = $this->page();
		update_option( Options::KEY, array( 'excluded_posts' => (string) $page->ID ) );

		$this->assertSame( 'excluded_pattern', $this->eligibility->reason( $page ) );
	}

	public function test_a_page_marked_noindex_by_an_active_seo_plugin_is_not_served(): void {
		$page = $this->page();
		update_post_meta( $page->ID, '_yoast_wpseo_meta-robots-noindex', '1' );
		add_filter( 'agentwelcome_noindex_sources', static fn( array $sources ): array => array_merge( $sources, array( '_yoast_wpseo_meta-robots-noindex' => true ) ) );

		$this->assertSame( 'noindex', $this->eligibility->reason( $page ) );
	}

	public function test_a_noindex_page_is_served_when_the_setting_is_off(): void {
		$page = $this->page();
		update_post_meta( $page->ID, '_yoast_wpseo_meta-robots-noindex', '1' );
		add_filter( 'agentwelcome_noindex_sources', static fn( array $sources ): array => array_merge( $sources, array( '_yoast_wpseo_meta-robots-noindex' => true ) ) );
		update_option( Options::KEY, array( 'exclude_noindex' => false ) );

		$this->assertNull( $this->eligibility->reason( $page ) );
	}

	public function test_a_filter_can_exclude_a_page(): void {
		$page = $this->page();
		add_filter( 'agentwelcome_is_eligible', static fn(): bool => false );

		$this->assertSame( 'filtered', $this->eligibility->reason( $page ) );
	}
}
