<?php
/**
 * Settings screen under Settings > AgentReady.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Admin;

use AgentReady\Cache\Batch;
use AgentReady\Cache\Coverage;
use AgentReady\Content\PostTypes;
use AgentReady\Contracts\Hookable;
use AgentReady\Environment\Environment;
use AgentReady\Http\Urls;
use AgentReady\Settings\Options;
use AgentReady\Settings\Sanitizer;

defined( 'ABSPATH' ) || exit;

final class SettingsPage implements Hookable {

	public const SLUG = 'agentready';

	private const GROUP = 'agentready_group';

	private const COMMON_CONTENT = array( 'main', '[role="main"]', 'article', '#content', '#main', '#primary', '.site-content', '.entry-content', '.post-content', '.page-content' );

	private const COMMON_EXCLUDES = array( '.breadcrumb', '.breadcrumbs', '.cookie-notice', '.related-posts', '.post-navigation', '.author-box', '.share-buttons', '.newsletter-signup' );

	public function __construct(
		private Options $options,
		private PostTypes $post_types,
		private Sanitizer $sanitizer,
		private Environment $environment,
		private Coverage $coverage,
		private Batch $batch,
		private TermList $terms,
		private Urls $urls,
		private Notices $notices
	) {}

	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( AGENTREADY_FILE ), array( $this, 'action_links' ) );
	}

	public function register_settings(): void {
		register_setting(
			self::GROUP,
			Options::KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this->sanitizer, 'sanitize' ),
				'default'           => Options::defaults(),
			)
		);
	}

	public function enqueue( string $hook ): void {
		if ( 'toplevel_page_' . self::SLUG !== $hook ) {
			return;
		}

		Assets::style();
		wp_enqueue_script( 'agentready-admin', AGENTREADY_URL . 'assets/js/admin.js', array(), Assets::version( 'assets/js/admin.js' ), true );
	}

	/**
	 * @param string[] $links Existing action links.
	 * @return string[]
	 */
	public function action_links( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( $this->url() ) . '">' . esc_html__( 'Settings', 'agentready' ) . '</a>' );

		return $links;
	}

	public function url(): string {
		return admin_url( 'admin.php?page=' . self::SLUG );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		View::render(
			'page',
			array(
				'tabs'          => array(
					'content'     => __( 'Content', 'agentready' ),
					'rendering'   => __( 'Rendering', 'agentready' ),
					'environment' => __( 'Environment', 'agentready' ),
				),
				'settings_url'  => $this->url(),
				'notices'       => $this->notices->data(),
				'after_notices' => 'settings_errors',
				'content'       => array( $this, 'render_form' ),
			)
		);
	}

	public function render_form(): void {
		View::render(
			'settings-form',
			array(
				'group'           => self::GROUP,
				'name'            => Options::KEY,
				'options'         => $this->options,
				'environment'     => $this->environment,
				'types'           => $this->types( true ),
				'term_groups'     => $this->terms->groups(),
				'term_limit'      => TermList::LIMIT,
				'home_urls'       => array(
					Urls::HOME_SHORT => home_url( '/' . Urls::HOME_SHORT ),
					Urls::HOME_LONG  => home_url( '/' . Urls::HOME_LONG ),
				),
				'home_collision'  => $this->urls->index_collision(),
				'excluded_terms'  => array_map( 'absint', (array) $this->options->get( 'excluded_terms', array() ) ),
				'common_content'  => self::COMMON_CONTENT,
				'common_excludes' => self::COMMON_EXCLUDES,
				'status'          => $this->status(),
			)
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function status(): array {
		global $wp_version;

		$reason = $this->environment->reason();

		return array(
			'inactive'      => null === $reason ? null : $this->environment->describe( $reason ),
			'pretty'        => Urls::has_pretty_permalinks(),
			'types'         => $this->types( false ),
			'cache'         => $this->coverage->summary(),
			'running'       => $this->batch->is_running(),
			'cron_disabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			'verify_url'    => VerifyPage::url(),
			'home_file'     => $this->urls->home_file(),
			'system'        => array(
				__( 'WordPress', 'agentready' )   => (string) $wp_version,
				__( 'PHP', 'agentready' )         => PHP_VERSION,
				__( 'AgentReady', 'agentready' )  => AGENTREADY_VERSION,
				__( 'Environment', 'agentready' ) => $this->environment->type(),
			),
		);
	}

	/**
	 * @param bool $all Every available type with its exclusion state, or only the enabled ones.
	 * @return array<int, array{slug: string, label: string, published: string, excluded: bool}>
	 */
	private function types( bool $all ): array {
		$types = array();

		foreach ( $all ? $this->post_types->available() : $this->post_types->enabled() as $type ) {
			$object  = get_post_type_object( $type );
			$types[] = array(
				'slug'      => $type,
				'label'     => $object ? $object->labels->name : $type,
				'published' => $this->published_label( $type ),
				'excluded'  => in_array( $type, $this->post_types->excluded(), true ),
			);
		}

		return $types;
	}

	private function published_label( string $type ): string {
		$counts = wp_count_posts( $type );
		$total  = is_object( $counts ) && isset( $counts->publish ) ? (int) $counts->publish : 0;

		/* translators: %s: number of published posts. */
		return sprintf( _n( '%s published', '%s published', $total, 'agentready' ), number_format_i18n( $total ) );
	}
}
