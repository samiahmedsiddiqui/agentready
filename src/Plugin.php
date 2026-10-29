<?php
/**
 * Plugin bootstrap and component wiring.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady;

use AgentReady\Admin\MetaBox;
use AgentReady\Admin\PreviewTool;
use AgentReady\Admin\SettingsPage;
use AgentReady\Cache\Invalidator;
use AgentReady\Cache\Store;
use AgentReady\Cli\Command;
use AgentReady\Content\Eligibility;
use AgentReady\Content\Exclusions;
use AgentReady\Content\PostTypes;
use AgentReady\Contracts\Hookable;
use AgentReady\Http\DiscoveryTags;
use AgentReady\Http\Router;
use AgentReady\Integrations\Acf;
use AgentReady\Integrations\Elementor;
use AgentReady\Integrations\Seo;
use AgentReady\Rendering\Converter;
use AgentReady\Rendering\Extractor;
use AgentReady\Rendering\FrontMatter;
use AgentReady\Rendering\Generator;
use AgentReady\Rendering\Renderer;
use AgentReady\Robots\RobotsTxt;
use AgentReady\Settings\Options;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private static ?self $instance = null;

	/** @var Hookable[] */
	private array $components = array();

	public static function boot(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}

		return self::$instance;
	}

	private function init(): void {
		load_plugin_textdomain( 'agentready', false, dirname( plugin_basename( AGENTREADY_FILE ) ) . '/languages' );

		$options     = new Options();
		$post_types  = new PostTypes( $options );
		$eligibility = new Eligibility( $post_types, new Exclusions( $options ) );
		$store       = new Store();
		$generator   = new Generator( $options, new Renderer(), new Extractor(), new Converter(), new FrontMatter() );

		$this->components = array(
			new Router( $eligibility ),
			new DiscoveryTags( $eligibility ),
			new Invalidator( $store ),
			new RobotsTxt( $options ),
			new Seo(),
			new Elementor(),
			new Acf(),
		);

		if ( is_admin() ) {
			$this->components[] = new SettingsPage( $options, $post_types );
			$this->components[] = new MetaBox( $post_types );
			$this->components[] = new PreviewTool( $eligibility, $generator );
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$this->components[] = new Command( $store );
		}

		foreach ( $this->components as $component ) {
			$component->register();
		}
	}
}
