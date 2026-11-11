<?php
/**
 * Plugin bootstrap and component wiring.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome;

defined( 'ABSPATH' ) || exit;

use AgentWelcome\Admin\CacheActions;
use AgentWelcome\Admin\Capabilities;
use AgentWelcome\Admin\LlmsPage;
use AgentWelcome\Admin\Menu;
use AgentWelcome\Admin\MetaBox;
use AgentWelcome\Admin\Notices;
use AgentWelcome\Admin\SettingsPage;
use AgentWelcome\Admin\TermList;
use AgentWelcome\Admin\VerifyPage;
use AgentWelcome\Cache\Batch;
use AgentWelcome\Cache\Candidates;
use AgentWelcome\Cache\Changes;
use AgentWelcome\Cache\Coverage;
use AgentWelcome\Cache\Invalidator;
use AgentWelcome\Cache\Lock;
use AgentWelcome\Cache\PageCache;
use AgentWelcome\Cache\PageStatus;
use AgentWelcome\Cache\Schema;
use AgentWelcome\Cache\WpdbStore;
use AgentWelcome\Cli\Command;
use AgentWelcome\Content\Description;
use AgentWelcome\Content\Eligibility;
use AgentWelcome\Content\Exclusions;
use AgentWelcome\Content\PostTypes;
use AgentWelcome\Contracts\Hookable;
use AgentWelcome\Environment\Environment;
use AgentWelcome\Http\CronAuth;
use AgentWelcome\Http\DiscoveryTags;
use AgentWelcome\Http\MarkdownResponse;
use AgentWelcome\Http\Router;
use AgentWelcome\Http\Urls;
use AgentWelcome\Index\LlmsFullTxt;
use AgentWelcome\Index\LlmsTxt;
use AgentWelcome\Index\OutputCache;
use AgentWelcome\Integrations\Acf;
use AgentWelcome\Integrations\CustomPermalinks;
use AgentWelcome\Integrations\Elementor;
use AgentWelcome\Integrations\Seo;
use AgentWelcome\Rendering\Converter;
use AgentWelcome\Rendering\Extractor;
use AgentWelcome\Rendering\FrontMatter;
use AgentWelcome\Rendering\Generator;
use AgentWelcome\Rendering\Renderer;
use AgentWelcome\Rendering\Selector;
use AgentWelcome\Rest\PagesController;
use AgentWelcome\Rest\ScanController;
use AgentWelcome\Robots\RobotsTxt;
use AgentWelcome\Settings\LlmsOptions;
use AgentWelcome\Settings\Options;
use AgentWelcome\Settings\Sanitizer;
use AgentWelcome\Verify\Scan;
use AgentWelcome\Verify\Verifier;

/** Starts the plugin: builds the components and lets each one attach its hooks. */
final class Plugin {

	/**
	 * The one instance of the plugin.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Components that attach hooks.
	 *
	 * @var Hookable[]
	 */
	private array $components = array();

	/**
	 * Creates the plugin once.
	 *
	 * @return void
	 */
	public static function boot(): void {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}
	}

	/**
	 * Builds the components and registers them. Without the PHP DOM extension only a notice is added.
	 *
	 * @return void
	 */
	private function init(): void {
		if ( ! class_exists( 'DOMDocument' ) ) {
			add_action( 'admin_notices', array( $this, 'missing_dom_notice' ) );
			return;
		}

		$options     = new Options();
		$environment = new Environment( $options );
		$post_types  = new PostTypes( $options );
		$urls        = new Urls( $options );
		$eligibility = new Eligibility( $post_types, new Exclusions( $options ), $urls );
		$description = new Description();
		$store       = new WpdbStore();
		$candidates  = new Candidates();
		$renderer    = new Renderer( $options, $urls );
		$generator   = new Generator(
			$options,
			$urls,
			$renderer,
			new Extractor(
				new Selector(),
				(array) apply_filters(
					'agentwelcome_strip_selectors',
					array_merge( Extractor::DEFAULT_STRIP, ( new Selector() )->parse_list( (string) $options->get( 'strip_selectors', '' ) )['valid'] )
				)
			),
			new Converter(),
			new FrontMatter(),
			$description
		);
		$lock        = new Lock();
		$pages       = new PageCache( $store, $generator, $lock, $eligibility, $environment );
		$batch       = new Batch( $store, $pages, $post_types, $eligibility, $environment, $candidates );
		$invalidator = new Invalidator( $store, $pages, $batch, $post_types );
		$changes     = new Changes( $batch, $environment );
		$status      = new PageStatus( $store, $eligibility, $environment );
		$coverage    = new Coverage( $store, $candidates, $post_types, $eligibility );
		$verifier    = new Verifier( $store, $generator, $renderer, $eligibility );
		$scan        = new Scan( $store, $verifier, $environment, $coverage, $lock );

		$llms_options = new LlmsOptions();
		$llms         = new LlmsTxt( $post_types, $eligibility, $urls, $description, $llms_options );
		$llms_full    = new LlmsFullTxt( $store, $eligibility, $urls );

		$this->components = array(
			new Schema(),
			$pages,
			$batch,
			$invalidator,
			$changes,
			new Capabilities(),
			new OutputCache(),
			new Router( $urls, $eligibility, $pages, $environment, $llms, $llms_full, $llms_options, new MarkdownResponse() ),
			new DiscoveryTags( $eligibility, $urls, $environment ),
			new CronAuth( $options ),
			new PagesController( $store, $pages, $status, $eligibility, $environment ),
			$scan,
			new ScanController( $scan, $environment ),
			new RobotsTxt( $llms_options, $environment ),
			new Seo( $invalidator ),
			new Elementor( $invalidator, $batch, $environment ),
			new Acf( $batch, $environment ),
			new CustomPermalinks( $urls ),
		);

		if ( is_admin() ) {
			$notices  = new Notices( $environment, $changes );
			$settings = new SettingsPage(
				$options,
				$post_types,
				new Sanitizer( $post_types, $options ),
				$environment,
				$coverage,
				$batch,
				new TermList(),
				$urls,
				$notices
			);
			$verify   = new VerifyPage( $urls, $verifier, $notices, $scan, $batch, $pages, $environment );

			$llms_page = new LlmsPage( $llms_options, $llms, $coverage, $environment, $notices );

			$this->components[] = $settings;
			$this->components[] = $llms_page;
			$this->components[] = $verify;
			$this->components[] = new Menu( $settings, $llms_page, $verify );
			$this->components[] = new MetaBox( $post_types, $options, $status );
			$this->components[] = new CacheActions( $store, $pages, $batch, $post_types, $eligibility, $environment, $changes );
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$this->components[] = new Command( $store, $pages, $post_types, $eligibility, $environment, $candidates, $coverage );
		}

		foreach ( $this->components as $component ) {
			$component->register();
		}
	}

	/**
	 * Tells administrators that the plugin is inactive because the PHP DOM extension is missing.
	 *
	 * @return void
	 */
	public function missing_dom_notice(): void {
		if ( current_user_can( 'manage_options' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'AgentWelcome needs the PHP DOM extension, which is not available on this server. The plugin is inactive.', 'agentwelcome' ) . '</p></div>';
		}
	}
}
