<?php
/**
 * Settings page of the AgentReady menu.
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

/** The main settings page, with its Content, Rendering and Environment tabs. */
final class SettingsPage implements Hookable {

	/** Page slug, also the slug of the top-level menu. */
	public const SLUG = 'agentready';

	/** Settings group of the form. */
	private const GROUP = 'agentready_group';

	/** Selectors offered as examples for the main content. */
	private const COMMON_CONTENT = array( 'main', '[role="main"]', 'article', '#content', '#main', '#primary', '.site-content', '.entry-content', '.post-content', '.page-content' );

	/** Selectors offered as examples for parts to remove. */
	private const COMMON_EXCLUDES = array( '.breadcrumb', '.breadcrumbs', '.cookie-notice', '.related-posts', '.post-navigation', '.author-box', '.share-buttons', '.newsletter-signup' );

	/**
	 * @param Options     $options     Saved settings.
	 * @param PostTypes   $post_types  Post types that are served.
	 * @param Sanitizer   $sanitizer   Cleans the submitted settings.
	 * @param Environment $environment Tells whether the plugin is running on this site.
	 * @param Coverage    $coverage    Counts the pages by state.
	 * @param Batch       $batch       Tells whether a refresh is running.
	 * @param TermList    $terms       Lists the terms that can be excluded.
	 * @param Urls        $urls        Builds the addresses.
	 * @param Notices     $notices     Notices to show.
	 */
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

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( AGENTREADY_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Registers the setting with its cleaning function.
	 *
	 * @return void
	 */
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

	/**
	 * Loads the style and script on this page.
	 *
	 * @param string $hook Admin page hook.
	 * @return void
	 */
	public function enqueue( string $hook ): void {
		if ( 'toplevel_page_' . self::SLUG !== $hook ) {
			return;
		}

		Assets::style();
		wp_enqueue_script( 'agentready-admin', AGENTREADY_URL . 'assets/js/admin.js', array(), Assets::version( 'assets/js/admin.js' ), true );
	}

	/**
	 * Adds a Settings link to the plugin's row on the Plugins screen.
	 *
	 * @param string[] $links Existing action links.
	 * @return string[] The links, with Settings first.
	 */
	public function action_links( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( $this->url() ) . '">' . esc_html__( 'Settings', 'agentready' ) . '</a>' );

		return $links;
	}

	/**
	 * Address of the page.
	 *
	 * @return string Admin address.
	 */
	public function url(): string {
		return admin_url( 'admin.php?page=' . self::SLUG );
	}

	/**
	 * Prints the page.
	 *
	 * @return void
	 */
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

	/**
	 * Prints the settings form inside the page.
	 *
	 * @return void
	 */
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
	 * Values for the status card.
	 *
	 * @return array<string, mixed> Values by name.
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
			'cron_disabled' => $this->environment->cron_disabled(),
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
	 * Post types for the settings form.
	 *
	 * @param bool $all Every available type with its exclusion state, or only the enabled ones.
	 * @return array<int, array{slug: string, label: string, published: string, excluded: bool}> One entry per type.
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

	/**
	 * Text that says how many pages of a type are published.
	 *
	 * @param string $type Post type.
	 * @return string Text such as "12 published".
	 */
	private function published_label( string $type ): string {
		$counts = wp_count_posts( $type );
		$total  = is_object( $counts ) && isset( $counts->publish ) ? (int) $counts->publish : 0;

		/* translators: %s: number of published posts. */
		return sprintf( _n( '%s published', '%s published', $total, 'agentready' ), number_format_i18n( $total ) );
	}
}
