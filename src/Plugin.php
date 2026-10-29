<?php
/**
 * Plugin bootstrap and component wiring.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady;

defined( 'ABSPATH' ) || exit;

use AgentReady\Admin\CacheActions;
use AgentReady\Admin\MetaBox;
use AgentReady\Admin\PreviewTool;
use AgentReady\Admin\SettingsPage;
use AgentReady\Admin\TermList;
use AgentReady\Cache\Batch;
use AgentReady\Cache\Candidates;
use AgentReady\Cache\Coverage;
use AgentReady\Cache\Invalidator;
use AgentReady\Cache\Lock;
use AgentReady\Cache\PageCache;
use AgentReady\Cache\PageStatus;
use AgentReady\Cache\Schema;
use AgentReady\Cache\WpdbStore;
use AgentReady\Cli\Command;
use AgentReady\Content\Description;
use AgentReady\Content\Eligibility;
use AgentReady\Content\Exclusions;
use AgentReady\Content\PostTypes;
use AgentReady\Contracts\Hookable;
use AgentReady\Environment\Environment;
use AgentReady\Http\DiscoveryTags;
use AgentReady\Http\MarkdownResponse;
use AgentReady\Http\Router;
use AgentReady\Http\Urls;
use AgentReady\Index\LlmsTxt;
use AgentReady\Integrations\Acf;
use AgentReady\Integrations\CustomPermalinks;
use AgentReady\Integrations\Elementor;
use AgentReady\Integrations\Seo;
use AgentReady\Rendering\Converter;
use AgentReady\Rendering\Extractor;
use AgentReady\Rendering\FrontMatter;
use AgentReady\Rendering\Generator;
use AgentReady\Rendering\Renderer;
use AgentReady\Rendering\Selector;
use AgentReady\Rendering\Summary;
use AgentReady\Rest\PagesController;
use AgentReady\Robots\RobotsTxt;
use AgentReady\Settings\Options;
use AgentReady\Settings\Sanitizer;

final class Plugin {

	private static ?self $instance = null;

	/** @var Hookable[] */
	private array $components = array();

	public static function boot(): void {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}
	}

	private function init(): void {
		if ( ! class_exists( 'DOMDocument' ) ) {
			add_action( 'admin_notices', array( $this, 'missing_dom_notice' ) );
			return;
		}

		$options     = new Options();
		$environment = new Environment( $options );
		$post_types  = new PostTypes( $options );
		$urls        = new Urls();
		$eligibility = new Eligibility( $post_types, new Exclusions( $options ), $urls );
		$description = new Description();
		$store       = new WpdbStore();
		$candidates  = new Candidates();
		$generator   = new Generator(
			$options,
			$urls,
			new Renderer( $options, $urls ),
			new Extractor(
				new Selector(),
				(array) apply_filters(
					'agentready_strip_selectors',
					array_merge( Extractor::DEFAULT_STRIP, ( new Selector() )->parse_list( (string) $options->get( 'strip_selectors', '' ) )['valid'] )
				)
			),
			new Converter(),
			new FrontMatter(),
			$description,
			new Summary()
		);
		$pages       = new PageCache( $store, $generator, new Lock(), $eligibility, $environment );
		$batch       = new Batch( $store, $pages, $post_types, $eligibility, $environment, $candidates );
		$status      = new PageStatus( $store, $eligibility, $environment );
		$coverage    = new Coverage( $store, $candidates, $post_types, $eligibility );

		$this->components = array(
			new Schema(),
			$pages,
			$batch,
			new Invalidator( $store, $pages, $batch, $post_types ),
			new Router( $urls, $eligibility, $pages, $environment, new LlmsTxt( $post_types, $eligibility, $urls, $description ), new MarkdownResponse() ),
			new DiscoveryTags( $eligibility, $urls, $environment ),
			new PagesController( $store, $pages, $status, $eligibility, $environment ),
			new RobotsTxt( $options ),
			new Seo(),
			new Elementor(),
			new Acf(),
			new CustomPermalinks( $urls ),
		);

		if ( is_admin() ) {
			$this->components[] = new SettingsPage(
				$options,
				$post_types,
				new Sanitizer( $post_types, $options ),
				new PreviewTool( $urls, $eligibility, $generator ),
				$environment,
				$coverage,
				$batch,
				new TermList()
			);
			$this->components[] = new MetaBox( $post_types, $options, $status );
			$this->components[] = new CacheActions( $store, $pages, $batch, $post_types, $eligibility, $environment );
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$this->components[] = new Command( $store, $pages, $post_types, $eligibility, $environment, $candidates, $coverage );
		}

		foreach ( $this->components as $component ) {
			$component->register();
		}
	}

	public function missing_dom_notice(): void {
		if ( current_user_can( 'manage_options' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'AgentReady needs the PHP DOM extension, which is not available on this server. The plugin is inactive.', 'agentready' ) . '</p></div>';
		}
	}
}
